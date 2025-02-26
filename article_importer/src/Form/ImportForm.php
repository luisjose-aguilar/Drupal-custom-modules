<?php

namespace Drupal\article_importer\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImportForm extends FormBase {

  public function getFormId() {
    return 'article_importer_import_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = \Drupal::config('article_importer.field_mapping');
    $fields = \Drupal::service('article_importer.field_helper')->getArticleFields();

    // CSV File Upload
    $form['csv_file'] = [
      '#type' => 'file',
      '#title' => $this->t('CSV File'),
      '#description' => $this->t('Upload the CSV file for importing articles.'),
    ];

    // Field Mapping Table
    $form['field_mapping'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Field Name'),
        $this->t('CSV Column Number'),
        $this->t('Default Value'),
      ],
      '#empty' => $this->t('No fields available.'),
    ];

    foreach ($fields as $field_name => $field_label) {
      $form['field_mapping'][$field_name]['field_label'] = [
        '#markup' => $this->t('@label (@field)', ['@label' => $field_label, '@field' => $field_name]),
      ];

      $form['field_mapping'][$field_name]['csv_column'] = [
        '#type' => 'number',
        '#default_value' => $config->get("field_mapping.$field_name.csv_column") ?? '',
        '#size' => 5,
      ];

      $form['field_mapping'][$field_name]['default_value'] = [
        '#type' => 'textfield',
        '#default_value' => $config->get("field_mapping.$field_name.default_value") ?? '',
        '#size' => 20,
      ];
    }

    // Submit Button
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import Articles'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Save field mapping configuration dynamically.
    $field_mapping = $form_state->getValue('field_mapping');
    $config = \Drupal::service('config.factory')->getEditable('article_importer.field_mapping');
    $config->set('field_mapping', $field_mapping)->save();
    
    // Handle CSV File Upload.
    $validators = ['file_validate_extensions' => ['csv']];
    $file = file_save_upload('csv_file', $validators, FALSE, 0);
  
    if (!$file) {
      $this->messenger()->addError($this->t('Please upload a valid CSV file.'));
      return;
    }

    $file_path = $file->getFileUri();
    $non_empty_row_count = 0;

    if (($handle = fopen(\Drupal::service('file_system')->realpath($file_path), 'r')) !== FALSE) {
        while (($row = fgetcsv($handle)) !== FALSE) {
            if (array_filter($row)) {
                $non_empty_row_count++;
            }
        }
        fclose($handle);
    }
  
    // Move the file to the public directory and make it permanent.
    $file->setPermanent();
    $file->save();
  
    // Process the uploaded CSV.
    \Drupal::service('article_importer.csv_processor')->processCsv($file_path);
  
    // Notify the user.
    $this->messenger()->addStatus($this->t('@count articles have been imported successfully.', ['@count' => $non_empty_row_count-1]));
  }
}