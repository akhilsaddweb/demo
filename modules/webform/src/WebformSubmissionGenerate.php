<?php

namespace Drupal\webform;

use Drupal\Core\Form\OptGroup;
use Drupal\Core\Serialization\Yaml;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Webform submission generator.
 *
 * @see \Drupal\webform\WebformSubmissionGenerateInterface
 * @see \Drupal\webform\Plugin\DevelGenerate\WebformSubmissionDevelGenerate
 */
class WebformSubmissionGenerate implements WebformSubmissionGenerateInterface {

  /**
   * The configuration object factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The webform token manager.
   *
   * @var \Drupal\webform\WebformTokenManager
   */
  protected $tokenManager;

  /**
   * The webform element manager.
   *
   * @var \Drupal\webform\WebformElementManagerInterface
   */
  protected $elementManager;

  /**
   * An associative array containing test values for elements by type.
   *
   * @var array
   */
  protected $types;

  /**
   * An associative array containing test values for elements by name.
   *
   * @var array
   */
  protected $names;

  /**
   * Constructs a WebformSubmissionGenerate object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration object factory.
   * @param \Drupal\webform\WebformTokenManager $token_manager
   *   The webform token manager.
   * @param \Drupal\webform\WebformElementManagerInterface $element_manager
   *   The webform element manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, WebformTokenManager $token_manager, WebformElementManagerInterface $element_manager) {
    $this->configFactory = $config_factory;
    $this->tokenManager = $token_manager;
    $this->elementManager = $element_manager;

    $this->types = Yaml::decode($this->configFactory->get('webform.settings')->get('test.types') ?: '');
    $this->names = Yaml::decode($this->configFactory->get('webform.settings')->get('test.names') ?: '');
  }

  /**
   * {@inheritdoc}
   */
  public function getData(WebformInterface $webform) {
    $elements = $webform->getElementsInitializedAndFlattened();

    $data = [];
    foreach ($elements as $key => $element) {
      $value = $this->getTestValue($webform, $key, $element);
      if ($value !== NULL) {
        $data[$key] = $value;
      }
    }
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function getTestValue(WebformInterface $webform, $name, array $element, array $options = []) {
    // Set default options.
    $options += [
      // Return random test value(s).
      'random' => TRUE,
    ];

    /** @var \Drupal\webform\WebformElementInterface $element_handler */
    $plugin_id = $this->elementManager->getElementPluginId($element);
    $element_handler = $this->elementManager->createInstance($plugin_id);

    // Exit if element does not have a value.
    if (!$element_handler->isInput($element)) {
      return NULL;
    }

    // Exit if test values are null or an empty array.
    $values = $this->getTestValues($webform, $name, $element, $options);
    if ($values === NULL || (is_array($values) && empty($values))) {
      return NULL;
    }
    // Make sure value is an array.
    if (!is_array($values)) {
      $values = [$values];
    }

    // $values = $this->tokenManager->replace($values, $webform);.
    // Elements that use multiple values require an array as the
    // default value.
    if ($element_handler->hasMultipleValues($element)) {
      if ($options['random']) {
        shuffle($values);
      }
      return array_slice($values, 0, 3);
    }
    else {
      return ($options['random']) ? $values[array_rand($values)] : reset($values);
    }
  }

  /**
   * Get test values from a webform element.
   *
   * @param \Drupal\webform\WebformInterface $webform
   *   A webform.
   * @param string $name
   *   The name of the element.
   * @param array $element
   *   The FAPI element.
   * @param array $options
   *   (options) Options for generated value.
   *
   * @return array|int|null
   *   An array containing multiple test values or a single test value.
   */
  protected function getTestValues(WebformInterface $webform, $name, array $element, array $options = []) {
    // Get test value from the actual element.
    if (isset($element['#test'])) {
      return $element['#test'];
    }

    // Never populate hidden and value elements.
    if (in_array($element['#type'], ['hidden', 'value'])) {
      return NULL;
    }

    // Invoke WebformElement::test and get a test value.
    // If test value is NULL this element should never be populated with
    // test data.
    // @see \Drupal\webform\Plugin\WebformElement\ContainerBase::getTestValues().
    $test_values = $this->elementManager->invokeMethod('getTestValues', $element, $webform, $options);
    if ($test_values) {
      return $test_values;
    }
    elseif ($test_values === NULL) {
      return NULL;
    }

    // Get test values from options.
    if (isset($element['#options'])) {
      return array_keys(OptGroup::flattenOptions($element['#options']));
    }

    // Get test values using #type.
    if (isset($this->types[$element['#type']])) {
      return $this->types[$element['#type']];
    }

    // Get test values using on exact name matches.
    if (isset($this->types[$name])) {
      return $this->types[$name];
    }

    // Get test values using partial name matches.
    foreach ($this->names as $key => $values) {
      if (preg_match('/(^|_)' . $key . '(_|$)/i', $name)) {
        return $values;
      }
    }

    // Get test #unique value.
    if (!empty($element['#unique'])) {
      return uniqid();
    }

    // Return default values.
    return (isset($this->names['default'])) ? $this->names['default'] : NULL;
  }

}
