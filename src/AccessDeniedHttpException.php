<?php

namespace Drupal\shield_agent;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException as AccessDeniedHttpExceptionBase;

/**
 * Defines a namespace-oriented Access Denied Http Exception.
 *
 * The class should be used internally to throw 403 errors instead of the core
 * Symfony classes. The primary purpose is to enable other subscribers to
 * detect whether the 403 is coming from ourselves or another service.
 */
class AccessDeniedHttpException extends AccessDeniedHttpExceptionBase {

}
