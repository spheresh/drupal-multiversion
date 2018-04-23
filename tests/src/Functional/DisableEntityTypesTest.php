<?php

namespace Drupal\Tests\multiversion\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * @group multiversion
 */
class DisableEntityTypesTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'key_value',
    'multiversion',
    'serialization',
    'node',
    'views',
    'views_ui',
  ];

  /**
   * Tests visibility on admin/content page when not multi-versionable.
   */
  public function testDisableEntityTypes() {
    \Drupal::service('multiversion.manager')->disableEntityTypes();

    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Page']);

    $this->drupalLogin($this->rootUser);

    $this->drupalGet('node/add/page');
    $this->drupalPostForm(NULL, [
      'title[0][value]' => 'Node title test',
      'body[0][value]' => 'This is node content',
    ], t('Save'));
    $this->drupalGet('admin/content');

    $this->assertSession()->pageTextContains('Node title test');
  }

}
