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
                "<strong>View modes</strong>: " . (!empty($view_modes) ? implode(', ', $view_modes) : '<em>No view mode</em>')
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
        // @todo - Need cache.
        if (module_exists('field_group')) {
            $fields_processed = $this->processBundleFieldsWithGroups($entity_type, $bundle);
        }
        else {
            $fields_processed = $this->processBundleFields($entity_type, $bundle);
        }

        return array(
          l($bundle_info['label'], $bundle_info['admin']['real path'])
          . ' (' . $bundle . ')'
          . (
            empty($bundle_info['description'])
                ? ''
                : _filter_autop(strip_tags($bundle_info['description'])
            )
          ),
          drupal_render($fields_processed),
          $this->iconInfo() . ' %permission'
        );
    }

    private function processBundleFields($entity_type, $bundle)
    {
        $rows = array();
        foreach (field_info_instances($entity_type, $bundle) as $field_name => $field_info) {
            $rows[] = array(
              "{$field_info['label']}" . (!empty($field_info['required']) ? '<span class="form-required">*</span>' : '') . " ({$field_name})",
              $field_info['widget']['type'],
              !empty($field_info['description']) ? $field_info['description'] : ($this->iconError() . '<em>Missing</em>'),
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

    /**
     * Show hierachical fields with field groups supported.
     *
     * @param string $entity_type
     * @param string $bundle
     * @return renderable array
     */
    private function processBundleFieldsWithGroups($entity_type, $bundle)
    {
        // Each node contain enough information to be converted to row.
        $nodes = array();
        $hierarchical_info = array();

        // Get all fields.
        $fields = field_info_instances($entity_type, $bundle);
        foreach ($fields as $machine_name => $field) {
            // Create node from field.
            $node = array(
              // Field's name doesn't have strong tag.
              'name' => "{$field['label']}" . (!empty($field['required']) ? '<span class="form-required">*</span>' : '') . " ({$machine_name})",
              'widget_type' => $field['widget']['type'],
              'description' => !empty($field['description']) ? $field['description'] : ($this->iconError() . '<em>Missing</em>'),
              'weight' => $field['widget']['weight'],
            );
            $nodes[$machine_name] = $node;
        }
        unset($fields, $field);

        // Get all groups.
        $groups = field_group_info_groups($entity_type, $bundle, 'form');
        foreach ($groups as $machine_name => $group) {
            // Create node from group.
            $node = array(
              // Group's name have strong tag, field doesn't have that tag.
              'name' => "<strong>{$group->label}</strong> ({$machine_name})",
              'widget_type' => $group->format_type,
              'description' => !empty($group->format_settings['instance_settings']['description']) ? $group->format_settings['instance_settings']['description'] : ($this->iconError() . '<em>Missing</em>'),
              'weight' => $group->weight,
            );
            $nodes[$machine_name] = $node;

            // Build hierarchical info.
            $hierarchical_info[$machine_name]['parent'] = !empty($group->parent_name) ? $group->parent_name : '';
            $hierarchical_info[$machine_name]['children'] = !empty($group->children) ? $group->children : array();
        }
        unset($groups, $group);

        // Build ordered nodes.
        $odered_nodes = $this->buildOrderedNodes($nodes, $hierarchical_info);
        unset($nodes, $hierarchical_info);

        // Build rows for table.
        $rows = array();
        foreach ($odered_nodes as $node) {
            $rows[] = array(
              $node['name'],
              $node['widget_type'],
              $node['description'],
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

    private function buildOrderedNodes(&$nodes, $hierarchical_info, $current_parent = '')
    {
        $ordered_nodes = array();
        $added_keys = array();

        // 1. Find all nodes this level.
        foreach ($nodes as $key => $node) {

            if ($hierarchical_info[$key]['parent'] == $current_parent) {
                $ordered_nodes[$key] = $node;
                $added_keys[] = $key;
            }

        }

        usort($ordered_nodes, function($a, $b) {
            return ($a['weight'] == $b['weight']) ? 0 : (($a['weight'] < $b['weight']) ? -1 : +1);
        });

        // 2. Remove all nodes that is assigned to $ordered_nodes.
        foreach ($added_keys as $key) {
            unset($nodes[$key]);
        }

        // 3. Find all children nodes of each node in this level.
        $children_nodes = array();
        foreach ($ordered_nodes as $key => $node) {
            if (!empty($nodes)) {
                $children_nodes[$key] = $this->buildOrderedNodes($nodes, $hierarchical_info, $key);
            }
        }

        // 4. At rool level, add node that doesn't have parent.
        if ($current_parent == '') {
            $ordered_nodes = array_merge($ordered_nodes, $nodes);

            usort($ordered_nodes, function($a, $b) {
                return ($a['weight'] == $b['weight']) ? 0 : (($a['weight'] < $b['weight']) ? -1 : +1);
            });
        }

        // 5. Finally sort and return all rows.
        $results = array();
        foreach ($ordered_nodes as $key => $node) {
            array_push($results, $node);
            if (isset($children_nodes[$key])) {
                foreach ($children_nodes[$key] as $children_node) {
                    array_push($results, $children_node);
                }
            }
        }

        return $results;
    }

}
