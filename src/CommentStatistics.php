<?php

namespace Drupal\multiversion;

use Drupal\comment\CommentInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\multiversion\Entity\Storage\ContentEntityStorageInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\comment\CommentStatistics as CoreCommentStatistics;

/**
 * Extends core CommentStatistics class and implements the update() method, it's
 * necessary to take into consideration that comments are not deleted when using
 * the Multiversion module, just flagged as deleted. We add a new condition - to
 * count just entities that have the _deleted flag equal to FALSE.
 */
class CommentStatistics extends CoreCommentStatistics {

  /**
   * {@inheritdoc}
   */
  public function update(CommentInterface $comment) {
    $storage_class = $comment->getEntityType()->getStorageClass();
    // Do changes only if the storage class for comment entity type is provided
    // by Multiversion. This check is needed because the 'comment.statistics'
    // is modified before the comment entity type will be fully migrated to the
    // new storage.
    if (is_subclass_of($storage_class, ContentEntityStorageInterface::class) !== FALSE) {
      // Allow bulk updates and inserts to temporarily disable the maintenance of
      // the {comment_entity_statistics} table.
      if (!$this->state->get('comment.maintain_entity_statistics')) {
        return;
      }

      $query = $this->database->select('comment_field_data', 'c');
      $query->addExpression('COUNT(cid)');
      $count = $query->condition('c.entity_id', $comment->getCommentedEntityId())
        ->condition('c.entity_type', $comment->getCommentedEntityTypeId())
        ->condition('c.field_name', $comment->getFieldName())
        ->condition('c.status', CommentInterface::PUBLISHED)
        ->condition('c._deleted', FALSE)
        ->condition('default_langcode', 1)
        ->execute()
        ->fetchField();

      if ($count > 0) {
        // Comments exist.
        $last_reply = $this->database->select('comment_field_data', 'c')
          ->fields('c', ['cid', 'name', 'changed', 'uid'])
          ->condition('c.entity_id', $comment->getCommentedEntityId())
          ->condition('c.entity_type', $comment->getCommentedEntityTypeId())
          ->condition('c.field_name', $comment->getFieldName())
          ->condition('c.status', CommentInterface::PUBLISHED)
          ->condition('c._deleted', FALSE)
          ->condition('default_langcode', 1)
          ->orderBy('c.created', 'DESC')
          ->range(0, 1)
          ->execute()
          ->fetchObject();
        // Use merge here because entity could be created before comment field.
        $this->database->merge('comment_entity_statistics')
          ->fields([
            'cid' => $last_reply->cid,
            'comment_count' => $count,
            'last_comment_timestamp' => $last_reply->changed,
            'last_comment_name' => $last_reply->uid ? '' : $last_reply->name,
            'last_comment_uid' => $last_reply->uid,
          ])
          ->keys([
            'entity_id' => $comment->getCommentedEntityId(),
            'entity_type' => $comment->getCommentedEntityTypeId(),
            'field_name' => $comment->getFieldName(),
          ])
          ->execute();
      }
      else {
        // Comments do not exist.
        $entity = $comment->getCommentedEntity();
        // Get the user ID from the entity if it's set, or default to the
        // currently logged in user.
        if ($entity instanceof EntityOwnerInterface) {
          $last_comment_uid = $entity->getOwnerId();
        }
        if (!isset($last_comment_uid)) {
          // Default to current user when entity does not implement
          // EntityOwnerInterface or author is not set.
          $last_comment_uid = $this->currentUser->id();
        }
        $this->database->update('comment_entity_statistics')
          ->fields([
            'cid' => 0,
            'comment_count' => 0,
            // Use the created date of the entity if it's set, or default to
            // REQUEST_TIME.
            'last_comment_timestamp' => ($entity instanceof EntityChangedInterface) ? $entity->getChangedTimeAcrossTranslations() : REQUEST_TIME,
            'last_comment_name' => '',
            'last_comment_uid' => $last_comment_uid,
          ])
          ->condition('entity_id', $comment->getCommentedEntityId())
          ->condition('entity_type', $comment->getCommentedEntityTypeId())
          ->condition('field_name', $comment->getFieldName())
          ->execute();
      }

      // Reset the cache of the commented entity so that when the entity is loaded
      // the next time, the statistics will be loaded again. But don't do this for
      // stub entities since they don't have all the necessary data at this point.
      if (!$comment->_rev->is_stub) {
        $this->entityManager->getStorage($comment->getCommentedEntityTypeId())->resetCache([$comment->getCommentedEntityId()]);
      }
    }
    else {
      parent::update($comment);
    }
  }

}
