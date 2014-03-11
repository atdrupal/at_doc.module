<?php
namespace Drupal\at_doc\Report;

class Roles extends BaseReport {
  public $name = 'User Roles';

  public function process() {
    foreach (user_role_permissions(user_roles()) as $role_id => $permissions) {
      if ($this->is_cli) {
        $permissions = implode("\n", array_keys($permissions));
      }
      else {
        $permissions = theme(
          'item_list',
          array(
            'items' => array_keys($permissions),
            'attributes' => array('style' => '-webkit-column-count: 5; -moz-column-count: 5;'),
          )
        );
      }

      $user_role_name = user_role_load($role_id)->name;

      $rows[] = array($this->findRoleFeature($user_role_name), $user_role_name, $permissions);
    }

    return array(
      'header' => array('Feature', 'Role', 'Permissions'),
      'widths' => array(20, 20, 30, 15, 80),
      'rows' => $rows,
    );
  }

  private function findRoleFeature($user_role_name) {
    // Locked roles.
    if (in_array($user_role_name, array('anonymous user', 'authenticated user'))) {
      return $this->iconOk() . ' <em>locked</em>';
    }

    $map = features_get_component_map('user_role');

    return $this->findFeature($map, $user_role_name);
  }
}
