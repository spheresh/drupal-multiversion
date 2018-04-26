<?php

namespace Drupal\multiversion\Workspace;

use Drupal\multiversion\Entity\WorkspaceInterface;
use Drupal\user\PrivateTempStoreFactory;
use Symfony\Component\HttpFoundation\Request;

class SessionWorkspaceNegotiator extends WorkspaceNegotiatorBase {

  /**
   * @var \Drupal\user\PrivateTempStore
   */
  protected $tempstore;

  /**
   * Constructor.
   *
   * @param \Drupal\user\PrivateTempStoreFactory $tempstore_factory
   */
  public function __construct(PrivateTempStoreFactory $tempstore_factory) {
    $this->tempstore = $tempstore_factory->get('workspace.negotiator.session');
  }

  /**
   * {@inheritdoc}
   */
  public function applies(Request $request) {
    // This negotiator only applies if the current user is authenticated,
    // i.e. a session exists.
    return $this->currentUser->isAuthenticated();
  }

  /**
   * {@inheritdoc}
   */
  public function getWorkspaceId(Request $request) {
    $workspace_id = $this->tempstore->get('active_workspace_id');
    return $workspace_id ?: $this->container->getParameter('workspace.default');
  }

  /**
   * {@inheritdoc}
   */
  public function persist(WorkspaceInterface $workspace) {
    $this->tempstore->set('active_workspace_id', $workspace->id());
    return TRUE;
  }

}
