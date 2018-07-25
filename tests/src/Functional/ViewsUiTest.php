<?php

namespace Drupal\Tests\multiversion\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\User;

/**
 * @group multiversion
 */
class ViewsUiTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'key_value',
    'multiversion',
    'workspaces',
    'serialization',
    'views',
    'views_ui',
  ];

  /**
   * Tests that workspace entity fields are available in Views UI.
   */
  public function testWorkspaceFieldsAvailable() {
    $account = User::load(1);
    $account->setPassword('admin')->save();
    $account->passRaw = 'admin';
    $this->drupalLogin($account);

    $this->drupalGet('/admin/structure/views/add');
    // Assert that workspaces are available as a base table option. Since
    // workspaces use the generic Views data handler, this pretty much ensures
    // that all fields of the workspace entity type are available to the view.
    $this->assertSession()->optionExists('show[wizard_key]', 'standard:workspace');
  }

}
