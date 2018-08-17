<?php

namespace Drupal\Tests\multiversion\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\workspaces\Entity\Workspace;


/**
 * @group multiversion
 */
class EntityLoadingTest extends KernelTestBase {

  use ContentTypeCreationTrait;
  use NodeCreationTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'multiversion',
    'workspaces',
    'key_value',
    'serialization',
    'user',
    'system',
    'field',
    'filter',
    'node',
    'text',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('workspace');
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('workspace');
    $this->installEntitySchema('workspace_association');
    $this->installConfig(['multiversion', 'filter', 'node', 'system']);
    $this->installSchema('node', ['node_access']);
    $this->installSchema('system', ['key_value_expire', 'sequences']);
    $this->installSchema('key_value', ['key_value_sorted']);
    $multiversion_manager = $this->container->get('multiversion.manager');
    $multiversion_manager->enableEntityTypes();

    $this->createContentType(['type' => 'page']);
  }

  /**
   * Tests loading entities.
   */
  public function testLoadingEntities() {
    $admin = $this->createUser([
      'administer nodes',
      'create workspace',
      'view any workspace',
      'edit any workspace',
      'delete any workspace',
    ]);
    $this->setCurrentUser($admin);

    $storage = \Drupal::entityTypeManager()->getStorage('node');
    /** @var \Drupal\workspaces\WorkspaceManagerInterface $workspace_manager */
    $workspace_manager = \Drupal::service('workspaces.manager');
    $un_workspace = Workspace::create([
      'id' => 'un_workspace',
      'label' => 'Un Workspace',
    ]);
    $un_workspace->save();
    $workspace_manager->setActiveWorkspace($un_workspace);
    $this->assertEquals($un_workspace->id(), $workspace_manager->getActiveWorkspace()->id());

    $node = $this->createNode();

    $entities = $storage->loadMultiple();
    $this->assertEquals(1, count($entities));
    $this->assertEquals($node->id(), reset($entities)->id());

    $results = $storage->getQuery()->execute();
    $this->assertEquals(1, count($results));
    $this->assertEquals($node->id(), reset($results));

    $dau_workspace = Workspace::create([
      'id' => 'dau_workspace',
      'label' => 'Dau Workspace',
    ]);
    $dau_workspace->save();
    $workspace_manager->setActiveWorkspace($dau_workspace);
    $this->assertEquals($dau_workspace->id(), $workspace_manager->getActiveWorkspace()->id());

    $node2 = $this->createNode();

    $entities = $storage->loadMultiple();
    $this->assertEquals(1, count($entities));
    $this->assertEquals($node2->id(), reset($entities)->id());

    $results = $storage->getQuery()->execute();
    $this->assertEquals(1, count($results));
    $this->assertEquals($node2->id(), reset($results));

    // Create one more entity on the second workspace.
    $node3 = $this->createNode();

    $entities = $storage->loadMultiple();
    $this->assertEquals(2, count($entities));
    $this->assertEquals($node2->id(), $entities[$node2->id()]->id());
    $this->assertEquals($node3->id(), $entities[$node3->id()]->id());

    $results = $storage->getQuery()->execute();
    $this->assertEquals(2, count($results));
    $ids = array_values($results);
    $this->assertEquals($node2->id(), $ids[0]);
    $this->assertEquals($node3->id(), $ids[1]);

    // Switch back to the first workspace and check if we still have the same
    // number of nodes associated with it.
    $workspace_manager->setActiveWorkspace($un_workspace);

    $entities = $storage->loadMultiple();
    $this->assertEquals(1, count($entities));
    $this->assertEquals($node->id(), reset($entities)->id());

    $results = $storage->getQuery()->execute();
    $this->assertEquals(1, count($results));
    $this->assertEquals($node->id(), reset($results));
  }

}
