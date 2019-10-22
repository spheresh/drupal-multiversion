<?php

namespace Drupal\multiversion\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\multiversion\Event\MultiversionManagerEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * SearchApiMigrateSubscriber class.
 *
 * Makes the Search API indexes read only before the multiversion migration
 * if they were writable and reverts back the changes after the migration since
 * we don't need to reindex each entity during the migration process.
 */
class SearchApiMigrateSubscriber implements EventSubscriberInterface {

  /**
   * An array of Search API Index entities.
   *
   * @var array $indexes
   */
  protected $indexes = [];

  /**
   * Constructs a new SearchApiMigrateSubscriber instance.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   The invalid plugin definition exception.
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   The plugin not found exception.
   */
  public function __construct(ModuleHandlerInterface $module_handler, EntityTypeManagerInterface $entity_type_manager) {
    if ($module_handler->moduleExists('search_api')) {
      $indexes = $entity_type_manager->getStorage('search_api_index')->loadMultiple();

      foreach ($indexes as $index_id => $index) {
        // We are interested only in enabled and writable indexes.
        if ($index->status() && !$index->isReadOnly()) {
          $this->indexes[$index_id] = $index;
        }
      }
    }
  }

  /**
   * Disable Search API node indexing.
   */
  public function onPreMigrate() {
    $this->indexSetReadOnly(TRUE);
  }

  /**
   * Enable Search API node indexing.
   *
   * Note that this will not be triggered in case if the migration process will fail with an exception.
   */
  public function onPostMigrate() {
    $this->indexSetReadOnly(FALSE);
  }

  /**
   * Set the index read only property state.
   *
   * @param bool $state
   *  The read only state.
   */
  private function indexSetReadOnly($state) {
    foreach ($this->indexes as $index_key => $index) {
      $index->set('read_only', $state);
      $index->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      MultiversionManagerEvents::PRE_MIGRATE => ['onPreMigrate'],
      MultiversionManagerEvents::POST_MIGRATE => ['onPostMigrate'],
    ];
  }

}
