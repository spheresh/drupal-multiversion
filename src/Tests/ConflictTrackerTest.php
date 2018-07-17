<?php

namespace Drupal\multiversion\Tests;

/**
 * Test the methods on the ConflictTracker class.
 *
 * @group multiversion
 */
class ConflictTrackerTest extends MultiversionWebTestBase {

  /**
   * @var \Drupal\multiversion\Conflict\ConflictTracker;
   */
  protected $conflictTracker;

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface;
   */
  protected $storage;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->conflictTracker = \Drupal::service('multiversion.conflict_tracker');
    $this->storage = $this->entityTypeManager->getStorage('entity_test_rev');
  }

  /**
   * Tests the Conflict Tracker.
   */
  public function testConflictTracker() {
    $entity_1_conflicts = $this->checkEntityConflicts();
    $entity_2_conflicts = $this->checkEntityConflicts(6);
    $all_entities_conflicts = $entity_1_conflicts + $entity_2_conflicts;
    $all_tracker_conflicts = $this->conflictTracker->getAll();
    $this->assertEqual($all_entities_conflicts, $all_tracker_conflicts, 'Both entities conflicts are tracked in  workspace conflict tracker.');

    $entity_1_uuid = array_keys($entity_1_conflicts)[0];
    $this->resolveConflicts($entity_1_uuid);

    // Check that after all conflicts have been resolved for entity 1 it is remove completely from the tracker.
    $all_tracker_conflicts = $this->conflictTracker->getAll();
    $this->assertEqual($entity_2_conflicts, $all_tracker_conflicts, 'Workspace conflicts only contain Entity 1\'s conflicts after Entity 2\' conflicts resolved.');
  }

  /**
   * Checks the conflict tracker for one entity.
   *
   * @return array
   *   keys - uuid of entity
   *   values - The conflicts array as returned from ConflictTracker::get().
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function checkEntityConflicts($revision_start = 0) {
    /** @var \Drupal\entity_test\Entity\EntityTest $entity */
    $entity = $this->storage->create();
    $uuid = $entity->uuid();

    // Create a conflict scenario to fully test the parsing.

    // Initial revision.
    $entity->save();
    $revs[] = $entity->_rev->value;

    $entity->save();
    $revs[] = $entity->_rev->value;

    $entity->save();
    $revs[] = $leaf_one = $entity->_rev->value;

    // Create a new branch from the second revision.
    $entity = $this->storage->loadRevision($revision_start + 2);
    $entity->save();
    $revs[] = $entity->_rev->value;


    // Continue the last branch.
    $entity = $this->storage->loadRevision($revision_start + 4);
    $entity->save();
    $revs[] = $entity->_rev->value;

    // Create a new branch based on the first revision.
    $entity = $this->storage->loadRevision($revision_start + 1);
    $entity->save();
    $revs[] = $entity->_rev->value;

    $expected_conflicts = [
      $revs[2] => 'available',
      $revs[5] => 'available',
    ];

    $tracker_conflicts = $this->conflictTracker->get($uuid);
    $this->assertEqual($tracker_conflicts, $expected_conflicts, 'Tracker conflicts are correct');
    return [$uuid => $tracker_conflicts];
  }

  /**
   * Resolve a conflict and make sure it removed from the tracker.
   *
   * @param $uuid
   *   The uuid of entity to resolve a conflict for.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function resolveConflicts($uuid) {
    // Get the conflicts for this entity.
    $expected_conflicts = $this->conflictTracker->get($uuid);
    $revs = array_keys($expected_conflicts);
    foreach ($revs as $rev) {
      // Load and delete one of the revisions in conflict
      $record = \Drupal::service('multiversion.entity_index.rev')->get("$uuid:$rev");
      $revision = $this->storage->loadRevision($record['revision_id']);
      $revision->delete();
      // Unset the expected conflict for the revision just deleted.
      unset($expected_conflicts[$rev]);
      $tracker_conflicts = $this->conflictTracker->get($uuid);
      $this->assertEqual($tracker_conflicts, $expected_conflicts, 'Resolved conflict removed correctly.');
    }
    $this->assertEqual($tracker_conflicts, [], 'All conflicts resolved for entity.');
  }
}
