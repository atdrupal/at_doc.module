<?php
namespace Drupal\at_doc\Drush;

class NodeTypes extends BaseReport {
  protected $name = 'Content Types';
  protected $entity_type = 'node';

  public function process() {
    $info = entity_get_info($this->entity_type);

    foreach ($info['bundles'] as $machine_name => $bundle) {
      $node_type = node_type_load($machine_name);
      $fields = $permissions = array();

      foreach (field_info_instances('node', $machine_name) as $fn => $fi) {
        $fields[] = "{$fi['label']} ($fn): {$fi['widget']['type']}";
      }

      foreach (array('create', 'edit own', 'edit any', 'delete own', 'delete any') as $action) {
        $perm = "{$action} {$machine_name} content";

        foreach (user_roles() as $role_id => $role_name) {
          $u = new stdClass();
          $u->uid = $role_id;
          $u->roles = array($role_id => $role_name);
          if (user_access($perm, $u)) {
            $permissions[$perm][$role_id] = $role_name;
          }
        }

        if (!empty($permissions[$perm])) {
          $permissions[$perm] = "{$perm}\n- " . implode("\n- ", $permissions[$perm]) . "\n";
        }
      }

      $rows[] = array(
        $node_type->module,
        "{$bundle['label']} ($node_type->type)\n\n" . strip_tags($node_type->description),
        implode("\n", $permissions),
        implode("\n", $fields),
      );
    }

    return array(
      'header' => array('Feature', 'Bundle', 'Permissions', 'Fields'),
      'rows' => $rows,
      'widths' => array(10, 40, 30, 80),
    );
  }
}
