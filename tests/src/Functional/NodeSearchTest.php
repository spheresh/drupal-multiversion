<?php

namespace Drupal\Tests\multiversion\Functional;

use Drupal\Tests\search\Functional\SearchNodeUpdateAndDeletionTest;

/**
 * Tests the search page text.
 *
 * @group multiversion
 */
class NodeSearchTest extends SearchNodeUpdateAndDeletionTest {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'search', 'multiversion'];

  /**
   * Tests that the search index info is updated when a node is deleted.
   */
  public function testSearchIndexUpdateOnNodeDeletion() {
    // Create a node.
    $node = $this->drupalCreateNode([
      'title' => 'No dragons here',
      'body' => [['value' => 'Again: No dragons here']],
      'type' => 'page',
    ]);

    $node_search_plugin = $this->container->get('plugin.manager.search')->createInstance('node_search');
    // Update the search index.
    $node_search_plugin->updateIndex();
    search_update_totals();

    // Search the node to verify it appears in search results
    $edit = ['keys' => 'dragons'];
    $this->drupalPostForm('search/node', $edit, t('Search'));
    $this->assertText($node->label());

    // Get the node info from the search index tables.
    $search_index_dataset = db_query("SELECT sid FROM {search_index} WHERE type = 'node_search' AND  word = :word", [':word' => 'dragons'])
      ->fetchField();
    $this->assertNotEqual($search_index_dataset, FALSE, t('Node info found on the search_index'));

    // Delete the node.
    $node->delete();

    // Make sure the node delete doesn't remove the node from index with
    // multiversion enabled.
    $search_index_dataset = db_query("SELECT sid FROM {search_index} WHERE type = 'node_search' AND  word = :word", [':word' => 'dragons'])
      ->fetchField();
    $this->assertNotEmpty($search_index_dataset, t('Node info found on the search_index'));

    // Search to verify the node doesn't appear anymore.
    $this->drupalPostForm('search/node', $edit, t('Search'));
    $this->assertNoText($node->label());
  }

}
