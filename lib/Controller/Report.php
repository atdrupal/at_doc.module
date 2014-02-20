<?php
namespace Drupal\at_doc\Controller;

class Report {
    public function pageCallback() {
      $reports[] = 'Drupal\at_doc\Report\Roles';
      $reports[] = 'Drupal\at_doc\Report\NodeTypes';
      $reports[] = 'Drupal\at_doc\Report\InputFormats';
      $reports[] = 'Drupal\at_doc\Report\Views';
      foreach ($reports as $i => $report) {
        $report = new $report();
        $output[] = array(
          '#prefix' => "<h3><a name='doc-{$i}' href='#doc-{$i}'>{$report->name}</a></h3>",
          $report->render()
        );
      }

      $output[]['#markup'] = '<style>table td { vertical-align: top !important; }</style>';

      return $output;
    }
}
