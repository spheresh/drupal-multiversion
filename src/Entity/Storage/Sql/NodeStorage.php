<?php

namespace Drupal\multiversion\Entity\Storage\Sql;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\multiversion\Entity\Storage\ContentEntityStorageInterface;
use Drupal\multiversion\Entity\Storage\ContentEntityStorageTrait;
use Drupal\node\NodeStorage as CoreNodeStorage;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Storage handler for nodes.
 */
class NodeStorage extends CoreNodeStorage implements ContentEntityStorageInterface {

  use ContentEntityStorageTrait {
    delete as deleteEntities;
    truncate as truncateEntityTables;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('database'),
      $container->get('entity.manager'),
      $container->get('cache.entity'),
      $container->get('language_manager'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeInterface $entity_type, Connection $database, EntityManagerInterface $entity_manager, CacheBackendInterface $cache, LanguageManagerInterface $language_manager, ModuleHandler $module_handler) {
    parent::__construct($entity_type, $database, $entity_manager, $cache, $language_manager);
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   *
   * @todo: {@link https://www.drupal.org/node/2597534 Figure out why we need
   * this}, core seems to solve it some other way.
   */
  public function delete(array $entities) {
    // Delete all menus and comments before deleting the nodes.
    /** @var \Drupal\node\Entity\Node $entity */
    foreach ($entities as $entity) {
      if ($this->moduleHandler->moduleExists('comment')) {
        try {
          comment_entity_predelete($entity);
        }
        catch (\Exception $e) {
          // We don't want node delete to fail because of broken comments.
        }
      }

      if ($this->moduleHandler->moduleExists('menu_link_content')) {
        try {
          menu_link_content_entity_predelete($entity);
        }
        catch (\Exception $e) {
          // We don't want node delete to fail because of broken menu links.
        }
      }
    }

    $this->deleteEntities($entities);
  }

  /**
   * {@inheritdoc}
   */
  public function truncate() {
    $this->truncateEntityTables();
    /* @var \Drupal\node\NodeAccessControlHandlerInterface $access_control_handler */
    $access_control_handler = $this->entityManager->getAccessControlHandler('node');
    $access_control_handler->deleteGrants();
  }

}
