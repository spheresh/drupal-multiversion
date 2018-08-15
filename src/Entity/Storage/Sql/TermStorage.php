<?php

namespace Drupal\multiversion\Entity\Storage\Sql;

use Drupal\multiversion\Entity\Storage\ContentEntityStorageInterface;
use Drupal\multiversion\Entity\Storage\ContentEntityStorageTrait;
use Drupal\taxonomy\TermStorage as CoreTermStorage;

/**
 * Storage handler for taxonomy terms.
 */
class TermStorage extends CoreTermStorage implements ContentEntityStorageInterface {

  use ContentEntityStorageTrait {
    delete as deleteEntities;
  }

  /**
   * {@inheritdoc}
   */
  public function delete(array $entities) {
    $this->deleteEntities($entities);
    foreach ($entities as $entity) {
      $this->updateParentHierarchy([$entity->id()]);
    }
  }

  /**
   * Updates terms hierarchy information for the children when terms are deleted.
   *
   * @param array $tids
   *   Array of terms that need to be removed from hierarchy.
   */
  public function updateParentHierarchy($tids) {
    $this->database->update('taxonomy_term__parent')
      ->condition('parent_target_id', $tids, 'IN')
      ->fields(['parent_target_id' => 0])
      ->execute();
  }

}
