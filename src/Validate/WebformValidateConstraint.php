<?php

namespace Drupal\webform_validation_unique\Validate;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Utility\WebformElementHelper;
use Drupal\webform\Utility\WebformArrayHelper;

/**
 * Form API callback. Validate element value.
 */
class WebformValidateConstraint {

  /**
   * Validates Backend fields.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state.
   */
  public static function validateBackendComponents(array $form, FormStateInterface &$formState): void {
    $valid = FALSE;
    $values = $formState->cleanValues()->getValues();
    if ($values['properties']['unique_field_values'] ?? FALSE) {
      $uniqueComponents = array_filter($values['properties']['unique_field_value_components']);
      if (empty($uniqueComponents)) {
        $formState->setErrorByName('unique_field_values_components', 'Please select at least 1 Unique components.');
      }
    }
  }

  /**
   * Validates form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state.
   */
  public static function validate(array &$form, FormStateInterface $formState): void {
    self::validateElements($form['elements'], $form, $formState);
  }

  /**
   * Validates element.
   *
   * @param array $elements
   *   The form elements.
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state.
   */
  private static function validateElements(array $elements, array &$form, FormStateInterface $formState): void {
    foreach ($elements as $keyElement => &$keyValue) {
      if (!WebformElementHelper::isElement($keyValue, $keyElement)) {
        continue;
      }
      if (!empty($keyValue['#unique_field_values'])) {
        self::validateFrontUniqueComponent($keyValue, $formState, $form);
      }
      self::validateElements($keyValue, $form, $formState);
    }
  }

  /**
   * Validates unique components on front end.
   *
   * @param array $element
   *   The form element to process.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state.
   * @param array $form
   *   The form array.
   */
  public static function validateFrontUniqueComponent(array &$element, FormStateInterface $formState, array &$form): void {
    $webformKey = $element['#webform_key'];
    $uniqueComponents = $element['#unique_field_value_components'];
    $ignoreBlank = (bool) $element['#unique_field_values_ignore_blank'];
    $comparedValues[$webformKey] = $formState->getValue($webformKey);
    $submittedValues = $formState->cleanValues()->getValues();
    // Create an array of the submitted values on all form elements we need to compare.
    foreach ($uniqueComponents as $key => $value) {
      if ($value) {
        if (!$ignoreBlank || $submittedValues[$key]) {
          $comparedValues[$key] = $submittedValues[$key];
        }
      }
    }
    // Find duplicates.
    $valueCounts = array_count_values($comparedValues);
    $duplicates = array_filter($comparedValues, function ($value) use ($valueCounts) {
      return $valueCounts[$value] > 1;
    });
    // Set error messages if needed.
    foreach ($duplicates as $duplicateElementKey => $duplicateValue) {
      // Get the key of the first element with a duplicated value (by diffing the original comparedValues with a deduplicated copy).
      $errorElement = self::recursiveFind($form['elements'], $duplicateElementKey);
      if (isset($errorElement['#title'])) {
        $tArgs = ['%name' => empty($errorElement['#title']) ? $errorElement['#parents'][0] : $errorElement['#title']];
        $formState->setError($errorElement, t('%name is not a unique value', $tArgs));
      }
      else {
        $formState->setError($errorElement);
      }
    }
  }

  private static function recursiveFind(array $haystack, $needle)  {
    $iterator  = new \RecursiveArrayIterator($haystack);
    $recursive = new \RecursiveIteratorIterator(
      $iterator,
      \RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($recursive as $key => $value) {
      if (!is_array($value) || !isset($value['#title'])) {
        continue;
      }
      if ($key === $needle) {
        return $value;
      }
    }
  }
}
