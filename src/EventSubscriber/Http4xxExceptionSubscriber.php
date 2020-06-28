<?php

namespace Drupal\shield_agent\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Converts a 403 access denied exception to a 404 not found.
 */
class Http4xxExceptionSubscriber implements EventSubscriberInterface {

  /**
   * Creates a new Http4xxExceptionSubscriber instance.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Session\AccountProxyInterface $account
   *   The current user.
   */
  public function __construct(ConfigFactoryInterface $config_factory, AccountProxyInterface $account) {
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
    if ($mask_403_as_404 && $exception instanceof AccessDeniedHttpException) {
      if ($this->account->isAuthenticated()) {
        $redirect_url = Url::fromRoute('entity.user.canonical', [
          'user' => $this->account->id(),
        ]);
        $redirect = RedirectResponse::create($redirect_url, 301);
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
