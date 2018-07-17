<?php

namespace Drupal\multiversion;

use Drupal\Core\TypedData\TypedData;

/**
 * The 'is_stub' property for revision token fields.
 */
class IsStub extends TypedData {

  /**
   * {@inheritdoc}
   */
  public function getValue($langcode = NULL) {
    // Check if we have explicitly set a value.
    if (isset($this->value) && $this->value !== NULL) {
      return $this->value;
    }
    // Check if the entity was saved as a stub earlier.
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->getRoot()->getValue();
    if (!$entity->isNew() && $entity->_rev->value == '0-00000000000000000000000000000000') {
      return TRUE;
    }
    // Fall back on FALSE as the default value.
    return FALSE;
  }

}
