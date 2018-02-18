<?php

namespace Drupal\shield_agent\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\AdminContext;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Routing\UrlGeneratorTrait;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 *
 */
class RequestSubscriber implements EventSubscriberInterface {

  use UrlGeneratorTrait;

  /**
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $routeMatch;

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * @var \Drupal\Core\Routing\AdminContext
   */
  protected $adminContext;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $account;

  public function __construct(CurrentRouteMatch $route_match, ConfigFactoryInterface $config_factory, AdminContext $admin_context, AccountProxyInterface $account, UrlGeneratorInterface $url_generator) {
    $this->routeMatch = $route_match;
    $this->config = $config_factory;
    $this->adminContext = $admin_context;
    $this->account = $account;
    $this->setUrlGenerator($url_generator);
  }

  /**
   * {@inheritdoc}
   */
  public function onRequest(GetResponseEvent $event) {
    if (!$event->isMasterRequest()) {
      return;
    }

    $route_name = $this->routeMatch->getRouteName();
    if (empty($route_name)) {
      // @todo: Determine if further handling of this case should occur.
      return;
    }

    $protecting = $this->config->get('shield_agent.protector')
      ->get('is');
    if (empty($protecting)) {
      return;
    }

    list($_protecting_env, $_protecting_type) = explode('.', $protecting, 2);

    $protected_environments = $this->config->get('shield_agent.protector')
      ->get('environments');
    $protected_environments = !is_array($protected_environments) ? [] : $protected_environments;

    if (isset($protected_environments[$_protecting_env][$_protecting_type])) {
      $protected_environment = $protected_environments[$_protecting_env][$_protecting_type];
      $protected_environment += [
        'routes' => [],
        'allow_routes_admin_context' => FALSE,
      ];

      $route = $this->routeMatch->getRouteObject();
      if ($this->adminContext->isAdminRoute($route)) {
        if (isset($protected_environment['allow_routes_admin_context']) && $protected_environment['allow_routes_admin_context']) {
          return;
        }

        $event->stopPropagation();
        throw new AccessDeniedHttpException();
      }

      if (empty($protected_environment['routes'])) {
        return;
      }

      $shielded_routes_collections = $this->config->get('shield_agent.protector')
        ->get('routes');
      foreach ($protected_environment['routes'] as $collection_name) {
        if (!empty($shielded_routes_collections[$collection_name])) {
          if (in_array($route_name, $shielded_routes_collections[$collection_name])) {
            $event->stopPropagation();
            throw new AccessDeniedHttpException();
          }
        }
      }

    }

  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [KernelEvents::REQUEST => [['onRequest', 10]]];
  }

}
