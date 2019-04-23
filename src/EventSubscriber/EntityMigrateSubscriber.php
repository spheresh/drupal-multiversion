<?php

namespace Drupal\multiversion\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\multiversion\Event\MultiversionManagerEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\multiversion\Workspace\WorkspaceManagerInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * EntityWorkspaceMigrateSubscriber class.
 *
 * Deletes all entities that do not belong to the current workspace.
 * This is a necessary step since "workspace" and "_deleted" properties
 * will be deleted and this is possible to get duplicated entities.
 */
class EntityMigrateSubscriber implements EventSubscriberInterface {

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
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   */
  protected $configFactory;

  /**
   * EntityMigrateSubscriber constructor.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Tne entity type manager.
   * @param \Drupal\multiversion\Workspace\WorkspaceManagerInterface $workspace_manager
   *   The workspace manager.
   * @param \Drupal\Core\Entity\Query\QueryFactory $entity_query
   *   The query factory.
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ModuleHandlerInterface $module_handler, EntityTypeManagerInterface $entity_type_manager, WorkspaceManagerInterface $workspace_manager, QueryFactory $entity_query, ContainerInterface $container, ConfigFactoryInterface $config_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityQuery = $entity_query;
    $this->container = $container;
    $this->configFactory = $config_factory;
    $this->enabledEntityTypes = $this->configFactory->get('multiversion.settings')->get('enabled_entity_types');

    // TODO: provide a proper handling of the workspace module dependency.
    if ($module_handler->moduleExists('workspace')) {
      $this->workspaceManager = $workspace_manager;
      $this->defaultWorkspaceId = $this->container->getParameter('workspace.default');
      $this->activeWorkspace = $this->workspaceManager->getActiveWorkspace();
    }
  }

  /**
   * Delete all entities that do not belong to the default workspace.
   */
  public function onPreMigrate() {
    $workspaces = $this->workspaceManager->loadMultiple();

    if (!($this->defaultWorkspaceId == $this->activeWorkspace->id())) {
      // Something went wrong.
      return;
    }

    // Keep everything for the default workspace.
    unset($workspaces[$this->defaultWorkspaceId]);

    foreach ($workspaces as $workspace_id => $workspace) {
      $this->workspaceManager->setActiveWorkspace($workspace);

      foreach ($this->enabledEntityTypes as $entity_type) {
        $entity_ids = $this->entityQuery->get($entity_type)
          ->condition('workspace', $workspace_id)
          ->execute();
        $controller = $this->entityTypeManager->getStorage($entity_type);
        // TODO: chunks?
        $entities = $controller->loadMultiple($entity_ids);
        $controller->delete($entities);
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
