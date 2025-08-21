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

  /**
   * Create a new user via the API.
   *
   * @param string $email
   *   User email address.
   * @param string $username
   *   Username.
   * @param string|null $external_user_id
   *   External user ID.
   * @param int $initial_credits
   *   Initial credits to assign.
   * @param float $initial_balance
   *   Initial balance to assign.
   *
   * @return array|null
   *   The created user data or null on error.
   */
  public function createUser(string $email, string $username, ?string $external_user_id = NULL, int $initial_credits = 0, float $initial_balance = 0.00): ?array {
    $admin_token = $this->getAdminToken();
    if (!$admin_token) {
      $this->logger->error('Admin API token not configured for user creation');
      return NULL;
    }

    try {
      $api_url = $this->getApiBaseUrl();
      
      // Generate external_user_id if not provided (required field in API)
      if (empty($external_user_id)) {
        $external_user_id = 'drupal_' . uniqid();
      }
      
      $data = [
        'email' => $email,
        'username' => $username,
        'external_user_id' => $external_user_id,
      ];
      
      // Create the user
      $response = $this->httpClient->request('POST', $api_url . '/admin/users', [
        'headers' => [
          'Authorization' => 'Bearer ' . $admin_token,
          'Content-Type' => 'application/json',
          'Accept' => 'application/json',
        ],
        'json' => $data,
        'timeout' => 30,
      ]);

      $user_data = json_decode($response->getBody()->getContents(), TRUE);
      
      // If user created successfully and we have initial credits/balance, update them
      if ($user_data && isset($user_data['id'])) {
        if ($initial_credits > 0) {
          $this->updateUserCredits($user_data['id'], $initial_credits);
        }
        if ($initial_balance > 0) {
          $this->updateUserBalance($user_data['id'], $initial_balance);
        }
      }
      
      // Clear cache to reflect new user
      $this->clearCache();
      
      return $user_data;
    }
    catch (GuzzleException $e) {
      $this->logger->error('Failed to create user via API: @error', [
        '@error' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * Create an API token for a user.
   *
   * @param int $user_id
   *   The user ID.
   * @param string $name
   *   Token name/description.
   * @param string $tier
   *   Token tier (basic/premium).
   * @param bool $is_admin
   *   Whether to grant admin privileges.
   *
   * @return array|null
   *   The token data including the full token (only shown once) or null on error.
   */
  public function createUserToken(int $user_id, string $name, string $tier = 'basic', bool $is_admin = FALSE): ?array {
    $admin_token = $this->getAdminToken();
    if (!$admin_token) {
      $this->logger->error('Admin API token not configured for token creation');
      return NULL;
    }

    try {
      $api_url = $this->getApiBaseUrl();
      
      $response = $this->httpClient->request('POST', $api_url . '/admin/users/' . $user_id . '/tokens', [
        'headers' => [
          'Authorization' => 'Bearer ' . $admin_token,
          'Content-Type' => 'application/json',
          'Accept' => 'application/json',
        ],
        'json' => [
          'name' => $name,
          'tier' => $tier,
          'is_admin' => $is_admin,
        ],
        'timeout' => 30,
      ]);

      return json_decode($response->getBody()->getContents(), TRUE);
    }
    catch (GuzzleException $e) {
      $this->logger->error('Failed to create user token via API: @error', [
        '@error' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * Update user credits.
   *
   * @param int $user_id
   *   The user ID.
   * @param int $credits
   *   The new credit amount.
   *
   * @return array|null
   *   The updated billing info or null on error.
   */
  public function updateUserCredits(int $user_id, int $credits): ?array {
    $admin_token = $this->getAdminToken();
    if (!$admin_token) {
      $this->logger->error('Admin API token not configured for credit update');
      return NULL;
    }

    try {
      $api_url = $this->getApiBaseUrl();
      
      $response = $this->httpClient->request('PUT', $api_url . '/admin/users/' . $user_id . '/credits', [
        'headers' => [
          'Authorization' => 'Bearer ' . $admin_token,
          'Content-Type' => 'application/json',
          'Accept' => 'application/json',
        ],
        'json' => [
          'credits' => $credits,
        ],
        'timeout' => 30,
      ]);

      // Clear cache for this user
      $this->cache->invalidateTags([self::CACHE_TAG]);
      
      return json_decode($response->getBody()->getContents(), TRUE);
    }
    catch (GuzzleException $e) {
      $this->logger->error('Failed to update user credits via API: @error', [
        '@error' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * Update user balance (set to specific amount).
   *
   * @param int $user_id
   *   The user ID.
   * @param float $balance
   *   The new balance amount.
   *
   * @return array|null
   *   The updated billing info or null on error.
   */
  public function updateUserBalance(int $user_id, float $balance): ?array {
    $admin_token = $this->getAdminToken();
    if (!$admin_token) {
      $this->logger->error('Admin API token not configured for balance update');
      return NULL;
    }

    try {
      $api_url = $this->getApiBaseUrl();
      
      $response = $this->httpClient->request('PUT', $api_url . '/admin/users/' . $user_id . '/balance', [
        'headers' => [
          'Authorization' => 'Bearer ' . $admin_token,
          'Content-Type' => 'application/json',
          'Accept' => 'application/json',
        ],
        'json' => [
          'balance' => $balance,
        ],
        'timeout' => 30,
      ]);

      // Clear cache for this user
      $this->cache->invalidateTags([self::CACHE_TAG]);
      
      return json_decode($response->getBody()->getContents(), TRUE);
    }
    catch (GuzzleException $e) {
      $this->logger->error('Failed to update user balance via API: @error', [
        '@error' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * Add to user balance (increment).
   *
   * @param int $user_id
   *   The user ID.
   * @param float $amount
   *   The amount to add.
   * @param string|null $description
   *   Optional description for the transaction.
   *
   * @return array|null
   *   The updated billing info or null on error.
   */
  public function addToUserBalance(int $user_id, float $amount, ?string $description = NULL): ?array {
    $admin_token = $this->getAdminToken();
    if (!$admin_token) {
      $this->logger->error('Admin API token not configured for balance addition');
      return NULL;
    }

    try {
      $api_url = $this->getApiBaseUrl();
      
      $data = ['amount' => $amount];
      if ($description) {
        $data['description'] = $description;
      }
      
      $response = $this->httpClient->request('POST', $api_url . '/admin/users/' . $user_id . '/balance/add', [
        'headers' => [
          'Authorization' => 'Bearer ' . $admin_token,
          'Content-Type' => 'application/json',
          'Accept' => 'application/json',
        ],
        'json' => $data,
        'timeout' => 30,
      ]);

      // Clear cache for this user
      $this->cache->invalidateTags([self::CACHE_TAG]);
      
      return json_decode($response->getBody()->getContents(), TRUE);
    }
    catch (GuzzleException $e) {
      $this->logger->error('Failed to add to user balance via API: @error', [
        '@error' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

}