<?php

namespace Drupal\multiversion\Workspace;

use Drupal\Core\State\StateInterface;
use Drupal\multiversion\Entity\WorkspaceInterface;
use Symfony\Component\HttpFoundation\Request;

class CronWorkspaceNegotiator extends WorkspaceNegotiatorBase {

  /**
   * @var \Drupal\Core\State\StateInterface
   */
  private $state;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\State\StateInterface $state
   */
  public function __construct(StateInterface $state) {
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(Request $request) {
    // This negotiator only applies if the current route is 'system.cron',
    // 'system.run_cron' or '<none>';
    $route = $request->attributes->get('_route');
    return in_array($route, ['system.cron', 'system.run_cron', '<none>']);
  }

  /**
   * {@inheritdoc}
   */
  public function getWorkspaceId(Request $request) {
    $workspace_id = $this->state->get('workspace.negotiator.cron.active_workspace_id');
    return $workspace_id ?: $this->container->getParameter('workspace.default');
  }

  /**
   * {@inheritdoc}
   */
  public function persist(WorkspaceInterface $workspace) {
    $this->state->set('workspace.negotiator.cron.active_workspace_id', $workspace->id());
    return TRUE;
  }

}
