<?php

namespace Drupal\ccom_faq\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\faqfield\Plugin\Field\FieldFormatter\FaqFieldSimpleTextFormatter;

/**
 * Plugin implementation of the 'faqfield_ccom_answer_text' formatter.
 *
 * @FieldFormatter(
 *   id = "faqfield_ccom_answer_text",
 *   label = @Translation("Ccom Answer text"),
 *   field_types = {
 *     "faqfield"
 *   }
 * )
 */
class CcomFaqAnswerFormatter extends FaqFieldSimpleTextFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $default_format = $this->getFieldSetting('default_format');

    $elements = array();
    foreach ($items as $delta => $item) {
      // Decide whether to use the default format or the custom one.
      $format = (!empty($item->answer_format) ? $item->answer_format : $default_format);

      // Add each Q&A as page element, to be rendered by the
      // faqfield_simple_text_formatter template.
      $elements[$delta] = array(
        '#theme' => 'faqfield_ccom_answer_text',
        '#question' => $item->question,
        '#answer' => $item->answer,
        '#answer_format' => $format,
        '#delta' => $delta,
      );
    }

    return $elements;
  }

}
