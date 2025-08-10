<?php

declare(strict_types=1);

namespace Drupal\yt_to_article\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Site\Settings;
use Psr\Log\LoggerInterface;
use WebSocket\Client;
use WebSocket\TimeoutException;
use Drupal\yt_to_article\Exception\WebSocketException;
use Drupal\yt_to_article\ValueObject\WebSocketMessage;

/**
 * WebSocket client service for real-time article generation updates.
 */
final class YtToArticleWebSocketClient {
  
  /**
   * WebSocket connection timeout in seconds.
   */
  private const CONNECTION_TIMEOUT = 10;
  
  /**
   * Read timeout for receiving messages in seconds.
   */
  private const READ_TIMEOUT = 300;
  
  /**
   * The WebSocket client instance.
   */
  private ?Client $client = null;
  
  /**
   * The current request ID.
   */
  private ?string $currentRequestId = null;
  
  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly LoggerInterface $logger,
  ) {}
  
  /**
   * Connect to the WebSocket server for a specific request.
   *
   * @param string $requestId The request ID to monitor.
   *
   * @throws WebSocketException
   */
  public function connect(string $requestId): void {
    if ($this->client !== null && $this->currentRequestId === $requestId) {
      // Already connected to this request
      return;
    }
    
    // Close existing connection if any
    $this->disconnect();
    
    try {
      $wsUrl = $this->getWebSocketUrl() . '/article/' . $requestId;
      
      // Add token as query parameter for WebSocket authentication
      $settings = Settings::get('yt_to_article', []);
      $apiToken = $settings['api_token'] ?? null;
      
      if ($apiToken) {
        $wsUrl .= '?token=' . urlencode($apiToken);
      }
      
      $this->logger->info('Connecting to WebSocket: {url}', ['url' => $wsUrl]);
      
      $this->client = new Client($wsUrl, [
        'timeout' => self::CONNECTION_TIMEOUT,
        'headers' => $this->getHeaders(),
      ]);
      
      $this->currentRequestId = $requestId;
      
    } catch (\Exception $e) {
      $errorMessage = $e->getMessage();
      $errorCode = $e->getCode();
      
      // Log more detailed error information
      $this->logger->error('WebSocket connection failed: {message} (Code: {code})', [
        'message' => $errorMessage,
        'code' => $errorCode,
        'request_id' => $requestId,
        'url' => $wsUrl,
        'token_provided' => !empty($apiToken),
      ]);
      
      // Check for specific error patterns
      if (strpos($errorMessage, '403') !== false || strpos($errorMessage, 'Forbidden') !== false) {
        $this->logger->error('WebSocket authentication failed - token may be invalid or not properly sent');
      }
      
      throw new WebSocketException(
        message: 'Failed to connect to WebSocket: ' . $errorMessage,
        previous: $e,
        requestId: $requestId
      );
    }
  }
  
  /**
   * Receive a message from the WebSocket.
   *
   * @return WebSocketMessage|null The received message or null if connection closed.
   *
   * @throws WebSocketException
   */
  public function receive(): ?WebSocketMessage {
    if ($this->client === null) {
      throw new WebSocketException('Not connected to WebSocket');
    }
    
    try {
      $this->client->setTimeout(self::READ_TIMEOUT);
      $message = $this->client->receive();
      
      if ($message === null || $message === '') {
        // Connection closed
        return null;
      }
      
      $this->logger->debug('Received WebSocket message: {message}', ['message' => $message]);
      
      return WebSocketMessage::fromJson($message);
      
    } catch (TimeoutException $e) {
      throw new WebSocketException(
        message: 'WebSocket read timeout',
        previous: $e,
        requestId: $this->currentRequestId
      );
    } catch (\Exception $e) {
      $this->logger->error('Failed to receive WebSocket message: {message}', [
        'message' => $e->getMessage(),
        'request_id' => $this->currentRequestId,
      ]);
      
      throw new WebSocketException(
        message: 'Failed to receive WebSocket message: ' . $e->getMessage(),
        previous: $e,
        requestId: $this->currentRequestId
      );
    }
  }
  
  /**
   * Send a message to the WebSocket server.
   *
   * @param string $message The message to send.
   *
   * @throws WebSocketException
   */
  public function send(string $message): void {
    if ($this->client === null) {
      throw new WebSocketException('Not connected to WebSocket');
    }
    
    try {
      $this->client->send($message);
      
      $this->logger->debug('Sent WebSocket message: {message}', ['message' => $message]);
      
    } catch (\Exception $e) {
      throw new WebSocketException(
        message: 'Failed to send WebSocket message: ' . $e->getMessage(),
        previous: $e,
        requestId: $this->currentRequestId
      );
    }
  }
  
  /**
   * Disconnect from the WebSocket server.
   */
  public function disconnect(): void {
    if ($this->client !== null) {
      try {
        $this->client->close();
      } catch (\Exception $e) {
        // Log but don't throw - we're cleaning up
        $this->logger->warning('Error closing WebSocket connection: {message}', [
          'message' => $e->getMessage(),
        ]);
      }
      
      $this->client = null;
      $this->currentRequestId = null;
    }
  }
  
  /**
   * Check if connected to a WebSocket.
   */
  public function isConnected(): bool {
    return $this->client !== null && $this->client->isConnected();
  }
  
  /**
   * Get the current request ID.
   */
  public function getCurrentRequestId(): ?string {
    return $this->currentRequestId;
  }
  
  /**
   * Receive all messages until completion or error.
   *
   * @param callable|null $callback Optional callback for each message.
   *
   * @return WebSocketMessage[] All received messages.
   *
   * @throws WebSocketException
   */
  public function receiveUntilComplete(?callable $callback = null): array {
    $messages = [];
    
    while ($message = $this->receive()) {
      $messages[] = $message;
      
      if ($callback !== null) {
        $callback($message);
      }
      
      if ($message->isComplete() || $message->isError()) {
        break;
      }
    }
    
    return $messages;
  }
  
  /**
   * Get the WebSocket base URL.
   */
  private function getWebSocketUrl(): string {
    $settings = Settings::get('yt_to_article', []);
    $configUrl = $this->configFactory->get('yt_to_article.settings')->get('websocket_url');
    
    return $settings['websocket_url'] ?? $configUrl ?? 'ws://localhost:8000/api/v1/ws';
  }
  
  /**
   * Get WebSocket headers.
   */
  private function getHeaders(): array {
    $settings = Settings::get('yt_to_article', []);
    $apiToken = $settings['api_token'] ?? null;
    
    $headers = [
      'User-Agent' => 'Drupal/YtToArticle/1.0',
    ];
    
    // Add authorization if we have a token
    if ($apiToken) {
      $headers['Authorization'] = 'Bearer ' . $apiToken;
    }
    
    return $headers;
  }
  
  /**
   * Destructor to ensure connections are closed.
   */
  public function __destruct() {
    $this->disconnect();
  }
}