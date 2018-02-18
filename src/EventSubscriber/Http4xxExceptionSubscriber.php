<?php

namespace Drupal\shield_agent\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Routing\UrlGeneratorTrait;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Converts a 403 access denied exception to a 404 not found.
 */
class Http4xxExceptionSubscriber implements EventSubscriberInterface {

  use UrlGeneratorTrait;

  /**
   * Creates a new Http4xxExceptionSubscriber instance.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Session\AccountProxyInterface $account
   *   The current user.
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $url_generator
   *   The URL Generator service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, AccountProxyInterface $account, UrlGeneratorInterface $url_generator) {
    $this->config = $config_factory;
    $this->account = $account;
    $this->setUrlGenerator($url_generator);
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
        $redirect = $this->redirect('entity.user.canonical', ['user' => $this->account->id()]);
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
