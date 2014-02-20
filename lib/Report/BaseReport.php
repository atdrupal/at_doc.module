<?php
namespace Drupal\at_doc\Report;

abstract class BaseReport {
  public function render() {
    $results = $this->process();

    return array(
      '#theme' => 'table',
      '#header' => $results['header'],
      '#rows' => $results['rows']
    );
  }
}
