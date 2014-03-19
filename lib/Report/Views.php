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
      $empty_messages_rows = array();

      foreach ($view->display as $display) {
        if ($display->display_plugin === 'page') {
          $link = url($display->display_options['path']);
          $links[] = $link;
        }

        // Default empty behaviours on all displays.
        if (isset($view->display['default']->display_options['empty'])) {
          $empty_behaviours = $view->display['default']->display_options['empty'];
        }
        else {
          $empty_behaviours = array();
        }

        if ($display->display_plugin !== 'default') {
          // Overrided empty behaviours.
          if (isset($display->display_options['empty'])) {
            $empty_behaviours = $display->display_options['empty'];
          }

          // List readable empty messages.
          $empty_messages = array();
          foreach ($empty_behaviours as $key => $behaviour) {
            if (in_array($behaviour['field'], array('area_text_custom', 'area')) && !empty($behaviour['content'])) {
              $empty_messages[] = $behaviour['content'];
            }
          }

          $empty_messages_rows[] = array(
            $display->display_title,
            theme('item_list', array('items' => $empty_messages))
          );
        }
      }

      if (!empty($empty_messages_rows)) {
        $empty_messages_table = array(
          '#theme' => 'table',
          '#header' => array('Display', 'Messages'),
          '#rows' => $empty_messages_rows,
          '#empty' => t('No Messages'),
        );
        $c4 = drupal_render($empty_messages_table);
      }

      $rows[] = array(
        $c1,
        $c2 . (empty($links) ? '' : theme('item_list', array('items' => $links, 'title' => t('Paths')))),
        $c3,
        $c4
      );
    }

    return array(
      '#theme' => 'table',
      '#header' => array('Feature', 'View', 'Tag', 'Empty Message'),
      '#rows' => $rows,
      '#empty' => t('No enabled views'),
    );
  }
}
