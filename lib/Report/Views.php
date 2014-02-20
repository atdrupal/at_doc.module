<?php
namespace Drupal\at_doc\Report;

class Views extends BaseReport {
  public $name = 'Views';

  /**
   * @TODO: Col > Access
   */
  public function process() {
    foreach (views_get_enabled_views() as $id => $view) {
      $c1  = $view->export_module ? $view->export_module : 'N/A';
      $c2  = "<strong>{$view->human_name}</strong> ({$view->name})";
      $c2 .= _filter_autop($view->description);
      $c3  = $view->tag;
      $c4  = array();

      foreach ($view->display as $display) {
        if ($display->display_plugin === 'page') {
          $link = url($display->display_options['path']);
          $c4[] = l($link, $link);
        }
      }

      $rows[] = array($c1, $c2, $c3, implode(',', $c4));
    }

    return array(
      'header' => array('Feature', 'View', 'Tag', 'Path'),
      'rows' => $rows,
    );
  }
}
