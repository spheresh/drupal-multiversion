<?php

namespace Drupal\multiversion\Tests\Views;

use Drupal\workspaces\Entity\Workspace;

/**
 * Tests the workspace and current_workspace field handlers.
 *
 * @group multiversion
 * @see \Drupal\multiversion\Plugin\views\filter\CurrentWorkspace
 */
class WorkspaceTest extends MultiversionTestBase {

  protected $strictConfigSchema = FALSE;

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_current_workspace'];

  /**
   * Tests the workspace filter.
   */
  public function testWorkspace() {
    $admin_user = $this->drupalCreateUser(['administer workspaces', 'bypass node access']);
    $uid = $admin_user->id();
    $this->drupalLogin($admin_user);

    /** @var \Drupal\workspaces\WorkspaceManagerInterface $workspace_manager */
    $workspace_manager = \Drupal::service('workspaces.manager');

    // Get workspace nodes will be added to.
    /** @var \Drupal\workspaces\Entity\Workspace $initial_workspace */
    $initial_workspace = $workspace_manager->getActiveWorkspace();

    // Create two nodes on 'default' workspace.
    $node1 = $this->drupalCreateNode(['uid' => $uid]);
    $node2 = $this->drupalCreateNode(['uid' => $uid]);

    // Create a new workspace and switch to it.
    $new_workspace = Workspace::create(['id' => 'new_workspace', 'label' => 'New Workspace']);
    $new_workspace->save();
    $workspace_manager->setActiveWorkspace($new_workspace);
    $this->assertEqual($new_workspace->id(), $workspace_manager->getActiveWorkspace()->id());

    // Create two nodes on 'new_workspace' workspace.
    $node3 = $this->drupalCreateNode(['uid' => $uid]);
    $node4 = $this->drupalCreateNode(['uid' => $uid]);

    // Test current_workspace filter.
    $this->drupalGet('test_current_workspace');
    $this->assertNoText($node1->label());
    $this->assertNoText($node2->label());
    $this->assertText($node3->label());
    $this->assertText($node4->label());

    // Switch back to the original workspace and test the view.
    $workspace_manager->setActiveWorkspace($initial_workspace);
    $this->assertEqual($initial_workspace->id(), $workspace_manager->getActiveWorkspace()->id());
    $this->drupalGet('test_current_workspace');
    $this->assertText($node1->label());
    $this->assertText($node2->label());
    $this->assertNoText($node3->label());
    $this->assertNoText($node4->label());
  }

}