<?php

namespace Drupal\multiversion;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Utility\Error;
use Drupal\workspaces\WorkspaceManagerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\Serializer\Serializer;

class MultiversionManager implements MultiversionManagerInterface, ContainerAwareInterface {

  use ContainerAwareTrait;

  /**
   * @var \Drupal\workspaces\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * @var \Symfony\Component\Serializer\Serializer
   */
  protected $serializer;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * @var int
   */
  protected $lastSequenceId;

  /**
   * @param \Drupal\workspaces\WorkspaceManagerInterface $workspace_manager
   * @param \Symfony\Component\Serializer\Serializer $serializer
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\Core\State\StateInterface $state
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   * @param \Drupal\Core\Database\Connection $connection
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   */
  public function __construct(WorkspaceManagerInterface $workspace_manager, Serializer $serializer, EntityTypeManagerInterface $entity_type_manager, StateInterface $state, LanguageManagerInterface $language_manager, CacheBackendInterface $cache, Connection $connection, EntityFieldManagerInterface $entity_field_manager) {
    $this->workspaceManager = $workspace_manager;
    $this->serializer = $serializer;
    $this->entityTypeManager = $entity_type_manager;
    $this->state = $state;
    $this->languageManager = $language_manager;
    $this->cache = $cache;
    $this->connection = $connection;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * Static method maintaining the enable status.
   *
   * This method needs to be static because in some strange situations Drupal
   * might create multiple instances of this manager. Is this only an issue
   * during tests perhaps?
   *
   * @param boolean|array $status
   * @return boolean|array
   */
  public static function enableIsActive($status = NULL) {
    static $cache = FALSE;
    if ($status !== NULL) {
      $cache = $status;
    }
    return $cache;
  }

  /**
   * Static method maintaining the disable status.
   *
   * @param boolean|array $status
   * @return boolean|array
   */
  public static function disableIsActive($status = NULL) {
    static $cache = FALSE;
    if ($status !== NULL) {
      $cache = $status;
    }
    return $cache;
  }

  /**
   * {@inheritdoc}
   *
   * @todo: {@link https://www.drupal.org/node/2597337 Consider using the
   * nextId API to generate more sequential IDs.}
   * @see \Drupal\Core\Database\Connection::nextId
   */
  public function newSequenceId() {
    // Multiply the microtime by 1 million to ensure we get an accurate integer.
    // Credit goes to @letharion and @logaritmisk for this simple but genius
    // solution.
    $this->lastSequenceId = (int) (microtime(TRUE) * 1000000);
    return $this->lastSequenceId;
  }

  /**
   * {@inheritdoc}
   */
  public function lastSequenceId() {
    return $this->lastSequenceId;
  }

  /**
   * {@inheritdoc}
   */
  public function isSupportedEntityType(EntityTypeInterface $entity_type) {
    $supported_entity_types = \Drupal::config('multiversion.settings')->get('supported_entity_types') ?: [];
    if (empty($supported_entity_types)) {
      return FALSE;
    }

    if (!in_array($entity_type->id(), $supported_entity_types)) {
      return FALSE;
    }

    return ($entity_type instanceof ContentEntityTypeInterface);
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedEntityTypes() {
    $entity_types = [];
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if ($this->isSupportedEntityType($entity_type)) {
        $entity_types[$entity_type->id()] = $entity_type;
      }
    }
    return $entity_types;
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabledEntityType(EntityTypeInterface $entity_type) {
    if ($this->isSupportedEntityType($entity_type)) {
      $entity_type_id = $entity_type->id();
      $enabled_entity_types = \Drupal::config('multiversion.settings')->get('enabled_entity_types') ?: [];
      if (in_array($entity_type_id, $enabled_entity_types)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function allowToAlter(EntityTypeInterface $entity_type) {
    $supported_entity_types = \Drupal::config('multiversion.settings')->get('supported_entity_types') ?: [];
    $id = $entity_type->id();
    $enable_active = self::enableIsActive();
    $disable_migration = self::disableIsActive();
    // Don't allow to alter entity type that is not supported.
    if (!in_array($id, $supported_entity_types)) {
      return FALSE;
    }
    // Don't allow to alter entity type that is in process to be disabled.
    if (is_array($disable_migration) && in_array($id, $disable_migration)) {
      return FALSE;
    }
    // Allow to alter entity type that is in process to be enabled.
    if (is_array($enable_active) && in_array($id, $enable_active)) {
      return TRUE;
    }
    return ($this->isEnabledEntityType($entity_type));
  }

  /**
   * {@inheritdoc}
   */
  public function getEnabledEntityTypes() {
    $entity_types = [];
    foreach ($this->getSupportedEntityTypes() as $entity_type_id => $entity_type) {
      if ($this->isEnabledEntityType($entity_type)) {
        $entity_types[$entity_type_id] = $entity_type;
      }
    }
    return $entity_types;
  }

  /**
   * {@inheritdoc}
   */
  public function enableEntityTypes($entity_types_to_enable = NULL) {
    $entity_types = ($entity_types_to_enable !== NULL) ? $entity_types_to_enable : $this->getSupportedEntityTypes();
    if (empty($entity_types)) {
      return $this;
    }

    // Temporarily disable the maintenance of the {comment_entity_statistics} table.
    $this->state->set('comment.maintain_entity_statistics', FALSE);
    $multiversion_settings = \Drupal::configFactory()
      ->getEditable('multiversion.settings');
    $enabled_entity_types = $multiversion_settings->get('enabled_entity_types') ?: [];
    $operations = [];
    $sandbox = [];
    // Define the step size.
    $sandbox['step_size'] = Settings::get('entity_conversion_batch_size', 50);
    foreach ($entity_types as $entity_type_id => $entity_type) {
      if (in_array($entity_type_id, $enabled_entity_types)) {
        continue;
      }
      $base_table = $entity_type->getBaseTable();
      $sandbox['base_tables'][$entity_type_id] = $base_table;
      $entities_count = $this->connection->select($base_table)
        ->countQuery()
        ->execute()
        ->fetchField();
      $i = 0;
      while ($i <= $entities_count) {
        $operations[] = [
          [
            get_class($this),
            'convertToMultiversionable',
          ],
          [
            $entity_type_id,
            $this->entityTypeManager,
            $this->state,
            $multiversion_settings,
            &$sandbox
          ],
        ];
        $i += $sandbox['step_size'];
      }
      $operations[] = [
        [
          get_class($this),
          'fixPrimaryKeys',
        ],
        [
          $entity_type_id,
          $this->entityTypeManager,
          $this->connection,
        ],
      ];
    }

    // Create and process the batch.
    if (!empty($operations)) {
      $batch = [
        'operations' => $operations,
        'finished' => [get_class($this), 'conversionFinished']
      ];
      batch_set($batch);
      $batch =& batch_get();
      $batch['progressive'] = FALSE;
      batch_process();
    }

    // Enable the the maintenance of entity statistics for comments.
    $this->state->set('comment.maintain_entity_statistics', TRUE);
    self::enableIsActive(NULL);

    return $this;
  }

  public static function convertToMultiversionable($entity_type_id, EntityTypeManagerInterface $entity_type_manager, StateInterface $state, $multiversion_settings, &$sandbox) {
    self::enableIsActive([$entity_type_id]);
    $entity_type_manager->useCaches(FALSE);
    $enabled_entity_types = $multiversion_settings->get('enabled_entity_types') ?: [];
    /** @var \Drupal\multiversion\Entity\Storage\Sql\MultiversionStorageSchemaConverter $schema_converter */
    $schema_converter = \Drupal::service('multiversion.schema_converter_factory')
      ->getStorageSchemaConverter($entity_type_id);

    try {
      $schema_converter->convertToMultiversionable($sandbox);
      if (isset($sandbox[$entity_type_id]['finished'])
        && $sandbox[$entity_type_id]['finished'] == 1
        && !in_array($entity_type_id, $enabled_entity_types)) {
        $enabled_entity_types[] = $entity_type_id;
        $multiversion_settings
          ->set('enabled_entity_types', $enabled_entity_types)
          ->save();
        // Remove the entity from failed to convert entity types, if it's there.
        $failed_entity_types = $state->get('multiversion.failed_entity_types', []);
        if (($key = array_search($entity_type_id, $failed_entity_types)) !== FALSE) {
          unset($failed_entity_types[$key]);
          $state->set('multiversion.failed_entity_types', $failed_entity_types);
        }
      }
    }
    catch (\Exception $e) {
      $sandbox[$entity_type_id]['failed'] = TRUE;
      $failed_entity_types = $state->get('multiversion.failed_entity_types', []);
      $arguments = Error::decodeException($e) + ['%entity_type' => $entity_type_id];
      \Drupal::logger('multiversion')->warning('Entity type \'%entity_type\' failed to be converted to multiversionable. More info: %type: @message in %function (line %line of %file).', $arguments);
      $failed_entity_types[] = $entity_type_id;
      $state->set('multiversion.failed_entity_types', $failed_entity_types);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function disableEntityTypes($entity_types_to_disable = NULL) {
//    $entity_types = ($entity_types_to_disable !== NULL) ? $entity_types_to_disable : $this->getEnabledEntityTypes();
//    if (empty($entity_types)) {
//      return $this;
//    }
//
//    // Temporarily disable the maintenance of the {comment_entity_statistics} table.
//    $this->state->set('comment.maintain_entity_statistics', FALSE);
//    $multiversion_settings = \Drupal::configFactory()
//      ->getEditable('multiversion.settings');
//    $enabled_entity_types = $multiversion_settings->get('enabled_entity_types') ?: [];
//    $operations = [];
//    $sandbox = [];
//    // Define the step size.
//    $sandbox['step_size'] = Settings::get('entity_conversion_batch_size', 50);
//    foreach ($entity_types as $entity_type_id => $entity_type) {
//      if (!in_array($entity_type_id, $enabled_entity_types)) {
//        continue;
//      }
//      $base_table = $entity_type->getBaseTable();
//      $sandbox['base_tables'][$entity_type_id] = $base_table;
//      $entities_count = $this->connection->select($base_table)
//        ->countQuery()
//        ->execute()
//        ->fetchField();
//      $i = 0;
//      while ($i <= $entities_count) {
//        $operations[] = [
//          [
//            get_class($this),
//            'convertToOriginal',
//          ],
//          [
//            $entity_type_id,
//            $this->entityTypeManager,
//            $this->state,
//            $multiversion_settings,
//            &$sandbox
//          ],
//        ];
//        $i += $sandbox['step_size'];
//      }
//      $operations[] = [
//        [
//          get_class($this),
//          'fixPrimaryKeys',
//        ],
//        [
//          $entity_type_id,
//          $this->entityTypeManager,
//          $this->connection,
//        ],
//      ];
//    }
//
//    // Create and process the batch.
//    if (!empty($operations)) {
//      $batch = [
//        'operations' => $operations,
//        'finished' => [get_class($this), 'conversionFinished']
//      ];
//      batch_set($batch);
//      $batch =& batch_get();
//      $batch['progressive'] = FALSE;
//      batch_process();
//    }
//
//    // Enable the the maintenance of entity statistics for comments.
//    $this->state->set('comment.maintain_entity_statistics', TRUE);
//    self::disableIsActive(NULL);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function newRevisionId(ContentEntityInterface $entity, $index = 0) {
    $deleted = $entity->_deleted->value;
    $old_rev = $entity->_rev->value;
    // The 'new_revision_id' context will be used in normalizers (where it's
    // necessary) to identify in which format to return the normalized entity.
    $normalized_entity = $this->serializer->normalize($entity, NULL, ['new_revision_id' => TRUE]);
    // Remove fields internal to the multiversion system.
    $this->filterNormalizedEntity($normalized_entity);
    // The terms being serialized are:
    // - deleted
    // - old sequence ID (@todo: {@link https://www.drupal.org/node/2597341
    // Address this property.})
    // - old revision hash
    // - normalized entity (without revision info field)
    // - attachments (@todo: {@link https://www.drupal.org/node/2597341
    // Address this property.})
    return ($index + 1) . '-' . md5($this->termToBinary([$deleted, 0, $old_rev, $entity->id(), []]));
  }

  /**
   * @param array $normalized_entity
   */
  protected function filterNormalizedEntity(&$normalized_entity){
    foreach ($normalized_entity as $key => &$value) {
      if ($key{0} == '_') {
        unset($normalized_entity[$key]);
      }
      elseif (is_array($value)) {
        $this->filterNormalizedEntity($value);
      }
    }
  }

  protected function termToBinary(array $term) {
    // @todo: {@link https://www.drupal.org/node/2597478 Switch to BERT
    // serialization format instead of JSON.}
    return $this->serializer->serialize($term, 'json');
  }

  static function fixPrimaryKeys($entity_type_id, EntityTypeManagerInterface $entity_type_manager, Connection $connection) {
    $connection = \Drupal::service('database');
    $entity_type = $entity_type_manager->getStorage($entity_type_id)->getEntityType();
    // Make sure that 'id', 'revision' and 'langcode' are primary keys.
    if ($entity_type_id != 'file' && $entity_type->get('local') != TRUE && !empty($entity_type->getKey('langcode'))) {
      $schema = $connection->schema();
      // Get the tables name used for base table and revision table.
      $table_base = ($entity_type->isTranslatable()) ? $entity_type->getDataTable() : $entity_type->getBaseTable();
      $table_revision = ($entity_type->isTranslatable()) ? $entity_type->getRevisionDataTable() : $entity_type->getRevisionTable();
      if ($table_base && $schema->tableExists($table_base)) {
        try {
          $schema->addPrimaryKey($table_base, [$entity_type->getKey('id'), 'langcode']);
        }
        catch (\Exception $e) {
          // Do nothing, the index already exists.
        }
      }
      if ($table_revision && $schema->tableExists($table_revision)) {
        try {
          $schema->addPrimaryKey($table_revision, [$entity_type->getKey('revision'), 'langcode']);
        }
        catch (\Exception $e) {
          // Do nothing, the index already exists.
        }
      }
    }
  }

}
