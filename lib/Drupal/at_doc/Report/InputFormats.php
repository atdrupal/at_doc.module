<?php
namespace Drupal\at_doc\Report;

class InputFormats extends BaseReport {
  public $name = 'Text formats';

  public function process() {
    $fallback_format = filter_fallback_format();

    foreach (filter_formats() as $id => $format) {
      $is_fallback = ($id == $fallback_format);

      $roles_markup = '';

      if ($is_fallback) {
        $roles_markup = drupal_placeholder(t('All roles may use this format'));
      }
      else {
        $roles = array_map('check_plain', filter_get_roles_by_format($format));
        $roles_markup = $roles ? implode(', ', $roles) : t('No roles may use this format');
      }

      $rows[] = array(
        'x',
        "<strong>{$format->name}</strong> ({$format->format})",
        $roles_markup,
        $format->weight
      );
    }

    return array(
      'header' => array(t('Feature'), t('Name'), t('Permission'), t('Weight')),
      'rows' => $rows,
    );
  }
}
