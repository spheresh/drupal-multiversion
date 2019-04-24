<?php

namespace Drupal\multiversion\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\multiversion\Event\MultiversionManagerEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\multiversion\Workspace\WorkspaceManagerInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\multiversion\Event\MultiversionManagerEvent;

/**
 * EntityWorkspaceMigrateSubscriber class.
 *
 * Deletes all entities that do not belong to the current workspace.
 * This is a necessary step since "workspace" and "_deleted" properties
 * will be deleted and this is possible to get duplicated entities.
 */
class EntityWorkspaceMigrateSubscriber implements EventSubscriberInterface {

  /**
   * The amount of entities to load at once.
   *
   * @var int $chunkSize
   */
  const CHUNK_SIZE = 100;

  /**
   * An array of entity types enabled in Multiversion.
   *
   * @var array $enabledEntityTypes
   */
  protected $enabledEntityTypes;

  /**
   * Default workspace ID.
   *
   * @var int $defaultWorkspaceId
   */
  protected $defaultWorkspaceId;

  /**
   * Active workspace.
   *
   * @var \Drupal\multiversion\Entity\Workspace $activeWorkspace
   */
  protected $activeWorkspace;

  /**
   * The workspace manager.
   *
   * @var \Drupal\multiversion\Workspace\WorkspaceManagerInterface $workspaceManager
   */
  protected $workspaceManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The entity query.
   *
   * @var \Drupal\Core\Entity\Query\QueryFactory $entityQuery
   */
  protected $entityQuery;

  /**
   * The container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface $container
   */
  protected $container;

  /**
   * Constructs a new EntityWorkspaceMigrateSubscriber instance.
   *
   * @param EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param WorkspaceManagerInterface $workspace_manager
   *   The workspace manager.
   * @param QueryFactory $entity_query
   *   The query factory.
   * @param ContainerInterface $container
   *   The container
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, WorkspaceManagerInterface $workspace_manager, QueryFactory $entity_query, ContainerInterface $container) {
    $this->entityTypeManager = $entity_type_manager;
    $this->workspaceManager = $workspace_manager;
    $this->entityQuery = $entity_query;
    $this->container = $container;
    $this->defaultWorkspaceId = $this->container->getParameter('workspace.default');
    $this->activeWorkspace = $this->workspaceManager->getActiveWorkspace();
  }

  /**
   * Delete all entities that do not belong to the default workspace.
   */
  public function onPreMigrate(MultiversionManagerEvent $event) {
    $workspaces = $this->workspaceManager->loadMultiple();

    // Keep everything for the default workspace.
    unset($workspaces[$this->defaultWorkspaceId]);

    foreach ($workspaces as $workspace_id => $workspace) {
      $this->workspaceManager->setActiveWorkspace($workspace);

      foreach ($event->getEntityTypes() as $entity_type_id => $entity_type) {
        $entity_ids = $this->entityQuery->get($entity_type_id)
          ->condition('workspace', $workspace_id)
          ->execute();
        $controller = $this->entityTypeManager->getStorage($entity_type_id);

        foreach (array_chunk($entity_ids, self::CHUNK_SIZE) as $chunk_data) {
          $entities = $controller->loadMultiple($chunk_data);
          $controller->delete($entities);
        }
      }
    }
    // Set the active workspace back to the default value.
    $this->workspaceManager->setActiveWorkspace($this->activeWorkspace);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      MultiversionManagerEvents::PRE_MIGRATE => ['onPreMigrate'],
    ];
  }

}
