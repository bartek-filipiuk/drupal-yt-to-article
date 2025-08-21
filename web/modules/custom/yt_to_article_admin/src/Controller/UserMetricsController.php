<?php

declare(strict_types=1);

namespace Drupal\yt_to_article_admin\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\yt_to_article_admin\Service\ApiUserService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for user metrics display.
 */
class UserMetricsController extends ControllerBase {

  /**
   * The API user service.
   *
   * @var \Drupal\yt_to_article_admin\Service\ApiUserService
   */
  protected $apiUserService;

  /**
   * Constructs a UserMetricsController object.
   *
   * @param \Drupal\yt_to_article_admin\Service\ApiUserService $api_user_service
   *   The API user service.
   */
  public function __construct(ApiUserService $api_user_service) {
    $this->apiUserService = $api_user_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('yt_to_article_admin.api_user_service')
    );
  }

  /**
   * Display the user metrics page.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return array
   *   The render array.
   */
  public function metrics(Request $request): array {
    // Get query parameters
    $offset = (int) $request->query->get('offset', 0);
    $limit = (int) $request->query->get('limit', 20);
    $search = $request->query->get('search');

    // Fetch summary data
    $summary = $this->apiUserService->fetchUsersSummary();
    
    // Fetch users with pagination
    $users_data = $this->apiUserService->fetchUsers($offset, $limit, $search);
    
    // Prepare users for display
    $users = [];
    if ($users_data && isset($users_data['users'])) {
      foreach ($users_data['users'] as $user) {
        $users[] = [
          'id' => $user['id'],
          'username' => $user['username'],
          'email' => $user['email'],
          'credits' => $user['credits'] ?? 0,
          'balance' => number_format($user['balance'] ?? 0, 2),
          'token_count' => $user['token_count'] ?? 0,
          'created' => $this->formatDate($user['created_at']),
          'status' => $user['is_active'] ? $this->t('Active') : $this->t('Inactive'),
          'view_details_url' => Url::fromRoute('yt_to_article_admin.user_details', [
            'user_id' => $user['id'],
          ])->toString(),
        ];
      }
    }

    // Build the render array
    $build = [
      '#theme' => 'yt_user_metrics',
      '#summary' => $summary,
      '#users' => $users,
      '#search' => $search,
      '#pagination' => [
        'offset' => $offset,
        'limit' => $limit,
        'total' => $users_data['total'] ?? 0,
        'has_more' => $users_data['has_more'] ?? FALSE,
      ],
      '#refresh_form' => $this->formBuilder()->getForm('Drupal\yt_to_article_admin\Form\UserMetricsRefreshForm'),
      '#attached' => [
        'library' => [
          'yt_to_article_admin/user_metrics',
        ],
      ],
      '#cache' => [
        'max-age' => 300, // 5 minutes
        'tags' => ['yt_to_article_admin:users'],
      ],
    ];

    // Add error message if data couldn't be fetched
    if (!$users_data) {
      $this->messenger()->addError($this->t('Unable to fetch user data from API. Please check the configuration.'));
    }

    return $build;
  }

  /**
   * Display detailed information for a specific user.
   *
   * @param int $user_id
   *   The API user ID.
   *
   * @return array
   *   The render array.
   */
  public function userDetails(int $user_id): array {
    // Fetch user billing info
    $billing = $this->apiUserService->fetchUserBilling($user_id);
    
    // Fetch recent transactions
    $transactions = $this->apiUserService->fetchUserTransactions($user_id, 10);
    
    // Format transactions for display
    $formatted_transactions = [];
    if ($transactions && is_array($transactions)) {
      foreach ($transactions as $transaction) {
        $formatted_transactions[] = [
          'request_id' => $transaction['request_id'] ?? '',
          'type' => $transaction['transaction_type'] ?? '',
          'amount' => number_format($transaction['amount'] ?? 0, 4),
          'credits_after' => $transaction['credits_after'] ?? 0,
          'balance_after' => number_format($transaction['balance_after'] ?? 0, 2),
          'created' => $this->formatDate($transaction['created_at'] ?? ''),
        ];
      }
    }

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['user-metrics-details']],
    ];

    if ($billing) {
      $build['billing'] = [
        '#type' => 'details',
        '#title' => $this->t('Billing Information'),
        '#open' => TRUE,
        'content' => [
          '#theme' => 'table',
          '#rows' => [
            [$this->t('Username'), $billing['username'] ?? '-'],
            [$this->t('Email'), $billing['email'] ?? '-'],
            [$this->t('Credits'), $billing['credits'] ?? 0],
            [$this->t('Balance'), '$' . number_format($billing['balance'] ?? 0, 2)],
            [$this->t('Billing Access'), $billing['has_billing_access'] ? $this->t('Yes') : $this->t('No')],
          ],
        ],
      ];
    }

    if ($formatted_transactions) {
      $build['transactions'] = [
        '#type' => 'details',
        '#title' => $this->t('Recent Transactions'),
        '#open' => TRUE,
        'content' => [
          '#theme' => 'table',
          '#header' => [
            $this->t('Request ID'),
            $this->t('Type'),
            $this->t('Amount'),
            $this->t('Credits After'),
            $this->t('Balance After'),
            $this->t('Date'),
          ],
          '#rows' => $formatted_transactions,
          '#empty' => $this->t('No transactions found.'),
        ],
      ];
    }

    // Add back link
    $build['back'] = [
      '#type' => 'link',
      '#title' => $this->t('← Back to User Metrics'),
      '#url' => Url::fromRoute('yt_to_article_admin.user_metrics'),
      '#attributes' => ['class' => ['button']],
      '#weight' => -100,
    ];

    return $build;
  }

  /**
   * Format a date string.
   *
   * @param string $date
   *   The date string.
   *
   * @return string
   *   The formatted date.
   */
  protected function formatDate(string $date): string {
    if (empty($date)) {
      return '-';
    }
    
    try {
      $timestamp = strtotime($date);
      if ($timestamp) {
        return \Drupal::service('date.formatter')->format($timestamp, 'short');
      }
    }
    catch (\Exception $e) {
      // Ignore date parsing errors
    }
    
    return $date;
  }

  /**
   * Autocomplete callback for user search.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with matching users.
   */
  public function autocomplete(Request $request) {
    $matches = [];
    $string = $request->query->get('q');
    
    if ($string) {
      // Fetch users matching the search string
      $users_data = $this->apiUserService->fetchUsers(0, 10, $string);
      
      if ($users_data && isset($users_data['users'])) {
        foreach ($users_data['users'] as $user) {
          $label = sprintf('%s (%s)', $user['username'], $user['email']);
          $matches[] = [
            'value' => $user['id'] . '|' . $user['username'],
            'label' => $label,
          ];
        }
      }
    }
    
    return new \Symfony\Component\HttpFoundation\JsonResponse($matches);
  }

}