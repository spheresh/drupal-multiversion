<?php

namespace Drupal\multiversion;

use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\file\FileStorageInterface;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\multiversion\Entity\Storage\ContentEntityStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MultiversionMigration implements MultiversionMigrationInterface {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface
   */
  protected $updateManager;

  /**
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * @var \Drupal\Core\Extension\ModuleInstallerInterface
   */
  protected $moduleInstaller;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, EntityTypeManagerInterface $entity_type_manager) {
    return new static(
      $entity_type_manager,
      $container->get('entity.definition_update_manager'),
      $container->get('module_handler'),
      $container->get('module_installer')
    );
  }

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface $update_manager
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   * @param \Drupal\Core\Extension\ModuleInstallerInterface $module_installer
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityDefinitionUpdateManagerInterface $update_manager, ModuleHandlerInterface $module_handler, ModuleInstallerInterface $module_installer) {
    $this->entityTypeManager = $entity_type_manager;
    $this->updateManager = $update_manager;
    $this->moduleHandler = $module_handler;
    $this->moduleInstaller = $module_installer;
    
  }

  /**
   * {@inheritdoc}
   */
  public function installDependencies() {
    $modules = ['migrate', 'migrate_drupal'];
    foreach ($modules as $i => $module) {
      if ($this->moduleHandler->moduleExists($module)) {
        unset($modules[$i]);
      }
    }
    if (!empty($modules)) {
      $this->moduleInstaller->install($modules, TRUE);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   * @param array $field_map
   *
   * @return \Drupal\multiversion\MultiversionMigrationInterface
   */
  public function migrateContentToTemp(EntityTypeInterface $entity_type, $field_map) {
    $id = $entity_type->id() . '__' . MultiversionManager::TO_TMP;
    $definition = [
      'id' => $id,
      'label' => '',
      'process' => $field_map,
      'source' => [
        'plugin' => 'multiversion',
        'translations' => (bool) $entity_type->getKey('langcode'),
      ],
      'destination' => [
        'plugin' => 'tempstore',
        'translations' => (bool) $entity_type->getKey('langcode'),
      ],
    ];
    $migration = \Drupal::service('plugin.manager.migration')
      ->createStubMigration($definition);
    $this->executeMigration($migration);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function copyFilesToMigrateDirectory(FileStorageInterface $storage) {
    foreach ($storage->loadMultiple() as $entity) {
      $uri = $entity->getFileUri();

      $target = file_uri_target($uri);

      if ($target !== FALSE) {
        $destination = 'migrate://' . $target;

        if (multiversion_prepare_file_destination($destination)) {
          // Copy the file to a folder from 'migrate://' directory.
          file_unmanaged_copy($uri, $destination, FILE_EXISTS_REPLACE);
        }
      }
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   *
   * Usage example:
   * @code
   * // For some specific content types, we are still able to use
   * // a `purge` or `delete` function.
   * if (in_array($this->getEntityTypeId(), ['replication_log'])) {
   *   $original_storage = $storage->getOriginalStorage();
   *   $entities = $original_storage->loadMultiple();
   *   $this->purge($entities);
   * }
   * @endcode
   */
  public function emptyOldStorage(EntityStorageInterface $storage) {
    if ($storage instanceof ContentEntityStorageInterface) {
      $storage->truncate();
    }
    else {
      $entities = $storage->loadMultiple();
      $storage->delete($entities);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function applyNewStorage() {
    // The first call is for making entity types revisionable, the second call
    // is for adding required fields.
    $this->updateManager->applyUpdates();
    $this->updateManager->applyUpdates();
    return $this;
  }

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   * @param array $field_map
   *
   * @return \Drupal\multiversion\MultiversionMigrationInterface
   */
  public function migrateContentFromTemp(EntityTypeInterface $entity_type, $field_map) {
    $id = $entity_type->id() . '__' . MultiversionManager::FROM_TMP;
    $definition = [
      'id' => $id,
      'label' => '',
      'process' => $field_map,
      'source' => [
        'plugin' => 'tempstore',
        'translations' => (bool) $entity_type->getKey('langcode'),
        ],
      'destination' => [
        'plugin' => 'multiversion',
        'translations' => (bool) $entity_type->getKey('langcode'),
      ],
    ];
    $migration = \Drupal::service('plugin.manager.migration')
      ->createStubMigration($definition);
    $this->executeMigration($migration);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function uninstallDependencies() {
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function cleanupMigration($id) {
    \Drupal::service('plugin.manager.migration')
      ->createStubMigration(['id' => $id])
      ->getIdMap()
      ->destroy();
  }

  /**
   * Helper method to fetch the field map for an entity type.
   *
   * @param EntityTypeInterface $entity_type
   * @param string $op
   * @param string $action
   *
   * @return array
   */
  public function getFieldMap(EntityTypeInterface $entity_type, $op, $action) {
    $map = [];
    // For some reasons it sometimes doesn't work if injecting the service.
    $entity_type_bundle_info = \Drupal::service('entity_type.bundle.info');
    $entity_type_bundle_info->clearCachedBundles();
    $bundle_info = $entity_type_bundle_info->getBundleInfo($entity_type->id());
    foreach ($bundle_info as $bundle_id => $bundle_label) {
      // For some reasons it sometimes doesn't work if injecting the service.
      $entity_field_manager = \Drupal::service('entity_field.manager');
      $entity_field_manager->clearCachedFieldDefinitions();
      $definitions = $entity_field_manager->getFieldDefinitions($entity_type->id(), $bundle_id);
      foreach ($definitions as $definition) {
        $name = $definition->getName();
        // We don't want our own fields to be part of the migration mapping or
        // they would get assigned NULL instead of default values.
        if (!in_array($name, ['workspace', '_deleted', '_rev'])) {
          $map[$name] = $name;
        }
      }
    }

    // @todo Implement hook/alter functionality here.
    if (MultiversionManager::OP_DISABLE == $op) {
      $parent_key = 'parent';
      if ('menu_link_content' == $entity_type->id() && isset($map[$parent_key])) {
        $map[$parent_key] = [
          [
            'plugin' => 'splice',
            'delimiter' => ':',
            'source' => $parent_key,
            'strict' => FALSE,
            'slice' => 2,
          ],
        ];
      }
    }

    return $map;
  }

  /**
   * Helper method for running a migration.
   *
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   * @return \Drupal\migrate\MigrateExecutableInterface
   */
  protected function executeMigration(MigrationInterface $migration) {
    // Add necessary database connection that the Migrate API needs during
    // a migration like this.
    $connection_info = Database::getConnectionInfo('default');
    foreach ($connection_info as $target => $value) {
      $connection_info[$target]['prefix'] = [
        'default' => $value['prefix']['default'],
      ];
    }
    Database::addConnectionInfo('migrate', 'default', $connection_info['default']);

    $message = new MigrateMessage();
    $executable = new MigrateExecutable($migration, $message);
    $executable->import();
    return $executable;
  }

}
