<?php

namespace Drupal\multiversion\Tests;

use Drupal\comment\Entity\Comment;
use Drupal\node\Entity\Node;

/**
 * Tests comment statistics.
 *
 * @group multiversion
 */
class CommentStatisticsTest extends MultiversionWebTestBase {

  /**
   * The profile to install as a basis for testing.
   *
   * @var string
   */
  protected $profile = 'standard';

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['workspaces', 'multiversion', 'comment', 'node'];

  /**
   * A test node to which comments will be posted.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser([
      'administer content types',
      'administer blocks',
      'administer comments',
      'administer comment types',
      'post comments',
      'create article content',
      'access administration pages',
      'access comments',
      'access content',
    ]);
    $this->drupalLogin($this->adminUser);
    $this->drupalPlaceBlock('local_tasks_block');

    $this->node = Node::create([
      'type' => 'article',
      'title' => 'New node',
      'promote' => 1,
      'uid' => $this->adminUser->id()
    ]);
    $this->node->save();
  }

  /**
   * Tests the node comment statistics.
   */
  function testCommentNodeCommentStatistics() {
    $node_storage = $this->container->get('entity.manager')->getStorage('node');
    $this->drupalGet('<front>');
    $this->assertNoLink(t('1 comment'));
    $this->assertEqual($this->node->get('comment')->comment_count, 0, 'The number of comments for the node is correct (0 comments)');

    // Test comment statistic when creating comments.
    $comment1 = Comment::create([
      'entity_type' => 'node',
      'field_name' => 'comment',
      'subject' => 'How much wood would a woodchuck chuck',
      'comment_body' => $this->randomMachineName(128),
      'entity_id' => $this->node->id(),
    ]);
    $comment1->save();
    $node_storage->resetCache([$this->node->id()]);
    $node = $node_storage->load($this->node->id());
    $this->assertEqual($node->get('comment')->comment_count, 1, 'The number of comments for the node is correct (1 comment)');
    $this->drupalGet('<front>');
    $this->assertLink(t('1 comment'));
    $comment2 = Comment::create([
      'entity_type' => 'node',
      'field_name' => 'comment',
      'subject' => 'A big black bug bit a big black dog',
      'comment_body' => $this->randomMachineName(128),
      'entity_id' => $this->node->id(),
    ]);
    $comment2->save();
    $comment3 = Comment::create([
      'entity_type' => 'node',
      'field_name' => 'comment',
      'subject' => 'How much pot, could a pot roast roast',
      'comment_body' => $this->randomMachineName(128),
      'entity_id' => $this->node->id(),
    ]);
    $comment3->save();
    $node_storage->resetCache([$this->node->id()]);
    $node = $node_storage->load($this->node->id());
    $this->assertEqual($node->get('comment')->comment_count, 3, 'The number of comments for the node is correct (3 comments)');
    $this->drupalGet('<front>');
    $this->assertLink(t('3 comments'));

    // Test comment statistic when deleting comments.
    $comment1->delete();
    $comment2->delete();
    $node_storage->resetCache([$this->node->id()]);
    $node = $node_storage->load($this->node->id());
    $this->assertEqual($node->get('comment')->comment_count, 1, 'The number of comments for the node is correct (1 comment)');
    $this->drupalGet('<front>');
    $this->assertLink(t('1 comment'));

    $comment3->delete();
    $node_storage->resetCache([$this->node->id()]);
    $node = $node_storage->load($this->node->id());
    $this->assertEqual($node->get('comment')->comment_count, 0, 'The number of comments for the node is correct (0 comments)');
    $this->drupalGet('<front>');
    $this->assertNoLink(t('1 comment'));
    $this->assertNoLink(t('comments'));
  }

}
