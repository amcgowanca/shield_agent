<?php

namespace Drupal\shield_agent\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\shield_agent\AccessDeniedHttpException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException as AccessDeniedHttpExceptionBase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Converts a 403 access denied exception to a 404 not found.
 */
class Http4xxExceptionSubscriber implements EventSubscriberInterface {

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $currentRouteMatch;

  /**
   * The configuration factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $account;

  /**
   * Creates a new Http4xxExceptionSubscriber instance.
   *
   * @param \Drupal\Core\Routing\CurrentRouteMatch $current_route_match
   *   The current route match.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Session\AccountProxyInterface $account
   *   The current user.
   */
  public function __construct(CurrentRouteMatch $current_route_match, ConfigFactoryInterface $config_factory, AccountProxyInterface $account) {
    $this->currentRouteMatch = $current_route_match;
    $this->config = $config_factory;
    $this->account = $account;
  }

  /**
   * {@inheritdoc}
   */
  public function on4xxException(GetResponseForExceptionEvent $event) {
    if (!$event->isMasterRequest()) {
      return;
    }

    $mask_403_as_404 = (bool) $this->config->get('shield_agent.settings')
      ->get('mask_403');
    $exception = $event->getException();
    if ($mask_403_as_404 && $exception instanceof AccessDeniedHttpExceptionBase) {
      if ($this->account->isAuthenticated() && !($exception instanceof AccessDeniedHttpException)) {
        if ($this->currentRouteMatch->getRouteName() == 'entity.user.canonical') {
          return;
        }

        $redirect_url = Url::fromRoute('entity.user.canonical', [
          'user' => $this->account->id(),
        ]);
        $redirect = RedirectResponse::create($redirect_url->toString(), 301);
        $event->setResponse($redirect);
        return;
      }

      $event->setException(new NotFoundHttpException());
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [KernelEvents::EXCEPTION => [['on4xxException', 500]]];
  }

}
