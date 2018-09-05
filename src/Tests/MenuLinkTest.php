<?php

namespace Drupal\multiversion\Tests;

use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\workspaces\Entity\Workspace;
use Drupal\simpletest\WebTestBase;

/**
 * Tests menu links deletion.
 *
 * @group multiversion
 */
class MenuLinkTest extends WebTestBase {

  protected $strictConfigSchema = FALSE;

  /**
   * @var \Drupal\workspaces\WorkspaceManager
   */
  protected $workspaceManager;

  /**
   * @var \Drupal\workspaces\Entity\Workspace
   */
  protected $initialWorkspace;

  /**
   * @var \Drupal\workspaces\Entity\Workspace
   */
  protected $newWorkspace;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'workspaces',
    'multiversion',
    'menu_link_content',
    'block',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->workspaceManager = \Drupal::service('workspaces.manager');
    $web_user = $this->drupalCreateUser(['administer menu', 'administer workspaces']);
    $this->drupalLogin($web_user);
    $this->drupalPlaceBlock('system_menu_block:main');

    $this->initialWorkspace = Workspace::create(['id' => 'foo', 'label' => 'Foo']);
    $this->initialWorkspace->save();
    $this->workspaceManager->setActiveWorkspace($this->initialWorkspace);
    $this->newWorkspace = Workspace::create(['id' => 'bar', 'label' => 'Bar']);
    $this->newWorkspace->save();
  }

  public function testMenuLinksInDifferentWorkspaces() {
    /** @var MenuLinkContentInterface $pineapple */
    $pineapple = MenuLinkContent::create([
      'menu_name' => 'main',
      'link' => 'route:user.page',
      'title' => 'Pineapple'
    ]);
    $pineapple->save();

    /** @var \Drupal\workspaces\WorkspaceAssociationStorageInterface $workspace_association_storage */
    $workspace_association_storage = \Drupal::entityTypeManager()->getStorage('workspace_association');
    $tracking_workspace_ids = $workspace_association_storage->getEntityTrackingWorkspaceIds($pineapple);
    $this->assertEqual(1, count($tracking_workspace_ids), 'Pineapple tracked in correct number of workspaces.');
    $this->assertTrue(in_array($this->initialWorkspace->id(), $tracking_workspace_ids), 'Pineapple in initial workspace.');

    $this->workspaceManager->setActiveWorkspace($this->newWorkspace);

    // Save another menu link.
    /** @var MenuLinkContentInterface $pear */
    $pear = MenuLinkContent::create([
      'menu_name' => 'main',
      'link' => 'route:user.page',
      'title' => 'Pear',
    ]);
    $pear->save();

    $tracking_workspace_ids = $workspace_association_storage->getEntityTrackingWorkspaceIds($pear);
    $this->assertEqual(1, count($tracking_workspace_ids), 'Pear tracked in correct number of workspaces.');
    $this->assertTrue(in_array($this->newWorkspace->id(), $tracking_workspace_ids), 'Pear in new workspace');

    // Cheack again Pineapple.
    $tracking_workspace_ids = $workspace_association_storage->getEntityTrackingWorkspaceIds($pineapple);
    $this->assertEqual(1, count($tracking_workspace_ids), 'Pineapple tracked in correct number of workspaces.');
    $this->assertTrue(in_array($this->initialWorkspace->id(), $tracking_workspace_ids), 'Pineapple in initial workspace.');
  }

}
