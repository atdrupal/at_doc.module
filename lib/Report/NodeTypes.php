<?php
namespace Drupal\at_doc\Report;

class NodeTypes extends BaseReport {
  public $name = 'Content Types';
  protected $entity_type = 'node';

  private function checkRolePerm($role_id, $role_name, $perm) {
    $u = new \stdClass();
    $u->uid = 1000 + $role_id;
    $u->roles = array($role_id => $role_name);
    return user_access($perm, $u) || user_access('bypass node access', $u)
      ? $this->iconOk()
      : 'No';
  }

  public function process() {
    return array(
      'header' => array('Feature', 'Content Type', 'Fields', 'Permissions'),
      'rows' => $this->getRows(),
      'widths' => array(10, 40, 30, 80),
    );
  }

  private function getRows() {
    $info = entity_get_info($this->entity_type);

    $rows = array();
    foreach ($info['bundles'] as $machine_name => $bundle) {
      $rows[] = $this->getRow($machine_name, $bundle);
    }

    return $rows;
  }

  private function getRow($machine_name, $bundle) {
    $node_type = node_type_load($machine_name);
    $fields = $permissions = $p_header = array();

    foreach (field_info_instances('node', $machine_name) as $fn => $fi) {
      $label = l($fi['label'], "admin/structure/types/manage/". str_replace('_', '-', $machine_name) ."/fields/{$fn}");
      $fields[] = array(
        $label = $label . (!empty($fi['required']) ? '<span class="form-required">*</span>' : '')  ." ({$fn})",
        $fi['widget']['type'],
        !empty($fi['description']) ? $fi['description'] : ($this->iconError() . ' <em>Missing</em>'),
      );
    }

    $p_header[] = t('Role');
    foreach (user_roles() as $role_id => $role_name) {
      $permissions[$role_name][$role_id] = $role_name;

      foreach (array('create', 'edit own', 'edit any', 'delete own', 'delete any') as $action) {
        $perm = "{$action} {$machine_name} content";
        $p_header[$perm] = ucwords($action);
        $permissions[$role_name][$perm] = $this->checkRolePerm($role_id, $role_name, $perm);
      }
    }

    $fields = theme('table', array(
      'header' => array('Field', 'Widget', 'Description'),
      'rows' => $fields
    ));

    $permissions = theme('table', array(
      'header' => $p_header,
      'rows' => $permissions,
    ));

    return array(
      $node_type->module,
      "<strong>{$bundle['label']}</strong> ($node_type->type)"
        . _filter_autop(
            (empty($node_type->description)
                ? $this->iconError() . '<em>Missing description</em>'
                : strip_tags($node_type->description))
          ),
      $fields,
      $permissions,
    );
  }
}
