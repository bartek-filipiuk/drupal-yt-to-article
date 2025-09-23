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
    // Get and parse the raw body
    $rawBody = $request->getContent();
    $payload = json_decode($rawBody, TRUE);

    if (json_last_error() !== JSON_ERROR_NONE) {
      $error = json_last_error_msg();
      $this->logger->error('WEBHOOK TEST - JSON Parse Error', [
        'error' => $error,
        'raw_body_sample' => substr($rawBody, 0, 1000)
      ]);

      return new JsonResponse([
        'status' => 'error',
        'message' => 'Invalid JSON: ' . $error,
        'raw_body_sample' => substr($rawBody, 0, 500)
      ], 400);
    }

    // Collect all headers
    $headers = [];
    foreach ($request->headers->all() as $name => $values) {
      $headers[$name] = implode(', ', $values);
    }

    // Analyze payload structure
    $analysis = [
      'timestamp' => date('Y-m-d H:i:s'),
      'request_info' => [
        'method' => $request->getMethod(),
        'content_length' => strlen($rawBody),
        'headers' => $headers
      ],
      'payload_structure' => [
        'top_level_keys' => array_keys($payload),
        'event' => $payload['event'] ?? 'not_specified',
        'request_id' => $payload['request_id'] ?? 'not_specified'
      ]
    ];

    // Analyze data field if present
    if (isset($payload['data'])) {
      $data = $payload['data'];
      $analysis['data_analysis'] = [
        'data_type' => gettype($data),
        'data_keys' => is_array($data) ? array_keys($data) : 'not_array'
      ];

      // Analyze content field
      if (isset($data['content'])) {
        $content = $data['content'];
        $contentType = gettype($content);
        $analysis['content_analysis'] = [
          'content_type' => $contentType
        ];

        if ($contentType === 'string') {
          $analysis['content_analysis']['length'] = strlen($content);
          $analysis['content_analysis']['preview'] = substr($content, 0, 200);
        } elseif ($contentType === 'array' || $contentType === 'object') {
          if ((is_array($content) || is_object($content)) && isset($content['format'])) {
            $analysis['content_analysis']['format'] = $content['format'];
          }
          if ((is_array($content) || is_object($content)) && isset($content['article'])) {
            $article = $content['article'];
            $analysis['content_analysis']['article'] = [
              'type' => gettype($article),
              'structure' => is_array($article) ? array_keys($article) : 'not_array'
            ];
            if (is_string($article)) {
              $analysis['content_analysis']['article']['length'] = strlen($article);
            }
          }
        }
      }

      // Analyze other data fields
      if (isset($data['content_type'])) {
        $analysis['content_type'] = $data['content_type'];
      }
      if (isset($data['video_info'])) {
        $analysis['video_info'] = $data['video_info'];
      }
      if (isset($data['metadata'])) {
        $analysis['metadata'] = [
          'present' => true,
          'keys' => is_array($data['metadata']) ? array_keys($data['metadata']) : 'not_array'
        ];
      }
    }

    // Log everything in one consolidated entry using proper Drupal placeholders
    $this->logger->notice('WEBHOOK TEST - Complete Analysis | Analysis: @analysis | Full Payload: @payload', [
      '@analysis' => json_encode($analysis, JSON_PRETTY_PRINT),
      '@payload' => json_encode($payload, JSON_PRETTY_PRINT)
    ]);

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