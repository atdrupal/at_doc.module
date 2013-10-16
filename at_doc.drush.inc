<?php

/**
 * Implements hook_drush_command()
 *
 * @return array
 */
function at_doc_drush_command() {
  $items['at-doc'] = array(
    'description' => 'Generate document for Drupal site.',
  );
  return $items;
}

/**
 * @todo module
 * @todo menus, links
 * @todo taxonomy
 * @todo pages — Ctools pages.
 * @todo boxes
 * @todo views
 * @todo context
 *
 * Callback for at-doc command.
 */
function drush_at_doc() {
  $reports[] = 'Drupal\at_doc\Drush\Roles';
  $reports[] = 'Drupal\at_doc\Drush\NodeTypes';
  foreach ($reports as $report) {
    $report = new $report();
    $report->render();
  }
}
