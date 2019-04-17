<?php

namespace Drupal\multiversion\Event;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\multiversion\MultiversionMigrationInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * MultiversionManagerEvent class.
 *
 * Subscribers of this event can add additional logic for specific content type
 * on pre/post import on including/excluding content type to multiversionable.
 * As examples:
 *  - Rebuild menu_tree table on a menu_link_content migration.
 *  - Rebuild node_grants table permissions.
 */
class MultiversionManagerEvent extends Event {

  /**
   * List of content type affected by enableEntityTypes/disableEntityTypes function of MultiversionManager class.
   *
   * @var \Drupal\Core\Entity\ContentEntityTypeInterface[]
   */
  protected $entityTypes;

  /**
   * @var \Drupal\multiversion\MultiversionMigrationInterface
   */
  protected $migration;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $entity_types, MultiversionMigrationInterface $migration) {
    $this->entityTypes = $entity_types;
    $this->migration = $migration;
  }

  /**
   * It helps the event subscriber to validate the entity_type_id value.
   *
   * @return \Drupal\Core\Entity\ContentEntityTypeInterface|NULL
   */
  public function getEntityType($entity_type_id) {
    if (isset($this->entityTypes[$entity_type_id]) && $this->entityTypes[$entity_type_id] instanceof ContentEntityTypeInterface) {
      return $this->entityTypes[$entity_type_id];
    }
    return NULL;
  }
}
