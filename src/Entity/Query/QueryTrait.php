<?php

namespace Drupal\multiversion\Entity\Query;

use Drupal\multiversion\Entity\Storage\ContentEntityStorageInterface;
use Drupal\workspaces\Entity\Workspace;

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
    /** @var \Drupal\Core\Entity\ContentEntityTypeInterface $entity_type */
    $entity_type = $this->entityTypeManager->getDefinition($this->entityTypeId);
    $enabled = $this->multiversionManager->isEnabledEntityType($entity_type);
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

      $workspace = Workspace::load($this->getWorkspaceId());
      if (!$workspace->isDefaultWorkspace()) {
        $this->sqlQuery->addMetaData('active_workspace_id', $workspace->id());
        $this->sqlQuery->addMetaData('simple_query', FALSE);

        // LEFT JOIN 'workspace_association' to the base table of the query so we
        // can properly include live content along with a possible workspace
        // revision.
        $id_key = $entity_type->getKey('id');
//        $this->sqlQuery->leftJoin('workspace_association', 'workspace_association', "%alias.target_entity_type_id = '{$this->entityTypeId}' AND %alias.target_entity_id = base_table.{$id_key} AND %alias.workspace = '{$workspace->id()}'");
        $workspace_association_table = 'workspace_association';
        $this->sqlQuery->leftJoin($workspace_association_table, $workspace_association_table, "%alias.target_entity_type_id = '{$this->entityTypeId}' AND %alias.target_entity_id = base_table.{$id_key}");
        $this->sqlQuery->condition($this->sqlQuery->orConditionGroup()
          ->condition("$workspace_association_table.workspace", $workspace->id())
          ->condition("$workspace_association_table.workspace", NULL, 'IS')
        );
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
    if ($workspace = \Drupal::service('workspaces.manager')->getActiveWorkspace()) {
      return $workspace->id();
    }
    return NULL;
  }

}
