<?php

namespace Drupal\shield_agent;

use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Shields incoming requests and intercepts them for HTTP Basic Auth protection.
 */
class HttpBasicAuthMiddleware implements HttpKernelInterface {

  /**
   * The HTTP Kernel.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $httpKernel;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * Creates a new HttpBasicAuthMiddleware instance.
   *
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $http_kernel
   *   The HTTP Kernel.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   */
  public function __construct(HttpKernelInterface $http_kernel, ConfigFactoryInterface $config_factory) {
    $this->httpKernel = $http_kernel;
    $this->config = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = TRUE) {
    if ($type != self::MASTER_REQUEST) {
      return $this->httpKernel->handle($request, $type, $catch);
    }

    $protector_settings = $this->config->get('shield_agent.protector');
    $always_on_protector = (bool) $protector_settings->get('always_on');

    if ($always_on_protector) {
      return $this->activateShieldProtector($request, $type, $catch);
    }

    $protecting = $protector_settings->get('is');
    if (empty($protecting)) {
      return $this->httpKernel->handle($request, $type, $catch);
    }

    $protected_environments = $protector_settings->get('environments');
    list($_protecting_env, $_protecting_type) = explode('.', $protecting, 2);

    if (isset($protected_environments[$_protecting_env][$_protecting_type])) {
      $protected_environment = $protected_environments[$_protecting_env][$_protecting_type];
      $protected_environment += ['auth' => FALSE];

      if ($protected_environment['auth']) {
        return $this->activateShieldProtector($request, $type, $catch);
      }
    }

    return $this->httpKernel->handle($request, $type, $catch);
  }

  /**
   * Activates HTTP Basic Auth shield protection for the request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param int $type
   *   The type of request (e.g. HttpKernelInterface::MASTER_REQUEST).
   * @param bool $catch
   *   A boolean indicating whether exceptions should caught & handled.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response.
   *
   * @throws \Exception
   *   Thrown when an unexpected error has occurred.
   */
  protected function activateShieldProtector(Request $request, $type, $catch) {
    $protector_settings = $this->config->get('shield_agent.protector');
    $allow_cli = (bool) $protector_settings->get('allow_cli');

    $username = $protector_settings->get('auth.username');
    $password = $protector_settings->get('auth.password');

    if ($type != self::MASTER_REQUEST || empty($username) || (PHP_SAPI === 'cli' && $allow_cli)) {
      return $this->httpKernel->handle($request, $type, $catch);
    }
    else {
      if ($request->server->has('PHP_AUTH_USER') && $request->server->has('PHP_AUTH_PW')) {
        $input_username = $request->server->get('PHP_AUTH_USER');
        $input_password = $request->server->get('PHP_AUTH_PW');
      }
      elseif (!empty($request->server->get('HTTP_AUTHORIZATION'))) {
        list($input_username, $input_password) = explode(':', base64_decode(substr($request->server->get('HTTP_AUTHORIZATION'), 6)), 2);
      }
      elseif (!empty($request->server->get('REDIRECT_HTTP_AUTHORIZATION'))) {
        list($input_username, $input_password) = explode(':', base64_decode(substr($request->server->get('REDIRECT_HTTP_AUTHORIZATION'), 6)), 2);
      }

      if (isset($input_username) && isset($input_password) && $input_username === $username && hash_equals($password, $input_password)) {
        return $this->httpKernel->handle($request, $type, $catch);
      }
    }

    $basic_auth_response = new Response();
    $basic_auth_response->headers->add([
      'WWW-Authenticate' => 'Basic realm="' . strtr($protector_settings->get('auth.message'), [
        '[user]' => $username,
        '[pass]' => $password,
      ]) . '"',
    ]);
    $basic_auth_response->setStatusCode(401);
    return $basic_auth_response;
  }

}
