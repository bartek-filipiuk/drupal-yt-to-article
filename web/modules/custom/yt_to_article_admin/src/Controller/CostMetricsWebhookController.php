<?php

declare(strict_types=1);

namespace Drupal\yt_to_article_admin\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\Entity\Node;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controller for handling cost metrics webhook requests.
 */
class CostMetricsWebhookController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a CostMetricsWebhookController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    LoggerInterface $logger
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('entity_type.manager'),
      $container->get('logger.channel.yt_to_article_admin')
    );
  }

  /**
   * Handle incoming cost metrics webhook.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The response.
   */
  public function handleWebhook(Request $request): JsonResponse {
    $this->logger->info('Cost metrics webhook received');

    try {
      // Get the webhook secret from settings.php instead of config
      $settings = \Drupal::service('settings')->get('yt_to_article_admin', []);
      $expectedSecret = $settings['webhook_secret'] ?? '';

      // Get the secret from header
      $providedSecret = $request->headers->get('X-Webhook-Secret', '');

      // Verify the secret
      if (empty($expectedSecret) || empty($providedSecret)) {
        $this->logger->warning('Missing webhook secret');
        return new JsonResponse(['error' => 'Authentication required'], 401);
      }

      if ($providedSecret !== $expectedSecret) {
        $this->logger->warning('Invalid webhook secret provided');
        return new JsonResponse(['error' => 'Invalid authentication'], 401);
      }

      // Parse the JSON payload
      $content = $request->getContent();
      $data = json_decode($content, TRUE);

      if (json_last_error() !== JSON_ERROR_NONE) {
        $this->logger->error('Invalid JSON in webhook payload');
        return new JsonResponse(['error' => 'Invalid JSON'], 400);
      }

      // Validate required fields
      if (empty($data['request_id'])) {
        $this->logger->error('Missing request_id in webhook payload');
        return new JsonResponse(['error' => 'Missing request_id'], 400);
      }

      // Check if metric already exists for this request ID
      $existing = $this->findExistingMetric($data['request_id']);
      if ($existing) {
        $this->logger->info('Cost metric already exists for request: @id', ['@id' => $data['request_id']]);
        return new JsonResponse([
          'success' => TRUE,
          'message' => 'Metric already exists',
          'node_id' => $existing->id(),
        ]);
      }

      // Create the cost metrics node
      $node = $this->createCostMetricNode($data);

      $this->logger->info('Created cost metric node @nid for request @request_id', [
        '@nid' => $node->id(),
        '@request_id' => $data['request_id'],
      ]);

      return new JsonResponse([
        'success' => TRUE,
        'message' => 'Cost metric created',
        'node_id' => $node->id(),
      ], 201);

    } catch (\Exception $e) {
      $this->logger->error('Error processing cost webhook: @error', [
        '@error' => $e->getMessage(),
      ]);
      return new JsonResponse([
        'error' => 'Internal server error'
      ], 500);
    }
  }

  /**
   * Find existing cost metric node by request ID.
   *
   * @param string $request_id
   *   The request ID.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The node if found, NULL otherwise.
   */
  protected function findExistingMetric(string $request_id): ?Node {
    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', 'article_cost_metrics')
      ->condition('field_request_id', $request_id)
      ->accessCheck(FALSE)
      ->range(0, 1);

    $nids = $query->execute();
    if (!empty($nids)) {
      return Node::load(reset($nids));
    }

    return NULL;
  }

  /**
   * Create cost metric node from webhook data.
   *
   * @param array $data
   *   The webhook data.
   *
   * @return \Drupal\node\NodeInterface
   *   The created node.
   */
  protected function createCostMetricNode(array $data): Node {
    // Extract cost summary
    $costSummary = $data['cost_summary'] ?? [];
    
    // Create the node
    $node = Node::create([
      'type' => 'article_cost_metrics',
      'title' => sprintf('Cost Metrics - %s', $data['request_id']),
      'status' => 1,
      'uid' => 1, // Admin user
      'field_request_id' => $data['request_id'],
    ]);

    // Add cost fields
    if (!empty($costSummary['total_usd'])) {
      $node->set('field_total_cost_usd', $costSummary['total_usd']);
    }

    if (!empty($costSummary['cost_breakdown']['llm'])) {
      $node->set('field_llm_cost_usd', $costSummary['cost_breakdown']['llm']);
    }

    if (!empty($costSummary['input_tokens'])) {
      $node->set('field_input_tokens', $costSummary['input_tokens']);
    }

    if (!empty($costSummary['output_tokens'])) {
      $node->set('field_output_tokens', $costSummary['output_tokens']);
    }

    // Add the full cost breakdown as JSON
    if (!empty($costSummary)) {
      $node->set('field_cost_breakdown_json', json_encode($costSummary, JSON_PRETTY_PRINT));
    }

    // Add LLM model and provider information
    if (!empty($data['llm_model'])) {
      $node->set('field_llm_model', $data['llm_model']);
    }

    if (!empty($data['llm_provider'])) {
      $node->set('field_llm_provider', $data['llm_provider']);
    }

    // Add additional metadata if provided
    if (!empty($data['video_title'])) {
      $node->setTitle(sprintf('%s - Cost Metrics', $data['video_title']));
    }

    // Save the node
    $node->save();

    return $node;
  }

}