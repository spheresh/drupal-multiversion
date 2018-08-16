<?php

namespace Drupal\Tests\multiversion\Functional\Views;

/**
 * Tests the _deleted field handler.
 *
 * @group multiversion
 */
class DeletedTest extends ViewsTestBase {

  protected $strictConfigSchema = FALSE;

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_deleted', 'test_not_deleted'];

  /**
   * Tests the _deleted filter when _deleted == 1.
   */
  public function testDeleted() {
    // Create four nodes and delete two of them.
    $node1 = $this->drupalCreateNode(['uid' => $this->uid]);
    $node2 = $this->drupalCreateNode(['uid' => $this->uid]);
    $node3 = $this->drupalCreateNode(['uid' => $this->uid]);
    $node3->delete();
    $node4 = $this->drupalCreateNode(['uid' => $this->uid]);
    $node4->delete();

    $this->drupalGet('test_deleted');
    $session = $this->assertSession();
    $session->pageTextNotContains($node1->label());
    $session->pageTextNotContains($node2->label());
    $session->pageTextContains($node3->label());
    $session->pageTextContains($node4->label());

    $alpha = $this->createWorkspaceThroughUi('Alpha', 'alpha');
    $this->switchToWorkspace($alpha);
    $node5 = $this->drupalCreateNode(['uid' => $this->uid]);
    $node6 = $this->drupalCreateNode(['uid' => $this->uid]);
    $node6->delete();
    $this->drupalGet('test_deleted');
    $session = $this->assertSession();
    $session->pageTextNotContains($node1->label());
    $session->pageTextNotContains($node2->label());
    $session->pageTextNotContains($node5->label());
    $session->pageTextContains($node3->label());
    $session->pageTextContains($node4->label());
    $session->pageTextContains($node6->label());
  }

  /**
   * Tests the _deleted filter when _deleted == 0.
   */
  public function testNotDeleted() {
    // Create four nodes and delete two of them.
    $node1 = $this->drupalCreateNode(['uid' => $this->uid]);
    $node2 = $this->drupalCreateNode(['uid' => $this->uid]);
    $node3 = $this->drupalCreateNode(['uid' => $this->uid]);
    $node3->delete();
    $node4 = $this->drupalCreateNode(['uid' => $this->uid]);
    $node4->delete();

    $this->drupalGet('test_not_deleted');
    $session = $this->assertSession();
    $session->pageTextContains($node1->label());
    $session->pageTextContains($node2->label());
    $session->pageTextNotContains($node3->label());
    $session->pageTextNotContains($node4->label());

    $alpha = $this->createWorkspaceThroughUi('Alpha', 'alpha');
    $this->switchToWorkspace($alpha);
    $node5 = $this->drupalCreateNode(['uid' => $this->uid]);
    $node6 = $this->drupalCreateNode(['uid' => $this->uid]);
    $node6->delete();
    $this->drupalGet('test_not_deleted');
    $session = $this->assertSession();
    $session->pageTextContains($node1->label());
    $session->pageTextContains($node2->label());
    $session->pageTextContains($node5->label());
    $session->pageTextNotContains($node3->label());
    $session->pageTextNotContains($node4->label());
    $session->pageTextNotContains($node6->label());
  }

}
