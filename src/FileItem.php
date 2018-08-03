<?php

namespace Drupal\multiversion;

use Drupal\file\Plugin\Field\FieldType\FileItem as CoreFileItem;

/**
 * Alternative file field item type class.
 *
 * @todo We have integrations tests that ensure this is working. But some unit
 *   tests would be good to ensure all possible scenarios are covered.
 */
class FileItem extends CoreFileItem {
  use EntityReferenceFieldTrait;
}
