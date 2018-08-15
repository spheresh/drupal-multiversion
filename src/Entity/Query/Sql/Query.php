<?php

namespace Drupal\multiversion\Entity\Query\Sql;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\workspaces\EntityQuery\Query as WorkspacesQuery;
use Drupal\multiversion\Entity\Query\QueryInterface;
use Drupal\multiversion\Entity\Query\QueryTrait;
use Drupal\workspaces\WorkspaceManagerInterface;

class Query extends WorkspacesQuery implements QueryInterface {

  use QueryTrait;

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
  private $workspace_manager;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeInterface $entity_type, $conjunction, Connection $connection, array $namespaces, WorkspaceManagerInterface $workspace_manager) {
    parent::__construct($entity_type, $conjunction, $connection, $namespaces, $workspace_manager);
    $this->entityTypeManager = \Drupal::service('entity_type.manager');
    $this->multiversionManager = \Drupal::service('multiversion.manager');
  }

}
