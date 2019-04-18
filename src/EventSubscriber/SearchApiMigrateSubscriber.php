<?php

namespace Drupal\dset_search\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\multiversion\Event\MultiversionManagerEvent;
use Drupal\multiversion\Event\MultiversionManagerEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * SearchApiMigrateSubscriber class.
 */
class SearchApiMigrateSubscriber implements EventSubscriberInterface {

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * @var \Drupal\Core\Menu\MenuLinkManagerInterface
   */
  protected $menuLinkManager;

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * @var string Default Solr site index ID
   */
  protected $solrIndexId = 'search_api.index.solr_site_index';

  /**
   * @var bool Read only state of the index
   */
  protected $solrIndexReadOnlyStateInitial;

  /**
   * {@inheritdoc}
   */
  public function __construct(Connection $connection, MenuLinkManagerInterface $menu_link_manager, ConfigFactoryInterface $config_factory) {
    $this->connection = $connection;
    $this->menuLinkManager = $menu_link_manager;
    $this->configFactory = $config_factory;
  }

  /**
   * Disable Search API node indexing.
   */
  public function onPreMigrate(MultiversionManagerEvent $event) {
    $config = $this->configFactory->get($this->solrIndexId);
    $read_only_state = $config->get('read_only');
    $this->solrIndexReadOnlyStateInitial = $read_only_state;
    // Set to TRUE if it was FALSE.
    if (!$this->solrIndexReadOnlyStateInitial) {
      $config = $this->configFactory->getEditable($this->solrIndexId);
      $config->set('read_only', TRUE)->save();
    }
  }

  /**
   * Enable Search API node indexing.
   */
  public function onPostMigrate(MultiversionManagerEvent $event) {
    // Set back to FALSE if it was like this initially.
    if (!$this->solrIndexReadOnlyStateInitial) {
      $config = $this->configFactory->getEditable($this->solrIndexId);
      $config->set('read_only', FALSE)->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      MultiversionManagerEvents::PREMIGRATE => ['onPreMigrate'],
      MultiversionManagerEvents::POSTMIGRATE => ['onPostMigrate'],
    ];
  }

}
