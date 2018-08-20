<?php

namespace Drupal\multiversion\Tests;

use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\multiversion\Entity\Query\QueryInterface;
use Drupal\multiversion\Entity\Storage\ContentEntityStorageInterface;
use Drupal\simpletest\WebTestBase;

/**
 * Tests the module installation.
 *
 * @group multiversion
 */
class InstallTest extends WebTestBase {

  protected $strictConfigSchema = FALSE;

  /**
   * @var \Drupal\multiversion\MultiversionManagerInterface
   */
  protected $multiversionManager;

  /**
   * @var \Drupal\Core\Extension\ModuleInstallerInterface
   */
  protected $moduleInstaller;

  /**
   * The entity definition update manager.
   *
   * @var \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface
   */
  protected $entityDefinitionUpdateManager;

  /**
   * @var array
   */
  protected $entityTypes = [
    'entity_test' => [],
    'entity_test_rev' => [],
    'entity_test_mul' => [],
    'entity_test_mulrev' => [],
    'node' => ['type' => 'article', 'title' => 'Foo'],
    'file' => [
      'uid' => 1,
      'filemime' => 'text/plain',
      'status' => FILE_STATUS_PERMANENT,
    ],
    'block_content' => [
      'info' => 'New block',
      'type' => 'basic',
    ],
    'menu_link_content' => [
      'menu_name' => 'menu_test',
      'bundle' => 'menu_link_content',
      'link' => [['uri' => 'user-path:/']],
    ],
    'shortcut' => [
      'shortcut_set' => 'default',
      'title' => 'Llama',
      'weight' => 0,
      'link' => [['uri' => 'internal:/admin']],
    ],
    'comment' => [
      'entity_type' => 'node',
      'comment_type' => 'comment',
      'field_name' => 'comment',
      'subject' => 'How much wood would a woodchuck chuck',
      'mail' => 'someone@example.com',
    ],
  ];

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'entity_test',
    'node',
    'comment',
    'menu_link_content',
    'block_content',
    'shortcut',
    'file',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->moduleInstaller = \Drupal::service('module_installer');

    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);
  }

  public function testEnableWithExistingContent() {
    foreach ($this->entityTypes as $entity_type_id => $values) {
      $storage = \Drupal::entityTypeManager()->getStorage($entity_type_id);

      $count = 2;
      for ($i = 0; $i < $count; $i++) {
        if ($entity_type_id == 'file') {
          $values['filename'] = "test$i.txt";
          $values['uri'] = "public://test$i.txt";
          $this->assertTrue($values['uri'], t('The test file has been created.'));
          $file = $storage->create($values);
          file_put_contents($file->getFileUri(), 'Hello world!');
          $file->save();
          continue;
        }
        $storage->create($values)->save();
      }
      $count_before[$entity_type_id] = $count;
    }

    // Installing Multiversion will trigger the migration of existing content.
    $this->moduleInstaller->install(['multiversion']);
    $this->multiversionManager = \Drupal::service('multiversion.manager');

    // Check if all updates have been applied.
    $update_manager = \Drupal::service('entity.definition_update_manager');
    $this->assertFalse($update_manager->needsUpdates(), 'All compatible entity types have been updated.');


    $ids_after = [];
    // Now check that the previously created entities still exist, have the
    // right IDs and are multiversion enabled. That means profit. Big profit.
    foreach ($this->entityTypes as $entity_type_id => $values) {
      $manager = \Drupal::entityTypeManager();
      $entity_type = $manager->getDefinition($entity_type_id);
      $storage = $manager->getStorage($entity_type_id);
      $id_key = $entity_type->getKey('id');

      $this->assertTrue($this->multiversionManager->isEnabledEntityType($entity_type), "$entity_type_id was enabled for Multiversion.");
      $this->assertTrue($storage instanceof ContentEntityStorageInterface, "$entity_type_id got the correct storage handler assigned.");
      $this->assertTrue($storage->getQuery() instanceof QueryInterface, "$entity_type_id got the correct query handler assigned.");
      $this->assertTrue($entity_type->isRevisionable(), "$entity_type_id is revisionable.");
      $this->assertTrue($entity_type->entityClassImplements(EntityPublishedInterface::class), "$entity_type_id is publishable.");

      $ids_after[$entity_type_id] = $storage->getQuery()->execute();
      $this->assertEqual($count_before[$entity_type_id], count($ids_after[$entity_type_id]), "All ${entity_type_id}s were migrated.");

      foreach ($ids_after[$entity_type_id] as $revision_id => $entity_id) {
        $rev = (int) $storage->getQuery()
          ->condition($id_key, $entity_id)
          ->condition('_rev', 'NULL', '<>')
          ->count()
          ->execute();
        $this->assertEqual($rev, 1, "$entity_type_id $entity_id has a revision hash in database");

        $deleted = (int) $storage->getQuery()
          ->condition($id_key, $entity_id)
          ->condition('_deleted', 0)
          ->count()
          ->execute();
        $this->assertEqual($deleted, 1, "$entity_type_id $entity_id is not marked as deleted in database");

      }
    }

    // Now install a module with an entity type AFTER the migration and assert
    // that is being returned as supported and enabled.
    $this->moduleInstaller->install(['taxonomy']);

    $entity_type_id = 'taxonomy_term';
    $storage = \Drupal::entityTypeManager()->getStorage($entity_type_id);
    $entity_type = $storage->getEntityType();
    $this->assertTrue($this->multiversionManager->isEnabledEntityType($entity_type), 'Newly installed entity types got enabled as well.');
    $this->assertTrue($storage instanceof ContentEntityStorageInterface, "$entity_type_id got the correct storage handler assigned.");
    $this->assertTrue($storage->getQuery() instanceof QueryInterface, "$entity_type_id got the correct query handler assigned.");
    $this->assertTrue($entity_type->isRevisionable(), "$entity_type_id is revisionable.");
    $this->assertTrue($entity_type->entityClassImplements(EntityPublishedInterface::class), "$entity_type_id is publishable.");

    $this->assertFalse($update_manager->needsUpdates(), 'There are not new updates to apply.');
  }

}
