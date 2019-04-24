<?php

namespace Drupal\multiversion\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\migrate\process\Explode;
use Drupal\migrate\Row;

/**
 * Perform custom value transformations.
 *
 * @MigrateProcessPlugin(
 *   id = "splice",
 *   handle_multiples = TRUE
 * )
 */
class Splice extends Explode {

  /**
   * {@inheritDoc}
   *
   * @throws \Drupal\migrate\MigrateException
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $value = parent::transform($value, $migrate_executable, $row, $destination_property);

    if (isset($this->configuration['slice'])) {
      $value = array_splice($value, 0, $this->configuration['slice']);
    }

    return implode($this->configuration['delimiter'], $value);
  }

  /**
   * {@inheritdoc}
   */
  public function multiple() {
    return TRUE;
  }

}
