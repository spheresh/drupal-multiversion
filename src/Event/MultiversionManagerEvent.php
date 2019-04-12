<?php

namespace Drupal\multiversion\Event;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\multiversion\MultiversionMigrationInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * MultiversionManagerEvent class.
 */
class MultiversionManagerEvent extends Event {

  /**
   * @var \Drupal\Core\Entity\ContentEntityTypeInterface[]
   */
  protected $entityTypes;

  /**
   * {@inheritdoc}
   */
  public function __construct($entity_types, MultiversionMigrationInterface $migration) {
    $this->entityTypes = $entity_types;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityType($entity_type) {
    if (isset($this->entityTypes[$entity_type]) && $this->entityTypes[$entity_type] instanceof ContentEntityTypeInterface) {
      return $this->entityTypes[$entity_type];
    }
    return NULL;
  }
}
