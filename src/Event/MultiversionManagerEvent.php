<?php

namespace Drupal\multiversion\Event;

use Drupal\Core\Entity\ContentEntityTypeInterface;
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
   * List of entity types keyed with their entity id.
   *
   * @var \Drupal\Core\Entity\ContentEntityTypeInterface[]
   */
  protected $entityTypes;

  /**
   * Operation type name.
   *
   * @var string
   */
  protected $op;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\ContentEntityTypeInterface[] $entity_types
   * @param $op
   */
  public function __construct(array $entity_types, $op) {
    $this->entityTypes = $entity_types;
    $this->op = $op;
  }

  /**
   * Get the operation type value.
   *
   * @return string
   */
  public function getOp() {
    return $this->op;
  }

  /**
   * Get the list of entity types.
   *
   * @return \Drupal\Core\Entity\ContentEntityTypeInterface[]
   */
  public function getEntityTypes() {
    return $this->entityTypes;
  }

  /**
   * It helps the event subscriber to validate the entity_type_id value.
   *
   * @param string $entity_type_id
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
