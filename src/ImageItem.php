<?php

namespace Drupal\multiversion;

use Drupal\image\Plugin\Field\FieldType\ImageItem as CoreImageItem;

/**
 * Alternative image field item type class.
 *
 * @todo We have integrations tests that ensure this is working. But some unit
 *   tests would be good to ensure all possible scenarios are covered.
 */
class ImageItem extends CoreImageItem {
  use EntityReferenceFieldTrait;
}
