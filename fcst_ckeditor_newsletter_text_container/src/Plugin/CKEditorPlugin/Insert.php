<?php

namespace Drupal\ckeditor_newsletter_text_container\Plugin\CKEditorPlugin;

use Drupal\ckeditor\CKEditorPluginBase;
use Drupal\editor\Entity\Editor;

/**
 * Defines the "Newsletter Text Container" plugin.
 *
 * @CKEditorPlugin(
 *   id = "newsletter_text_container",
 *   label = @Translation("Newsletter Text Container"),
 *   module = "ckeditor_newsletter_text_container"
 * )
 */
class Insert extends CKEditorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getFile() {
    return 'libraries/newsletter_text_container/plugin.js';

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
      'newsletter_text_container' => [
        'label' => t('Newsletter Text Container'),
        'image' => 'libraries/newsletter_text_container/icons/newsletter_text_container.png',
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
