<?php

namespace Drupal\at_doc\Report;

class Taxonomy extends BaseReport
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

        $vocabularies = taxonomy_get_vocabularies();

        // Get map of all taxonomy term reference fields.
        $field_map = array();
        foreach (field_info_field_map() as $field_name => $bundles_info) {

            if ($bundles_info['type'] == 'taxonomy_term_reference') {
                $field_info = field_info_field($field_name);
                if (empty($field_info['settings']['allowed_values'][0]['vocabulary'])) {
                    continue;
                }
                $vocabulary = $field_info['settings']['allowed_values'][0]['vocabulary'];

                foreach ($bundles_info['bundles'] as $entity_type => $bundles) {
                    foreach ($bundles as $bundle) {
                        $field_map[$vocabulary][$entity_type][$bundle][] = $field_name;
                    }
                }
            }
        }

        foreach ($vocabularies as $vocabulary) {
            $processed_vocabulary = $this->processEntityType($field_map, $vocabulary->machine_name);

            $build[] = array(
              '#theme' => 'fieldset',
              '#title' => check_plain($vocabulary->name),
              '#description' => theme('item_list', array('items' => array(
                "<strong>Machine name</strong>: {$vocabulary->machine_name}",
                "<strong>Description</strong>: {$vocabulary->description}",
                "<strong>Structure</strong>: " . $this->getStructure($vocabulary),
              ))),
              '#value' => render($processed_vocabulary),
              '#collapsible' => TRUE,
              '#attributes' => array('class' => array('collapsible')),
            );
        }

        return $build;
    }

    /**
     * Get structure of all vocabulary's terms.
     *
     * @param \stdClass $vocabulary
     * @return string
     */
    private function getStructure($vocabulary) {

        $tree = taxonomy_get_tree($vocabulary->vid);

        if (empty($tree)) {
            return t('No terms available.');
        }

        $structure = '<pre class="taxonomy-structure"><code>';
        foreach ($tree as $item) {
            $structure .= '<span>' . str_repeat('-', $item->depth) . $item->name . '</span>';
        }
        $structure .= '</code></pre>';

        return $structure;
    }

    /**
     * Show all entity types and its taxonomy term reference fields.
     *
     * @param type $field_map
     * @param type $vocabulary
     * @return array
     */
    private function processEntityType($field_map, $vocabulary)
    {
        $rows = array();

        foreach ($field_map[$vocabulary] as $entity_type => $bundles) {
            $entity_info = entity_get_info($entity_type);
            $name = $entity_info['label'] . ' (' . $entity_type . ')';

            $bundle_processed = $this->processBundle($field_map, $vocabulary, $entity_type, $entity_info);
            $rows[] = array(
              $name,
              drupal_render($bundle_processed)
            );
        }

        return array(
          '#theme' => 'table',
          '#header' => array(
            array('data' => t('Entity Type'), 'width' => '120'),
            array('data' => t('Bundles')),
          ),
          '#rows' => $rows,
        );
    }

    /**
     * Show all bundles and its taxonomy term reference fields.
     *
     * @param type $field_map
     * @param type $vocabulary
     * @param type $entity_type
     * @param type $entity_info
     * @return array
     */
    private function processBundle($field_map, $vocabulary, $entity_type, $entity_info) {
        $rows = array();

        foreach ($field_map[$vocabulary][$entity_type] as $bundle => $fields) {
            $name = $entity_info['bundles'][$bundle]['label'] . ' (' . $bundle . ')';

            $fields_processed = $this->processFields($field_map, $vocabulary, $entity_type, $bundle);
            $rows[] = array(
              $name,
              drupal_render($fields_processed)
            );
        }

        return array(
          '#theme' => 'table',
          '#header' => array(
            array('data' => t('Bundle'), 'width' => '120'),
            array('data' => t('Fields')),
          ),
          '#rows' => $rows,
        );
    }

    /**
     * Show all taxonomy term reference fields.
     *
     * @param type $field_map
     * @param type $vocabulary
     * @param type $entity_type
     * @param type $bundle
     * @return array
     */
    private function processFields($field_map, $vocabulary, $entity_type, $bundle) {
        $rows = array();

        foreach ($field_map[$vocabulary][$entity_type][$bundle] as $field_name) {
            $field_instance = field_info_instance($entity_type, $field_name, $bundle);

            $rows[] = array(
              "{$field_instance['label']}" . (!empty($field_instance['required']) ? '<span class="form-required">*</span>' : '') . " ({$field_name})",
              $field_instance['widget']['type'],
              !empty($field_instance['description']) ? $field_instance['description'] : ($this->iconError() . '<em> Missing</em>'),
            );
        }

        return array(
          '#theme' => 'table',
          '#header' => array(
            'Field',
            array('data' => t('Widget'),      'width' => '150'),
            array('data' => t('Description'), 'width' => '200')
          ),
          '#rows' => $rows
        );
    }

}
