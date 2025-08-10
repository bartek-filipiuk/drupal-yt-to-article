<?php

declare(strict_types=1);

namespace Drupal\yt_to_article\Exception;

/**
 * Exception for rate limit errors.
 */
class RateLimitException extends ApiException {
  
  public function __construct(
    string $message = "Rate limit exceeded",
    private readonly ?int $retryAfter = null,
    int $code = 429,
    ?\Throwable $previous = null
  ) {
    parent::__construct($message, $code, $previous);
  }
  
  /**
   * Get the number of seconds to wait before retrying.
   */
  public function getRetryAfter(): ?int {
    return $this->retryAfter;
  }
}