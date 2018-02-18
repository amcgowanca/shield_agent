<?php

/**
 * example.settings.php
 *
 * Provides an example of settings.php inclusion for Shield agent's config. The
 * examples listed below are commonly used in conjunction with Acquia BLT.
 */

/**
 * Processes the domain name to determine environment type.
 */
$forwarded_domain = empty($_SERVER['HTTP_X_FORWARDED_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['HTTP_X_FORWARDED_HOST'];
$forwarded_domain_pieces = explode('.', $forwarded_domain);

/**
 * Provides a domain/environment type specifier used for protection details.
 */
$config['shield_agent.environments']['type'] = in_array($forwarded_domain_pieces[0], ['edit']) ? 'edit' : 'default';

/**
 * Provides a default protector environment indicator for Shield agent.
 */
$config['shield_agent.protector']['is'] = 'local.' . $config['shield_agent.environments']['type'];

/**
 * Shield agent's `always_on` will enforce HTTP Basic Auth protection.
 *
 * Once an application is deployed to 'production',
 */
$config['shield_agent.protector']['always_on'] = FALSE;

/**
 * Sets the Shield agent's environment (type) detection booleans.
 *
 * Primary variables used for detection are created in Acquia BLT's inclusion
 * from within the `settings.php`.
 */
$config['shield_agent.environments']['is'] = [
  'production' => isset($is_ah_prod_env) ? $is_ah_prod_env : FALSE,
  'stage' => isset($is_ah_stage_env) ? $is_ah_stage_env : FALSE,
  'dev' => isset($is_ah_dev_env) ? $is_ah_dev_env : FALSE,
  'local' => $is_local_env && !getenv('TRAVIS') ? TRUE : FALSE,
  'ci' => $is_local_env && getenv('TRAVIS') ? TRUE : FALSE,
];

/**
 * Sets the active environment being protected.
 */
foreach ($config['shield_agent.environments']['is'] as $environment => $is) {
  if ($is) {
    $config['shield_agent.protector']['is'] = $environment . '.' . $config['shield_agent.environments']['type'];
  }
}
