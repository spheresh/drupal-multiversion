<?php

namespace Drupal\multiversion\Normalizer;

use Drupal\serialization\Normalizer\FieldItemNormalizer;

/**
 * Returns an empty value for the workspace reference field.
 */
class WorkspaceReferenceItemNormalizer extends FieldItemNormalizer {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = 'Drupal\multiversion\Plugin\Field\FieldType\WorkspaceReferenceItem';

  /**
   * {@inheritdoc}
   */
  public function normalize($field_item, $format = NULL, array $context = []) {
    return [];
  }

}
