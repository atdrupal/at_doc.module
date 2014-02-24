<?php
namespace Drupal\at_doc\Report;

abstract class BaseReport {
  protected function process() {
      throw new \Exception('Please implement process method.');
  }

  public function render() {
    $results = $this->process();

    if (!empty($results['rows'])) {
        return array(
          '#theme' => 'table',
          '#header' => $results['header'],
          '#rows' => $results['rows']
        );
    }

    return $results;
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
