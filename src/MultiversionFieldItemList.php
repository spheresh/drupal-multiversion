<?php

namespace Drupal\multiversion;

use Drupal\pathauto\PathautoFieldItemList;

class MultiversionFieldItemList extends PathautoFieldItemList {

  /**
   * @inheritDoc
   */
  public function delete() {
    \Drupal::service('pathauto.alias_storage_helper')->deleteEntityPathAll($this->getEntity());
    if ($first = $this->first()) {
      $first->get('pathauto')->purge();
    }
  }

}
