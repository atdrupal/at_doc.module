<?php
namespace Drupal\at_doc\Report;

class NodeTypes extends BaseReport {
  public $name = 'Content Types';
  protected $entity_type = 'node';

  private function checkRolePerm($role_id, $role_name, $perm) {
    $u = new \stdClass();
    $u->uid = 1000 + $role_id;
    $u->roles = array($role_id => $role_name);
    return user_access($perm, $u) ? t('Yes') : t('No');
  }

  public function process() {
    $info = entity_get_info($this->entity_type);

    foreach ($info['bundles'] as $machine_name => $bundle) {
      $node_type = node_type_load($machine_name);
      $fields = $permissions = $p_header = array();

      foreach (field_info_instances('node', $machine_name) as $fn => $fi) {
        $fields[] = array("{$fi['label']} ({$fn})", $fi['widget']['type']);
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

      $fields = theme('table', array('header' => array('Field', 'Widget'), 'rows' => $fields));
      $permissions = theme('table', array('header' => $p_header, 'rows' => $permissions, 'caption' => t('Permissions')));

      $rows[] = array(
        $node_type->module,
        "<strong>{$bundle['label']}</strong> ($node_type->type)" . _filter_autop(strip_tags($node_type->description)) . $permissions,
        $fields,
      );
    }

    return array(
      'header' => array('Feature', 'Bundle', 'Fields'),
      'rows' => $rows,
      'widths' => array(10, 40, 30, 80),
    );
  }
}
