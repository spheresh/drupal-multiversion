<?php

namespace Drupal\Tests\multiversion\Kernel;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\multiversion\Entity\Workspace;
use Drupal\Tests\field\Traits\EntityReferenceTestTrait;
use Drupal\Tests\SchemaCheckTestTrait;

/**
 * Test for paragraphs integration.
 *
 * @requires module paragraphs
 * @requires module entity_reference_revisions
 * @group multiversion
 */
class ReferencesLoadTest extends EntityKernelTestBase {

  use SchemaCheckTestTrait;
  use EntityReferenceTestTrait;

  /**
   * Modules to install.
   *
   * @var array
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
    'entity_test',
    'file',
    'entity_reference_test',
  ];

  /**
   * The entity type used in this test.
   *
   * @var string
   */
  protected $entityType = 'entity_test_rev';

  /**
   * The entity type that is being referenced.
   *
   * @var string
   */
  protected $referencedEntityType = 'entity_test_rev';

  /**
   * The name of the field used in this test.
   *
   * @var string
   */
  protected $fieldName = 'field_test';

  /**
   * The bundle used in this test.
   *
   * @var string
   */
  protected $bundle = 'entity_test_rev';

  /**
   * @var \Drupal\node\NodeStorageInterface
   */
  protected $nodeStorage;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Entity\ContentEntityStorageInterface
   */
  protected $paragraphStorage;

  /**
   * @var \Drupal\multiversion\EntityReferencesManagerInterface
   */
  protected $entityReferencesManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('entity_test_rev');
    $this->installConfig(['multiversion', 'multiversion_test_paragraphs']);
    $this->installEntitySchema('workspace');
    $this->installEntitySchema('entity_test_rev');
    $this->installSchema('node', 'node_access');
    $this->installSchema('key_value', 'key_value_sorted');
    $workspace = Workspace::create([
      'machine_name' => 'live',
      'label' => 'Live',
      'type' => 'basic',
    ]);
    $workspace->save();
    $multiversion_manager = $this->container->get('multiversion.manager');
    $multiversion_manager->enableEntityTypes();
    $this->entityTypeManager = $this->container->get('entity_type.manager');
    $this->nodeStorage = $this->entityTypeManager->getStorage('node');
    $this->paragraphStorage = $this->entityTypeManager->getStorage('paragraph');
    $this->entityReferencesManager = $this->container->get('multiversion.entity_references.manager');
    // Create a field.
    $this->createEntityReferenceField(
      $this->entityType,
      $this->bundle,
      $this->fieldName,
      'Field test',
      $this->referencedEntityType,
      'default',
      ['target_bundles' => [$this->bundle]],
      FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED
    );
  }

  public function testReferencesLoad() {
    // Create the parent entity.
    $entity = $this->entityTypeManager
      ->getStorage($this->entityType)
      ->create(['type' => $this->bundle]);

    // Create three target entities and attach them to parent field.
    $target_entities = [];
    $reference_field = [];
    for ($i = 0; $i < 3; $i++) {
      $target_entity = $this->entityTypeManager
        ->getStorage($this->referencedEntityType)
        ->create(['type' => $this->bundle]);
      $target_entity->save();
      $target_entities[] = $target_entity;
      $reference_field[]['target_id'] = $target_entity->id();
    }
    // Set the field value.
    $entity->{$this->fieldName}->setValue($reference_field);
    $entity->save();

    foreach ($target_entities as $target) {
      $uuids[] = $target->uuid();
      $ids[$this->referencedEntityType][] = (int) $target->id();
    }
    $referenced_uuids = $this->entityReferencesManager->getReferencedEntitiesUuids($entity);
    $this->assertEquals($uuids, $referenced_uuids);
    $referenced_ids = $this->entityReferencesManager->getReferencedEntitiesIds($entity);
    $this->assertEquals($ids, $referenced_ids);
  }

  public function testParagraphReferencesLoad() {
    $paragraph = $this->paragraphStorage->create([
      'title' => 'Test paragraph',
      'type' => 'test_paragraph_type',
      'field_test_field' => 'First revision title',
    ]);
    $paragraph->save();
    $node = $this->nodeStorage->create([
      'type' => 'paragraphs_node_type',
      'title' => 'Test node',
      'field_paragraph' => $paragraph,
    ]);
    $node->save();

    $ids['paragraph'][] = (int) $paragraph->id();
    $referenced_uuids = $this->entityReferencesManager->getReferencedEntitiesUuids($node);
    $this->assertEquals([$paragraph->uuid()], $referenced_uuids);
    $referenced_ids = $this->entityReferencesManager->getReferencedEntitiesIds($node);
    $this->assertEquals($ids, $referenced_ids);
  }

  public function testParentsLoad() {
    // Create three target entities.
    $target_entities = [];
    $reference_field = [];
    for ($i = 0; $i < 3; $i++) {
      $target_entity = $this->entityTypeManager
        ->getStorage($this->referencedEntityType)
        ->create(['type' => $this->bundle]);
      $target_entity->save();
      $target_entities[] = $target_entity;
      $reference_field[]['target_id'] = $target_entity->id();
    }

    // Create the first parent entity and set all target entities as references.
    $entity1 = $this->entityTypeManager
      ->getStorage($this->entityType)
      ->create([
        'type' => $this->bundle,
        $this->fieldName => $reference_field
        ]);
    $entity1->save();

    unset($reference_field[0]);

    // Create the second parent entity and set only two target entities as
    // references.
    $entity2 = $this->entityTypeManager
      ->getStorage($this->entityType)
      ->create([
        'type' => $this->bundle,
        $this->fieldName => $reference_field
      ]);
    $entity2->save();

    unset($reference_field[1]);

    // Create the third parent entity and set only one target entity as
    // reference.
    $entity3 = $this->entityTypeManager
      ->getStorage($this->entityType)
      ->create([
        'type' => $this->bundle,
        $this->fieldName => $reference_field
      ]);
    $entity3->save();

    // First target entity should be referenced by one entity.
    $parent_uuids = $this->entityReferencesManager->getParentEntitiesUuids($target_entities[0]);
    $expected = [
      $entity1->uuid(),
    ];
    $this->assertEquals($expected, $parent_uuids);

    // Second target entity should be referenced by two entities.
    $parent_uuids = $this->entityReferencesManager->getParentEntitiesUuids($target_entities[1]);
    $expected = [
      $entity1->uuid(),
      $entity2->uuid(),
    ];
    $this->assertEquals($expected, $parent_uuids);

    // Third target entity should be referenced by three entities.
    $parent_uuids = $this->entityReferencesManager->getParentEntitiesUuids($target_entities[2]);
    $expected = [
      $entity1->uuid(),
      $entity2->uuid(),
      $entity3->uuid(),
    ];
    $this->assertEquals($expected, $parent_uuids);

    // First target entity should be referenced by one entity.
    $parent_uuids = $this->entityReferencesManager->getParentEntitiesIds($target_entities[0]);
    $expected = [
      $this->entityType => [
        $entity1->id(),
      ],
    ];
    $this->assertEquals($expected, $parent_uuids);

    // Second target entity should be referenced by two entities.
    $parent_uuids = $this->entityReferencesManager->getParentEntitiesIds($target_entities[1]);
    $expected = [
      $this->entityType => [
        $entity1->id(),
        $entity2->id(),
      ],
    ];
    $this->assertEquals($expected, $parent_uuids);

    // Third target entity should be referenced by three entities.
    $parent_uuids = $this->entityReferencesManager->getParentEntitiesIds($target_entities[2]);
    $expected = [
      $this->entityType => [
        $entity1->id(),
        $entity2->id(),
        $entity3->id(),
      ],
    ];
    $this->assertEquals($expected, $parent_uuids);
  }

}
