<?php

namespace Drupal\multiversion\Entity\Storage\Sql;

use Drupal\Component\Utility\Random;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorageSchemaConverter;
use Drupal\Core\Entity\Sql\TemporaryTableMapping;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\multiversion\MultiversionManagerInterface;
use Drupal\workspaces\WorkspaceManagerInterface;

class MultiversionStorageSchemaConverter extends SqlContentEntityStorageSchemaConverter {

  /**
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * @var \Drupal\multiversion\MultiversionManagerInterface
   */
  protected $multiversionManager;

  /**
   * @var \Drupal\workspaces\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * @var \Drupal\Component\Utility\Random
   */
  protected $random;

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
   * @param \Drupal\multiversion\MultiversionManagerInterface $multiversion_manager
   * @param \Drupal\workspaces\WorkspaceManagerInterface $workspace_manager
   */
  public function __construct($entity_type_id, EntityTypeManagerInterface $entity_type_manager, EntityDefinitionUpdateManagerInterface $entity_definition_update_manager, EntityLastInstalledSchemaRepositoryInterface $last_installed_schema_repository, KeyValueStoreInterface $installed_storage_schema, Connection $database, EntityFieldManagerInterface $entity_field_manager, MultiversionManagerInterface $multiversion_manager, WorkspaceManagerInterface $workspace_manager) {
    parent::__construct($entity_type_id, $entity_type_manager, $entity_definition_update_manager, $last_installed_schema_repository, $installed_storage_schema, $database);
    $this->entityFieldManager = $entity_field_manager;
    $this->multiversionManager = $multiversion_manager;
    $this->workspaceManager = $workspace_manager;
    $this->random = new Random();
  }

  public function convertToMultiversionable(array &$sandbox) {
    // Return if the conversion for current entity type has been finished.
    if ((isset($sandbox[$this->entityTypeId]['finished'])
      && $sandbox[$this->entityTypeId]['finished'] == 1)
      || !empty($sandbox[$this->entityTypeId]['failed'])) {
      return;
    }

    // Initialize entity types conversion.
    $this->initializeConversion($sandbox);

    // If the condition is TRUE, then this will be the first run of the
    // operation.
    if (!isset($sandbox[$this->entityTypeId]['finished'])
      || $sandbox[$this->entityTypeId]['finished'] < 1) {
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
    if ($sandbox[$this->entityTypeId]['finished'] == 1) {
      $sandbox['current_id'] = 0;
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

        // Install the published status field.
        $this->installPublishedStatusField($actual_entity_type);

        // Install the fields provided by Multiversion.
        $this->installMultiversionFields($actual_entity_type);

        // Reload the entity type and update it again. Multiversion makes
        // changes for other fields, like UUID, those updates needs to be
        // applied.
        $this->entityTypeManager->clearCachedDefinitions();
        $field_definitions = $this->entityFieldManager->getFieldStorageDefinitions($this->entityTypeId);
        foreach ($field_definitions as $field_definition) {
          $this->entityDefinitionUpdateManager->updateFieldStorageDefinition($field_definition);
        }
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

//  public function convertToOriginalStorage(array &$sandbox) {
//    // Return if the conversion for current entity type has been finished.
//    if ((isset($sandbox[$this->entityTypeId]['finished'])
//        && $sandbox[$this->entityTypeId]['finished'] == 1)
//      || !empty($sandbox[$this->entityTypeId]['failed'])) {
//      return;
//    }
//
//    // Initialize entity types conversion.
//    $this->initializeConversion($sandbox);
//
//    // If the condition is TRUE, then this will be the first run of the
//    // operation.
//    if (!isset($sandbox[$this->entityTypeId]['finished'])
//      || $sandbox[$this->entityTypeId]['finished'] < 1) {
//      // Store the original entity type and field definitions in the $sandbox
//      // array so we can use them later in the update process.
//      $this->collectOriginalDefinitions($sandbox);
//
//      // Create a temporary environment in which the new data will be stored.
//      $this->createTemporaryDefinitions($sandbox, []);
//
//      // Create the updated entity schema using temporary tables.
//      /** @var \Drupal\Core\Entity\Sql\SqlContentEntityStorage $storage */
//      $storage = $this->entityTypeManager->getStorage($this->entityTypeId);
//      $storage->setTemporary(TRUE);
//      $storage->setEntityType($sandbox['temporary_entity_type']);
//      $storage->onEntityTypeCreate($sandbox['temporary_entity_type']);
//    }
//
//    // Copy over the existing data to the new temporary tables.
//    $this->copyDataToOriginal($sandbox);
//
//    // If the data copying has finished successfully, we can drop the temporary
//    // tables and call the appropriate update mechanisms.
//    if ($sandbox[$this->entityTypeId]['finished'] == 1) {
//      $sandbox['current_id'] = 0;
//      $this->entityTypeManager->useCaches(FALSE);
//      $actual_entity_type = $this->entityTypeManager->getDefinition($this->entityTypeId);
//
//      // Rename the original tables so we can put them back in place in case
//      // anything goes wrong.
//      foreach ($sandbox['original_table_mapping']->getTableNames() as $table_name) {
//        $old_table_name = TemporaryTableMapping::getTempTableName($table_name, 'old_');
//        $this->database->schema()->renameTable($table_name, $old_table_name);
//      }
//
//      // Put the new tables in place and update the entity type and field
//      // storage definitions.
//      try {
//        $storage = $this->entityTypeManager->getStorage($this->entityTypeId);
//        $storage->setEntityType($actual_entity_type);
//        $storage->setTemporary(FALSE);
//        $actual_table_names = $storage->getTableMapping()->getTableNames();
//
//        $table_name_mapping = [];
//        foreach ($actual_table_names as $new_table_name) {
//          $temp_table_name = TemporaryTableMapping::getTempTableName($new_table_name);
//          $table_name_mapping[$temp_table_name] = $new_table_name;
//          $this->database->schema()->renameTable($temp_table_name, $new_table_name);
//        }
//
//        // Rename the tables in the cached entity schema data.
//        $entity_schema_data = $this->installedStorageSchema->get($this->entityTypeId . '.entity_schema_data', []);
//        foreach ($entity_schema_data as $temp_table_name => $schema) {
//          if (isset($table_name_mapping[$temp_table_name])) {
//            $entity_schema_data[$table_name_mapping[$temp_table_name]] = $schema;
//            unset($entity_schema_data[$temp_table_name]);
//          }
//        }
//        $this->installedStorageSchema->set($this->entityTypeId . '.entity_schema_data', $entity_schema_data);
//
//        // Rename the tables in the cached field schema data.
//        foreach ($sandbox['updated_storage_definitions'] as $storage_definition) {
//          $field_schema_data = $this->installedStorageSchema->get($this->entityTypeId . '.field_schema_data.' . $storage_definition->getName(), []);
//          foreach ($field_schema_data as $temp_table_name => $schema) {
//            if (isset($table_name_mapping[$temp_table_name])) {
//              $field_schema_data[$table_name_mapping[$temp_table_name]] = $schema;
//              unset($field_schema_data[$temp_table_name]);
//            }
//          }
//          $this->installedStorageSchema->set($this->entityTypeId . '.field_schema_data.' . $storage_definition->getName(), $field_schema_data);
//        }
//
//        // Instruct the entity schema handler that data migration has been
//        // handled already and update the entity type.
//        $actual_entity_type->set('requires_data_migration', FALSE);
//        $this->entityDefinitionUpdateManager->updateEntityType($actual_entity_type);
//
//        // Apply updates.
//        $this->entityTypeManager->clearCachedDefinitions();
////        $field_definitions = $this->entityFieldManager->getFieldStorageDefinitions($this->entityTypeId);
////        foreach ($field_definitions as $field_definition) {
////          $this->entityDefinitionUpdateManager->updateFieldStorageDefinition($field_definition);
////        }
//        $this->entityDefinitionUpdateManager->applyUpdates();
//        $this->entityDefinitionUpdateManager->applyUpdates();
//
//      }
//      catch (\Exception $e) {
//        // Something went wrong, bring back the original tables.
//        foreach ($sandbox['original_table_mapping']->getTableNames() as $table_name) {
//          // We are in the 'original data recovery' phase, so we need to be sure
//          // that the initial tables can be properly restored.
//          if ($this->database->schema()->tableExists($table_name)) {
//            $this->database->schema()->dropTable($table_name);
//          }
//
//          $old_table_name = TemporaryTableMapping::getTempTableName($table_name, 'old_');
//          $this->database->schema()->renameTable($old_table_name, $table_name);
//        }
//
//        // Re-throw the original exception.
//        throw $e;
//      }
//
//      // At this point the update process either finished successfully or any
//      // error has been handled already, so we can drop the backup entity
//      // tables.
//      foreach ($sandbox['original_table_mapping']->getTableNames() as $table_name) {
//        $old_table_name = TemporaryTableMapping::getTempTableName($table_name, 'old_');
//        $this->database->schema()->dropTable($old_table_name);
//      }
//    }
//  }

  /**
   * @param array $sandbox
   */
  protected function initializeConversion(array &$sandbox) {
    // If 'progress' is not set, then this will be the first run of the batch.
    if (!isset($sandbox['progress'])) {
      $max = 0;
      foreach ($sandbox['base_tables'] as $entity_type_id => $base_table) {
        $entities_count = $this->database->select($sandbox['base_tables'][$entity_type_id])
          ->countQuery()
          ->execute()
          ->fetchField();
        $sandbox[$entity_type_id]['max'] = (int) $entities_count;
        $max += $entities_count;
      }
      $sandbox['current_id'] = 0;
      $sandbox['max'] = $max;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function updateFieldStorageDefinitionsToRevisionable(ContentEntityTypeInterface $entity_type, array $storage_definitions, array $fields_to_update = [], $update_cached_definitions = TRUE) {
    $updated_storage_definitions = array_map(function ($storage_definition) {
      return clone $storage_definition;
    }, $storage_definitions);

    // Update the 'langcode' field manually, as it is configured in the base
    // content entity field definitions.
    if ($entity_type->hasKey('langcode')) {
      $fields_to_update = array_merge([$entity_type->getKey('langcode')], $fields_to_update);
    }

    foreach ($fields_to_update as $field_name) {
      if (!empty($updated_storage_definitions[$field_name]) && !$updated_storage_definitions[$field_name]->isRevisionable()) {
        $updated_storage_definitions[$field_name]->setRevisionable(TRUE);

        if ($update_cached_definitions) {
          $this->entityDefinitionUpdateManager->updateFieldStorageDefinition($updated_storage_definitions[$field_name]);
        }
      }
    }

    // Add the revision ID field.
    $revision_field = BaseFieldDefinition::create('integer')
      ->setName($entity_type->getKey('revision'))
      ->setTargetEntityTypeId($entity_type->id())
      ->setTargetBundle(NULL)
      ->setLabel(new TranslatableMarkup('Revision ID'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    if ($update_cached_definitions) {
      $this->entityDefinitionUpdateManager->installFieldStorageDefinition($revision_field->getName(), $entity_type->id(), $entity_type->getProvider(), $revision_field);
    }
    $updated_storage_definitions[$entity_type->getKey('revision')] = $revision_field;

    // Add the default revision flag field.
    $field_name = $entity_type->getRevisionMetadataKey('revision_default');
    $storage_definition = BaseFieldDefinition::create('boolean')
      ->setName($field_name)
      ->setTargetEntityTypeId($entity_type->id())
      ->setTargetBundle(NULL)
      ->setLabel(t('Default revision'))
      ->setDescription(t('A flag indicating whether this was a default revision when it was saved.'))
      ->setStorageRequired(TRUE)
      ->setTranslatable(FALSE)
      ->setRevisionable(TRUE);

    if ($update_cached_definitions) {
      $this->entityDefinitionUpdateManager->installFieldStorageDefinition($field_name, $entity_type->id(), $entity_type->getProvider(), $storage_definition);
    }
    $updated_storage_definitions[$field_name] = $storage_definition;

    // Add the 'revision_translation_affected' field if needed.
    if ($entity_type->isTranslatable()) {
      $revision_translation_affected_field = BaseFieldDefinition::create('boolean')
        ->setName($entity_type->getKey('revision_translation_affected'))
        ->setTargetEntityTypeId($entity_type->id())
        ->setTargetBundle(NULL)
        ->setLabel(new TranslatableMarkup('Revision translation affected'))
        ->setDescription(new TranslatableMarkup('Indicates if the last edit of a translation belongs to current revision.'))
        ->setReadOnly(TRUE)
        ->setRevisionable(TRUE)
        ->setTranslatable(TRUE);

      if ($update_cached_definitions) {
        $this->entityDefinitionUpdateManager->installFieldStorageDefinition($revision_translation_affected_field->getName(), $entity_type->id(), $entity_type->getProvider(), $revision_translation_affected_field);
      }
      $updated_storage_definitions[$entity_type->getKey('revision_translation_affected')] = $revision_translation_affected_field;
    }

    return $updated_storage_definitions;
  }

  /**
   * {@inheritdoc}
   */
  protected function copyData(array &$sandbox) {
    /** @var \Drupal\Core\Entity\Sql\TemporaryTableMapping $temporary_table_mapping */
    $temporary_table_mapping = $sandbox['temporary_table_mapping'];
    $temporary_entity_type = $sandbox['temporary_entity_type'];
    $original_table_mapping = $sandbox['original_table_mapping'];
    $original_entity_type = $sandbox['original_entity_type'];

    $original_base_table = $original_entity_type->getBaseTable();

    $revision_id_key = $temporary_entity_type->getKey('revision');
    $published_key = $temporary_entity_type->getKey('published');
    $revision_default_key = $temporary_entity_type->getRevisionMetadataKey('revision_default');
    $revision_translation_affected_key = $temporary_entity_type->getKey('revision_translation_affected');

    if (!isset($sandbox['progress'])) {
      $sandbox['progress'] = 0;
    }
    if (!isset($sandbox[$this->entityTypeId]['progress'])) {
      $sandbox[$this->entityTypeId]['progress'] = 0;
    }

    $id = $original_entity_type->getKey('id');

    // Get the next entity IDs to migrate.
    $entity_ids = $this->database->select($original_base_table)
      ->fields($original_base_table, [$id])
      ->condition($id, $sandbox['current_id'], '>')
      ->orderBy($id, 'ASC')
      ->range(0, $sandbox['step_size'] ?: 50)
      ->execute()
      ->fetchAllKeyed(0, 0);

    /** @var \Drupal\Core\Entity\Sql\SqlContentEntityStorage $storage */
    $storage = $this->entityTypeManager->getStorage($temporary_entity_type->id());
    $storage->setEntityType($original_entity_type);
    $storage->setTableMapping($original_table_mapping);

    $entities = $storage->loadMultiple($entity_ids);

    // Now inject the temporary entity type definition and table mapping in the
    // storage and re-save the entities.
    $storage->setEntityType($temporary_entity_type);
    $storage->setTableMapping($temporary_table_mapping);

    // This clear cache is needed at least for menu_link_content entity type.
    $this->entityTypeManager->clearCachedDefinitions();

    foreach ($entities as $entity_id => $entity) {
      try {
        // Set the revision ID to be same as the entity ID.
        $entity->set($revision_id_key, $entity_id);

        // We had no revisions so far, so the existing data belongs to the
        // default revision now.
        $entity->set($revision_default_key, TRUE);

        // Set the published status to TRUE.
        $entity->set($published_key, TRUE);

        // Set the revision token field.
        $rev_token = '1-' . md5($entity->id() . $entity->uuid() . $this->random->string(10, TRUE));
        $entity->set('_rev', $rev_token);
        $entity->_rev->new_edit = FALSE;

        // The _deleted field should be FALSE.
        $entity->set('_deleted', FALSE);

        // Set the 'revision_translation_affected' flag to TRUE to match the
        // previous API return value: if the field was not defined the value
        // returned was always TRUE.
        if ($temporary_entity_type->isTranslatable()) {
          $entity->set($revision_translation_affected_key, TRUE);
        }

        // Treat the entity as new in order to make the storage do an INSERT
        // rather than an UPDATE.
        $entity->enforceIsNew(TRUE);

        // Finally, save the entity in the temporary storage.
        $storage->save($entity);

        // Delete the entry for the old entry in the menu_tree table.
        if ($original_entity_type->id() == 'menu_link_content' && $this->database->schema()->tableExists('menu_tree')) {
          $this->database->delete('menu_tree')
            ->condition('id', 'menu_link_content:' . $entity->uuid())
            ->execute();
        }
      }
      catch (\Exception $e) {
        // In case of an error during the save process, we need to roll back the
        // original entity type and field storage definitions and clean up the
        // temporary tables.
        $this->restoreOriginalDefinitions($sandbox);

        foreach ($temporary_table_mapping->getTableNames() as $table_name) {
          $this->database->schema()->dropTable($table_name);
        }

        // Re-throw the original exception with a helpful message.
        throw new EntityStorageException("The entity update process failed while processing the entity {$original_entity_type->id()}:$entity_id.", $e->getCode(), $e);
      }

      $sandbox['progress']++;
      $sandbox[$this->entityTypeId]['progress']++;
      $sandbox['current_id'] = $entity_id;
    }

    // If we're not in maintenance mode, the number of entities could change at
    // any time so make sure that we always use the latest record count.
    $max = 0;
    foreach ($sandbox['base_tables'] as $entity_type_id => $base_table) {
      $entities_count = $this->database->select($sandbox['base_tables'][$entity_type_id])
        ->countQuery()
        ->execute()
        ->fetchField();
      $sandbox[$entity_type_id]['max'] = $entities_count;
      $max += $entities_count;
    }
    $sandbox['max'] = $max;

    $sandbox[$this->entityTypeId]['finished'] = empty($sandbox[$this->entityTypeId]['max']) ? 1 : ($sandbox[$this->entityTypeId]['progress'] / $sandbox[$this->entityTypeId]['max']);
    $sandbox['#finished'] = empty($sandbox['max']) ? 1 : ($sandbox['progress'] / $sandbox['max']);
  }

//  /**
//   * {@inheritdoc}
//   */
//  protected function copyDataToOriginal(array &$sandbox) {
//    /** @var \Drupal\Core\Entity\Sql\TemporaryTableMapping $temporary_table_mapping */
//    $temporary_table_mapping = $sandbox['temporary_table_mapping'];
//    $temporary_entity_type = $sandbox['temporary_entity_type'];
//    $original_table_mapping = $sandbox['original_table_mapping'];
//    $original_entity_type = $sandbox['original_entity_type'];
//
//    $original_base_table = $original_entity_type->getBaseTable();
//
//    if (!isset($sandbox['progress'])) {
//      $sandbox['progress'] = 0;
//    }
//    if (!isset($sandbox[$this->entityTypeId]['progress'])) {
//      $sandbox[$this->entityTypeId]['progress'] = 0;
//    }
//
//    $id = $original_entity_type->getKey('id');
//
//    // Get the next entity IDs to migrate.
//    $entity_ids = $this->database->select($original_base_table)
//      ->fields($original_base_table, [$id])
//      ->condition($id, $sandbox['current_id'], '>')
//      ->orderBy($id, 'ASC')
//      ->range(0, $sandbox['step_size'] ?: 50)
//      ->execute()
//      ->fetchAllKeyed(0, 0);
//
//    /** @var \Drupal\Core\Entity\Sql\SqlContentEntityStorage $storage */
//    $storage = $this->entityTypeManager->getStorage($temporary_entity_type->id());
//    $storage->setEntityType($original_entity_type);
//    $storage->setTableMapping($original_table_mapping);
//
//    $entities = $storage->loadMultiple($entity_ids);
//
//    // Now inject the temporary entity type definition and table mapping in the
//    // storage and re-save the entities.
//    $storage->setEntityType($temporary_entity_type);
//    $storage->setTableMapping($temporary_table_mapping);
//
//    // This clear cache is needed at least for menu_link_content entity type.
//    $this->entityTypeManager->clearCachedDefinitions();
//
//    foreach ($entities as $entity_id => $entity) {
//      try {
//        // Treat the entity as new in order to make the storage do an INSERT
//        // rather than an UPDATE.
//        $entity->enforceIsNew(TRUE);
//
//        // Finally, save the entity in the temporary storage.
//        $storage->save($entity);
//
//        // Delete the entry for the old entry in the menu_tree table.
//        if ($original_entity_type->id() == 'menu_link_content' && $this->database->schema()->tableExists('menu_tree')) {
//          $this->database->delete('menu_tree')
//            ->condition('id', 'menu_link_content:' . $entity->uuid())
//            ->execute();
//        }
//      }
//      catch (\Exception $e) {
//        // In case of an error during the save process, we need to roll back the
//        // original entity type and field storage definitions and clean up the
//        // temporary tables.
//        $this->restoreOriginalDefinitions($sandbox);
//
//        foreach ($temporary_table_mapping->getTableNames() as $table_name) {
//          $this->database->schema()->dropTable($table_name);
//        }
//
//        // Re-throw the original exception with a helpful message.
//        throw new EntityStorageException("The entity update process failed while processing the entity {$original_entity_type->id()}:$entity_id.", $e->getCode(), $e);
//      }
//
//      $sandbox['progress']++;
//      $sandbox[$this->entityTypeId]['progress']++;
//      $sandbox['current_id'] = $entity_id;
//    }
//
//    // If we're not in maintenance mode, the number of entities could change at
//    // any time so make sure that we always use the latest record count.
//    $max = 0;
//    foreach ($sandbox['base_tables'] as $entity_type_id => $base_table) {
//      $entities_count = $this->database->select($sandbox['base_tables'][$entity_type_id])
//        ->countQuery()
//        ->execute()
//        ->fetchField();
//      $sandbox[$entity_type_id]['max'] = $entities_count;
//      $max += $entities_count;
//    }
//    $sandbox['max'] = $max;
//
//    $sandbox[$this->entityTypeId]['finished'] = empty($sandbox[$this->entityTypeId]['max']) ? 1 : ($sandbox[$this->entityTypeId]['progress'] / $sandbox[$this->entityTypeId]['max']);
//    $sandbox['#finished'] = empty($sandbox['max']) ? 1 : ($sandbox['progress'] / $sandbox['max']);
//  }

  /**
   * @param \Drupal\Core\Entity\ContentEntityTypeInterface $entity_type
   */
  protected function installPublishedStatusField(ContentEntityTypeInterface $entity_type) {
    // Get the 'published' key for the published status field.
    $published_key = $entity_type->getKey('published') ?: 'status';

    // Add the status field.
    $field = BaseFieldDefinition::create('boolean')
      ->setName($published_key)
      ->setTargetEntityTypeId($entity_type->id())
      ->setTargetBundle(NULL)
      ->setLabel(t('Publishing status'))
      ->setDescription(t('A boolean indicating the published state.'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDefaultValue(TRUE);

    $has_content_translation_status_field = \Drupal::moduleHandler()->moduleExists('content_translation') && $this->entityDefinitionUpdateManager->getFieldStorageDefinition('content_translation_status', $entity_type->id());
    if ($has_content_translation_status_field) {
      $field->setInitialValueFromField('content_translation_status', TRUE);
    }
    else {
      $field->setInitialValue(TRUE);
    }

    $this->entityDefinitionUpdateManager->installFieldStorageDefinition($published_key, $entity_type->id(), $field->getProvider(), $field);

    // Uninstall the 'content_translation_status' field if needed.
    if ($has_content_translation_status_field) {
      $content_translation_status = $this->entityDefinitionUpdateManager->getFieldStorageDefinition('content_translation_status', 'taxonomy_term');
      $this->entityDefinitionUpdateManager->uninstallFieldStorageDefinition($content_translation_status);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function createTemporaryDefinitions(array &$sandbox, array $fields_to_update) {
    // Make sure to get the latest entity type definition from code.
    $this->entityTypeManager->useCaches(FALSE);
    $actual_entity_type = $this->entityTypeManager->getDefinition($this->entityTypeId);

    $temporary_entity_type = clone $actual_entity_type;
    $temporary_entity_type->set('base_table', TemporaryTableMapping::getTempTableName($temporary_entity_type->getBaseTable()));
    $temporary_entity_type->set('revision_table', TemporaryTableMapping::getTempTableName($temporary_entity_type->getRevisionTable()));
    if ($temporary_entity_type->isTranslatable()) {
      $temporary_entity_type->set('data_table', TemporaryTableMapping::getTempTableName($temporary_entity_type->getDataTable()));
      $temporary_entity_type->set('revision_data_table', TemporaryTableMapping::getTempTableName($temporary_entity_type->getRevisionDataTable()));
    }

    /** @var \Drupal\Core\Entity\Sql\SqlContentEntityStorage $storage */
    $storage = $this->entityTypeManager->getStorage($this->entityTypeId);
    $storage->setTemporary(TRUE);
    $storage->setEntityType($temporary_entity_type);

    $updated_storage_definitions = $this->entityFieldManager->getFieldStorageDefinitions($temporary_entity_type->id());
    $temporary_table_mapping = $storage->getTableMapping($updated_storage_definitions);

    $sandbox['temporary_entity_type'] = $temporary_entity_type;
    $sandbox['temporary_table_mapping'] = $temporary_table_mapping;
    $sandbox['updated_storage_definitions'] = $updated_storage_definitions;
  }

  /**
   * Install fields provided by Multiversion.
   *
   * @param \Drupal\Core\Entity\ContentEntityTypeInterface $entity_type
   */
  protected function installMultiversionFields(ContentEntityTypeInterface $entity_type) {
    $fields[] = BaseFieldDefinition::create('boolean')
      ->setName('_deleted')
      ->setTargetEntityTypeId($entity_type->id())
      ->setTargetBundle(NULL)
      ->setLabel(t('Deleted flag'))
      ->setDescription(t('Indicates if the entity is flagged as deleted or not.'))
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDefaultValue(FALSE)
      ->setCardinality(1);

    $fields[] = BaseFieldDefinition::create('revision_token')
      ->setName('_rev')
      ->setTargetEntityTypeId($entity_type->id())
      ->setTargetBundle(NULL)
      ->setLabel(t('Revision token'))
      ->setDescription(t('The token for this entity revision.'))
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setCardinality(1)
      ->setReadOnly(TRUE);

    foreach ($fields as $field) {
      $this->entityDefinitionUpdateManager->installFieldStorageDefinition($field->getName(), $entity_type->id(), 'multiversion', $field);
    }
  }

  /**
   * Helper that returns the fields that need to be revisionable for the
   * current entity type.
   *
   * @return array
   */
  protected function getFieldsToUpdate() {
    $base_field_definitions = $this->entityFieldManager->getBaseFieldDefinitions($this->entityTypeId);
    $entity_type = $this->entityDefinitionUpdateManager->getEntityType($this->entityTypeId);
    $exclude_fields = [
      $entity_type->getKey('id'),
      $entity_type->getKey('revision') ?: 'revision_id',
      $entity_type->getKey('uuid'),
      $entity_type->getKey('bundle'),
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
