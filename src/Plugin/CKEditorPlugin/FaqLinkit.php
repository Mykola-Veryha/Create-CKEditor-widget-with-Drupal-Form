<?php

namespace Drupal\ccom_faq\Plugin\CKEditorPlugin;

use Drupal\editor\Entity\Editor;
use Drupal\linkit\Plugin\CKEditorPlugin\Linkit;

/**
 * Defines the "linkit" plugin.
 *
 * @CKEditorPlugin(
 *   id = "ccom_faq_linkit",
 *   label = @Translation("FAQ Linkit"),
 *   module = "ccom_faq"
 * )
 */
class FaqLinkit extends Linkit {

  /**
   * {@inheritdoc}
   */
  public function getFile() {
    $plugin_path = '/js/plugins/faq_linkit/plugin.js';

    return drupal_get_path('module', 'ccom_faq') . $plugin_path;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(Editor $editor) {
    return [
      'linkit_dialogTitleAdd' => t('Add FAQ link'),
      'linkit_dialogTitleEdit' => t('Edit FAQ link'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getButtons() {
    $icon_path = '/js/plugins/faq_linkit/linkit.png';

    return [
      'FaqLinkit' => [
        'label' => t('FAQ Linkit'),
        'image' => drupal_get_path('module', 'ccom_faq') . $icon_path,
      ],
    ];
  }

}
