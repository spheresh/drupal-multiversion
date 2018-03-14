<?php

namespace Drupal\multiversion\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\Access\NodeRevisionAccessCheck as CoreNodeRevisionAccessCheck;
use Drupal\node\NodeInterface;
use Symfony\Component\Routing\Route;

class NodeRevisionAccessCheck extends CoreNodeRevisionAccessCheck {


  public function access(Route $route, AccountInterface $account, $node_revision = NULL, NodeInterface $node = NULL) {
    return AccessResult::forbidden('Multiversion does not support reverting revisions');
  }

}
