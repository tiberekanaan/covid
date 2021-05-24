<?php

namespace Drupal\webp\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Class RouteSubscriber.
 *
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('image.style_public')) {
      $route->setDefault('_controller', 'Drupal\webp\Controller\ImageStyleDownloadController::deliver');
    }
  }

}
