<?php

declare(strict_types=1);

namespace Drupal\yt_to_article\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Site\Settings;
use Drupal\Component\Datetime\TimeInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Drupal\yt_to_article\Exception\ApiException;
use Drupal\yt_to_article\Exception\AuthenticationException;
use Drupal\yt_to_article\Exception\RateLimitException;
use Drupal\yt_to_article\Exception\InsufficientFundsException;
use Drupal\yt_to_article\ValueObject\ArticleResponse;

/**
 * Service for interacting with the PocketFlow API.
 */
final class YtToArticleApiClient {
  
  /**
   * API endpoint for article generation.
   */
  private const ARTICLE_ENDPOINT = '/article/';
  
  /**
   * Cache tag for API responses.
   */
  private const CACHE_TAG = 'yt_to_article:api';
  
  /**
   * Default request timeout in seconds.
   */
  private const DEFAULT_TIMEOUT = 30;
  
  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly ClientInterface $httpClient,
    private readonly LoggerInterface $logger,
    private readonly CacheBackendInterface $cache,
    private readonly TimeInterface $time,
  ) {}
  
  /**
   * Generate an article from a YouTube URL.
   *
   * @param string $youtubeUrl The YouTube URL to convert.
   * @param array $options Additional options for the API request.
   *
   * @return ArticleResponse The API response.
   *
   * @throws ApiException
   * @throws AuthenticationException
   * @throws RateLimitException
   */
  public function generateArticle(string $youtubeUrl, array $options = []): ArticleResponse {
    $this->validateYoutubeUrl($youtubeUrl);
    
    $requestData = [
      'youtube_url' => $youtubeUrl,
    ];
    
    // Add config if provided
    if (!empty($options['config'])) {
      $requestData['config'] = $options['config'];
    }
    
    // Add webhook configuration if provided
    if (!empty($options['webhook_url'])) {
      $requestData['webhook_url'] = $options['webhook_url'];
      
      // Add webhook config if provided, otherwise use defaults
      $requestData['webhook_config'] = $options['webhook_config'] ?? [
        'content_type' => 'markdown',
        'include_metadata' => true,
      ];
    }
    
    try {
      $response = $this->httpClient->request('POST', $this->getApiUrl() . self::ARTICLE_ENDPOINT, [
        'headers' => $this->getHeaders(),
        'json' => $requestData,
        'timeout' => $options['timeout'] ?? self::DEFAULT_TIMEOUT,
      ]);
      
      $statusCode = $response->getStatusCode();
      $body = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
      
      return match ($statusCode) {
        200, 201, 202 => ArticleResponse::fromArray($body['data'] ?? $body),
        401 => throw new AuthenticationException($body['detail'] ?? 'Invalid API token'),
        402 => throw new InsufficientFundsException(
          message: is_array($body['detail']) ? ($body['detail']['detail'] ?? 'Insufficient funds') : ($body['detail'] ?? 'Insufficient funds'),
          currentCredits: is_array($body['detail']) ? ($body['detail']['current_credits'] ?? 0) : ($body['current_credits'] ?? 0),
          currentBalance: is_array($body['detail']) ? ($body['detail']['current_balance'] ?? 0.0) : ($body['current_balance'] ?? 0.0),
          minimumBalance: is_array($body['detail']) ? ($body['detail']['minimum_balance'] ?? 0.30) : ($body['minimum_balance'] ?? 0.30)
        ),
        429 => throw new RateLimitException(
          message: $body['detail'] ?? 'Rate limit exceeded',
          retryAfter: $this->parseRetryAfter($response->getHeader('Retry-After'))
        ),
        default => throw new ApiException(
          message: $body['detail'] ?? 'Unexpected API response',
          code: $statusCode,
          context: ['response' => $body]
        ),
      };
      
    } catch (GuzzleException $e) {
      $this->logger->error('API request failed: {message}', [
        'message' => $e->getMessage(),
        'youtube_url' => $youtubeUrl,
      ]);
      
      throw new ApiException(
        message: 'Failed to connect to API: ' . $e->getMessage(),
        previous: $e
      );
    } catch (\JsonException $e) {
      throw new ApiException(
        message: 'Invalid JSON response from API',
        previous: $e
      );
    }
  }
  
  /**
   * Get the status of an article generation request.
   *
   * @param string $requestId The request ID to check.
   *
   * @return ArticleResponse The current status.
   *
   * @throws ApiException
   */
  public function getArticleStatus(string $requestId): ArticleResponse {
    $cacheKey = 'yt_to_article:status:' . $requestId;
    
    if ($cached = $this->cache->get($cacheKey)) {
      return $cached->data;
    }
    
    try {
      $response = $this->httpClient->request('GET', $this->getApiUrl() . self::ARTICLE_ENDPOINT . $requestId, [
        'headers' => $this->getHeaders(),
        'timeout' => self::DEFAULT_TIMEOUT,
      ]);
      
      $body = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
      $articleResponse = ArticleResponse::fromArray($body);
      
      // Cache completed responses longer
      $cacheExpiry = $articleResponse->isComplete() 
        ? $this->time->getRequestTime() + 3600 
        : $this->time->getRequestTime() + 60;
      
      $this->cache->set($cacheKey, $articleResponse, $cacheExpiry, [self::CACHE_TAG]);
      
      return $articleResponse;
      
    } catch (GuzzleException $e) {
      throw new ApiException(
        message: 'Failed to get article status: ' . $e->getMessage(),
        previous: $e
      );
    }
  }
  
  /**
   * Download the generated article as markdown.
   *
   * @param string $requestId The request ID.
   *
   * @return string The markdown content.
   *
   * @throws ApiException
   */
  public function downloadArticleMarkdown(string $requestId): string {
    try {
      $response = $this->httpClient->request('GET', $this->getApiUrl() . self::ARTICLE_ENDPOINT . $requestId . '/markdown', [
        'headers' => $this->getHeaders(),
        'timeout' => self::DEFAULT_TIMEOUT,
      ]);
      
      return (string) $response->getBody();
      
    } catch (GuzzleException $e) {
      throw new ApiException(
        message: 'Failed to download article: ' . $e->getMessage(),
        previous: $e
      );
    }
  }
  
  /**
   * Get queue status information.
   *
   * @return array Queue status data.
   *
   * @throws ApiException
   */
  public function getQueueStatus(): array {
    try {
      $response = $this->httpClient->request('GET', $this->getApiUrl() . '/article/queue-status', [
        'headers' => $this->getHeaders(),
        'timeout' => 10,
      ]);
      
      return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
      
    } catch (GuzzleException | \JsonException $e) {
      throw new ApiException(
        message: 'Failed to get queue status: ' . $e->getMessage(),
        previous: $e
      );
    }
  }
  
  /**
   * Validate a YouTube URL.
   *
   * @throws \InvalidArgumentException
   */
  private function validateYoutubeUrl(string $url): void {
    $pattern = '/^(https?:\/\/)?(www\.)?(youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)[\w-]+(&[\w=]*)?$/i';
    
    if (!preg_match($pattern, $url)) {
      throw new \InvalidArgumentException('Invalid YouTube URL format');
    }
  }
  
  /**
   * Get API headers including authentication.
   */
  private function getHeaders(): array {
    $apiToken = $this->getApiToken();
    
    return [
      'Authorization' => 'Bearer ' . $apiToken,
      'Accept' => 'application/json',
      'Content-Type' => 'application/json',
      'User-Agent' => 'Drupal/YtToArticle/1.0',
    ];
  }
  
  /**
   * Get the API base URL.
   */
  private function getApiUrl(): string {
    $settings = Settings::get('yt_to_article', []);
    $configUrl = $this->configFactory->get('yt_to_article.settings')->get('api_url');
    
    return $settings['api_url'] ?? $configUrl ?? 'http://localhost:8000/api/v1';
  }
  
  /**
   * Get the API token.
   *
   * @throws AuthenticationException
   */
  private function getApiToken(): string {
    $settings = Settings::get('yt_to_article', []);
    $token = $settings['api_token'] ?? null;
    
    if (!$token) {
      throw new AuthenticationException('API token not configured in settings.php');
    }
    
    return $token;
  }
  
  /**
   * Parse the Retry-After header value.
   */
  private function parseRetryAfter(array $header): ?int {
    if (empty($header)) {
      return null;
    }
    
    $value = $header[0];
    
    // If it's a number, it's seconds
    if (is_numeric($value)) {
      return (int) $value;
    }
    
    // If it's a date, calculate seconds from now
    if ($timestamp = strtotime($value)) {
      return max(0, $timestamp - $this->time->getRequestTime());
    }
    
    return null;
  }
  
  /**
   * Get the webhook URL from configuration.
   *
   * @return string|null The webhook URL or null if not configured.
   */
  public function getWebhookUrl(): ?string {
    $settings = Settings::get('yt_to_article', []);
    return $settings['webhook_url'] ?? null;
  }
}