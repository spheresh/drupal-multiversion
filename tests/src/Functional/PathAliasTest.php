<?php

namespace Drupal\Tests\multiversion\Functional;

use Drupal\workspaces\Entity\Workspace;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * @group multiversion
 */
class PathAliasTest extends BrowserTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['system', 'path', 'multiversion', 'workspaces', 'key_value', 'serialization', 'user', 'node'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    if ($this->profile != 'standard') {
      $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);
      $this->drupalCreateContentType(['type' => 'page', 'name' => 'Page']);
    }

    // Create test user and log in.
    $web_user = $this->drupalCreateUser(['administer url aliases', 'create url aliases', 'administer workspaces']);
    $this->drupalLogin($web_user);
  }

  /**
   * Test creating, loading, updating and deleting aliases.
   */
  public function testPathAlias() {
    /** @var \Drupal\Core\Path\AliasStorageInterface $alias_storage */
    $alias_storage = \Drupal::service('path.alias_storage');

    // Create a live (default) and stage workspace.
    $live = Workspace::create([
      'id' => 'live',
      'label' => 'Live',
    ]);
    $live->save();
    $stage = Workspace::create([
      'id' => 'stage',
      'label' => 'Stage',
    ]);
    $stage->save();

    // Set live as the active workspace.
    \Drupal::service('workspaces.manager')->setActiveWorkspace($live);

    $alias = '/foo';
    $node1 = $this->drupalCreateNode();
    $node1->get('path')->alias = $alias;
    $node1->save();
    $this->assertEquals($alias, $node1->get('path')->alias);

    $stored_alias = $alias_storage->lookupPathAlias('/' . $node1->toUrl()->getInternalPath(), $node1->language()->getId());
    $this->assertEquals($alias, $stored_alias);

    // Set stage as the active workspace.
    \Drupal::service('workspaces.manager')->setActiveWorkspace($stage);

    $stored_alias = $alias_storage->lookupPathAlias('/' . $node1->toUrl()->getInternalPath(), $node1->language()->getId());
    $this->assertFalse($stored_alias);

    // Create a new node on stage workspace with the same alias.
    $node2 = $this->drupalCreateNode();
    $node2->get('path')->alias = $alias;
    $node2->save();
    $this->assertEquals($alias, $node2->get('path')->alias);
    $stored_alias = $alias_storage->lookupPathAlias('/' . $node2->toUrl()->getInternalPath(), $node2->language()->getId());
    $this->assertEquals($alias, $stored_alias);

    $this->drupalGet($alias);
    $web_assert = $this->assertSession();
    $web_assert->statusCodeEquals(200);
    $web_assert->pageTextNotContains($node1->label());
    $web_assert->pageTextContains($node2->label());

    $this->drupalGet('admin/config/search/path');
    $web_assert->statusCodeEquals(200);
    $web_assert->pageTextNotContains('/node/' . $node1->id());
    $web_assert->pageTextContains('/node/' . $node2->id());

    // Set live as the active workspace.
    \Drupal::service('workspaces.manager')->setActiveWorkspace($live);

    $this->drupalGet($alias);
    $web_assert->statusCodeEquals(200);
    $web_assert->pageTextNotContains($node2->label());
    $web_assert->pageTextContains($node1->label());

    $this->drupalGet('admin/config/search/path');
    $web_assert->statusCodeEquals(200);
    $web_assert->pageTextNotContains('/node/' . $node2->id());
    $web_assert->pageTextContains('/node/' . $node1->id());

    // Delete node1.
    $node1->delete();
    $this->drupalGet($alias);
    $web_assert->statusCodeEquals(404);

    $this->drupalGet('admin/config/search/path');
    $web_assert->statusCodeEquals(200);
    $web_assert->pageTextNotContains('/node/' . $node2->id());
    $web_assert->pageTextNotContains('/node/' . $node1->id());

    // Set stage as the active workspace.
    \Drupal::service('workspaces.manager')->setActiveWorkspace($stage);

    $this->drupalGet($alias);
    $web_assert = $this->assertSession();
    $web_assert->statusCodeEquals(200);
    $web_assert->pageTextNotContains($node1->label());
    $web_assert->pageTextContains($node2->label());

    $this->drupalGet('admin/config/search/path');
    $web_assert->statusCodeEquals(200);
    $web_assert->pageTextNotContains('/node/' . $node1->id());
    $web_assert->pageTextContains('/node/' . $node2->id());

    //Set a new alias for node2.
    $alias2 = '/bar';
    $node2->get('path')->alias = $alias2;
    $node2->save();
    $this->assertEquals($alias2, $node2->get('path')->alias);
    $stored_alias = $alias_storage->lookupPathAlias('/' . $node2->toUrl()->getInternalPath(), $node2->language()->getId());
    $this->assertEquals($alias2, $stored_alias);

    $this->drupalGet('admin/config/search/path');
    $web_assert->statusCodeEquals(200);
    $web_assert->pageTextNotContains('/node/' . $node1->id());
    $web_assert->pageTextContains('/node/' . $node2->id());
    $web_assert->pageTextContains($alias2);

    $session = $this->getSession();
    $page = $session->getPage();
    $page->clickLink('Delete');
    $web_assert->pageTextContains('Are you sure you want to delete path alias ' . $alias2 . '?');
    $page->pressButton('Confirm');

    $this->drupalGet($alias2);
    $web_assert = $this->assertSession();
    $web_assert->statusCodeEquals(404);

    $this->drupalGet('admin/config/search/path');
    $web_assert->statusCodeEquals(200);
    $web_assert->pageTextNotContains('/node/' . $node2->id());
    $web_assert->pageTextNotContains('/node/' . $node1->id());
    $stored_alias = $alias_storage->lookupPathAlias('/' . $node2->toUrl()->getInternalPath(), $node2->language()->getId());
    $this->assertFalse($stored_alias);

    // Set live as the active workspace.
    \Drupal::service('workspaces.manager')->setActiveWorkspace($live);
    // Add an alias that should be accessible from all workspaces.
    $alias3 = '/aliases';
    $edit = [
      'source' => '/admin/config/search/path',
      'alias' => $alias3,
    ];
    $this->drupalPostForm('admin/config/search/path/add', $edit, t('Save'));
    $this->drupalGet('admin/config/search/path');
    $web_assert->statusCodeEquals(200);
    $web_assert->pageTextContains($alias3);


    // Set stage as the active workspace.
    \Drupal::service('workspaces.manager')->setActiveWorkspace($stage);

    $this->drupalGet('admin/config/search/path');
    $web_assert->statusCodeEquals(200);
    $web_assert->pageTextContains($alias3);

    // Delete alias3.
    $session = $this->getSession();
    $page = $session->getPage();
    $page->clickLink('Delete');
    $web_assert->pageTextContains('Are you sure you want to delete path alias ' . $alias3 . '?');
    $page->pressButton('Confirm');
    $this->drupalGet('admin/config/search/path');
    $web_assert->statusCodeEquals(200);
    $web_assert->pageTextNotContains($alias3);

    // Set live as the active workspace.
    \Drupal::service('workspaces.manager')->setActiveWorkspace($live);

    $this->drupalGet('admin/config/search/path');
    $web_assert->statusCodeEquals(200);
    $web_assert->pageTextNotContains($alias3);
  }

}
