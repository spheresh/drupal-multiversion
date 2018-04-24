<?php

namespace Drupal\multiversion\Entity\Query;

use Drupal\multiversion\Entity\Storage\ContentEntityStorageInterface;

/**
 * @property $entityTypeId
 * @property $entityTypeManager
 * @property $condition
 */
trait QueryTrait {

  /**
   * @var null|int
   */
  protected $workspaceId = NULL;

  /**
   * @var boolean
   */
  protected $isDeleted = FALSE;

  /**
   * @param int $id
   *
   * @return \Drupal\multiversion\Entity\Query\QueryTrait
   */
  public function useWorkspace($id) {
    $this->workspaceId = $id;
    return $this;
  }

  /**
   * @see \Drupal\multiversion\Entity\Query\QueryInterface::isDeleted()
   */
  public function isDeleted() {
    $this->isDeleted = TRUE;
    return $this;
  }

  /**
   * @see \Drupal\multiversion\Entity\Query\QueryInterface::isNotDeleted()
   */
  public function isNotDeleted() {
    $this->isDeleted = FALSE;
    return $this;
  }

  public function prepare() {
    parent::prepare();
    $entity_type = $this->entityTypeManager->getDefinition($this->entityTypeId);
    $enabled = \Drupal::state()->get('multiversion.migration_done.' . $this->getEntityTypeId(), FALSE);
    // Add necessary conditions just when the storage class is defined by the
    // Multiversion module. This is needed when uninstalling Multiversion.
    if (is_subclass_of($entity_type->getStorageClass(), ContentEntityStorageInterface::class) && $enabled) {
      $revision_key = $entity_type->getKey('revision');
      $revision_query = FALSE;
      foreach ($this->condition->conditions() as $condition) {
        if ($condition['field'] == $revision_key) {
          $revision_query = TRUE;
        }
      }

      // Set the workspace condition.
      if ($workspace_id = $this->getWorkspaceId()) {
        $this->condition('workspace', $workspace_id);
      }

      // Loading a revision is explicit. So when we try to load one we should do
      // so without a condition on the deleted flag.
      if (!$revision_query) {
        $this->condition('_deleted', (int) $this->isDeleted);
      }
    }
    return $this;
  }

  /**
   * Helper method to get the workspace ID to query.
   */
  protected function getWorkspaceId() {
    if ($this->workspaceId) {
      return $this->workspaceId;
    }
    if ($workspace = \Drupal::service('workspace.manager')->getActiveWorkspace()) {
      return $workspace->id();
    }
    return NULL;
  }

}
