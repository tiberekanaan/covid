<?php

namespace Drupal\charts_chartjs\Plugin\chart\Library;

use Drupal\charts\Plugin\chart\Library\ChartBase;
use Drupal\Component\Utility\Color;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Url;

/**
 * Define a concrete class for a Chart.
 *
 * @Chart(
 *   id = "chartjs",
 *   name = @Translation("Chart.js")
 * )
 */
class Chartjs extends ChartBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['placeholder'] = [
      '#title' => $this->t('Placeholder'),
      '#type' => 'fieldset',
      '#description' => $this->t(
        'This is a placeholder for Chart.js-specific library options. If you would like to help build this out, please work from <a href="@issue_link">this issue</a>.', [
          '@issue_link' => Url::fromUri('https://www.drupal.org/project/charts/issues/3046984')
            ->toString(),
        ]),
    ];
    $xaxis_configuration = $this->configuration['xaxis'] ?? [];
    $form['xaxis'] = [
      '#title' => $this->t('X-Axis Settings'),
      '#type' => 'fieldset',
      '#tree' => TRUE,
    ];
    $form['xaxis']['autoskip'] = [
      '#title' => $this->t('Enable autoskip'),
      '#type' => 'checkbox',
      '#default_value' => $xaxis_configuration['autoskip'] ?? 1,
    ];

    return $form;
  }

  /**
   * Build configurations.
   *
   * @param array $form
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['xaxis'] = $values['xaxis'];
    }
  }

  /**
   * Builds gauge options.
   *
   * @param array $options
   *   The options.
   *
   * @return array
   *   The scale color ranges.
   */
  protected function buildGaugeOptions(array $options) {
    $scaleColorRanges = [];
    $scaleColorRanges[0] = new \stdClass();
    $scaleColorRanges[1] = new \stdClass();
    $scaleColorRanges[2] = new \stdClass();
    // Red.
    $scaleColorRanges[0]->start = isset($options['display']['gauge']['red_from']) ? $options['display']['gauge']['red_from'] : '';
    $scaleColorRanges[0]->end = isset($options['display']['gauge']['red_to']) ? $options['display']['gauge']['red_to'] : '';
    $scaleColorRanges[0]->color = '#ff000c';
    // Yellow.
    $scaleColorRanges[1]->start = isset($options['display']['gauge']['yellow_from']) ? $options['display']['gauge']['yellow_from'] : '';
    $scaleColorRanges[1]->end = isset($options['yellow_to']) ? $options['display']['gauge']['yellow_to'] : '';
    $scaleColorRanges[1]->color = '#ffff00';
    // Green.
    $scaleColorRanges[2]->start = isset($options['display']['gauge']['green_from']) ? $options['display']['gauge']['green_from'] : '';
    $scaleColorRanges[2]->end = isset($options['display']['gauge']['green_to']) ? $options['display']['gauge']['green_to'] : '';
    $scaleColorRanges[2]->color = '#008000';

    return $scaleColorRanges;
  }

  /**
   * {@inheritdoc}
   */
  public function preRender(array $element) {
    $chart_definition = [];

    if (!isset($element['#id'])) {
      $element['#id'] = Html::getUniqueId('chartjs-render');
    }

    $chart_definition = $this->populateCategories($element, $chart_definition);
    $chart_definition = $this->populateDatasets($element, $chart_definition);
    $chart_definition = $this->populateOptions($element, $chart_definition);

    $element['#attached']['library'][] = 'charts_chartjs/chartjs';
    $element['#attributes']['class'][] = 'charts-chartjs';
    $element['#chart_definition'] = $chart_definition;

    return $element;
  }

  /**
   * Populate options.
   *
   * @param array $element
   *   The element.
   * @param array $chart_definition
   *   The chart definition.
   *
   * @return array
   *   Return the chart definition.
   */
  private function populateOptions(array $element, array $chart_definition) {
    $chart_type = $this->populateChartType($element);
    $chart_definition['type'] = $chart_type;
    $xaxis_configuration = isset($this->configuration['xaxis']) ? $this->configuration['xaxis'] : [];

    if (!in_array($chart_type, ['pie', 'doughnut'])) {
      if (!empty($element['#stacking']) && $element['#stacking'] == 1) {
        $stacking = TRUE;
      }
      else {
        $stacking = FALSE;
      }
      if ($chart_type !== 'radar') {
        $chart_definition['options']['scales']['xAxes'][] = [
          'stacked' => $stacking,
          'ticks' => [
            'autoSkip' => isset($xaxis_configuration['autoskip']) ? $xaxis_configuration['autoskip'] : 1,
            'maxRotation' => $element['xaxis']['#labels_rotation'],
            'minRotation' => $element['xaxis']['#labels_rotation'],
          ]
        ];
        $chart_definition['options']['scales']['yAxes'][] = [
          'ticks' => [
            'beginAtZero' => NULL,
            'maxRotation' => $element['yaxis']['#labels_rotation'],
            'minRotation' => $element['yaxis']['#labels_rotation'],
          ],
          'maxTicksLimit' => 11,
          'precision' => NULL,
          'stepSize' => NULL,
          'suggestedMax' => NULL,
          'suggestedMin' => NULL,
          'stacked' => $stacking,
        ];
      }
    }

    $chart_definition['options']['title'] = $this->buildTitle($element);
    $chart_definition['options']['tooltips']['enabled'] = $element['#tooltips'];
    $chart_definition['options']['legend'] = $this->buildLegend($element);
    $chart_definition['scaleColorRanges'] = NULL;
    $chart_definition['range'] = NULL;

    // Merge in chart raw options.
    if (!empty($element['#raw_options'])) {
      $chart_definition = NestedArray::mergeDeepArray([
        $chart_definition,
        $element['#raw_options'],
      ]);
    }

    return $chart_definition;
  }

  /**
   * Populate categories.
   *
   * @param array $element
   *   The element.
   * @param array $chart_definition
   *   The chart definition.
   *
   * @return array
   *   Return the chart definition.
   */
  private function populateCategories(array $element, array $chart_definition) {
    $chart_type = $this->populateChartType($element);
    $categories = [];
    foreach (Element::children($element) as $key) {
      if (in_array($chart_type, ['pie', 'doughnut'])) {
        foreach ($element[$key]['#data'] as $index => $datum) {
          $categories[] = $element[$key]['#data'][$index][0];
        }
      }
      else {
        $categories = array_map('strip_tags', $element['xaxis']['#labels']);
      }

      // Merge in axis raw options.
      if (!empty($element[$key]['#raw_options'])) {
        $categories = NestedArray::mergeDeepArray([
          $element[$key]['#raw_options'],
          $categories,
        ]);
      }
    }

    $chart_definition['data']['labels'] = $categories;

    return $chart_definition;
  }

  /**
   * Populate Dataset.
   *
   * @param array $element
   *   The element.
   * @param array $chart_definition
   *   The chart definition.
   *
   * @return array
   *   Return the chart definition.
   */
  private function populateDatasets(array $element, array $chart_definition) {
    $chart_type = $this->populateChartType($element);
    $datasets = [];
    foreach (Element::children($element) as $key) {
      if ($element[$key]['#type'] === 'chart_data') {
        if (in_array($chart_type, ['pie', 'doughnut'])) {
          foreach ($element[$key]['#data'] as $index => $datum) {
            array_shift($element[$key]['#data'][$index]);
          }
        }
        $series_data = [];
        $dataset = new \stdClass();
        // Populate the data.
        foreach ($element[$key]['#data'] as $data_index => $data) {
          if (isset($series_data[$data_index])) {
            $series_data[$data_index][] = $data;
          }
          else {
            if ($chart_type && $chart_type === 'scatter') {
              $data = ['y' => $data[1], 'x' => $data[0]];
            }
            $series_data[$data_index] = $data;
          }
        }
        $dataset->label = $element[$key]['#title'];
        $dataset->data = $series_data;
        if (!in_array($chart_type, ['pie', 'doughnut'])) {
          $dataset->borderColor = $element[$key]['#color'];
        }
        $dataset->backgroundColor = $element[$key]['#color'];
        $series_type = isset($element[$key]['#chart_type']) ? $this->populateChartType($element[$key]) : $chart_type;
        $dataset->type = $series_type;
        if (!empty($element[$key]['#chart_type']) && $element[$key]['#chart_type'] === 'area') {
          $dataset->fill = 'origin';
          $dataset->backgroundColor = $this->getTranslucentColor($element[$key]['#color']);
        }
        elseif ($element['#chart_type'] === 'area') {
          $dataset->fill = 'origin';
          $dataset->backgroundColor = $this->getTranslucentColor($element[$key]['#color']);
        }
        else {
          $dataset->fill = FALSE;
        }
        $datasets[] = $dataset;
      }

      // Merge in axis raw options.
      if (!empty($element[$key]['#raw_options'])) {
        $datasets = NestedArray::mergeDeepArray([
          $datasets,
          $element[$key]['#raw_options'],
        ]);
      }

    }

    $chart_definition['data']['datasets'] = $datasets;

    return $chart_definition;
  }

  /**
   * Outputs a type that can be used by Chart.js.
   *
   * @param array $element
   *   The given element.
   *
   * @return string
   *   The generated type.
   */
  protected function populateChartType(array $element) {
    switch ($element['#chart_type']) {
      case 'bar':
        $type = 'horizontalBar';
        break;

      case 'column':
        $type = 'bar';
        break;

      case 'area':

      case 'spline':
        $type = 'line';
        break;

      case 'donut':
        $type = 'doughnut';
        break;

      case 'gauge':
        // Setting this, but gauge is currently not supported by Chart.js.
        $type = 'gauge';
        break;

      default:
        $type = $element['#chart_type'];
        break;
    }
    if (isset($element['#polar']) && $element['#polar'] == 1) {
      $type = 'radar';
    }

    return $type;
  }

  /**
   * Builds legend based on element properties.
   *
   * @param array $element
   *   The element.
   *
   * @return array
   *   The legend array.
   */
  protected function buildLegend(array $element) {
    $legend = [];
    if (!empty($element['#legend_position'])) {
      $legend['display'] = TRUE;
      $legend['position'] = $element['#legend_position'];
      if (!empty($element['#legend_font_style'])) {
        $legend['labels']['fontStyle'] = $element['#legend_font_style'];
      }
      if (!empty($element['#legend_font_size'])) {
        $legend['labels']['fontSize'] = $element['#legend_font_size'];
      }
    }

    return $legend;
  }

  /**
   * Builds title based on element properties.
   *
   * @param array $element
   *   The element.
   *
   * @return array
   *   The title array.
   */
  protected function buildTitle(array $element) {
    $title = [];
    if (!empty($element['#title'])) {
      $title = [
        'display' => TRUE,
        'text' => $element['#title'],
      ];
      if (!empty($element['#title_position'])) {
        if (in_array($element['#title_position'], ['in', 'out'])) {
          $title['position'] = 'top';
        }
        else {
          $title['position'] = $element['#title_position'];
        }
      }
      if (!empty($element['#title_color'])) {
        $title['fontColor'] = $element['#title_color'];
      }
      if (!empty($element['#title_font_style'])) {
        $title['fontStyle'] = $element['#title_font_style'];
      }
      if (!empty($element['#title_font_size'])) {
        $title['fontSize'] = $element['#title_font_size'];
      }
    }

    return $title;
  }

  /**
   * Get translucent color.
   *
   * @param string $color
   *   The color.
   *
   * @return string
   *   The color.
   */
  protected function getTranslucentColor($color) {
    $rgb = Color::hexToRgb($color);
    return 'rgba(' . implode(",", $rgb) . ',' . 0.5 . ')';

  }

}
