<?php

namespace Drupal\multiversion\EventSubscriber;

use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\multiversion\Event\MultiversionManagerEvent;
use Drupal\multiversion\Event\MultiversionManagerEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * MenuContentLinkMigrateSubscriber class.
 *
 * A menu_tree database table should be rediscovered
 * after enabling/disabling a menu_link_content entity.
 */
class FileUsageMigrateSubscriber implements EventSubscriberInterface {

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public function __construct(Connection $connection, ModuleHandler $module_handler) {
    $this->connection = $connection;
    $this->moduleHandler = $module_handler;
  }

  /**
   * Set rediscover property and rebuild menu tree.
   *
   * @param \Drupal\multiversion\Event\MultiversionManagerEvent $event
   */
  public function onPreMigrateFileUsage(MultiversionManagerEvent $event) {
    if ($this->moduleHandler->moduleExists('file')){
      foreach ($event->getEntityTypes() as $entity_type) {
        $type = $entity_type->id();
        $this->connection->delete('file_usage')
          ->condition('type', $type)
          ->execute();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [MultiversionManagerEvents::PRE_MIGRATE => ['onPreMigrateFileUsage']];
  }

}
