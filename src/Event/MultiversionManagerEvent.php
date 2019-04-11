<?php

namespace Drupal\multiversion\Event;

use Drupal\multiversion\MultiversionMigrationInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * MultiversionManagerEvent class.
 */
class MultiversionManagerEvent extends Event {

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
  public function getEntityTypes() {
    return $this->entityTypes;
  }

}
