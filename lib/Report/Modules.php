<?php

namespace Drupal\at_doc\Report;

class Modules extends BaseReport
{

    /**
     * Report only visible and enabled modules.
     *
     * @return array Renderable array.
     */
    protected function process()
    {
        $build = array();
        $build['#prefix'] = '<div class="vertical-tabs-panes">';
        $build['#suffix'] = '</div>';
        $build['#attached']['js'] = array('misc/form.js', 'misc/collapse.js');
        $build['#attached']['library'] = array('system', 'drupal.vertical-tabs');
        drupal_add_library('system', 'drupal.vertical-tabs');

        $all_modules = system_rebuild_module_data();

        // Visible modules.
        $visible_modules = $all_modules;
        foreach ($visible_modules as $filename => $module) {
          if (!empty($module->info['hidden'])) {
            unset($visible_modules[$filename]);
          }
        }

        // Visible and enabled modules.
        $filtered_modules = $all_modules;
        foreach ($filtered_modules as $filename => $module) {
          if (!empty($module->info['hidden']) || !$module->status) {
            unset($filtered_modules[$filename]);
          }
        }

        module_load_include('inc', 'system', 'system.admin');
        uasort($filtered_modules, 'system_sort_modules_by_info_name');

        // Used when displaying modules that are required by the installation profile.
        require_once DRUPAL_ROOT . '/includes/install.inc';
        $distribution_name = check_plain(drupal_install_profile_distribution_name());

        // Build packages.
        $packages = array();
        foreach ($filtered_modules as $filename => $module) {
            $required = !empty($module->info['required']);
            $status = $module->status;
            $explanation = !empty($module->info['explanation']) ? ' ('. $module->info['explanation'] .')' : '';

            $packages[$module->info['package']][$filename] = array(
                'name' => '<strong>' . $module->info['name'] . '</strong>',
                'version' => $module->info['version'],
                'description' => t($module->info['description']) .
                  $this->getModuleStatusLong($module->info) .
                  $this->getRequires($module->requires, $required, $explanation, $distribution_name, $all_modules, $visible_modules) .
                  $this->getRequiredBy($module->required_by, $status, $all_modules, $visible_modules),
            );
        }

        foreach ($packages as $package => $modules) {
            $count = count($modules);
            $processed_package = $this->processPackage($modules);

            $build[] = array(
              '#theme' => 'fieldset',
              '#title' => $package,
              '#description' => theme('item_list', array('items' => array(
                "Number of <strong>visible</strong>, <strong>enabled</strong> modules: {$count}",
              ))),
              '#value' => render($processed_package),
              '#collapsible' => TRUE,
              '#attributes' => array('class' => array('collapsible')),
            );
        }

        return $build;
    }

    /**
     * Get module status if there are any errors.
     *
     * @param type $info
     *   Module info.
     * @return string
     */
    private function getModuleStatusLong($info) {

        // Check the compatibilities.
        $compatible = TRUE;
        $status_long = '';

        // Check the core compatibility.
        if (!isset($info['core']) || $info['core'] != DRUPAL_CORE_COMPATIBILITY) {
            $compatible = FALSE;
            $status_long .= t('This version is not compatible with Drupal !core_version and should be replaced.', array('!core_version' => DRUPAL_CORE_COMPATIBILITY));
        }

        // Ensure this module is compatible with the currently installed version of PHP.
        if (version_compare(phpversion(), $info['php']) < 0) {
            $compatible = FALSE;
            $php_required = $info['php'];
            if (substr_count($info['php'], '.') < 2) {
              $php_required .= '.*';
            }
            $status_long .= t('This module requires PHP version @php_required and is incompatible with PHP version !php_version.', array('@php_required' => $php_required, '!php_version' => phpversion()));
        }

        return $status_long;
    }

    /**
     * Show markup of modules requires.
     * @see system_modules().
     *
     * @param type $modules_requires
     * @param type $required
     * @param type $explanation
     * @param type $distribution_name
     * @param type $all_modules
     * @param type $visible_modules
     * @return string Markup
     */
    private function getRequires($modules_requires, $required, $explanation, $distribution_name, $all_modules, $visible_modules) {
        $list = array();

        if ($required) {
            $list[] = $distribution_name . $explanation;
        }

        // If this module requires other modules, add them to the array.
        foreach ($modules_requires as $requires => $v) {
            if (!isset($all_modules[$requires])) {
                $list[$requires] = t('@module (<span class="admin-missing">missing</span>)', array('@module' => drupal_ucfirst($requires)));
            }
            // We show all (visible) modules requires, include enabled or disabled.
            elseif (isset($visible_modules[$requires])) {
                $requires_name = $all_modules[$requires]->info['name'];
                // Disable this module if it is incompatible with the dependency's version.
                if ($incompatible_version = drupal_check_incompatibility($v, str_replace(DRUPAL_CORE_COMPATIBILITY . '-', '', $all_modules[$requires]->info['version']))) {
                    $list[$requires] = t('@module (<span class="admin-missing">incompatible with</span> version @version)', array(
                      '@module' => $requires_name . $incompatible_version,
                      '@version' => $all_modules[$requires]->info['version'],
                    ));
                }
                // Disable this module if the dependency is incompatible with this
                // version of Drupal core.
                elseif ($all_modules[$requires]->info['core'] != DRUPAL_CORE_COMPATIBILITY) {
                    $list[$requires] = t('@module (<span class="admin-missing">incompatible with</span> this version of Drupal core)', array(
                      '@module' => $requires_name,
                    ));
                }
                elseif ($all_modules[$requires]->status) {
                    $list[$requires] = t('@module (<span class="admin-enabled">enabled</span>)', array('@module' => $requires_name));
                }
                else {
                    $list[$requires] = t('@module (<span class="admin-disabled">disabled</span>)', array('@module' => $requires_name));
                }
            }
        }

        return '<div class="admin-requirements">' . t('Requires: !module-list', array('!module-list' => implode(', ', $list))) . '</div>';
    }

    /**
     * Show markup of modules require by.
     * @see system_modules().
     *
     * @param type $modules_required_by
     * @param type $status
     * @param type $all_modules
     * @param type $visible_modules
     * @return string Markup
     */
    private function getRequiredBy($modules_required_by, $status, $all_modules, $visible_modules) {
        $list = array();

        // If this module is required by other modules, list those, and then make it
        // impossible to disable this one.
        foreach ($modules_required_by as $required_by => $v) {
            // Hidden modules are unset already.
            // We show all (visible) modules required by, include enabled or disabled.
            if (isset($visible_modules[$required_by])) {
                if ($all_modules[$required_by]->status == 1 && $status == 1) {
                    $list[] = t('@module (<span class="admin-enabled">enabled</span>)', array('@module' => $all_modules[$required_by]->info['name']));
                }
                else {
                    $list[] = t('@module (<span class="admin-disabled">disabled</span>)', array('@module' => $all_modules[$required_by]->info['name']));
                }
            }
        }

        return '<div class="admin-requirements">' . t('Required by: !module-list', array('!module-list' => implode(', ', $list))) . '</div>';
    }

    /**
     * Show table for each package.
     *
     * @param type $modules
     * @return array
     */
    private function processPackage($modules)
    {

        return array(
          '#theme' => 'table',
          '#header' => array(
            array('data' => t('Name'), 'width' => '120'),
            array('data' => t('Version'), 'width' => '50'),
            array('data' => t('Description')),
          ),
          '#rows' => $modules,
        );
    }

}
