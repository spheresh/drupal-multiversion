<?php

namespace Drupal\multiversion\Entity\Storage;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityInterface;

interface ContentEntityStorageInterface extends EntityStorageInterface {

  /**
   * What workspace to query.
   *
   * @param integer $id
   * @return \Drupal\multiversion\Entity\Storage\ContentEntityStorageInterface
   */
  public function useWorkspace($id);

  /**
   * @param integer $id
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   */
  public function loadDeleted($id);

  /**
   * @param array $ids
   * @return \Drupal\Core\Entity\ContentEntityInterface[]
   */
  public function loadMultipleDeleted(array $ids = NULL);

  /**
   * @param array $entities
   */
  public function purge(array $entities);

  /**
   * Truncate all related tables to entity type.
   *
   * This function should be called to avoid calling pre-delete/delete hooks.
   */
  public function truncate();

  /**
   * Save the given entity without forcing a new revision.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity that should be saved.
   *
   * @return
   *   SAVED_NEW or SAVED_UPDATED is returned depending on the operation
   *   performed.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   In case of failures, an exception is thrown.
   */
  public function saveWithoutForcingNewRevision(EntityInterface $entity);
}
