<?php

namespace Drupal\shield_agent\Theme;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Theme\ThemeNegotiatorInterface;

/**
 * Theme negotiator for the `system.db_update` system route.
 */
class PublicDbUpdateNegotiator implements ThemeNegotiatorInterface {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * Creates a new instance of PublicDbUpdateNegotiator.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The config factory service.
   */
  public function __construct(ConfigFactoryInterface $config) {
    $this->config = $config;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    return $route_match->getRouteName() == 'system.db_update';
  }

  /**
   * {@inheritdoc}
   */
  public function determineActiveTheme(RouteMatchInterface $route_match) {
    $protecting = $this->config->get('shield_agent.protector')
      ->get('is');
    if (empty($protecting)) {
      return;
    }

    $protected_environments = $this->config->get('shield_agent.protector')
      ->get('environments');
    list($_protecting_env, $_protecting_type) = explode('.', $protecting, 2);
    if (isset($protected_environments[$_protecting_env][$_protecting_type])) {
      $protected_environment = $protected_environments[$_protecting_env][$_protecting_type];
      $protected_environment += ['auth' => FALSE];
      if ($protected_environment['auth']) {
        $theme_name = $this->config->get('shield_agent.settings')
          ->get('auth_enabled.theme_dbupdate');
        if (!empty($theme_name)) {
          return $theme_name;
        }
      }
      else {
        $theme_name = $this->config->get('shield_agent.settings')
          ->get('auth_disabled.theme_dbupdate');
        if (!empty($theme_name)) {
          return $theme_name;
        }
      }
    }
  }

}
