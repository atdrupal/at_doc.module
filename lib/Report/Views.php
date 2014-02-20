<?php
namespace Drupal\at_doc\Report;

class Views extends BaseReport {
  public $name = 'Views';

  /**
   * @TODO: Col > Access
   */
  public function process() {
    foreach (views_get_enabled_views() as $id => $view) {
      $c1  = $view->export_module ? $view->export_module : $this->iconError();
      $c2  = "<strong>{$view->human_name}</strong> ({$view->name})";
      $c2 .= _filter_autop($view->description);
      $c3  = $view->tag;
      $links    = array();
      $displays = array();

      foreach ($view->display as $display) {
        if ($display->display_plugin === 'page') {
          $link = url($display->display_options['path']);
          $links[] = $link;
          $displays[] = '<strong>'. $display->display_title .'</strong>' . ' ('. $display->display_plugin .')';
        }
      }

      $rows[] = array(
        $c1,
        $c2 . (empty($links) ? '' : theme('item_list', array('items' => $links, 'title' => t('Paths')))),
        $c3,
        implode(', ', $displays)
      );
    }

    return array(
      'header' => array('Feature', 'View', 'Tag', 'Displays'),
      'rows' => $rows,
    );
  }
}
