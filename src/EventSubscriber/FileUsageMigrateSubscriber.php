<?php

namespace Drupal\multiversion\EventSubscriber;

use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\multiversion\Event\MultiversionManagerEvent;
use Drupal\multiversion\Event\MultiversionManagerEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * FileUsageMigrateSubscriber class.
 *
 * Records in the file usage table associated with the migrated entity type
 * needs to be removed, they will be automatically re-created when this entity
 * type records will be migrated back by file field handler.
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
   * Class constructor.
   *
   * @param \Drupal\Core\Database\Connection $connection
   * @param \Drupal\Core\Extension\ModuleHandler $module_handler
   */
  public function __construct(Connection $connection, ModuleHandler $module_handler) {
    $this->connection = $connection;
    $this->moduleHandler = $module_handler;
  }

  /**
   * Remove file usage records associated with the migrated entity type.
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
