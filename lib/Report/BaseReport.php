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

  protected function iconOk() {
    return '<img src="/misc/message-16-ok.png" />';
  }

  protected function iconInfo() {
    return '<img src="/misc/message-16-info.png" />';
  }

  protected function iconError() {
    return '<img src="/misc/message-16-error.png" />';
  }
}
