<?php

namespace Drupal\Tests\multiversion\Functional\Views;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\system\Functional\Cache\AssertPageCacheContextsAndTagsTrait;
use Drupal\Tests\workspaces\Functional\WorkspaceTestUtilities;
use Drupal\views\Tests\ViewTestData;

/**
 * Base class for all multiversion views tests.
 */
abstract class ViewsTestBase extends BrowserTestBase {

  use AssertPageCacheContextsAndTagsTrait;
  use WorkspaceTestUtilities;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'block',
    'multiversion',
    'workspaces',
    'multiversion_test_views'
  ];

  protected $uid;

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE) {
    parent::setUp($import_test_views);

    $permissions = [
      'create workspace',
      'edit own workspace',
      'view own workspace',
      'bypass entity access own workspace',
      'bypass node access',
    ];

    $this->setupWorkspaceSwitcherBlock();

    $user = $this->drupalCreateUser($permissions);
    $this->uid = $user->id();
    $this->drupalLogin($user);

    if ($import_test_views) {
      ViewTestData::createTestViews(get_class($this), ['multiversion_test_views']);
    }
  }

}
