<?php

namespace Drupal\multiversion\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Perform custom value transformations.
 *
 * @MigrateProcessPlugin(
 *   id = "multiversion_migrate_menu_link_content_parent"
 * )
 */
class MultiversionMigrateMenuLinkContentParent extends ProcessPluginBase {

  /**
   * It returns the correct (default) values of the parent on shutdown disabling menu_link_content.
   *
   * @see \Drupal\multiversion\Entity\MenuLinkContent::preSave
   *
   * @return string
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
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
