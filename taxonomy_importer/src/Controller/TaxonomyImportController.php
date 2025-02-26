<?php

namespace Drupal\taxonomy_importer\Controller;

use Drupal\Core\Controller\ControllerBase;

class TaxonomyImportController extends ControllerBase {

  public function content() {
    $form = \Drupal::formBuilder()->getForm('Drupal\taxonomy_importer\Form\TaxonomyImportForm');
    return $form;
  }
}
