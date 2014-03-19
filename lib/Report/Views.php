<?php
namespace Drupal\at_doc\Report;

class Views extends BaseReport {
  public $name = 'Views';

  /**
   * @TODO: Col > Access
   */
  public function process() {
    $rows = array();

    foreach (views_get_enabled_views() as $id => $view) {
      $c1  = isset($view->export_module) ? $view->export_module : $this->iconError() . ' unknown';
      $c2  = "<strong>{$view->human_name}</strong> ({$view->name})";
      $c2 .= _filter_autop($view->description);
      $c3  = $view->tag;
      $c4 = '';
      $links    = array();
      $displays = array();

      foreach ($view->display as $display) {
        // Only process display page.
        if ($display->display_plugin === 'page') {
          $link = url($display->display_options['path']);
          $links[] = $link;
          $displays[] = '<strong>'. $display->display_title .'</strong>' . ' ('. $display->display_plugin .')';

          // Empty behaviours.
          if (isset($display->display_options['empty'])) {
            // Overrided empty behaviours option.
            $empty_behaviours = $display->display_options['empty'];
          }
          else {
            $empty_behaviours = $view->display['default']->display_options['empty'];
          }

          // List readable empty messages.
          $empty_messages = array();
          foreach ($empty_behaviours as $key => $behaviour) {
            if (in_array($behaviour['field'], array('area_text_custom', 'area')) && !empty($behaviour['content'])) {
              $empty_messages[] = $behaviour['content'];
            }
          }
          $c4 .= theme('item_list', array('items' => $empty_messages));
        }
      }

      $rows[] = array(
        $c1,
        $c2 . (empty($links) ? '' : theme('item_list', array('items' => $links, 'title' => t('Paths')))),
        $c3,
        !empty($displays) ? implode(', ', $displays) : '<em>No display</em>',
        $c4
      );
    }

    return array(
      '#theme' => 'table',
      '#header' => array('Feature', 'View', 'Tag', 'Displays', 'Empty Message'),
      '#rows' => $rows,
      '#empty' => t('No enabled views'),
    );
  }
}
