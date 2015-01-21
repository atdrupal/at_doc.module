<?php
namespace Drupal\at_doc\Report;

class Roles extends BaseReport {
  public $name = 'User Roles';

  public function process() {
    foreach (user_role_permissions(user_roles()) as $role_id => $permissions) {
      // Report permissions with feature.
      // https://github.com/atdrupal/at_doc/issues/33
      $exported_permissions = array();
      $unexported_permissions = array();
      $conflict_permissions = array();

      $features_map = features_get_component_map('user_permission');
      foreach ($permissions as $key => $value) {

        if (isset($features_map[$key])) {
          if (count($features_map[$key]) > 1) {
            // Conflict.
            $features = implode(', ', $features_map[$key]);
            $conflict_permissions[] = $key . " ($features)";
          }
          else {
            $feature = reset($features_map[$key]);
            $exported_permissions[] = $key . " ($feature)";
          }
        }
        else {
          $unexported_permissions[] = $key;
        }
      }

      $permissions_list = '<div class="permissions-list">';
      if (!empty($exported_permissions)) {
        $permissions_list .= theme('item_list', array(
              'items' => $exported_permissions,
            ));
      }

      if (!empty($unexported_permissions)) {
        $permissions_list .=  $this->iconError() . " <strong>No feature</strong>: "
          . theme('item_list', array(
              'items' => $unexported_permissions,
            ));
      }

      if (!empty($conflict_permissions)) {
        $permissions_list .=  $this->iconError() . " <strong>Conflict</strong>: "
          . theme('item_list', array(
              'items' => $conflict_permissions,
            ));
      }
      $permissions_list .= '</div>';

      $user_role_name = user_role_load($role_id)->name;

      $rows[] = array($this->findRoleFeature($user_role_name), $user_role_name, $permissions_list);
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
