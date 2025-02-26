<?php

namespace Drupal\article_importer\Service;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;

class ArticleFieldHelper {
  protected $entityFieldManager;

  public function __construct(EntityFieldManagerInterface $entityFieldManager) {
    $this->entityFieldManager = $entityFieldManager;
  }

/**
 * Get all fields for the 'article' content type, sorted by weight in Manage Form Display.
 */
  public function getArticleFields() {
    // Get all field definitions for the 'article' content type.
    $fields = $this->entityFieldManager->getFieldDefinitions('node', 'article');

    // Load the Manage Form Display configuration.
    $form_display = \Drupal::entityTypeManager()
      ->getStorage('entity_form_display')
      ->load('node.article.default');

    // Get components (fields) from Manage Form Display.
    $enabled_fields = $form_display ? $form_display->getComponents() : [];

    $filtered_fields = [];

    foreach ($fields as $field_name => $field_definition) {
      // Exclude fields starting with "field_oak".
      if (strpos($field_name, 'field_oak') === 0) {
        continue;
      }

      // Include the UUID field explicitly.
      if ($field_name === 'uuid') {
        $filtered_fields[$field_name] = [
          'label' => t('UUID'),
          'weight' => -100, // Assign a high priority to UUID.
        ];
        continue;
      }

      // Include only fields enabled in Manage Form Display and that are not computed or read-only.
      if (isset($enabled_fields[$field_name]) && !$field_definition->isComputed() && !$field_definition->isReadOnly()) {
        $filtered_fields[$field_name] = [
          'label' => $field_definition->getLabel(),
          'weight' => $enabled_fields[$field_name]['weight'] ?? 0,
        ];
      }
    }

    // Sort fields by weight.
    uasort($filtered_fields, function ($a, $b) {
      return $a['weight'] <=> $b['weight'];
    });

    // Return only the sorted field labels.
    return array_map(fn($field) => $field['label'], $filtered_fields);
  }
}
