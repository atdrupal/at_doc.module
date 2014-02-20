<?php
namespace Drupal\at_doc\Controller;

class Report {
    public function render() {
      $reports['Roles'] = 'Drupal\at_doc\Report\Roles';
      $reports['NodeTypes'] = 'Drupal\at_doc\Report\NodeTypes';
      $reports['InputFormats'] = 'Drupal\at_doc\Report\InputFormats';
      $reports['Views'] = 'Drupal\at_doc\Report\Views';

      $output = array('reports' => array());

      foreach ($reports as $i => $report) {
        $report = new $report();
        $output['reports'][$i] = array(
          '#prefix' => "<h3><a name='doc-{$i}' href='#doc-{$i}'>{$report->name}</a></h3>",
          $report->render()
        );
      }

      return array(
        '#markup' => at_container('twig')->render('@at_doc/templates/report.html.twig', $output),
        '#attached' => array(
          'js' => array(
            drupal_get_path('module', 'at_doc') . '/misc/js/at_report.js',
          ),
          'library' => array(array('system', 'ui.tabs', FALSE)),
        ),
      );
    }
}
