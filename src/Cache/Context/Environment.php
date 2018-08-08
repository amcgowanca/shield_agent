<?php

namespace Drupal\shield_agent\Cache\Context;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CacheContextInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

class Environment implements CacheContextInterface, CacheableDependencyInterface {

  use StringTranslationTrait;

  protected $config;

  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->config = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function getLabel() {
    return t('Shield Agent Environment cache context');
  }

  /**
   * {@inheritdoc}
   */
  public function getContext() {
    return 'shield_agent_environment:' . $this->config->get('shield_agent.protector')->get('is');
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata() {
    return new CacheableMetadata();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return $this->config->get('shield_agent.protector')->get('is');
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return Cache::PERMANENT;
  }
}
