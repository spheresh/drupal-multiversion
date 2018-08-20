<?php

namespace Drupal\multiversion\Tests;

use Drupal\comment\Tests\CommentTestTrait;
use Drupal\simpletest\WebTestBase;

/**
 * Defines a base class for testing the Multiversion module.
 */
abstract class MultiversionWebTestBase extends WebTestBase {

  use CommentTestTrait;

  protected $strictConfigSchema = FALSE;

  /**
   * @var \Drupal\multiversion\Entity\Index\UuidIndexInterface;
   */
  protected $uuidIndex;

  /**
   * @var \Drupal\multiversion\Entity\Index\RevisionIndexInterface;
   */
  protected $revIndex;

  /**
   * @var \Drupal\multiversion\Entity\Index\RevisionTreeIndexInterface;
   */
  protected $revTree;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The multiversion manager.
   *
   * @var \Drupal\multiversion\MultiversionManagerInterface
   */
  protected $multiversionManager;

  /**
   * The workspace manager.
   *
   * @var \Drupal\workspaces\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * The entity definition update manager.
   *
   * @var \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface
   */
  protected $entityDefinitionUpdateManager;

  public static $modules = [
    'workspaces',
    'entity_test',
    'multiversion',
    'node',
    'taxonomy',
    'comment',
    'block_content',
    'menu_link_content',
    'file',
    'shortcut',
    'config',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->entityTypeManager = $this->container->get('entity_type.manager');
    $this->uuidIndex = $this->container->get('multiversion.entity_index.uuid');
    $this->revIndex = $this->container->get('multiversion.entity_index.rev');
    $this->revTree = $this->container->get('multiversion.entity_index.rev.tree');

    $this->multiversionManager = $this->container->get('multiversion.manager');
    $this->workspaceManager = $this->container->get('workspaces.manager');
    $this->entityDefinitionUpdateManager = $this->container->get('entity.definition_update_manager');

    // Create Basic page and Article node types.
    if ($this->profile != 'standard') {
      $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);
    }
    // Create comment field on article.
    $this->addDefaultCommentField('node', 'article');

    $test_user = $this->drupalCreateUser(['administer workspaces', 'administer nodes']);
    $this->drupalLogin($test_user);
  }

}
