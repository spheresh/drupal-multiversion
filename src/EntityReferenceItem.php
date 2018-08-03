<?php

namespace Drupal\multiversion;

use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem as CoreEntityReferenceItem;

/**
 * Alternative entity reference base field item type class.
 *
 * @todo We have integrations tests that ensure this is working. But some unit
 *   tests would be good to ensure all possible scenarios are covered.
 */
class EntityReferenceItem extends CoreEntityReferenceItem {
  use EntityReferenceFieldTrait;
}
