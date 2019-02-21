<?php

namespace Drupal\multiversion;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

class EntityReferencesManager implements EntityReferencesManagerInterface {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * @var \Drupal\multiversion\MultiversionManagerInterface
   */
  protected $multiversionManager;

  /**
   * EntityReferencesManager constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\multiversion\MultiversionManagerInterface $multiversion_manager
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, MultiversionManagerInterface $multiversion_manager, EntityFieldManagerInterface $entity_field_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info) {
    $this->entityTypeManager = $entity_type_manager;
    $this->multiversionManager = $multiversion_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
  }

  /**
   * {@inheritdoc}
   */
  public function getMultiversionableReferencedEntities(EntityInterface $entity) {
    $references = $entity->referencedEntities();
    foreach ($references as $key => $reference) {
      if (!($reference instanceof ContentEntityInterface)
        || !$this->multiversionManager->isEnabledEntityType($reference->getEntityType())) {
        unset($references[$key]);
      }
    }
    return $references;
  }

  /**
   * {@inheritdoc}
   */
  public function getReferencedEntitiesUuids(EntityInterface $entity) {
    $referenced_uuids = [];
    foreach ($this->getMultiversionableReferencedEntities($entity) as $reference) {
      $referenced_uuids[] = $reference->uuid();
    }
    return $referenced_uuids;
  }

  /**
   * {@inheritdoc}
   */
  public function getReferencedEntitiesIds(EntityInterface $entity) {
    $referenced_revisions = [];
    foreach ($this->getMultiversionableReferencedEntities($entity) as $reference) {
      $referenced_revisions[$reference->getEntityTypeId()][$reference->getRevisionId()] = (int) $reference->id();
    }
    return $referenced_revisions;
  }

}
