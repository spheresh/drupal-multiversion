<?php

namespace Drupal\multiversion\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\menu_link_content\Entity\MenuLinkContent as CoreMenuLinkContent;

class MenuLinkContent extends CoreMenuLinkContent {

  /**
   * {@inheritdoc}
   */
  public function getPluginId() {
    // Make the plugin ID unique adding the entity ID.
    return 'menu_link_content:' . $this->uuid() . ':' . $this->id();
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    $parent = $this->parent->value;
    if ($parent && strpos($parent, 'menu_link_content') === 0) {
      list($content_type_id, $parent_uuid, $parent_id) = explode(':', $parent);
      if (empty($parent_id) && $content_type_id == 'menu_link_content') {
        $parents = $storage->loadByProperties(['uuid' => $parent_uuid]);
        if (!empty($parents)) {
          $parent = reset($parents);
          $parent_id = $parent->id();
          $this->parent->value = "$content_type_id:$parent_uuid:$parent_id";
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginDefinition() {
    $definition = parent::getPluginDefinition();
    $definition['class'] = 'Drupal\multiversion\Plugin\Menu\MenuLinkContent';
    return $definition;
  }

}
