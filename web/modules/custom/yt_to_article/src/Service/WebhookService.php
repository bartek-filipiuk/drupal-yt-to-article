<?php

declare(strict_types=1);

namespace Drupal\yt_to_article\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\Entity\Node;
use Psr\Log\LoggerInterface;
use Drupal\Core\Render\Markup;
use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Autolink\AutolinkExtension;

/**
 * Service for processing webhook payloads.
 */
class WebhookService {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * Constructs a WebhookService object.
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
   * Verify webhook signature.
   *
   * @param string $payload
   *   The raw webhook payload.
   * @param string $signature
   *   The signature from the header.
   * @param string $secret
   *   The webhook secret.
   *
   * @return bool
   *   TRUE if signature is valid, FALSE otherwise.
   */
  public function verifySignature(string $payload, string $signature, string $secret): bool {
    if (empty($signature) || empty($secret)) {
      $this->logger->warning('Missing signature or secret for webhook verification');
      return FALSE;
    }

    // Remove the 'sha256=' prefix if present
    $signature = str_replace('sha256=', '', $signature);

    // Calculate expected signature
    $expectedSignature = hash_hmac('sha256', $payload, $secret);

    // Use hash_equals to prevent timing attacks
    return hash_equals($expectedSignature, $signature);
  }

  /**
   * Process article.completed webhook event.
   *
   * @param array $payload
   *   The webhook payload.
   *
   * @return array
   *   Result array with success status and message.
   */
  public function processArticleCompleted(array $payload): array {
    try {
      $data = $payload['data'] ?? [];
      $requestId = $payload['request_id'] ?? '';

      // Check if we already processed this webhook (idempotency)
      if ($this->nodeExistsForRequestId($requestId)) {
        $this->logger->info('Node already exists for request ID: @id', ['@id' => $requestId]);
        return [
          'success' => TRUE,
          'message' => 'Node already exists',
        ];
      }

      // Extract data
      $content = $data['content'] ?? '';
      $contentType = $data['content_type'] ?? 'markdown';
      $videoInfo = $data['video_info'] ?? [];
      $metadata = $data['metadata'] ?? [];

      // Log basic request info
      $this->logger->info('Processing article webhook: @type format, @length chars', [
        '@type' => $contentType,
        '@length' => strlen($content)
      ]);

      // Always convert markdown to HTML since API only sends markdown for webhooks
      $htmlContent = $content;

      if ($contentType == 'markdown') {
        $htmlContent = $this->convertMarkdownToHtml($content);
      }


      // Check available text formats for the user who will own the node
      $user = \Drupal\user\Entity\User::load(1);
      $formats = filter_formats($user);
      $available_formats = array_keys($formats);

      // Try to use full_html, then basic_html, then plain_text
      if (isset($formats['full_html'])) {
        $textFormat = 'full_html';
      } elseif (isset($formats['basic_html'])) {
        $textFormat = 'basic_html';
      } else {
        // Fall back to plain_text which should always exist
        $textFormat = 'plain_text';
      }


      // Create the node with all fields at once
      $node_values = [
        'type' => 'youtube_article',
        'title' => $videoInfo['title'] ?? 'Untitled Article',
        'status' => 1, // Published
        'uid' => 1, // Admin user
      ];

      // Add body field to creation array if we have content
    if (!empty($htmlContent)) {
        // Ensure we have a valid text format before setting body
        if (empty($textFormat) || !isset($formats[$textFormat])) {
          $textFormat = 'plain_text';
        }

        $node_values['body'] = [
          'value' => $htmlContent,
          'format' => $textFormat,
        ];
      }

      $node = Node::create($node_values);

      // Verify body field was set if content exists
      if (empty($htmlContent) && !empty($content)) {
        $this->logger->warning('HTML content is empty after conversion');
      }

      // Add custom fields if they exist
      if ($node->hasField('field_video_url') && !empty($videoInfo['url'])) {
        $node->set('field_video_url', $videoInfo['url']);
      }

      if ($node->hasField('field_request_id') && !empty($requestId)) {
        $node->set('field_request_id', $requestId);
      }

      if ($node->hasField('field_accuracy_score') && isset($metadata['accuracy_score'])) {
        $node->set('field_accuracy_score', $metadata['accuracy_score']);
      }

      if ($node->hasField('field_word_count') && isset($metadata['word_count'])) {
        $node->set('field_word_count', $metadata['word_count']);
      }

      if ($node->hasField('field_generation_time') && isset($metadata['generation_time_seconds'])) {
        $node->set('field_generation_time', $metadata['generation_time_seconds']);
      }

      // Add cost tracking fields
      // Cost summary can be in metadata or directly in data
      $costSummary = $metadata['cost_summary'] ?? $data['cost_summary'] ?? [];


      if (!empty($costSummary)) {
        // Total cost
        if ($node->hasField('field_total_cost') && isset($costSummary['total_usd'])) {
          $node->set('field_total_cost', $costSummary['total_usd']);
        }

        // LLM cost
        if ($node->hasField('field_llm_cost') && isset($costSummary['llm_cost_usd'])) {
          $node->set('field_llm_cost', $costSummary['llm_cost_usd']);
        }

        // Transcription cost - sum all transcription-related services
        if ($node->hasField('field_transcription_cost')) {
          $transcriptionCost = 0.0;
          foreach ($costSummary['service_costs'] ?? [] as $service => $cost) {
            if (stripos($service, 'transcription') !== false) {
              $transcriptionCost += $cost;
            }
          }
          if ($transcriptionCost > 0) {
            $node->set('field_transcription_cost', $transcriptionCost);
          }
        }

        // Token counts
        if ($node->hasField('field_input_tokens') && isset($costSummary['input_tokens'])) {
          $node->set('field_input_tokens', $costSummary['input_tokens']);
        }

        if ($node->hasField('field_output_tokens') && isset($costSummary['output_tokens'])) {
          $node->set('field_output_tokens', $costSummary['output_tokens']);
        }

        // LLM calls
        if ($node->hasField('field_llm_calls') && isset($costSummary['llm_calls'])) {
          $node->set('field_llm_calls', $costSummary['llm_calls']);
        }

        // Full cost breakdown as JSON
        if ($node->hasField('field_cost_breakdown')) {
          $node->set('field_cost_breakdown', json_encode($costSummary, JSON_PRETTY_PRINT));
        }

      }


      // Validate node before saving
      $violations = $node->validate();
      if ($violations->count() > 0) {
        $errors = [];
        foreach ($violations as $violation) {
          $field_name = $violation->getPropertyPath();
          $error_msg = sprintf('[%s]: %s', $field_name, $violation->getMessage());
          $errors[] = $error_msg;

        }
        $this->logger->error('Node validation failed: @errors', [
          '@errors' => implode(', ', $errors)
        ]);

        return [
          'success' => FALSE,
          'error' => 'Validation failed: ' . implode(', ', $errors),
        ];
      }

      // Save the node
      try {
        $node->save();
      } catch (\Exception $e) {
        $this->logger->error('Failed to save node: @error', [
          '@error' => $e->getMessage()
        ]);

        return [
          'success' => FALSE,
          'error' => 'Failed to save node: ' . $e->getMessage(),
        ];
      }

      $this->logger->info('Created node @nid for article request @request_id', [
        '@nid' => $node->id(),
        '@request_id' => $requestId,
      ]);

      // Update session for anonymous users
      $currentUser = \Drupal::currentUser();
      if ($currentUser->isAnonymous()) {
        $request = \Drupal::request();
        $session = $request->getSession();
        
        if ($session) {
          $anonymousData = $session->get('yt_to_article_anonymous', ['articles' => []]);
          
          // Update the article with node information
          foreach ($anonymousData['articles'] as &$article) {
            if (isset($article['request_id']) && $article['request_id'] === $requestId) {
              $article['node_id'] = $node->id();
              $article['title'] = $node->label();
              break;
            }
          }
          
          $session->set('yt_to_article_anonymous', $anonymousData);
          
          $this->logger->info('Updated session for anonymous user with node @nid', [
            '@nid' => $node->id(),
          ]);
        }
      }

      return [
        'success' => TRUE,
        'message' => 'Article node created successfully',
        'node_id' => $node->id(),
      ];

    }
    catch (\Exception $e) {
      $this->logger->error('Error creating node from webhook: @error', [
        '@error' => $e->getMessage(),
      ]);

      return [
        'success' => FALSE,
        'error' => 'Failed to create node: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Process article.failed webhook event.
   *
   * @param array $payload
   *   The webhook payload.
   *
   * @return array
   *   Result array with success status and message.
   */
  public function processArticleFailed(array $payload): array {
    $data = $payload['data'] ?? [];
    $requestId = $payload['request_id'] ?? '';
    $error = $data['error'] ?? 'Unknown error';
    $videoUrl = $data['video_url'] ?? '';

    $this->logger->error('Article generation failed for request @id: @error', [
      '@id' => $requestId,
      '@error' => $error,
    ]);

    // You could create a failed article node or send notifications here

    return [
      'success' => TRUE,
      'message' => 'Failure logged',
    ];
  }

  /**
   * Check if a node already exists for the given request ID.
   *
   * @param string $requestId
   *   The request ID to check.
   *
   * @return bool
   *   TRUE if node exists, FALSE otherwise.
   */
  protected function nodeExistsForRequestId(string $requestId): bool {
    if (empty($requestId)) {
      return FALSE;
    }

    try {
      $storage = $this->entityTypeManager->getStorage('node');

      // First check if the field exists
      $fieldDefinitions = \Drupal::service('entity_field.manager')
        ->getFieldDefinitions('node', 'youtube_article');

      if (!isset($fieldDefinitions['field_request_id'])) {
        // Field doesn't exist, so no duplicate checking
        return FALSE;
      }

      $query = $storage->getQuery()
        ->condition('type', 'youtube_article')
        ->condition('field_request_id', $requestId)
        ->accessCheck(FALSE)
        ->range(0, 1);

      $nids = $query->execute();

      return !empty($nids);
    }
    catch (\Exception $e) {
      $this->logger->error('Error checking for existing node: @error', [
        '@error' => $e->getMessage(),
      ]);
      return FALSE;
    }
  }

  /**
   * Convert markdown to HTML.
   *
   * @param string $markdown
   *   The markdown content.
   *
   * @return string
   *   The HTML content.
   */
  protected function convertMarkdownToHtml(string $markdown): string {
    // Configure the Environment with basic extensions
    $environment = new Environment([
      'html_input' => 'strip',
      'allow_unsafe_links' => false,
      'max_nesting_level' => 10,
    ]);

    // Add the CommonMark core extension (for basic markdown)
    $environment->addExtension(new CommonMarkCoreExtension());

    // Add autolink extension to automatically convert URLs to links
    $environment->addExtension(new AutolinkExtension());

    // Create the converter
    $converter = new CommonMarkConverter([
      'html_input' => 'strip',
      'allow_unsafe_links' => false,
    ], $environment);

    // Convert markdown to HTML
    $html = $converter->convert($markdown)->getContent();

    // Clean up the HTML for CKEditor
    // Remove any attributes from basic tags
    $html = preg_replace('/<(p|h[1-6]|ul|ol|li|blockquote)\s+[^>]*>/', '<$1>', $html);

    // Ensure proper spacing between elements
    $html = preg_replace('/(<\/(p|h[1-6]|ul|ol|blockquote)>)\s*/', "$1\n", $html);

    return trim($html);
  }

}
