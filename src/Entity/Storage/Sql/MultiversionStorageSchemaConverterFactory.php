<?php

namespace Drupal\multiversion\Entity\Storage\Sql;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\multiversion\MultiversionManagerInterface;
use Drupal\workspaces\WorkspaceManagerInterface;

class MultiversionStorageSchemaConverterFactory implements MultiversionStorageSchemaConverterFactoryInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity definition update manager service.
   *
   * @var \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface
   */
  protected $entityDefinitionUpdateManager;

  /**
   * The last installed schema repository service.
   *
   * @var \Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface
   */
  protected $lastInstalledSchemaRepository;

  /**
   * @var \Drupal\Core\KeyValueStore\KeyValueFactoryInterface
   */
  protected $keyValueFactory;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

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
   * MultiversionStorageSchemaConverterFactory constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface $entity_definition_update_manager
   * @param \Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface $last_installed_schema_repository
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $key_value_factory
   * @param \Drupal\Core\Database\Connection $database
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   * @param \Drupal\multiversion\MultiversionManagerInterface $multiversion_manager
   * @param \Drupal\workspaces\WorkspaceManagerInterface $workspace_manager
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityDefinitionUpdateManagerInterface $entity_definition_update_manager, EntityLastInstalledSchemaRepositoryInterface $last_installed_schema_repository, KeyValueFactoryInterface $key_value_factory, Connection $database, EntityFieldManagerInterface $entity_field_manager, MultiversionManagerInterface $multiversion_manager, WorkspaceManagerInterface $workspace_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityDefinitionUpdateManager = $entity_definition_update_manager;
    $this->lastInstalledSchemaRepository = $last_installed_schema_repository;
    $this->keyValueFactory = $key_value_factory;
    $this->database = $database;
    $this->entityFieldManager = $entity_field_manager;
    $this->multiversionManager = $multiversion_manager;
    $this->workspaceManager = $workspace_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getStorageSchemaConverter($entity_type_id) {
    return new MultiversionStorageSchemaConverter(
      $entity_type_id,
      $this->entityTypeManager,
      $this->entityDefinitionUpdateManager,
      $this->lastInstalledSchemaRepository,
      $this->keyValueFactory->get('entity.storage_schema.sql'),
      $this->database,
      $this->entityFieldManager,
      $this->multiversionManager,
      $this->workspaceManager
    );
  }

}
