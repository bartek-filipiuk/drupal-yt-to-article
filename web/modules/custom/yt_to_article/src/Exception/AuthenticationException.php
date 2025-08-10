<?php

declare(strict_types=1);

namespace Drupal\yt_to_article\Exception;

/**
 * Exception for authentication errors.
 */
class AuthenticationException extends ApiException {
  
  public function __construct(
    string $message = "Authentication failed",
    int $code = 401,
    ?\Throwable $previous = null
  ) {
    parent::__construct($message, $code, $previous);
  }
}