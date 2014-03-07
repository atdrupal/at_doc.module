<?php
namespace Drupal\at_doc\Controller;

class Report {
    public function render() {
      // $reports['Entity'] = 'Drupal\at_doc\Report\Entity';
      $reports['Roles'] = 'Drupal\at_doc\Report\Roles';
      $reports['InputFormats'] = 'Drupal\at_doc\Report\InputFormats';
      $reports['NodeTypes'] = 'Drupal\at_doc\Report\NodeTypes';
      $reports['Views'] = 'Drupal\at_doc\Report\Views';

      $output = array('reports' => array());

      foreach ($reports as $i => $report) {
        $report = new $report();
        $output['reports'][$i] = $report->render();
      }

      $return = array(
        '#markup' => at_container('twig')->render('@at_doc/templates/report.html.twig', $output),
        '#attached' => array(
          'js' => array(
            drupal_get_path('module', 'at_doc') . '/misc/js/at_report.js',
          ),
          'library' => array(array('system', 'ui.tabs', FALSE)),
        ),
      );

      return render($return);
    }

    public function renderReport($class) {
        $renderable_array = at_id(new $class)->render();
        return render($renderable_array);
    }
}
