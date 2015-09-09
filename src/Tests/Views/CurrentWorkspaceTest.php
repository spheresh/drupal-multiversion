<?php

/**
 * @file
 * Contains \Drupal\multiversion\Tests\Views\CurrentWorkspaceTest.
 */

namespace Drupal\multiversion\Tests\Views;

/**
 * Tests the current_workspace field handler.
 *
 * @group multiversion
 * @see \Drupal\multiversion\Plugin\views\filter\CurrentWorkspace
 */
class CurrentWorkspaceTest extends MultiversionTestBase {

  protected $strictConfigSchema = FALSE;

  //protected $profile = 'standard';

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = array('test_current_workspace');

  /**
   * Tests the current workspace filter.
   */
  public function testCurrentWorkspace() {
    $admin_user = $this->drupalCreateUser(array('bypass node access'));
    $uid = $admin_user->id();
    $this->drupalLogin($admin_user);

    // Create two nodes on 'default' workspace.
    $node1 = $this->drupalCreateNode(array('uid' => $uid));
    $node2 = $this->drupalCreateNode(array('uid' => $uid));

    // Create a new workspace and switch to it.
    $new_workspace = entity_create('workspace', array('id' => 'new_workspace'));
    $new_workspace->save();
    \Drupal::service('workspace.manager')->setActiveWorkspace($new_workspace);

    // Create two nodes on 'new_workspace' workspace.
    $node3 = $this->drupalCreateNode(array('uid' => $uid));
    $node4 = $this->drupalCreateNode(array('uid' => $uid));

    $this->drupalGet('test_current_workspace', ['query' => ['workspace' => 'new_workspace']]);
    $this->assertNoText($node1->label());
    $this->assertNoText($node2->label());
    $this->assertText($node3->label());
    $this->assertText($node4->label());

    $this->drupalGet('test_current_workspace', ['query' => ['workspace' => 'default']]);
    $this->assertText($node1->label());
    $this->assertText($node2->label());
    $this->assertNoText($node3->label());
    $this->assertNoText($node4->label());
  }

}
