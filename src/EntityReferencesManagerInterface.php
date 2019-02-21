<?php

namespace Drupal\multiversion;

use Drupal\Core\Entity\EntityInterface;

interface EntityReferencesManagerInterface {

  public function getMultiversionableReferencedEntities(EntityInterface $entity);

  public function getReferencedEntitiesUuids(EntityInterface $entity);

  public function getReferencedEntitiesIds(EntityInterface $entity);

}
