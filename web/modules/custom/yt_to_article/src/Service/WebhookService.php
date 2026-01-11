<?php

declare(strict_types=1);

namespace Drupal\yt_to_article\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\User;
use Psr\Log\LoggerInterface;
use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Autolink\AutolinkExtension;

/**
 * Service for processing webhook payloads from the article generation API.
 *
 * Handles incoming webhooks for article completion and failure events,
 * creating Drupal nodes from the generated content.
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
    LoggerInterface $logger,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
  }

  /**
   * Verifies webhook signature using HMAC-SHA256.
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

    $signature = str_replace('sha256=', '', $signature);
    $expectedSignature = hash_hmac('sha256', $payload, $secret);

    return hash_equals($expectedSignature, $signature);
  }

  /**
   * Processes article.completed webhook event.
   *
   * @param array $payload
   *   The webhook payload containing article data.
   *
   * @return array
   *   Result array with 'success' boolean and 'message' or 'error' string.
   */
  public function processArticleCompleted(array $payload): array {
    try {
      $requestId = $payload['request_id'] ?? '';

      if ($this->nodeExistsForRequestId($requestId)) {
        $this->logger->info('Node already exists for request ID: @id', ['@id' => $requestId]);
        return [
          'success' => TRUE,
          'message' => 'Node already exists',
        ];
      }

      $data = $payload['data'] ?? [];
      $htmlContent = $this->prepareHtmlContent($data);
      $textFormat = $this->determineTextFormat();

      $this->logger->info('Processing article webhook: @type format, @length chars', [
        '@type' => $data['content_type'] ?? 'markdown',
        '@length' => strlen($htmlContent),
      ]);

      $node = $this->createNodeFromPayload($data, $requestId, $htmlContent, $textFormat);

      $validationResult = $this->validateNode($node);
      if ($validationResult !== NULL) {
        return $validationResult;
      }

      $saveResult = $this->saveNode($node, $requestId);
      if ($saveResult !== NULL) {
        return $saveResult;
      }

      $this->updateAnonymousSession($requestId, $node);

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
   * Processes article.failed webhook event.
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

    $this->logger->error('Article generation failed for request @id: @error', [
      '@id' => $requestId,
      '@error' => $error,
    ]);

    return [
      'success' => TRUE,
      'message' => 'Failure logged',
    ];
  }

  /**
   * Prepares HTML content from payload data.
   *
   * Converts markdown to HTML if needed.
   *
   * @param array $data
   *   The payload data containing content and content_type.
   *
   * @return string
   *   The prepared HTML content.
   */
  protected function prepareHtmlContent(array $data): string {
    $content = $data['content'] ?? '';
    $contentType = $data['content_type'] ?? 'markdown';

    if ($contentType === 'markdown') {
      return $this->convertMarkdownToHtml($content);
    }

    return $content;
  }

  /**
   * Determines the appropriate text format for the node body.
   *
   * Checks available formats for the admin user and returns the best match.
   *
   * @return string
   *   The text format machine name (full_html, basic_html, or plain_text).
   */
  protected function determineTextFormat(): string {
    $user = User::load(1);
    $formats = filter_formats($user);

    if (isset($formats['full_html'])) {
      return 'full_html';
    }

    if (isset($formats['basic_html'])) {
      return 'basic_html';
    }

    return 'plain_text';
  }

  /**
   * Creates a node from webhook payload data.
   *
   * @param array $data
   *   The payload data.
   * @param string $requestId
   *   The unique request identifier.
   * @param string $htmlContent
   *   The prepared HTML content.
   * @param string $textFormat
   *   The text format to use for the body field.
   *
   * @return \Drupal\node\NodeInterface
   *   The created (unsaved) node entity.
   */
  protected function createNodeFromPayload(
    array $data,
    string $requestId,
    string $htmlContent,
    string $textFormat,
  ): NodeInterface {
    $videoInfo = $this->extractVideoData($data);
    $metadata = $data['metadata'] ?? [];

    $nodeValues = [
      'type' => 'youtube_article',
      'title' => $videoInfo['title'] ?? 'Untitled Article',
      'status' => 1,
      'uid' => 1,
    ];

    if (!empty($htmlContent)) {
      $nodeValues['body'] = [
        'value' => $htmlContent,
        'format' => $textFormat,
      ];
    }

    $node = Node::create($nodeValues);

    if (empty($htmlContent) && !empty($data['content'])) {
      $this->logger->warning('HTML content is empty after conversion');
    }

    $this->populateVideoFields($node, $videoInfo);
    $this->populateMetadataFields($node, $requestId, $metadata);
    $this->populateArticleTypeField($node, $data, $metadata);
    $this->populateCostFields($node, $data, $metadata);

    return $node;
  }

  /**
   * Extracts video information from payload data.
   *
   * @param array $data
   *   The payload data.
   *
   * @return array
   *   Video information with 'url' and 'title' keys.
   */
  protected function extractVideoData(array $data): array {
    return $data['video_info'] ?? [];
  }

  /**
   * Populates video-related fields on the node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   * @param array $videoInfo
   *   Video information from the payload.
   */
  protected function populateVideoFields(NodeInterface $node, array $videoInfo): void {
    $videoUrl = $videoInfo['url'] ?? '';

    if (empty($videoUrl)) {
      return;
    }

    if ($node->hasField('field_video_url')) {
      $node->set('field_video_url', $videoUrl);
    }

    if ($node->hasField('field_youtube_embed')) {
      $videoId = youtube_get_video_id($videoUrl);
      $node->set('field_youtube_embed', [
        'input' => $videoUrl,
        'video_id' => $videoId ?: '',
      ]);
    }
  }

  /**
   * Populates metadata fields on the node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   * @param string $requestId
   *   The unique request identifier.
   * @param array $metadata
   *   Metadata from the payload.
   */
  protected function populateMetadataFields(
    NodeInterface $node,
    string $requestId,
    array $metadata,
  ): void {
    $fieldMappings = [
      'field_request_id' => $requestId,
      'field_accuracy_score' => $metadata['accuracy_score'] ?? NULL,
      'field_word_count' => $metadata['word_count'] ?? NULL,
      'field_generation_time' => $metadata['generation_time_seconds'] ?? NULL,
    ];

    foreach ($fieldMappings as $fieldName => $value) {
      if ($value !== NULL && $node->hasField($fieldName)) {
        $node->set($fieldName, $value);
      }
    }
  }

  /**
   * Populates the article type taxonomy field.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   * @param array $data
   *   The payload data.
   * @param array $metadata
   *   Metadata from the payload.
   */
  protected function populateArticleTypeField(
    NodeInterface $node,
    array $data,
    array $metadata,
  ): void {
    if (!$node->hasField('field_article_type')) {
      return;
    }

    $articleLength = $data['article_length'] ?? $metadata['article_length'] ?? NULL;

    if (empty($articleLength)) {
      return;
    }

    $terms = $this->entityTypeManager->getStorage('taxonomy_term')
      ->loadByProperties(['name' => $articleLength, 'vid' => 'article_type']);

    if (!empty($terms)) {
      $term = reset($terms);
      $node->set('field_article_type', $term->id());
    }
    else {
      $this->logger->warning('Article type taxonomy term not found: @type', [
        '@type' => $articleLength,
      ]);
    }
  }

  /**
   * Populates cost tracking fields on the node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   * @param array $data
   *   The payload data.
   * @param array $metadata
   *   Metadata from the payload.
   */
  protected function populateCostFields(
    NodeInterface $node,
    array $data,
    array $metadata,
  ): void {
    $costSummary = $metadata['cost_summary'] ?? $data['cost_summary'] ?? [];

    if (empty($costSummary)) {
      return;
    }

    $this->populateSimpleCostFields($node, $costSummary);
    $this->populateTranscriptionCost($node, $costSummary);
    $this->populateCostBreakdown($node, $costSummary);
  }

  /**
   * Populates simple cost and token fields.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   * @param array $costSummary
   *   Cost summary data.
   */
  protected function populateSimpleCostFields(NodeInterface $node, array $costSummary): void {
    $fieldMappings = [
      'field_total_cost' => $costSummary['total_usd'] ?? NULL,
      'field_llm_cost' => $costSummary['llm_cost_usd'] ?? NULL,
      'field_input_tokens' => $costSummary['input_tokens'] ?? NULL,
      'field_output_tokens' => $costSummary['output_tokens'] ?? NULL,
      'field_llm_calls' => $costSummary['llm_calls'] ?? NULL,
    ];

    foreach ($fieldMappings as $fieldName => $value) {
      if ($value !== NULL && $node->hasField($fieldName)) {
        $node->set($fieldName, $value);
      }
    }
  }

  /**
   * Calculates and populates the transcription cost field.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   * @param array $costSummary
   *   Cost summary data.
   */
  protected function populateTranscriptionCost(NodeInterface $node, array $costSummary): void {
    if (!$node->hasField('field_transcription_cost')) {
      return;
    }

    $transcriptionCost = 0.0;
    $serviceCosts = $costSummary['service_costs'] ?? [];

    foreach ($serviceCosts as $service => $cost) {
      if (stripos($service, 'transcription') !== FALSE) {
        $transcriptionCost += $cost;
      }
    }

    if ($transcriptionCost > 0) {
      $node->set('field_transcription_cost', $transcriptionCost);
    }
  }

  /**
   * Populates the cost breakdown JSON field.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   * @param array $costSummary
   *   Cost summary data.
   */
  protected function populateCostBreakdown(NodeInterface $node, array $costSummary): void {
    if ($node->hasField('field_cost_breakdown')) {
      $node->set('field_cost_breakdown', json_encode($costSummary, JSON_PRETTY_PRINT));
    }
  }

  /**
   * Validates the node entity before saving.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity to validate.
   *
   * @return array|null
   *   Error result array if validation fails, NULL if valid.
   */
  protected function validateNode(NodeInterface $node): ?array {
    $violations = $node->validate();

    if ($violations->count() === 0) {
      return NULL;
    }

    $errors = [];
    foreach ($violations as $violation) {
      $fieldName = $violation->getPropertyPath();
      $errors[] = sprintf('[%s]: %s', $fieldName, $violation->getMessage());
    }

    $this->logger->error('Node validation failed: @errors', [
      '@errors' => implode(', ', $errors),
    ]);

    return [
      'success' => FALSE,
      'error' => 'Validation failed: ' . implode(', ', $errors),
    ];
  }

  /**
   * Saves the node entity.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity to save.
   * @param string $requestId
   *   The request ID for logging.
   *
   * @return array|null
   *   Error result array if save fails, NULL if successful.
   */
  protected function saveNode(NodeInterface $node, string $requestId): ?array {
    try {
      $node->save();

      $this->logger->info('Created node @nid for article request @request_id', [
        '@nid' => $node->id(),
        '@request_id' => $requestId,
      ]);

      return NULL;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to save node: @error', [
        '@error' => $e->getMessage(),
      ]);

      return [
        'success' => FALSE,
        'error' => 'Failed to save node: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Updates session data for anonymous users after node creation.
   *
   * @param string $requestId
   *   The request ID to match.
   * @param \Drupal\node\NodeInterface $node
   *   The created node.
   */
  protected function updateAnonymousSession(string $requestId, NodeInterface $node): void {
    $currentUser = \Drupal::currentUser();

    if (!$currentUser->isAnonymous()) {
      return;
    }

    $request = \Drupal::request();
    $session = $request->getSession();

    if (!$session) {
      return;
    }

    $anonymousData = $session->get('yt_to_article_anonymous', ['articles' => []]);

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

  /**
   * Checks if a node already exists for the given request ID.
   *
   * Provides idempotency by preventing duplicate node creation.
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
      $fieldDefinitions = \Drupal::service('entity_field.manager')
        ->getFieldDefinitions('node', 'youtube_article');

      if (!isset($fieldDefinitions['field_request_id'])) {
        return FALSE;
      }

      $storage = $this->entityTypeManager->getStorage('node');
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
   * Converts markdown content to HTML.
   *
   * Uses CommonMark with autolink extension for URL conversion.
   *
   * @param string $markdown
   *   The markdown content.
   *
   * @return string
   *   The converted HTML content.
   */
  protected function convertMarkdownToHtml(string $markdown): string {
    $environment = new Environment([
      'html_input' => 'strip',
      'allow_unsafe_links' => FALSE,
      'max_nesting_level' => 10,
    ]);

    $environment->addExtension(new CommonMarkCoreExtension());
    $environment->addExtension(new AutolinkExtension());

    $converter = new CommonMarkConverter([
      'html_input' => 'strip',
      'allow_unsafe_links' => FALSE,
    ], $environment);

    $html = $converter->convert($markdown)->getContent();

    // Clean up HTML for CKEditor - remove attributes from basic tags.
    $html = preg_replace('/<(p|h[1-6]|ul|ol|li|blockquote)\s+[^>]*>/', '<$1>', $html);

    // Ensure proper spacing between elements.
    $html = preg_replace('/(<\/(p|h[1-6]|ul|ol|blockquote)>)\s*/', "$1\n", $html);

    return trim($html);
  }

}
