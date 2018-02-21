<?php

namespace Drupal\multiversion\Entity;

use Drupal\menu_link_content\Entity\MenuLinkContent as CoreMenuLinkContent;

class MenuLinkContent extends CoreMenuLinkContent {

  /**
   * {@inheritdoc}
   */
  public function getPluginId() {
    // Make the plugin ID unique adding the entity ID.
    return 'menu_link_content:' . $this->uuid() . ':' . $this->id();
  }

}
