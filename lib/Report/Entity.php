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
        $field_nodes = array();
        $group_nodes = array();
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
              'level' => 0,
            );
            $field_nodes[$machine_name] = $node;
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
              'level' => 0,
            );
            $group_nodes[$machine_name] = $node;

            // Build hierarchical info.
            $hierarchical_info[$machine_name]['parent'] = !empty($group->parent_name) ? $group->parent_name : '';
            $hierarchical_info[$machine_name]['children'] = !empty($group->children) ? $group->children : array();
        }
        unset($groups, $group);

        // Build ordered nodes.
        $odered_nodes = $this->buildOrderedNodes($field_nodes, $group_nodes, $hierarchical_info);
        unset($field_nodes, $group_nodes, $hierarchical_info);

        // Build rows for table.
        $rows = array();
        foreach ($odered_nodes as $node) {
            $indentation = '';
            for ($i = 0; $i < $node['level']; $i++) {
                $indentation .= '<div class="indentation"> </div>';
            }

            $rows[] = array(
              $indentation . $node['name'],
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

    private function buildOrderedNodes(&$field_nodes, &$group_nodes, $hierarchical_info, $current_parent = '', $level = 0)
    {

        // 1. Find all groups this level.
        $group_nodes_this_level = array();
        $added_keys = array();
        foreach ($group_nodes as $key => $node) {

            if ($hierarchical_info[$key]['parent'] == $current_parent) {
                $node['level'] = $level;
                $group_nodes_this_level[$key] = $node;
                $added_keys[] = $key;
            }

        }

        foreach ($added_keys as $key) {
            unset($group_nodes[$key]);
        }
        unset($added_keys);

        // 2. Find all children nodes of each group node in this level.
        $children_nodes_this_level = array();
        foreach ($group_nodes_this_level as $key => $node) {

            $children_nodes_this_level[$key] = $this->buildOrderedNodes($field_nodes, $group_nodes, $hierarchical_info, $key, $level + 1);
        }

        $ordered_nodes = array();

        // 4. Find all field nodes this level.
        if ($current_parent == '' && !empty($field_nodes)) {
            // Root level, get all field node left.
            $ordered_nodes = array_merge($group_nodes_this_level, $field_nodes);
        }
        else {
            $ordered_nodes = $group_nodes_this_level;

            if (!empty($field_nodes)) {
                foreach ($hierarchical_info[$current_parent]['children'] as $child_node_key) {
                    if (isset($field_nodes[$child_node_key])) {
                        $node = $field_nodes[$child_node_key];
                        $node['level'] = $level;
                        $ordered_nodes[$child_node_key] = $node;
                        unset($field_nodes[$child_node_key]);
                    }
                }
            }
        }

        // 5. Sort all nodes this level.
        uasort($ordered_nodes, function($a, $b) {
            // Becareful, uasort won't work if the weight is equal, see e.g.
            // array (
            //     0 => array(
            //         'text' => 'A',
            //         'weight' => -4
            //     ),
            //     1 => array(
            //         'text' => 'B',
            //         'weight' => -4
            //     )
            // )
            // will return:
            // array (
            //     0 => array(
            //         'text' => 'A',
            //         'weight' => -4
            //     ),
            //     1 => array(
            //         'text' => 'A',
            //         'weight' => -4
            //     )
            // )
            // if we return 0 in compare function.

            /*if ($a['weight'] == $b['weight']) {
                return 0;
            }
            else*/if ($a['weight'] < $b['weight']) {
                return -1;
            }
            else {
                return 1;
            }
        });

        // 5. Finally return all nodes.
        $results = array();
        foreach ($ordered_nodes as $key => $node) {
            array_push($results, $node);
            if (isset($children_nodes_this_level[$key])) {
                foreach ($children_nodes_this_level[$key] as $children_node) {
                    array_push($results, $children_node);
                }
            }
        }

        return $results;
    }

}
