<?php
namespace Drupal\at_doc\Drush;

abstract class BaseReport {
  public function render() {
    if (function_exists('drush_print_table')) {
      $this->renderCli();
    }
    else {
      $this->renderHtml();
    }
  }

  protected function renderCli() {
    $results = $this->process();
    drush_print_r('------------------------------------');
    drush_print_r('     ' . $this->name);
    drush_print_r('------------------------------------');
    drush_print_r('');
    drush_print_table(
      array_merge($results['header'], $results['rows']),
      $header = TRUE,
      $results['widths']
    );
    drush_print_r('');
    drush_print_r('');
  }

  protected function renderHtml() {
    return array(
      '#theme' => 'table',
      '#header' => $results['header'],
      '#rows' => $results['rows']
    );
  }
}
