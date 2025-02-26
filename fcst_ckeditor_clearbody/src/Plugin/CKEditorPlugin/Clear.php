<?php

namespace Drupal\ckeditor_clearbody\Plugin\CKEditorPlugin;

use Drupal\ckeditor\CKEditorPluginBase;
use Drupal\editor\Entity\Editor;

/**
 * Defines the "Clear Body" plugin.
 *
 * @CKEditorPlugin(
 *   id = "clearbody",
 *   label = @Translation("Clear Body"),
 *   module = "ckeditor_clearbody"
 * )
 */
class Clear extends CKEditorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getFile() {
    //if ($library_path = libraries_get_path('clearbody')) {
    //  return $library_path . '/plugin.js';
    //}

    return 'libraries/clearbody/plugin.js';

  }

  /**
   * {@inheritdoc}
   */
  public function getDependencies(Editor $editor) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getLibraries(Editor $editor) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function isInternal() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getButtons() {
    return [
      'clearbody' => [
        'label' => t('Clear Body'),
        'image' => 'libraries/clearbody/icons/clearbody.png',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled(Editor $editor) {
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(Editor $editor) {
    return [];
  }

}
