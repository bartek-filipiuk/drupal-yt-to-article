<?php

declare(strict_types=1);

namespace Drupal\yt_to_article\Exception;

/**
 * Base exception for API-related errors.
 */
class ApiException extends \Exception {
  
  public function __construct(
    string $message = "",
    int $code = 0,
    ?\Throwable $previous = null,
    private readonly array $context = []
  ) {
    parent::__construct($message, $code, $previous);
  }
  
  /**
   * Get additional context about the error.
   */
  public function getContext(): array {
    return $this->context;
  }
}