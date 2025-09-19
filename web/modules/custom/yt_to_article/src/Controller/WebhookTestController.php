<?php

declare(strict_types=1);

namespace Drupal\yt_to_article\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Psr\Log\LoggerInterface;

/**
 * Test controller for debugging webhook payloads from the article API.
 *
 * This controller receives and logs webhook payloads without verification
 * for testing and debugging purposes.
 */
class WebhookTestController extends ControllerBase {

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * Constructs a WebhookTestController object.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   */
  public function __construct(LoggerInterface $logger) {
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('logger.channel.yt_to_article')
    );
  }

  /**
   * Handle incoming test webhook requests.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The response.
   */
  public function handleTestWebhook(Request $request): JsonResponse {
    // Log that we received a test webhook
    $this->logger->notice('TEST WEBHOOK RECEIVED');
    $this->logger->notice('=====================================');

    // Log all headers for debugging
    $headers = [];
    foreach ($request->headers->all() as $name => $values) {
      $headers[$name] = implode(', ', $values);
    }
    $this->logger->notice('Headers: @headers', ['@headers' => json_encode($headers, JSON_PRETTY_PRINT)]);

    // Get and log the raw body
    $rawBody = $request->getContent();
    $this->logger->notice('Raw body length: @length characters', ['@length' => strlen($rawBody)]);

    // Try to parse as JSON
    $payload = json_decode($rawBody, TRUE);

    if (json_last_error() !== JSON_ERROR_NONE) {
      $error = json_last_error_msg();
      $this->logger->error('Failed to parse JSON: @error', ['@error' => $error]);
      $this->logger->error('Raw body (first 1000 chars): @body', ['@body' => substr($rawBody, 0, 1000)]);

      return new JsonResponse([
        'status' => 'error',
        'message' => 'Invalid JSON: ' . $error,
        'raw_body_sample' => substr($rawBody, 0, 500)
      ], 400);
    }

    // Log the full payload structure
    $this->logger->notice('Parsed payload: @payload', ['@payload' => json_encode($payload, JSON_PRETTY_PRINT)]);

    // Check if this is a webhook event
    if (isset($payload['event'])) {
      $this->logger->notice('Event type: @event', ['@event' => $payload['event']]);
    }

    // Check the data field
    if (isset($payload['data'])) {
      $this->logger->notice('Data field present, type: @type', ['@type' => gettype($payload['data'])]);

      // Check content field specifically
      if (isset($payload['data']['content'])) {
        $contentType = gettype($payload['data']['content']);
        $this->logger->notice('Content field type: @type', ['@type' => $contentType]);

        if ($contentType === 'string') {
          $contentLength = strlen($payload['data']['content']);
          $this->logger->notice('Content is a string with @length characters', ['@length' => $contentLength]);
          $this->logger->notice('Content preview (first 500 chars): @preview', [
            '@preview' => substr($payload['data']['content'], 0, 500)
          ]);
        } elseif ($contentType === 'array' || $contentType === 'object') {
          $this->logger->notice('Content is an array/object: @content', [
            '@content' => json_encode($payload['data']['content'], JSON_PRETTY_PRINT)
          ]);

          // Check for nested format field
          if (is_array($payload['data']['content']) && isset($payload['data']['content']['format'])) {
            $this->logger->notice('Content format: @format', ['@format' => $payload['data']['content']['format']]);
          }

          // Check for nested article field
          if (is_array($payload['data']['content']) && isset($payload['data']['content']['article'])) {
            $articleType = gettype($payload['data']['content']['article']);
            $this->logger->notice('Article field type: @type', ['@type' => $articleType]);
            if ($articleType === 'string') {
              $this->logger->notice('Article length: @length characters', [
                '@length' => strlen($payload['data']['content']['article'])
              ]);
            }
          }
        } else {
          $this->logger->warning('Unexpected content type: @type', ['@type' => $contentType]);
        }
      } else {
        $this->logger->warning('No content field in data');
      }

      // Check content_type field
      if (isset($payload['data']['content_type'])) {
        $this->logger->notice('Content-Type specified: @type', ['@type' => $payload['data']['content_type']]);
      }

      // Check video_info
      if (isset($payload['data']['video_info'])) {
        $this->logger->notice('Video info: @info', [
          '@info' => json_encode($payload['data']['video_info'], JSON_PRETTY_PRINT)
        ]);
      }

      // Check metadata
      if (isset($payload['data']['metadata'])) {
        $this->logger->notice('Metadata: @metadata', [
          '@metadata' => json_encode($payload['data']['metadata'], JSON_PRETTY_PRINT)
        ]);
      }
    }

    // Log summary
    $this->logger->notice('=====================================');
    $this->logger->notice('TEST WEBHOOK PROCESSING COMPLETE');
    $this->logger->notice('Payload structure summary:');
    $this->logger->notice('- Top level keys: @keys', ['@keys' => implode(', ', array_keys($payload))]);

    if (isset($payload['data']) && is_array($payload['data'])) {
      $this->logger->notice('- Data keys: @keys', ['@keys' => implode(', ', array_keys($payload['data']))]);
    }

    // Return detailed response
    $response = [
      'status' => 'success',
      'message' => 'Test webhook received and logged',
      'received' => [
        'timestamp' => date('Y-m-d H:i:s'),
        'event' => $payload['event'] ?? 'unknown',
        'payload_size' => strlen($rawBody),
        'top_level_keys' => array_keys($payload),
      ]
    ];

    if (isset($payload['data'])) {
      $response['received']['data_keys'] = is_array($payload['data']) ? array_keys($payload['data']) : 'not_array';

      if (isset($payload['data']['content'])) {
        $response['received']['content_type'] = gettype($payload['data']['content']);

        if (is_string($payload['data']['content'])) {
          $response['received']['content_length'] = strlen($payload['data']['content']);
        } elseif (is_array($payload['data']['content'])) {
          $response['received']['content_structure'] = array_keys($payload['data']['content']);
        }
      }
    }

    return new JsonResponse($response);
  }
}