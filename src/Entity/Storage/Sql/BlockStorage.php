<?php

namespace Drupal\multiversion\Entity\Storage\Sql;

use Drupal\Core\Config\Entity\ConfigEntityStorage;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * A base entity storage class.
 */
class BlockStorage extends ConfigEntityStorage {

  /**
   * {@inheritdoc}
   */
  protected function doLoadMultiple(array $ids = NULL) {
    $entities = parent::doLoadMultiple($ids);
    $entity_type_manager = \Drupal::entityTypeManager();
    /** @var \Drupal\block\Entity\Block $entity */
    foreach ($entities as $id => $entity) {
      $plugin_id = $entity->getPluginId();
      if (strpos($plugin_id, ':') === FALSE) {
        continue;
      }
      list($provider, $uuid) = explode(':', $plugin_id);
      if ($provider && $provider === 'block_content' && $uuid) {
        $storage = $entity_type_manager->getStorage('block_content');
        $active_workspace = \Drupal::service('workspaces.manager')->getActiveWorkspace();
        $loaded_entity = $storage->loadByProperties(['uuid' => $uuid, 'workspace' => $active_workspace->id()]);
        $loaded_entity = reset($loaded_entity);
        if ($loaded_entity instanceof ContentEntityInterface) {
          $entities[$id]->addCacheableDependency($loaded_entity);
          $entities[$id]->addCacheableDependency($active_workspace);
        }
        else {
          unset($entities[$id]);
        }
      }
    }

    return $entities;
  }

}
