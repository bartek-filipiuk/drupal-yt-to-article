<?php

declare(strict_types=1);

namespace Drupal\yt_to_article\Exception;

/**
 * Exception for WebSocket-related errors.
 */
class WebSocketException extends \Exception {
  
  public function __construct(
    string $message = "",
    int $code = 0,
    ?\Throwable $previous = null,
    private readonly ?string $requestId = null
  ) {
    parent::__construct($message, $code, $previous);
  }
  
  /**
   * Get the request ID associated with this error.
   */
  public function getRequestId(): ?string {
    return $this->requestId;
  }
}