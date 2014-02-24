<?php

namespace Drupal\at_doc\Report;

class Entity extends BaseReport
{

    public $name = 'Entity';

    protected function process()
    {
        $build = array();
        $build['#prefix'] = '<div class="vertical-tabs-panes">';
        $build['#suffix'] = '</div>';
        $build['#attached']['js'] = array('misc/form.js', 'misc/collapse.js');
        $build['#attached']['library'] = array('system', 'drupal.vertical-tabs');
        drupal_add_library('system', 'drupal.vertical-tabs');

        foreach (entity_get_info() as $entity_type => $info) {
            $view_modes = array();
            foreach ($info['view modes'] as $view_mode) {
                $view_modes[] = $view_mode['label'];
            }

            $build[] = array(
              '#theme' => 'fieldset',
              '#title' => $info['label'],
              '#description' => theme('item_list', array('items' => array(
                "<strong>Machine name:</strong> {$entity_type}",
                "<strong>View modes</strong>: " . implode(', ', $view_modes)
              ))),
              '#value' => render($this->processEntityType($entity_type, $info)),
              '#collapsible' => TRUE,
              '#attributes' => array('class' => array('collapsible')),
            );
        }

        return $build;
    }

    private function processEntityType($entity_type, $entity_info)
    {
        $rows = array();

        if (!empty($entity_info['bundles'])) {
            foreach ($entity_info['bundles'] as $bundle => $bundle_info) {
                $rows[] = array_merge(
                  array(isset($entity_info['module']) ? $entity_info['module'] : $this->iconInfo() . ' <em>unknown</em>'),
                  $this->processBundle($entity_type, $bundle, $bundle_info)
                );
            }
        }

        return array(
          '#theme' => 'table',
          '#header' => array(
            array('data' => t('Feature'), 'width' => '120'),
            array('data' => t('Bundle'), 'width' => '250'),
            array('data' => t('Fields')),
            t('Permissions'),
          ),
          '#rows' => $rows,
        );
    }

    private function processBundle($entity_type, $bundle, $bundle_info)
    {
        return array(
          l($bundle_info['label'], $bundle_info['admin']['real path'])
          . ' (' . $bundle . ')'
          . (
            empty($bundle_info['description'])
                ? ''
                : _filter_autop(strip_tags($bundle_info['description'])
            )
          ),
          drupal_render($this->processBundleFields($entity_type, $bundle)),
          $this->iconInfo() . ' %permission'
        );
    }

    private function processBundleFields($entity_type, $bundle)
    {
        $rows = array();
        foreach (field_info_instances($entity_type, $bundle) as $field_name => $field_info) {
            $rows[] = array(
              $field_info['label'] . (!empty($field_info['required']) ? '<span class="form-required">*</span>' : '') . " ({$field_name})",
              $field_info['widget']['type'],
              !empty($field_info['description']) ? $field_info['description'] : ($this->iconError() . ' <em>Missing</em>'),
            );
        }

        if (!empty($rows)) {
            return array(
              '#theme' => 'table',
              '#header' => array(
                'Field',
                array('data' => t('Widget'),      'width' => '150'),
                array('data' => t('Description'), 'width' => '200')
              ),
              '#rows' => $rows
            );
        }
        else {
            return array('#markup' => $this->iconInfo() . ' <em>'. t('No field') .'</em>');
        }
    }

}
