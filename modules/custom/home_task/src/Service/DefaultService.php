<?php

namespace Drupal\home_task\Service;

use Drupal\Core\Site\Settings;
use Fhaculty\Graph\Exception\UnexpectedValueException;
use Fhaculty\Graph\Graph;
use Graphp\GraphViz\GraphViz;
use Symfony\Component\Yaml\Yaml;

/**
 * Class DefaultService
 *
 * @package Drupal\home_task\Service
 */
class DefaultService {

  /**
   * Drupal settings value containing the full path of Graphviz executable
   *
   * @var string
   */
  private const GRAPH_EXECUTABLE = 'graph_viz_executable';

  /**
   * Generated HTML of dependency graph
   *
   * @var string
   */
  private $dependencyGraphBuild;

  /**
   * Gets the dependency graph image HTML
   *
   * @return string
   */
  public function getDependencyGraphImageHtml(): string {
    return $this->dependencyGraphBuild;
  }

  /**
   * Processes dependency graph and saves to $dependencyGraphBuild
   * Requires clue/graph package to be included
   *
   * @return void
   */
  public function processDependencyGraph(): void {
    $graph = new Graph();
    $graphVertexByModules = [];
    $modules = [];
    $dependencies = [];

    foreach (\Drupal::moduleHandler()->getModuleList() as $extension) {
      $moduleName = $extension->getName();
      $graphVertexByModules[$moduleName] = $graph->createVertex($moduleName);

      $modules[] = $moduleName;
      $dependencies[$moduleName] = $this->parseDependencies($moduleName, $extension->getType());
    }

    foreach ($modules as $module) {
      foreach ($dependencies[$module] as $dependency) {
        $graphVertexByModules[$module]->createEdgeTo($graphVertexByModules[$dependency]);
      }
    }

    $this->dependencyGraphBuild = $this->generateGraphImageHtml($graph, 'svg');
  }

  /**
   * Generates html image containing generated graph created by GraphViz
   *
   * @TODO Splitting the error handling into other function.
   *
   * @param Graph $graph
   * @param string $imageFormat
   *
   * @return string
   */
  private function generateGraphImageHtml(Graph $graph, string $imageFormat = 'svg'): string {
    $graphExecutableWindowsFilename = 'dot.exe';
    $graphExecutableConstant = self::GRAPH_EXECUTABLE;
    $graphExecutableSetting = Settings::get($graphExecutableConstant);

    if (strpos(PHP_OS, 'WIN') !== false) {
      if (!$graphExecutableSetting) {
        return '<div>Error happened when generating the graph image - empty Graphviz library setting</div>' .
          "<b>Please provide a non-empty setting value for $graphExecutableConstant " .
          "containing the Graphviz library path!</b>";
      } else if (
        !preg_match("#$graphExecutableWindowsFilename$#",$graphExecutableSetting) ||
        !is_file($graphExecutableSetting)
      ) {
        return '<div>Error happened when generating the graph image - wrong Graphviz library setting</div>' .
          "<b>Please provide a non-empty setting value for $graphExecutableConstant " .
          "containing the Graphviz library path!</b>";
      }
    }

    $graphViz = new GraphViz();
    $graphViz->getExecutable();
    $graphViz->setExecutable($graphExecutableSetting);
    $graphViz->setFormat($imageFormat);

    try {
      $html = $graphViz->createImageHtml($graph);
    } catch (UnexpectedValueException $exception) {
      $html = '<div>Error happened when generating the graph image - Graphviz library is unreachable</div>' .
        "<b>Please install Graphviz library using" .
        "<a href='https://www.graphviz.org/' target='_blank'>this link</a> !</b><br>" . $exception->getMessage();
    }

    return $html;
  }

  /**
   * Parses dependencies of a specific module
   *
   * @param string $module
   * @param string $type
   *
   * @return array
   */
  private function parseDependencies(string $module, string $type): array {
    $filename = drupal_get_path($type, $module) . "/$module.info.yml";
    $info = Yaml::parseFile($filename);

    if ($type === 'profile') {
      return isset($info['install']) ?
          $this->cleanModuleName($info['install']) :
          [];
    }

    return isset($info['dependencies']) ?
      $this->cleanModuleName($info['dependencies']) :
      [];
  }

  /**
   * Cleans module names
   *
   * @param array $modules
   *
   * @return array
   */
  private function cleanModuleName(array $modules): array {
    $cleaned = [];

    foreach ($modules as $module) {
      $clean = explode(':', $module)[1] ?? $module;
      $cleaned[] = explode(' ', $clean)[0] ?? $module;
    }

    return $cleaned;
  }

}
