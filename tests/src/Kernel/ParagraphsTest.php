<?php

namespace Drupal\Tests\multiversion\Kernel;

use Drupal\Core\Entity\EntityInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\multiversion\Entity\Workspace;

/**
 * Test for paragraphs integration.
 *
 * @requires module paragraphs
 * @requires module entity_reference_revisions
 * @group multiversion
 */
class ParagraphsTest extends KernelTestBase {

  protected $strictConfigSchema = FALSE;

  /**
   * The paragraph entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $paragraphStorage;

  /**
   * The node entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $nodeStorage;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'system',
    'field',
    'key_value',
    'user',
    'serialization',
    'paragraphs',
    'multiversion_test_paragraphs',
    'node',
    'multiversion',
    'entity_reference_revisions',
    'file',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $entityTypeManager = $this->container->get('entity_type.manager');
    $this->installConfig(['multiversion', 'multiversion_test_paragraphs']);
    $this->installEntitySchema('workspace');
    $this->installSchema('node', 'node_access');
    $this->installSchema('key_value', 'key_value_sorted');
    $this->installSchema('file', 'file_usage');
    $multiversion_manager = $this->container->get('multiversion.manager');
    $multiversion_manager->enableEntityTypes();
    $workspace = Workspace::create([
      'machine_name' => 'live',
      'label' => 'Live',
      'type' => 'basic',
    ]);
    $workspace->save();
    $this->nodeStorage = $entityTypeManager->getStorage('node');
    $this->paragraphStorage = $entityTypeManager->getStorage('paragraph');
  }

  /**
   * Tests that paragraphs revisions created right when saving parent entity.
   */
  public function testDefaultParagraphsBehaviour() {
    $paragraph = $this->paragraphStorage->create([
      'title' => 'Stub of real paragraph',
      'type' => 'test_paragraph_type',
      'field_test_field' => 'First revision title',
    ]);
    $node = $this->nodeStorage->create([
      'type' => 'paragraphs_node_type',
      'title' => 'Test node',
      'field_paragraph' => $paragraph,
    ]);
    $node->save();

    $node_revision_id = $node->getRevisionId();
    $paragraph_entity_id = $node->field_paragraph->target_id;
    $paragraph_entity = $this->paragraphStorage->load($paragraph_entity_id);
    /** @var \Drupal\paragraphs\Entity\Paragraph $paragraph_entity */
    list($i, $hash) = explode('-', $paragraph_entity->_rev->value);
    $this->assertEquals($i, '1', 'After saving new node with paragraph we have new paragraph with one revision.');

    $paragraph->field_test_field = 'Second revision title';
    $node->field_paragraph = $paragraph;
    $node->save();

    $paragraph_entity_revision_id = $node->field_paragraph->target_revision_id;
    $paragraph_entity = $this->paragraphStorage->loadRevision($paragraph_entity_revision_id);

    $this->assertRevNumber($paragraph_entity, 2);
    $this->assertEquals($paragraph_entity->field_test_field->value, 'Second revision title');

    $node_first_revision = $this->nodeStorage->loadRevision($node_revision_id);
    $paragraph_entity_revision_id = $node_first_revision->field_paragraph->target_revision_id;
    $paragraph_entity = $this->paragraphStorage->loadRevision($paragraph_entity_revision_id);
    $this->assertEquals($paragraph_entity->field_test_field->value, 'First revision title');
  }

  /**
   * Tests stub handling for paragraph when it is created after parent entity.
   */
  public function testParagraphStubCreatedAfterParent() {
    $paragraph_stub = $this->paragraphStorage->create([
      'title' => 'Stub of real paragraph',
      'type' => 'test_paragraph_type',
    ]);
    $paragraph_stub->_rev->is_stub = TRUE;
    $node = $this->nodeStorage->create([
      'type' => 'paragraphs_node_type',
      'title' => 'Test node',
      'field_paragraph' => $paragraph_stub,
    ]);
    $node->save();
    $paragraph_stub_entity_id = $node->field_paragraph->target_id;
    $paragraph_stub_entity = $this->paragraphStorage->load($paragraph_stub_entity_id);
    $this->assertRevNumber($paragraph_stub_entity, 0);

    $paragraph_real = $this->paragraphStorage->create([
      'type' => 'test_paragraph_type',
      'id' => $paragraph_stub_entity->id(),
    ]);
    $paragraph_real->enforceIsNew(FALSE);
    $paragraph_real->_rev->is_stub = FALSE;
    $paragraph_real->save();

    $this->assertEquals($paragraph_real->id(), $paragraph_stub_entity_id);
    $this->assertRevNumber($paragraph_real, 1);
  }

  /**
   * Tests stub handling for paragraph when it is created before parent entity.
   */
  public function testParagraphStubCreatedBeforeParent() {
    // Create and save real paragraph.
    $paragraph = $this->paragraphStorage->create([
      'title' => 'Real paragraph',
      'type' => 'test_paragraph_type',
    ]);
    $paragraph->save();
    // Assert that created paragraph is not a stub and it is the first revision.
    $this->assertRevNumber($paragraph, 1);

    // Create stub paragraph with same uuid as real paragraph.
    $paragraph_stub_in_node = $this->paragraphStorage->create([
      'type' => 'test_paragraph_type',
      'uuid' => $paragraph->uuid(),
    ]);
    $paragraph_stub_in_node->_rev->is_stub = TRUE;

    // Create node with paragraph stub.
    $node = $this->nodeStorage->create([
      'type' => 'paragraphs_node_type',
      'title' => 'Test node',
      'field_paragraph' => $paragraph_stub_in_node,
    ]);
    $node->save();

    $paragraph_entity_id_from_node = $node->field_paragraph->target_id;
    $this->assertEquals($paragraph_entity_id_from_node, $paragraph->id());

    $paragraph_entity = $this->paragraphStorage->load($paragraph->id());
    $this->assertRevNumber($paragraph_entity, 1);
  }

  /**
   * Assert that entity has given _rev number.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Given entity.
   * @param int $expected_rev_number
   *   Expected _rev number.
   */
  protected function assertRevNumber(EntityInterface $entity, $expected_rev_number) {
    list($rev_number) = explode('-', $entity->_rev->value);
    $this->assertEquals($expected_rev_number, $rev_number);
  }

}
