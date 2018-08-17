<?php

namespace Drupal\multiversion\Entity\Query\Sql;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Query\Sql\Query as BaseQuery;
use Drupal\multiversion\Entity\Query\QueryInterface;
use Drupal\multiversion\Entity\Query\QueryTrait;
use Drupal\workspaces\WorkspaceManagerInterface;

class Query extends BaseQuery implements QueryInterface {

  use QueryTrait {
    prepare as traitPrepare;
  }

  /**
   * Stores the SQL expressions used to build the SQL query.
   *
   * The array is keyed by the expression alias and the values are the actual
   * expressions.
   *
   * @var array
   *   An array of expressions.
   */
  protected $sqlExpressions = [];

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\multiversion\MultiversionManager
   */
  protected $multiversionManager;

  /**
   * @var \Drupal\workspaces\WorkspaceManagerInterface
   */
  private $workspaceManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeInterface $entity_type, $conjunction, Connection $connection, array $namespaces, WorkspaceManagerInterface $workspace_manager) {
    parent::__construct($entity_type, $conjunction, $connection, $namespaces);
    $this->entityTypeManager = \Drupal::service('entity_type.manager');
    $this->multiversionManager = \Drupal::service('multiversion.manager');
    $this->workspaceManager = $workspace_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function prepare() {
    $this->traitPrepare();

    // If the prepare() method from the trait decided that we need to alter this
    // query, we need to re-define the the key fields for fetchAllKeyed() as SQL
    // expressions.
    if ($this->sqlQuery->getMetaData('active_workspace_id')) {
      $id_field = $this->entityType->getKey('id');
      $revision_field = $this->entityType->getKey('revision');

      // Since the query is against the base table, we have to take into account
      // that the revision ID might come from the workspace_association
      // relationship, and, as a consequence, the revision ID field is no longer
      // a simple SQL field but an expression.
      $this->sqlFields = [];
      $this->sqlExpressions[$revision_field] = "COALESCE(workspace_association.target_entity_revision_id, base_table.$revision_field)";
      $this->sqlExpressions[$id_field] = "base_table.$id_field";
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  protected function finish() {
    foreach ($this->sqlExpressions as $alias => $expression) {
      $this->sqlQuery->addExpression($expression, $alias);
    }
    return parent::finish();
  }

}
