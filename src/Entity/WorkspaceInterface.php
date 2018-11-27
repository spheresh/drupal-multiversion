<?php

namespace Drupal\multiversion\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\user\EntityOwnerInterface;

interface WorkspaceInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface, EntityPublishedInterface {

  /**
   * Returns the last sequence ID in the workspace's sequence index.
   *
   * @return float
   */
  public function getUpdateSeq();

  /**
   * Sets the workspace creation timestamp.
   *
   * @param int $timestamp
   *   The workspace creation timestamp.
   *
   * @return \Drupal\multiversion\Entity\WorkspaceInterface
   *   The called workspace entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the workspace creation timestamp.
   *
   * @return int
   *   Creation timestamp of the workspace.
   */
  public function getStartTime();

  /**
   * Returns the workspace machine name.
   *
   * @return string
   *   Machine name of the workspace.
   */
  public function getMachineName();

  /**
   * Determines whether the workspace is the default one or not.
   *
   * @return bool
   *   TRUE if this workspace is the default one (e.g 'Live'), FALSE otherwise.
   */
  public function isDefaultWorkspace();

  /**
   * Specifies that the workspace is queued for delete on next cron run.
   *
   * @param bool $queued
   *
   * @return mixed
   */
  public function setQueuedForDelete($queued = TRUE);

  /**
   * Returns the queued for delete flag.
   *
   * @return bool
   */
  public function getQueuedForDelete();

}
