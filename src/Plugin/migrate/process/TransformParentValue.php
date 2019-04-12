<?php

namespace Drupal\multiversion\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Perform custom value transformations.
 *
 * @MigrateProcessPlugin(
 *   id = "transform_parent_value"
 * )
 *
 * To do custom value transformations use the following:
 *
 * @code
 * field_text:
 *   plugin: transform_parent_value
 *   source: text
 * @endcode
 */
class TransformParentValue extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    // Throw an error if value and reverse value are the same.
    if (!empty($value)) {
      $parent_id = explode(':', $value);
      if ($parent_id[0] === 'menu_link_content' && count($parent_id) === 3) {
        unset($parent_id[2]);
        return implode(':', $parent_id);
      }
    }
    return $value;
  }

}
