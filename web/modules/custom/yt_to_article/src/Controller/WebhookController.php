<?php

declare(strict_types=1);

namespace Drupal\yt_to_article\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\yt_to_article\Service\WebhookService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Psr\Log\LoggerInterface;

/**
 * Controller for handling webhook requests from the article API.
 */
class WebhookController extends ControllerBase {

  /**
   * The webhook service.
   *
   * @var \Drupal\yt_to_article\Service\WebhookService
   */
  protected WebhookService $webhookService;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * Constructs a WebhookController object.
   *
   * @param \Drupal\yt_to_article\Service\WebhookService $webhook_service
   *   The webhook service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   */
  public function __construct(
    WebhookService $webhook_service,
    LoggerInterface $logger
  ) {
    $this->webhookService = $webhook_service;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('yt_to_article.webhook'),
      $container->get('logger.channel.yt_to_article')
    );
  }

  /**
   * Handle incoming webhook requests.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The response.
   */
  public function handleWebhook(Request $request): JsonResponse {
    // Log the incoming webhook
    $this->logger->info('Webhook received');

    try {
      // Get the raw body for signature verification
      $rawBody = $request->getContent();

      // Get signature from header
      $signature = $request->headers->get('X-Webhook-Signature', '');

      // For one-time webhooks, get the secret from header
      $webhookSecret = $request->headers->get('X-Webhook-Secret');

      // If no one-time secret, use the configured secret
      if (!$webhookSecret) {
        $settings = \Drupal::service('settings')->get('yt_to_article', []);
        $webhookSecret = $settings['webhook_secret'] ?? '';
      }

      // Verify signature
      if (!$this->webhookService->verifySignature($rawBody, $signature, $webhookSecret)) {
        $this->logger->warning('Invalid webhook signature');
        return new JsonResponse(['error' => 'Invalid signature'], 401);
      }

      // Parse the payload
      $payload = json_decode($rawBody, TRUE);
      if (json_last_error() !== JSON_ERROR_NONE) {
        $this->logger->error('Invalid JSON in webhook payload');
        return new JsonResponse(['error' => 'Invalid JSON'], 400);
      }

      // Log the event type
      $event = $payload['event'] ?? 'unknown';
      $this->logger->info('Processing webhook event: @event', ['@event' => $event]);

      // Process based on event type
      switch ($event) {
        case 'article.completed':
          $result = $this->webhookService->processArticleCompleted($payload);
          break;

        case 'article.failed':
          $result = $this->webhookService->processArticleFailed($payload);
          break;

        default:
          $this->logger->warning('Unknown webhook event: @event', ['@event' => $event]);
          return new JsonResponse(['error' => 'Unknown event type'], 400);
      }

      // Check if processing was successful
      if ($result['success']) {
        $this->logger->info('Webhook processed successfully');
        return new JsonResponse([
          'success' => TRUE,
          'message' => $result['message'] ?? 'Webhook processed',
          'node_id' => $result['node_id'] ?? NULL,
        ], 200);
      }
      else {
        $this->logger->error('Webhook processing failed: @error', [
          '@error' => $result['error'] ?? 'Unknown error'
        ]);
        return new JsonResponse([
          'error' => $result['error'] ?? 'Processing failed'
        ], 500);
      }

    }
    catch (\Exception $e) {
      $this->logger->error('Webhook exception: @message', [
        '@message' => $e->getMessage()
      ]);
      return new JsonResponse([
        'error' => 'Internal server error'
      ], 500);
    }
  }


}
