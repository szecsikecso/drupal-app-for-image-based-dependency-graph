<?php

namespace Drupal\home_task\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\home_task\Service\DefaultService;


/**
 * Class DefaultController
 *
 * @package Drupal\home_task\Controller
 */
class DefaultController extends ControllerBase {

  /**
   * Hello World function using plain markup
   *
   * @param string $name
   *
   * @return array
   */
  public function hello(string $name): array {
    return [
      '#type' => 'markup',
      '#markup' => "Hello $name!",
    ];
  }

  /**
   * Gathers graph data to fulfill dependency_graph template
   *
   * @return array
   */
  public function dependencyGraph(): array {
    $defaultService = new DefaultService();
    $defaultService->processDependencyGraph();
    $graph = $defaultService->getDependencyGraphImageHtml();

    $build = [
      '#theme' => 'dependency_graph',
      '#graph' => $graph,
    ];

    // To disable the cache
    $build[]['#cache']['max-age'] = 0;

    return $build;
  }

}
