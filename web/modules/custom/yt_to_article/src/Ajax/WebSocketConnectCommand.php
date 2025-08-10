<?php

declare(strict_types=1);

namespace Drupal\yt_to_article\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * AJAX command to connect to WebSocket.
 */
class WebSocketConnectCommand implements CommandInterface {
  
  /**
   * The WebSocket configuration.
   */
  protected array $config;
  
  /**
   * Constructs a WebSocketConnectCommand.
   */
  public function __construct(string $requestId, string $wsUrl, string $token) {
    $this->config = [
      'requestId' => $requestId,
      'wsUrl' => $wsUrl,
      'token' => $token,
    ];
  }
  
  /**
   * {@inheritdoc}
   */
  public function render(): array {
    return [
      'command' => 'ytToArticleWebSocketConnect',
      'config' => $this->config,
    ];
  }
}