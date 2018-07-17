<?php

namespace Drupal\multiversion;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Defines a service profiler for the multiversion module.
 */
class MultiversionServiceProvider extends ServiceProviderBase {

  public function alter(ContainerBuilder $container) {
    $renderer_config = $container->getParameter('renderer.config');
    $renderer_config['required_cache_contexts'][] = 'workspace';
    $container->setParameter('renderer.config', $renderer_config);

    // Switch the menu tree storage to our own that respect Workspace cache
    // contexts.
    $definition = $container->getDefinition('menu.tree_storage');
    $definition->setClass('Drupal\multiversion\MenuTreeStorage');

    // Override the comment.statistics class with a new class.
    try {
      $definition = $container->getDefinition('comment.statistics');
      $definition->setClass('Drupal\multiversion\CommentStatistics');
    }
    catch (InvalidArgumentException $e) {
      // Do nothing, comment module is not installed.
    }

    // Override the redirect.repository class with a new class.
    try {
      $definition = $container->getDefinition('redirect.repository');
      $definition->setClass('Drupal\multiversion\Redirect\RedirectRepository')
        ->addArgument(new Reference('workspace.manager'))
        ->addArgument(new Reference('state'));
    }
    catch (InvalidArgumentException $e) {
      // Do nothing, Redirect module is not installed.
    }

    // Override the access_check.node.revision class with a new class.
    try {
      $definition = $container->getDefinition('access_check.node.revision');
      $definition->setClass('Drupal\multiversion\Access\NodeRevisionAccessCheck');
    }
    catch (InvalidArgumentException $e) {
      // Do nothing, Node module is not installed.
    }
  }

}
