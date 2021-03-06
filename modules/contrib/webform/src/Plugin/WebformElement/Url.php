<?php

namespace Drupal\webform\Plugin\WebformElement;

/**
 * Provides a 'url' element.
 *
 * @WebformElement(
 *   id = "url",
 *   api = "https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Render!Element!Url.php/class/Url",
 *   label = @Translation("URL"),
 *   description = @Translation("Provides a form element for input of a URL."),
 *   category = @Translation("Advanced elements"),
 * )
 */
class Url extends TextBase {

  /**
   * {@inheritdoc}
   */
  public function formatHtml(array &$element, $value, array $options = []) {
    if (empty($value)) {
      return '';
    }

    $format = $this->getFormat($element);
    switch ($format) {
      case 'link':
        return [
          '#type' => 'link',
          '#title' => $value,
          '#url' => \Drupal::pathValidator()->getUrlIfValid($value),
        ];

      default:
        return parent::formatHtml($element, $value, $options);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultFormat() {
    return 'link';
  }

  /**
   * {@inheritdoc}
   */
  public function getFormats() {
    return parent::getFormats() + [
      'link' => $this->t('Link'),
    ];
  }

}
