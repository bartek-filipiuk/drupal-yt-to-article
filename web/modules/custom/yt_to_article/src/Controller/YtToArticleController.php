<?php

declare(strict_types=1);

namespace Drupal\yt_to_article\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\yt_to_article\Service\YtToArticleApiClient;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controller for YT to Article module.
 */
final class YtToArticleController extends ControllerBase implements ContainerInjectionInterface {
  
  /**
   * Constructor with dependency injection.
   */
  public function __construct(
    private readonly YtToArticleApiClient $apiClient,
    private readonly EntityTypeManagerInterface $entityManager,
  ) {}
  
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      apiClient: $container->get('yt_to_article.api_client'),
      entityManager: $container->get('entity_type.manager'),
    );
  }
  
  /**
   * Get the status of an article generation request.
   *
   * @param string $request_id
   *   The request ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with status information.
   */
  public function status(string $request_id): JsonResponse {
    try {
      $status = $this->apiClient->getArticleStatus($request_id);
      
      return new JsonResponse([
        'request_id' => $status->requestId,
        'status' => $status->status,
        'youtube_url' => $status->youtubeUrl,
        'error' => $status->error,
        'metadata' => $status->metadata,
      ]);
      
    } catch (\Exception $e) {
      return new JsonResponse([
        'error' => $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Get the node URL by request ID.
   *
   * @param string $request_id
   *   The request ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with node URL or error.
   */
  public function getNodeByRequestId(string $request_id): JsonResponse {
    try {
      // Check if the field exists
      $fieldDefinitions = \Drupal::service('entity_field.manager')
        ->getFieldDefinitions('node', 'youtube_article');
      
      if (!isset($fieldDefinitions['field_request_id'])) {
        return new JsonResponse([
          'error' => 'Request ID field not configured',
        ], 500);
      }

      // Query for nodes with this request_id
      $storage = $this->entityManager->getStorage('node');
      $query = $storage->getQuery()
        ->condition('type', 'youtube_article')
        ->condition('field_request_id', $request_id)
        ->accessCheck(TRUE)
        ->range(0, 1);
      
      $nids = $query->execute();
      
      if (empty($nids)) {
        return new JsonResponse([
          'found' => FALSE,
          'message' => 'Article not found. It may still be processing.',
        ], 404);
      }
      
      // Load the node
      $nid = reset($nids);
      $node = $storage->load($nid);
      
      if (!$node) {
        return new JsonResponse([
          'error' => 'Failed to load node',
        ], 500);
      }
      
      // Generate the node URL
      $url = Url::fromRoute('entity.node.canonical', ['node' => $nid]);
      $path = $url->toString();
      
      return new JsonResponse([
        'found' => TRUE,
        'nid' => $nid,
        'url' => $path,
        'title' => $node->label(),
      ]);
      
    } catch (\Exception $e) {
      return new JsonResponse([
        'error' => $e->getMessage(),
      ], 500);
    }
  }
}