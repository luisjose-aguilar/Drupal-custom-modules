<?php

namespace Drupal\taxonomy_importer\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Field\Entity\BaseFieldOverride;

class TaxonomyImportForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'taxonomy_import_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Get all available vocabularies.
    $vocabularies = Vocabulary::loadMultiple();
    $vocabulary_options = [];
    foreach ($vocabularies as $vocabulary) {
      $vocabulary_options[$vocabulary->id()] = $vocabulary->label();
    }

    // Vocabulary dropdown.
    $form['vocabulary'] = [
      '#type' => 'select',
      '#title' => $this->t('Select Vocabulary'),
      '#options' => $vocabulary_options,
      '#ajax' => [
        'callback' => '::updateFieldsCallback',
        'wrapper' => 'fields-wrapper',
      ],
      '#required' => TRUE,
    ];

    // Placeholder for fields that will be loaded dynamically.
    $form['fields_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => t('Custom Fields'),
      '#attributes' => ['id' => 'fields-wrapper'],
      '#states' => [
        // Show this fieldset only when the dropdown is NOT empty (not "- Select -").
        'visible' => [
            ':input[name="vocabulary"]' => ['!value' => ''],
        ],
      ],
    ];

    if ($vocabulary_id = $form_state->getValue('vocabulary')) {
      $this->populateVocabularyFields($form, $form_state, $vocabulary_id);
    }

    $form['csv_file'] = [
      '#type' => 'file',
      '#title' => $this->t('CSV file'),
      '#description' => $this->t('Upload a CSV file with taxonomy terms and translations. By default Column 0 = Term Name (example = A50), Column 1 = LangCode (example = es), Column 2 = Translation Name (example = A51 Spanish). After that you need to include the custom fields.'),
      '#required' => TRUE,
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import'),
    ];

    return $form;
  }

  /**
   * Ajax callback to update fields dynamically.
   */
  public function updateFieldsCallback(array &$form, FormStateInterface $form_state) {
    return $form['fields_wrapper'];
  }

  /**
   * Populate vocabulary fields dynamically.
   */
  private function populateVocabularyFields(array &$form, FormStateInterface $form_state, $vocabulary_id) {
    // Get the fields for the selected vocabulary.
    $field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('taxonomy_term', $vocabulary_id);

    // Iterate over the fields and add them to the form.
    $form['fields_wrapper']['fields'] = [
      '#type' => 'table',
      '#header' => [$this->t('Field Name'), $this->t('CSV Column')],
    ];

    foreach ($field_definitions as $field_name => $field_definition) {
      // Ignore base fields like 'vid', 'name', 'description'=, also 'parent' field is ignored for now and all the base overrides fields that we add.
      if ($field_definition->getTargetBundle() === $vocabulary_id && !$field_definition->isReadOnly() && $field_name !== 'parent' && !$field_definition instanceof BaseFieldOverride) {
        $form['fields_wrapper']['fields'][$field_name]['field_name'] = [
          '#markup' => $field_definition->getLabel(),
        ];

        // Input for CSV column mapping.
        $form['fields_wrapper']['fields'][$field_name]['csv_column'] = [
          '#type' => 'textfield',
          '#title' => $field_definition->getLabel(),
          '#title_display' => 'invisible',
        ];
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get selected vocabulary.
    $selected_vocabulary = $form_state->getValue('vocabulary');

    // Get CSV field mappings.
    $field_mappings = $form_state->getValue('fields');

    // Handle the CSV file upload and import.
    $files = file_save_upload('csv_file', [
      'file_validate_extensions' => ['csv'],
    ]);

    // Check if $files is an array or a single file object.
    if (is_array($files)) {
        $file = reset($files);
    } else {
        $file = $files;
    }

    if ($file) {
      $this->importTaxonomyTerms($file->getFileUri(), $selected_vocabulary, $field_mappings);
      \Drupal::messenger()->addStatus($this->t('The import process is complete.'));
    }
  }

  /**
   * Function to handle CSV parsing and taxonomy term creation.
   */
  private function importTaxonomyTerms($file_path, $vocabulary, $field_mappings) {
    if (($handle = fopen($file_path, 'r')) !== FALSE) {
      while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        if (!empty(array_filter($data, fn($value) => trim($value) !== ''))) {
          $term_name = $data[0]; // Assume first column is always the term name.
          $language_code = $data[1]; // Assume second column is the language code.
          $translation = $data[2]; // Assume third column is the translation.

          // Detect encoding and convert only if necessary
          if(!empty($translation)){
            $encoding = mb_detect_encoding($translation, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
            if ($encoding && $encoding != 'UTF-8') {
              $translation = mb_convert_encoding($translation, 'UTF-8', $encoding);
            }
          }

          $custom_values = [];

          // Map the custom fields dynamically.
          foreach ($field_mappings as $field_name => $csv_column) {
            $column_number = reset($csv_column);
            if(!empty($column_number)){
              $count = intval($column_number);  
              $custom_values[$field_name] = $data[$count];
            }
          }

          // Create or update the term.
          $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['name' => $term_name, 'vid' => $vocabulary]);

          if (!$term) {
            $term = \Drupal\taxonomy\Entity\Term::create([
              'vid' => $vocabulary,
              'name' => $term_name,
            ]);
          } else {
            $term = reset($term);
          }

          $field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('taxonomy_term', $term->bundle());

          // Set custom field values.
          foreach ($custom_values as $field_name => $value) {
            if (isset($field_definitions[$field_name]) && $field_definitions[$field_name]->getType() === 'boolean') {
              // Convert the string 'TRUE'/'FALSE' to a boolean value.
              $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }
            $term->set($field_name, $value);
          }
          try {
            $term->save();
          } catch (\Exception $e) {
            \Drupal::logger('csv_import')->error("Error in term->save(): {$language_code} on term_name {$term_name}. ".$e->getMessage());
          }

          // Handle translations.
          if ($language_code != 'en' && !empty($translation)) {
            if (!$term->hasTranslation($language_code)) {
              try {
                $translation_value = htmlspecialchars($translation, ENT_QUOTES, 'UTF-8');
                $translated_term = $term->addTranslation($language_code, ['name' => $translation_value]);
              } catch (\Exception $e) {
                \Drupal::logger('csv_import')->error("Invalid language code: {$language_code} on term_name {$term_name}. ".$e->getMessage());
              }
              foreach ($custom_values as $field_name => $value) {
                $translated_term->set($field_name, $value);
              }
              try {
                $translated_term->save();
              } catch (\Exception $e) {
                \Drupal::logger('csv_import')->error("Error in translated_term->save() create translation: {$language_code} on term_name {$term_name}. ".$e->getMessage());
              }
            }
            else{
              $translation_value = htmlspecialchars($translation, ENT_QUOTES, 'UTF-8');
              $translated_term = $term->getTranslation($language_code);
              $translated_term->set('name', $translation_value);
              foreach ($custom_values as $field_name => $custom_value) {
                $translated_term->set($field_name, $custom_value);
              }
              try {
                $translated_term->save();
              } catch (\Exception $e) {
                \Drupal::logger('csv_import')->error("Error in translated_term->save() edit translation: {$language_code} on term_name {$term_name}. ".$e->getMessage());
              }
            }
          }
        }
        else{
          break;
        } 
      }
      fclose($handle);
    }
  }
}
