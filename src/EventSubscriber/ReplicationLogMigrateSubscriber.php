<?php

namespace Drupal\multiversion\EventSubscriber;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\multiversion\Entity\Storage\Sql\ContentEntityStorage;
use Drupal\multiversion\Event\MultiversionManagerEvent;
use Drupal\multiversion\Event\MultiversionManagerEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * ReplicationLogMigrateSubscriber class.
 */
class ReplicationLogMigrateSubscriber implements EventSubscriberInterface {


  /**
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManager $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function onPreMigrateReplicationLog(MultiversionManagerEvent $event) {
    $entity_types = $event->getEntityTypes();
    if (isset($entity_types["replication_log"]) && $entity_types["replication_log"] instanceof ContentEntityTypeInterface) {
      $storage = $this->entityTypeManager->getStorage('replication_log');
      if ($storage instanceof ContentEntityStorage) {
        $original_storage = $storage->getOriginalStorage();
        $entities = $original_storage->loadMultiple();
        $storage->purge($entities);
      }
      else {
        $entities = $storage->loadMultiple();
        $storage->delete($entities);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[MultiversionManagerEvents::PREMIGRATE] = ['onPreMigrateReplicationLog'];
    return $events;
  }

}
