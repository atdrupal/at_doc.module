<?php
namespace Drupal\at_doc\Drush;

class Roles extends BaseReport {
  protected $name = 'User Roles';

  public function process() {
    foreach (user_role_permissions(user_roles()) as $role_id => $permissions) {
      $rows[] = array(
        user_role_load($role_id)->name,
        'coming',
        implode("\n", array_keys($permissions))
      );
    }

    return array(
      'header' => array('Role', 'Featured', 'Permissions'),
      'widths' => array(20, 20, 30, 15, 80),
      'rows' => $rows,
    );
  }
}
