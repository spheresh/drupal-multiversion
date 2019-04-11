<?php

namespace Drupal\multiversion\Event;

use Drupal\multiversion\MultiversionMigrationInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 *
 */
class MultiversionManagerEvent extends Event {

  protected $entityTypes;

  /**
   *
   */
  public function __construct($entity_types, MultiversionMigrationInterface $migration) {
    $this->entityTypes = $entity_types;
  }

  /**
   *
   */
  public function getEntityTypes() {
    return $this->entityTypes;
  }

}
