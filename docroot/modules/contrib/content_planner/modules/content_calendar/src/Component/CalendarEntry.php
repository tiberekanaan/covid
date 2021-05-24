<?php

namespace Drupal\content_calendar\Component;

use Drupal\content_calendar\DateTimeHelper;
use Drupal\content_calendar\Entity\ContentTypeConfig;
use Drupal\content_calendar\Form\SettingsForm;
use Drupal\content_planner\Component\BaseEntry;

/**
 * Class CalendarEntry.
 *
 * @package Drupal\content_calendar\Component
 */
class CalendarEntry extends BaseEntry {

  /**
   * @var int
   */
  protected $month;

  /**
   * @var int
   */
  protected $year;

  /**
   * @var \Drupal\content_calendar\Entity\ContentTypeConfig
   */
  protected $contentTypeConfig;

  /**
   * @var \stdClass
   */
  protected $node;

  /**
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * CalendarEntry constructor.
   *
   * @param int $month
   * @param int $year
   * @param \Drupal\content_calendar\Entity\ContentTypeConfig $content_type_config
   * @param \stdClass $node
   */
  public function __construct(
    $month,
    $year,
    ContentTypeConfig $content_type_config,
    \stdClass $node
  ) {
    $this->month = $month;
    $this->year = $year;
    $this->contentTypeConfig = $content_type_config;
    $this->node = $node;

    $this->config = \Drupal::config(SettingsForm::CONFIG_NAME);
  }

  /**
   * Get Node ID.
   *
   * @return mixed
   */
  public function getNodeID() {
    return $this->node->nid;
  }

  /**
   * Get the relevant date for the current node.
   *
   * When the Scheduler date is empty, then take the creation date.
   *
   * @return int
   */
  public function getRelevantDate() {

    if ($this->node->publish_on) {
      return $this->node->publish_on;
    }

    return $this->node->created;
  }

  /**
   * Format creation date as MySQL Date only.
   *
   * @return string
   */
  public function formatSchedulingDateAsMySQLDateOnly() {

    $datetime = DateTimeHelper::convertUnixTimestampToDatetime($this->node->created);

    return $datetime->format(DateTimeHelper::FORMAT_MYSQL_DATE_ONLY);
  }

  /**
   * Build.
   *
   * @return array
   */
  public function build() {

    // Get User Picture.
    $user_picture = $this->getUserPictureURL();

    
    if ($this->node->publish_on) {
      $this->node->scheduled = true;
    } else {
      $this->node->scheduled = false;
    }

    // Add time to node object.
    $this->node->publish_on_time = DateTimeHelper::convertUnixTimestampToDatetime($this->node->publish_on)->format('H:i');
    $this->node->created_on_time = DateTimeHelper::convertUnixTimestampToDatetime($this->node->created)->format('H:i');

    // Build options.
    $options = $this->buildOptions();

    if (\Drupal::currentUser()->hasPermission('manage content calendar')) {
      $this->node->editoptions = true;
    }

    if (\Drupal::currentUser()->hasPermission('manage own content calendar')) {
      if ($this->node->uid == \Drupal::currentUser()->id()) {
        $this->node->editoptions = true;
      }
    }

    $build = [
      '#theme' => 'content_calendar_entry',
      '#node' => $this->node,
      '#node_type_config' => $this->contentTypeConfig,
      '#month' => $this->month,
      '#year' => $this->year,
      '#user_picture' => $user_picture,
      '#options' => $options,
    ];

    return $build;
  }

  /**
   * Build options before rendering.
   *
   * @return array
   */
  protected function buildOptions() {

    $options = [];

    // Background color for unpublished content.
    $options['bg_color_unpublished_content'] = ($this->config->get('bg_color_unpublished_content'))
      ? $this->config->get('bg_color_unpublished_content')
      : SettingsForm::DEFAULT_BG_COLOR_UNPUBLISHED_CONTENT;

    return $options;
  }

  /**
   * Get the URL of the user picture.
   *
   * @return bool|string
   */
  protected function getUserPictureURL() {

    // If show user thumb is active.
    if ($this->config->get('show_user_thumb')) {
      return $this->getUserPictureFromCache($this->node->uid, 'content_calendar_user_thumb');
    }

    return FALSE;
  }

}
