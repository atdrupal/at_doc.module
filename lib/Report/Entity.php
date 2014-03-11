<?php

namespace Drupal\at_doc\Report;

class Entity extends BaseReport
{

    public $name = 'Entity';

    /**
     *
     * @var \Drupal\at_doc\Report\Taxonomy Taxonomy reporter.
     */
    private $taxonomy_reporter;

    private $role_permissions;

    public function __construct() {
        $role_names = user_roles();
        $this->role_permissions = user_role_permissions($role_names);
    }

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

            $processed_entity_type = $this->processEntityType($entity_type, $info);
            $build[] = array(
              '#theme' => 'fieldset',
              '#title' => $info['label'],
              '#description' => theme('item_list', array('items' => array(
                "<strong>Machine name:</strong> {$entity_type}",
                "<strong>View modes</strong>: " . (!empty($view_modes) ? implode(', ', $view_modes) : '<em>No view mode</em>')
              ))),
              '#value' => render($processed_entity_type),
              '#collapsible' => TRUE,
              '#attributes' => array('class' => array('collapsible')),
            );
        }

        return $build;
    }

    private function processEntityType($entity_type, $entity_info)
    {
        $rows = array();

        if ($entity_type == 'taxonomy_term') {
            $this->taxonomy_reporter = new Taxonomy();
        }

        if (!empty($entity_info['bundles'])) {
            foreach ($entity_info['bundles'] as $bundle => $bundle_info) {
                $rows[] = array_merge(
                  array($this->findBundleFeature($entity_type, $entity_info, $bundle)),
                  $this->processBundle($entity_type, $entity_info, $bundle, $bundle_info)
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

    private function processBundle($entity_type, $entity_info, $bundle, $bundle_info)
    {
        // @todo - Need cache.
        if (module_exists('field_group')) {
            $fields_processed = $this->processBundleFieldsWithGroups($entity_type, $bundle);
        }
        else {
            $fields_processed = $this->processBundleFields($entity_type, $bundle);
        }

        $permission_processed = $this->processPermission($entity_type, $entity_info, $bundle);

        if ($entity_type == 'node') {
            $node_type = node_type_load($bundle);
            $description = _filter_autop((empty($node_type->description)
                ? $this->iconError() . '<em>Missing description</em>'
                : strip_tags($node_type->description)));
        }
        else {
            $description = _filter_autop($this->iconError() . '<em>Missing description</em>');
        }

        return array(
          $this->getAdminLink($entity_type, $bundle, $entity_info)
          . ' (' . $bundle . ')'
          . $description
          . ($entity_type == 'taxonomy_term'
            ? $this->taxonomy_reporter->getUsedIn($bundle)
            : ''),
          drupal_render($fields_processed),
          drupal_render($permission_processed),
        );
    }

    private function processBundleFields($entity_type, $bundle)
    {
        $rows = array();
        foreach (field_info_instances($entity_type, $bundle) as $field_name => $field_info) {
            $rows[] = array(
              $this->findFieldFeature($entity_type, $bundle, $field_name),
              "{$field_info['label']}" . (!empty($field_info['required']) ? '<span class="form-required">*</span>' : '') . " ({$field_name})",
              $field_info['widget']['type'],
              !empty($field_info['description']) ? $field_info['description'] : ($this->iconError() . '<em> Missing</em>'),
            );
        }

        if (!empty($rows)) {
            return array(
              '#theme' => 'table',
              '#header' => array(
                array('data' => t('Feature'), 'width' => '120'),
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
              'feature' => $this->findFieldFeature($entity_type, $bundle, $machine_name),
              // Field's name doesn't have strong tag.
              'name' => "{$field['label']}" . (!empty($field['required']) ? '<span class="form-required">*</span>' : '') . " ({$machine_name})",
              'widget_type' => $field['widget']['type'],
              'description' => !empty($field['description']) ? $field['description'] : ($this->iconError() . '<em> Missing</em>'),
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
              'feature' => $this->findGroupFeature($group->identifier),
              // Group's name have strong tag, field doesn't have that tag.
              'name' => "<strong>{$group->label}</strong> ({$machine_name})",
              'widget_type' => $group->format_type,
              'description' => !empty($group->format_settings['instance_settings']['description']) ? $group->format_settings['instance_settings']['description'] : ($this->iconError() . '<em> Missing</em>'),
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
              $node['feature'],
              $indentation . $node['name'],
              $node['widget_type'],
              $node['description'],
            );
        }

        if (!empty($rows)) {
            return array(
              '#theme' => 'table',
              '#header' => array(
                array('data' => t('Feature'), 'width' => '120'),
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

    private function findBundleFeature($entity_type, $entity_info, $bundle) {

        // Support eck module.
        if (module_exists('eck') && isset($entity_info['module']) && $entity_info['module'] == 'eck') {
            // This entity type is created by eck.
            $map = features_get_component_map('eck_bundle');
            $bundle_machine_name = $entity_type . '_' . $bundle;

            return $this->findFeature($map, $bundle_machine_name);
        }
        else {
            // Support all bundles in taxonomy_term.
            if ($entity_type == 'taxonomy_term') {
                $map = features_get_component_map('taxonomy');

                return $this->findFeature($map, $bundle);
            }

            // Other entity type, include node.
            $map = features_get_component_map($entity_type);

            return $this->findFeature($map, $bundle);
        }
    }

    private function findFieldFeature($entity_type, $bundle, $field_name) {
        $map = features_get_component_map('field_instance');
        $field_feature_component = $entity_type . '-' . $bundle . '-' . $field_name;

        return $this->findFeature($map, $field_feature_component);
    }

    private function findGroupFeature($identifier) {
        $map = features_get_component_map('field_group');

        return $this->findFeature($map, $identifier);
    }

    private function processPermission($entity_type, $entity_info, $bundle) {
        $header[] = t('Role');
        $rows = array();

        foreach (user_roles() as $rid => $role_name) {
            $rows[$role_name][$rid] = $role_name;

            if ($entity_type == 'node') {
                $actions = array('create', 'edit own', 'edit any', 'delete own', 'delete any');
                $perm_template = "%action% %bundle% content";
            }
            elseif ($entity_type == 'comment') {
                $actions = array('access', 'post', 'edit own');
                $perm_template = "%action% comments";
            }
            elseif ($entity_type == 'taxonomy_term') {
                $vocabulary = taxonomy_vocabulary_machine_name_load($bundle);
                $actions = array('edit', 'delete');
                $perm_template = "%action% terms in " . $vocabulary->vid;
            }
            elseif (module_exists('eck') && isset($entity_info['module']) && $entity_info['module'] == 'eck') {
                $actions = array('add', 'edit', 'delete', 'list', 'view');
                $perm_template = "eck %action% %entity_type% %bundle% entities";
            }
            elseif ($entity_type == 'user') {
                $actions = array();
            }
            else {
                return array(
                  '#markup' => $this->iconInfo() . ' <em>'. t('No permission') .'</em>'
                );
            }

            foreach ($actions as $action) {
                $perm = $perm_template;
                $perm = str_replace('%action%', $action, $perm);
                $perm = str_replace('%entity_type%', $entity_type, $perm);
                $perm = str_replace('%bundle%', $bundle, $perm);
                $header[$perm] = ucwords($action);
                $rows[$role_name][$perm] = isset($this->role_permissions[$rid][$perm]) ? $this->iconOk() : 'No';
            }

            // Special permissions.
            switch ($entity_type) {
                case 'comment':
                    $perm = 'skip comment approval';
                    $header[$perm] = ucwords('Skip Approval');
                    $rows[$role_name][$perm] = isset($this->role_permissions[$rid][$perm]) ? $this->iconOk() : 'No';

                    break;
                case 'user':
                    $perms = array(
                      'access user profiles' => ucwords('Access Profiles'),
                      'change own username' => ucwords('Change Own Username'),
                      'cancel account' => ucwords('Cancel Own Account'),
                    );
                    foreach ($perms as $perm => $label) {
                        $header[$perm] = $label;
                        $rows[$role_name][$perm] = isset($this->role_permissions[$rid][$perm]) ? $this->iconOk() : 'No';
                    }

                    break;

                default:
                    break;
            }
        }

        return array(
          '#theme' => 'table',
          '#header' => $header,
          '#rows' => $rows,
        );
    }

}
