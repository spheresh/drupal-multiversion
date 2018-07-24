<?php

namespace Drupal\multiversion\Conflict;

use Drupal\workspaces\Entity\Workspace;

/**
 * The interface for services that track conflicts in a workspace.
 */
interface ConflictTrackerInterface {

  /**
   * Sets the workspace to be used in subsequent queries.
   *
   * If no workspace is set the default workspace will be used.
   *
   * @see \Drupal\workspaces\WorkspaceManagerInterface::getActiveWorkspace().
   *
   * @param \Drupal\workspaces\Entity\Workspace $workspace
   *   The id of the workspace.
   *
   * @return \Drupal\multiversion\Conflict\ConflictTrackerInterface
   */
  public function useWorkspace(Workspace $workspace);

  /**
   * Adds new conflicts to the tracker.
   *
   * @param string $uuid
   *   The uuid for the entity to track.
   * @param array $revision_conflicts
   *   The revision conflicts to add.
   *      keys - revision uuids
   *      values - revision statuses
   * @param bool $replace
   *   Whether to replace all existing conflicts.
   */
  public function add($uuid, array $revision_conflicts, $replace = FALSE);


  /**
   * Removes a conflict from the tracker.
   *
   * @param $uuid
   *   The uuid for the entity to track.
   * @param $revision_uuid
   */
  public function resolve($uuid, $revision_uuid);

  /**
   * Resolves all conflicts for an entity.
   *
   * @param $uuid
   *   The uuid for the entity for which to resolve conflicts.
   */
  public function resolveAll($uuid);

  /**
   * Gets all the conflicts for a specific UUID.
   *
   * @param $uuid
   *   The uuid for the entity being track.
   *
   * @return array
   *   The revision conflicts for the entity.
   *     keys - revision uuids
   *     values - revision statuses
   */
  public function get($uuid);

  /**
   * Gets all conflicts for entities in the workspace set by useWorkspace.
   *
   * @return array
   *   All of the conflicts for all entities in the workspace.
   *     keys - entity uuids
   *     values - array of conflicts for an entity as return by this::get($uuid).
   */
  public function getAll();

}
