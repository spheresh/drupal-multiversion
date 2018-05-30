<?php

namespace Drupal\Tests\multiversion\Kernel;

use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\views\Entity\View;

/**
 * Test for views integration.
 *
 * @requires module views
 * @group multiversion
 */
class ViewsTest extends ViewsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'multiversion',
    'key_value',
    'serialization',
  ];

  /**
   * {@inheritdoc}
   */
  public static $testViews = ['test_executable_displays'];

  /**
   * Test that ViewExecutable can be correctly (un-)serialized.
   *
   * @throws \Exception
   */
  public function testSerialization() {
    // Rebuild the container because the "module_handler" service assign
    // "router.route_provider" to "router.route_provider.old" and then
    // assign "router.route_provider.old" back to "router.route_provider".
    /* @see \Drupal\Core\Extension\ModuleInstaller::install() */
    $this->container->get('kernel')->rebuildContainer();
    // The "_serviceId" property is set only to public services.
    /* @see \Drupal\Core\DependencyInjection\Compiler\DependencySerializationTraitPass::process() */
    // The "router.route_provider" is decorated by "multiversion" and used
    // by the "ViewExecutable". The decorated service must not be private in
    // order to be able to serialize/deserialize "ViewExecutable" properly.
    /* @see \Drupal\views\ViewExecutable::__sleep() */
    static::assertTrue(isset($this->container->get('router.route_provider')->_serviceId));
    // The (un-)serialization must go smoothly without the errors.
    unserialize(serialize($this->container->get('views.executable')->get(View::load('test_executable_displays'))));
  }

}
