<?php
namespace Drupal\at_doc\Controller;

class Report {
    public function render() {
      $reports['Roles'] = 'Drupal\at_doc\Report\Roles';
      $reports['NodeTypes'] = 'Drupal\at_doc\Report\NodeTypes';
      $reports['InputFormats'] = 'Drupal\at_doc\Report\InputFormats';
      $reports['Views'] = 'Drupal\at_doc\Report\Views';
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
