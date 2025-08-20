<?php

declare(strict_types=1);

namespace Drupal\yt_to_article_admin\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

/**
 * Service for fetching user metrics from the API.
 */
class ApiUserService {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Cache tag for user data.
   */
  const CACHE_TAG = 'yt_to_article_admin:users';

  /**
   * Cache TTL in seconds (5 minutes).
   */
  const CACHE_TTL = 300;

  /**
   * Constructs an ApiUserService object.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(
    ClientInterface $http_client,
    ConfigFactoryInterface $config_factory,
    CacheBackendInterface $cache,
    LoggerInterface $logger
  ) {
    $this->httpClient = $http_client;
    $this->configFactory = $config_factory;
    $this->cache = $cache;
    $this->logger = $logger;
  }

  /**
   * Get the API base URL from settings.
   *
   * @return string
   *   The API base URL.
   */
  protected function getApiBaseUrl(): string {
    // First try to get from settings.php
    $api_url = \Drupal::config('yt_to_article.settings')->get('api_url');
    if ($api_url) {
      return rtrim($api_url, '/');
    }
    
    // Fallback to default
    return 'http://localhost:8888/api/v1';
  }

  /**
   * Get the admin API token from settings.
   *
   * @return string|null
   *   The admin API token or null if not configured.
   */
  protected function getAdminToken(): ?string {
    // Try to get from settings.php first (more secure)
    $settings_token = \Drupal::config('yt_to_article_admin.settings')->get('api_admin_token');
    if ($settings_token) {
      return $settings_token;
    }

    // Fallback to module config
    $config = $this->configFactory->get('yt_to_article_admin.settings');
    return $config->get('api_admin_token');
  }

  /**
   * Fetch users from the API with pagination.
   *
   * @param int $offset
   *   The offset for pagination.
   * @param int $limit
   *   The number of users to fetch.
   * @param string|null $search
   *   Optional search term for username/email.
   * @param bool $bypass_cache
   *   Whether to bypass the cache.
   *
   * @return array|null
   *   The API response data or null on error.
   */
  public function fetchUsers(int $offset = 0, int $limit = 20, ?string $search = NULL, bool $bypass_cache = FALSE): ?array {
    $cache_key = 'api_users:' . md5(serialize([$offset, $limit, $search]));
    
    // Check cache first unless bypassing
    if (!$bypass_cache) {
      $cached = $this->cache->get($cache_key);
      if ($cached && $cached->data) {
        return $cached->data;
      }
    }

    $admin_token = $this->getAdminToken();
    if (!$admin_token) {
      $this->logger->error('Admin API token not configured for user metrics');
      return NULL;
    }

    try {
      $api_url = $this->getApiBaseUrl();
      $query_params = [
        'offset' => $offset,
        'limit' => $limit,
      ];
      
      if ($search) {
        $query_params['search'] = $search;
      }

      $response = $this->httpClient->request('GET', $api_url . '/admin/users', [
        'headers' => [
          'Authorization' => 'Bearer ' . $admin_token,
          'Accept' => 'application/json',
        ],
        'query' => $query_params,
        'timeout' => 30,
      ]);

      $data = json_decode($response->getBody()->getContents(), TRUE);
      
      // Cache the response
      $this->cache->set($cache_key, $data, time() + self::CACHE_TTL, [self::CACHE_TAG]);
      
      return $data;
    }
    catch (GuzzleException $e) {
      $this->logger->error('Failed to fetch users from API: @error', [
        '@error' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * Fetch user summary statistics from the API.
   *
   * @param bool $bypass_cache
   *   Whether to bypass the cache.
   *
   * @return array|null
   *   The summary data or null on error.
   */
  public function fetchUsersSummary(bool $bypass_cache = FALSE): ?array {
    $cache_key = 'api_users_summary';
    
    // Check cache first unless bypassing
    if (!$bypass_cache) {
      $cached = $this->cache->get($cache_key);
      if ($cached && $cached->data) {
        return $cached->data;
      }
    }

    $admin_token = $this->getAdminToken();
    if (!$admin_token) {
      $this->logger->error('Admin API token not configured for user metrics');
      return NULL;
    }

    try {
      $api_url = $this->getApiBaseUrl();
      
      $response = $this->httpClient->request('GET', $api_url . '/admin/users/summary', [
        'headers' => [
          'Authorization' => 'Bearer ' . $admin_token,
          'Accept' => 'application/json',
        ],
        'timeout' => 30,
      ]);

      $data = json_decode($response->getBody()->getContents(), TRUE);
      
      // Cache the response
      $this->cache->set($cache_key, $data, time() + self::CACHE_TTL, [self::CACHE_TAG]);
      
      return $data;
    }
    catch (GuzzleException $e) {
      $this->logger->error('Failed to fetch users summary from API: @error', [
        '@error' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * Fetch billing information for a specific user.
   *
   * @param int $user_id
   *   The API user ID.
   * @param bool $bypass_cache
   *   Whether to bypass the cache.
   *
   * @return array|null
   *   The billing data or null on error.
   */
  public function fetchUserBilling(int $user_id, bool $bypass_cache = FALSE): ?array {
    $cache_key = 'api_user_billing:' . $user_id;
    
    // Check cache first unless bypassing
    if (!$bypass_cache) {
      $cached = $this->cache->get($cache_key);
      if ($cached && $cached->data) {
        return $cached->data;
      }
    }

    $admin_token = $this->getAdminToken();
    if (!$admin_token) {
      $this->logger->error('Admin API token not configured for user metrics');
      return NULL;
    }

    try {
      $api_url = $this->getApiBaseUrl();
      
      $response = $this->httpClient->request('GET', $api_url . '/admin/users/' . $user_id . '/billing', [
        'headers' => [
          'Authorization' => 'Bearer ' . $admin_token,
          'Accept' => 'application/json',
        ],
        'timeout' => 30,
      ]);

      $data = json_decode($response->getBody()->getContents(), TRUE);
      
      // Cache the response (shorter TTL for billing data)
      $this->cache->set($cache_key, $data, time() + 60, [self::CACHE_TAG]);
      
      return $data;
    }
    catch (GuzzleException $e) {
      $this->logger->error('Failed to fetch user billing from API: @error', [
        '@error' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * Fetch recent transactions for a specific user.
   *
   * @param int $user_id
   *   The API user ID.
   * @param int $limit
   *   Number of transactions to fetch.
   * @param bool $bypass_cache
   *   Whether to bypass the cache.
   *
   * @return array|null
   *   The transactions data or null on error.
   */
  public function fetchUserTransactions(int $user_id, int $limit = 5, bool $bypass_cache = FALSE): ?array {
    $cache_key = 'api_user_transactions:' . $user_id . ':' . $limit;
    
    // Check cache first unless bypassing
    if (!$bypass_cache) {
      $cached = $this->cache->get($cache_key);
      if ($cached && $cached->data) {
        return $cached->data;
      }
    }

    $admin_token = $this->getAdminToken();
    if (!$admin_token) {
      $this->logger->error('Admin API token not configured for user metrics');
      return NULL;
    }

    try {
      $api_url = $this->getApiBaseUrl();
      
      $response = $this->httpClient->request('GET', $api_url . '/admin/users/' . $user_id . '/transactions', [
        'headers' => [
          'Authorization' => 'Bearer ' . $admin_token,
          'Accept' => 'application/json',
        ],
        'query' => [
          'limit' => $limit,
        ],
        'timeout' => 30,
      ]);

      $data = json_decode($response->getBody()->getContents(), TRUE);
      
      // Cache the response
      $this->cache->set($cache_key, $data, time() + 120, [self::CACHE_TAG]);
      
      return $data;
    }
    catch (GuzzleException $e) {
      $this->logger->error('Failed to fetch user transactions from API: @error', [
        '@error' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * Clear all cached user data.
   */
  public function clearCache(): void {
    $this->cache->invalidateTags([self::CACHE_TAG]);
  }

}