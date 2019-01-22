<?php

namespace Drupal\multiversion\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Drupal\multiversion\Controller\MultiversionNodeController;
use Symfony\Component\Routing\RouteCollection;

/**
 * Alters entity.node.revision route.
 */
class NodeRevisionRouteSubscriber extends RouteSubscriberBase {

  /**
   * Alters existing route.
   *
   * @param \Symfony\Component\Routing\RouteCollection $collection
   *   The route collection for adding routes.
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('entity.node.version_history')) {
      $route->setDefault('_controller', MultiversionNodeController::class . '::revisionOverview');
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = parent::getSubscribedEvents();
    $events[RoutingEvents::ALTER] = array('onAlterRoutes', -500);
    return $events;
  }

}
