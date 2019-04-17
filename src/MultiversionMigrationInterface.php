<?php

namespace Drupal\multiversion;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\file\FileStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

interface MultiversionMigrationInterface {

  /**
   * Factory method.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   * @return \Drupal\multiversion\MultiversionMigrationInterface
   */
  public static function create(ContainerInterface $container, EntityTypeManagerInterface $entity_manager);

  /**
   * @return \Drupal\multiversion\MultiversionMigrationInterface
   */
  public function installDependencies();

  /**
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   * @param array $field_map
   *
   * @return \Drupal\multiversion\MultiversionMigrationInterface
   */
  public function migrateContentToTemp(EntityTypeInterface $entity_type, $field_map);

  /**
   * @param \Drupal\file\FileStorageInterface $storage
   * @return \Drupal\multiversion\MultiversionMigrationInterface
   */
  public function copyFilesToMigrateDirectory(FileStorageInterface $storage);

  /**
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   * @return \Drupal\multiversion\MultiversionMigrationInterface
   */
  public function emptyOldStorage(EntityStorageInterface $storage);

  /**
   * @return \Drupal\multiversion\MultiversionMigrationInterface
   */
  public function applyNewStorage();

  /**
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   * @param array $field_map
   *
   * @return \Drupal\multiversion\MultiversionMigrationInterface
   */
  public function migrateContentFromTemp(EntityTypeInterface $entity_type, $field_map);

  /**
   * @return \Drupal\multiversion\MultiversionMigrationInterface
   */
  public function uninstallDependencies();

  /**
   * Removes the map and message tables for a migration.
   *
   * @param int $id
   *   The migration ID.
   */
  public function cleanupMigration($id);

  /**
   * Helper method to fetch the field map for an entity type.
   *
   * @param EntityTypeInterface $entity_type
   * @param string $op
   * @param string $action
   *
   * @return array
   */
  public function getFieldMap(EntityTypeInterface $entity_type, $op, $action);
}
