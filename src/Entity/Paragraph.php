<?php

namespace Drupal\multiversion\Entity;

use Drupal\paragraphs\Entity\Paragraph as ParagraphBase;

/**
 * Class Paragraph override Paragraph class.
 *
 * @package Drupal\multiversion\Entity
 */
class Paragraph extends ParagraphBase {

  /**
   * {@inheritdoc}
   */
  public function createDuplicate() {
    $duplicate = parent::createDuplicate();

    $duplicate->_rev->applyDefaultValue();

    return $duplicate;
  }

}
