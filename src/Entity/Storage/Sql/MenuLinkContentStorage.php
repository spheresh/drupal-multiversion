<?php

namespace Drupal\multiversion\Entity\Storage\Sql;

use Drupal\multiversion\Entity\Storage\ContentEntityStorageInterface;

/**
 * Storage handler for menu link content.
 */
class MenuLinkContentStorage extends ContentEntityStorage implements ContentEntityStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function delete(array $entities) {
    parent::delete($entities);

    // Remove the deleted entity as parent for all children.
    foreach ($entities as $entity) {
      $children = $this->loadByProperties(['parent' => $entity->getPluginId()]);
      foreach ($children as $child) {
        $child->parent->value = '';
        $child->save();
      }
    }

    /** @var \Drupal\Core\Menu\MenuLinkManagerInterface $menu_link_manager */
    $menu_link_manager = \Drupal::service('plugin.manager.menu.link');

    foreach ($entities as $menu_link) {
      // Remove link definition from the menu tree storage.
      $menu_link_manager->removeDefinition($menu_link->getPluginId(), FALSE);
    }
  }

}
