<?php

namespace Drupal\article_importer\Plugin;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use DateTime;

class CsvProcessor {
  protected $configFactory;
  protected $entityTypeManager;

  public function __construct(ConfigFactoryInterface $configFactory, EntityTypeManagerInterface $entityTypeManager) {
    $this->configFactory = $configFactory;
    $this->entityTypeManager = $entityTypeManager;
  }

  public function processCsv($csv_path) {
    $field_mapping = $this->configFactory->get('article_importer.field_mapping')->get('field_mapping');
    $file = fopen($csv_path, 'r');
    $header = fgetcsv($file); // Assume the first row is a header row.

    while (($row = fgetcsv($file)) !== FALSE) {
      if (empty(array_filter($row))) {
        continue;
      }
      $node_data = [];

      foreach ($field_mapping as $field_name => $settings) {
        $csv_column = $settings['csv_column'];
        $default_value = $settings['default_value'];

        $entity_reference_fields = [
          'field_byline_author_',
          'field_article_tags',
          'field_article_retail_websites',
        ];
        $publish_date_fields = [
          'field_display_publish_date',
          'field_internal_publish_date',
          'field_subscriber_publish_date',
          'field_public_publish_date',
        ];

        // Handle specific field cases.
        if (in_array($field_name, $entity_reference_fields, true) && !empty($row[$csv_column])) {
          $guids = explode('|', strtolower(str_replace(['{', '}'], '', $row[$csv_column])));
          $node_data[$field_name] = $this->mapGuidsToEntityIds($guids, $field_name, $default_value);
        } elseif ($field_name === 'field_article_image' && !empty($row[$csv_column])) {
          if (preg_match('/\{([A-F0-9\-]+)\}/i', $row[$csv_column], $matches)) {
            $normalized_guid = strtolower(str_replace('-', '', $matches[1]));
            $node_data[$field_name] = $this->mapGuidToMediaEntity($normalized_guid);
          }
        } elseif ($field_name === 'uuid' && !empty($row[$csv_column])) {
          if (preg_match('/\{([A-F0-9\-]+)\}/i', $row[$csv_column], $matches)) {
            $node_data[$field_name] = strtolower($matches[1]);
          }
        } elseif (in_array($field_name, $publish_date_fields, true) && !empty($row[$csv_column])) {
          $date = DateTime::createFromFormat('m/d/Y h:i:s A', trim($row[$csv_column]));
          if ($date) {
              $row[$csv_column] = $date->format('Y-m-d\TH:i:s');
          }
          $node_data[$field_name] = isset($row[$csv_column]) && $row[$csv_column] !== '' ? $row[$csv_column] : $default_value;
        }
        else {
        $node_data[$field_name] = isset($row[$csv_column]) && $row[$csv_column] !== '' ? $row[$csv_column] : $default_value;
        }
      }

      $this->createNode($node_data);
    }
    \Drupal::logger('Article-Importer')->notice('All nodes were imported successfully');
    fclose($file);
  }

  /**
   * Map GUID values to Entity IDs.
   *
   * @param array $guids
   *   An array of GUID values from the CSV.
   *
   * @return array
   *   An array of entity IDs corresponding to the GUIDs.
   */
  protected function mapGuidsToEntityIds(array $guids, $field_name = null, $default_value = null) {
    $entity_ids = [];
    $storage = $this->entityTypeManager->getStorage('taxonomy_term'); 

    foreach ($guids as $guid) {
      if($field_name === 'field_byline_author_'){
        $entities = $storage->loadByProperties(['field_retail_author_guid' => trim($guid)]); 
        if ($entities) {
          $entity_id = reset($entities)->id();
          if (!in_array($entity_id, $entity_ids)) {
              $entity_ids[] = $entity_id;
          }
        }
      }
      if($field_name === 'field_article_tags'){
        $entities = $storage->loadByProperties(['field_retail_tag_guid' => trim($guid)]);
        if ($entities) {
          $entity_id = reset($entities)->id();
          if (!in_array($entity_id, $entity_ids)) {
              $entity_ids[] = $entity_id;
          }
        }
      }
      if($field_name === 'field_article_retail_websites'){
        $entities = $storage->loadByProperties(['field_website_guid' => trim($guid)]);
        if ($entities) {
          $entity_id = reset($entities)->id();
          if (!in_array($entity_id, $entity_ids)) {
              $entity_ids[] = $entity_id;
          }
        }
      }
    }
    if($field_name === 'field_byline_author_' && (count($entity_ids) > 1 || empty($entity_ids))){
      return $entity_ids[] = $default_value;
    }
    else{
      return $entity_ids;
    }
  }

   /**
  * Map a GUID to a media entity ID.
  *
  * @param string $guid
  *   The GUID from the CSV (e.g., "7F473D69-DD00-497E-8CE0-DFFB90612017").
  *
  * @return int|null
  *   The media entity ID if found, or NULL if no match is found.
  */
  protected function mapGuidToMediaEntity(string $guid): ?int {

    // Load media entities by a field containing the GUID.
    $storage = $this->entityTypeManager->getStorage('media');
    $media_entities = $storage->loadByProperties([
        'name' => $guid, // Assuming 'name' field contains the GUID.
    ]);

    // Return the first matching media entity's ID.
    if (!empty($media_entities)) {
        return reset($media_entities)->id();
    }

    return NULL;
  }

  protected function createNode(array $data) {
    // Initialize the node with required fields.
    $node = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'article',
    ]);

    $node->set('uid', \Drupal::currentUser()->id());
    $node->set('created', time());
    $node->set('changed', time());
    $node->set('revision_timestamp', time());

    // Set UUID from the CSV column if provided.
    if (!empty($data['uuid'])) {
      $node->uuid->value = $data['uuid']; // Manually set the UUID.
    }
  
    // Set other fields from the $data array.
    foreach ($data as $field_name => $value) {
      // Skip setting system fields again.
      if (in_array($field_name, ['uid','created', 'type','changed','revision_timestamp'])) {
        continue;
      }
  
      // Handle complex field types like entity reference or multi-value fields.
      if(!empty($value )){
        if($node->hasField($field_name)){
          if (is_array($value)) {
            $node->set($field_name, $value);
          } else {
            if($field_name === 'body'){
              $node->body->value = $value;
            }
            else{
              $node->set($field_name, trim($value));
            }
          }
        }
      }
    } 
  
    // Save the node.
    $node->save();

    // Retrieve the UUID of the node and set it in the `field_article_retail_parent_id` field.
    $uuid = $node->uuid();
    $node_id = $node->id();
    $node->set('field_article_retail_parent_id', $uuid);
    $node->set('field_article_retail_parent_node', $node_id);

    // Save the node again to persist the UUID in the field.
    $node->save();
  }
}