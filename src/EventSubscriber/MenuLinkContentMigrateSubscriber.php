<?php

namespace Drupal\multiversion\EventSubscriber;

use Drupal\Core\Database\Connection;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\multiversion\Event\MultiversionManagerEvent;
use Drupal\multiversion\Event\MultiversionManagerEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * MenuContentLinkMigrateSubscriber class.
 *
 * A menu_tree database table should be rediscovered
 * after enabling/disabling a menu_link_content entity.
 */
class MenuLinkContentMigrateSubscriber implements EventSubscriberInterface {

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
   * Set rediscover property and rebuild menu tree.
   *
   * @param \Drupal\multiversion\Event\MultiversionManagerEvent $event
   */
  public function onPostMigrateLinks(MultiversionManagerEvent $event) {
    if ($entity_type = $event->getEntityType('menu_link_content')) {
      $data_table = $entity_type->getDataTable();
      // Set a rediscover and rebuild menu_tree table.
      // @see \Drupal\menu_link_content\Plugin\Deriver\MenuLinkContentDeriver
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
    return [MultiversionManagerEvents::POST_MIGRATE => ['onPostMigrateLinks']];
  }

}
