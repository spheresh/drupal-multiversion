<?php

namespace Drupal\multiversion\Entity\Storage\Sql;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorageSchemaConverter;
use Drupal\Core\Entity\Sql\TemporaryTableMapping;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;

class ContentEntityStorageSchemaConverter extends SqlContentEntityStorageSchemaConverter {

  /**
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * ContentEntityStorageSchemaConverter constructor.
   *
   * @param $entity_type_id
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface $entity_definition_update_manager
   * @param \Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface $last_installed_schema_repository
   * @param \Drupal\Core\KeyValueStore\KeyValueStoreInterface $installed_storage_schema
   * @param \Drupal\Core\Database\Connection $database
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   */
  public function __construct($entity_type_id, EntityTypeManagerInterface $entity_type_manager, EntityDefinitionUpdateManagerInterface $entity_definition_update_manager, EntityLastInstalledSchemaRepositoryInterface $last_installed_schema_repository, KeyValueStoreInterface $installed_storage_schema, Connection $database, EntityFieldManagerInterface $entity_field_manager) {
    parent::__construct($entity_type_id, $entity_type_manager, $entity_definition_update_manager, $last_installed_schema_repository, $installed_storage_schema, $database);
    $this->entityFieldManager = $entity_field_manager;
  }

  public function convertToMultiversionable(array &$sandbox) {
    // If 'progress' is not set, then this will be the first run of the batch.
    if (!isset($sandbox['progress'])) {
      // Store the original entity type and field definitions in the $sandbox
      // array so we can use them later in the update process.
      $this->collectOriginalDefinitions($sandbox);

      // Create a temporary environment in which the new data will be stored.
      $fields_to_update = $this->getFieldsToUpdate();
      $this->createTemporaryDefinitions($sandbox, $fields_to_update);

      // Create the updated entity schema using temporary tables.
      /** @var \Drupal\Core\Entity\Sql\SqlContentEntityStorage $storage */
      $storage = $this->entityTypeManager->getStorage($this->entityTypeId);
      $storage->setTemporary(TRUE);
      $storage->setEntityType($sandbox['temporary_entity_type']);
      $storage->onEntityTypeCreate($sandbox['temporary_entity_type']);
    }

    // Copy over the existing data to the new temporary tables.
    $this->copyData($sandbox);

    // If the data copying has finished successfully, we can drop the temporary
    // tables and call the appropriate update mechanisms.
    if ($sandbox['#finished'] == 1) {
      $this->entityTypeManager->useCaches(FALSE);
      $actual_entity_type = $this->entityTypeManager->getDefinition($this->entityTypeId);

      // Rename the original tables so we can put them back in place in case
      // anything goes wrong.
      foreach ($sandbox['original_table_mapping']->getTableNames() as $table_name) {
        $old_table_name = TemporaryTableMapping::getTempTableName($table_name, 'old_');
        $this->database->schema()->renameTable($table_name, $old_table_name);
      }

      // Put the new tables in place and update the entity type and field
      // storage definitions.
      try {
        $storage = $this->entityTypeManager->getStorage($this->entityTypeId);
        $storage->setEntityType($actual_entity_type);
        $storage->setTemporary(FALSE);
        $actual_table_names = $storage->getTableMapping()->getTableNames();

        $table_name_mapping = [];
        foreach ($actual_table_names as $new_table_name) {
          $temp_table_name = TemporaryTableMapping::getTempTableName($new_table_name);
          $table_name_mapping[$temp_table_name] = $new_table_name;
          $this->database->schema()->renameTable($temp_table_name, $new_table_name);
        }

        // Rename the tables in the cached entity schema data.
        $entity_schema_data = $this->installedStorageSchema->get($this->entityTypeId . '.entity_schema_data', []);
        foreach ($entity_schema_data as $temp_table_name => $schema) {
          if (isset($table_name_mapping[$temp_table_name])) {
            $entity_schema_data[$table_name_mapping[$temp_table_name]] = $schema;
            unset($entity_schema_data[$temp_table_name]);
          }
        }
        $this->installedStorageSchema->set($this->entityTypeId . '.entity_schema_data', $entity_schema_data);

        // Rename the tables in the cached field schema data.
        foreach ($sandbox['updated_storage_definitions'] as $storage_definition) {
          $field_schema_data = $this->installedStorageSchema->get($this->entityTypeId . '.field_schema_data.' . $storage_definition->getName(), []);
          foreach ($field_schema_data as $temp_table_name => $schema) {
            if (isset($table_name_mapping[$temp_table_name])) {
              $field_schema_data[$table_name_mapping[$temp_table_name]] = $schema;
              unset($field_schema_data[$temp_table_name]);
            }
          }
          $this->installedStorageSchema->set($this->entityTypeId . '.field_schema_data.' . $storage_definition->getName(), $field_schema_data);
        }

        // Instruct the entity schema handler that data migration has been
        // handled already and update the entity type.
        $actual_entity_type->set('requires_data_migration', FALSE);
        $this->entityDefinitionUpdateManager->updateEntityType($actual_entity_type);

        // Update the field storage definitions.
        $this->updateFieldStorageDefinitionsToRevisionable($actual_entity_type, $sandbox['original_storage_definitions'], $fields_to_update);
      }
      catch (\Exception $e) {
        // Something went wrong, bring back the original tables.
        foreach ($sandbox['original_table_mapping']->getTableNames() as $table_name) {
          // We are in the 'original data recovery' phase, so we need to be sure
          // that the initial tables can be properly restored.
          if ($this->database->schema()->tableExists($table_name)) {
            $this->database->schema()->dropTable($table_name);
          }

          $old_table_name = TemporaryTableMapping::getTempTableName($table_name, 'old_');
          $this->database->schema()->renameTable($old_table_name, $table_name);
        }

        // Re-throw the original exception.
        throw $e;
      }

      // At this point the update process either finished successfully or any
      // error has been handled already, so we can drop the backup entity
      // tables.
      foreach ($sandbox['original_table_mapping']->getTableNames() as $table_name) {
        $old_table_name = TemporaryTableMapping::getTempTableName($table_name, 'old_');
        $this->database->schema()->dropTable($old_table_name);
      }
    }
  }

  protected function getFieldsToUpdate() {
    $base_field_definitions = $this->entityFieldManager->getBaseFieldDefinitions($this->entityTypeId);
    $entity_type = $this->entityDefinitionUpdateManager->getEntityType($this->entityTypeId);
    $exclude_fields = [
      $entity_type->getKey('id'),
      $entity_type->getKey('revision') ?: 'revision_id',
      $entity_type->getKey('uuid'),
      $entity_type->getKey('bundle'),
      $entity_type->getKey('langcode'),
      'revision_translation_affected',
      'revision_default',
      'workspace',
      '_deleted',
      '_rev',
    ];
    $fields_to_update = [];
    foreach ($base_field_definitions as $key => $field) {
      if (!in_array($key, $exclude_fields)) {
        $fields_to_update[] = $key;
      }
    }
    return $fields_to_update;
  }
}
