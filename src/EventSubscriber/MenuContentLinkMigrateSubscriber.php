<?php

namespace Drupal\multiversion\EventSubscriber;

use Drupal\Core\Database\Connection;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\multiversion\Event\MultiversionManagerEvent;
use Drupal\multiversion\Event\MultiversionManagerEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * MenuContentLinkMigrateSubscriber class.
 */
class MenuContentLinkMigrateSubscriber implements EventSubscriberInterface {

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * @var \Drupal\Core\Menu\MenuLinkManagerInterface
   */
  protected $menuLinkManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(Connection $connection, MenuLinkManagerInterface $menu_link_manager) {
    $this->connection = $connection;
    $this->menuLinkManager = $menu_link_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function onPostMigrateLinks(MultiversionManagerEvent $event) {
    if ($entity_type = $event->checkEntityType('menu_link_content')) {
      $data_table = $entity_type->getDataTable();
      // @TODO Add description here.
      $this->connection->update($data_table)
        ->fields(['rediscover' => 1])
        ->execute();
      $this->menuLinkManager->rebuild();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[MultiversionManagerEvents::POSTMIGRATE] = ['onPostMigrateLinks'];
    return $events;
  }

}
