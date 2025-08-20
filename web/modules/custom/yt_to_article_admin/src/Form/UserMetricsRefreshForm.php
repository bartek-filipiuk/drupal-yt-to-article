<?php

declare(strict_types=1);

namespace Drupal\yt_to_article_admin\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\yt_to_article_admin\Service\ApiUserService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for refreshing user metrics data.
 */
class UserMetricsRefreshForm extends FormBase {

  /**
   * The API user service.
   *
   * @var \Drupal\yt_to_article_admin\Service\ApiUserService
   */
  protected $apiUserService;

  /**
   * Constructs a UserMetricsRefreshForm object.
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
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'yt_to_article_admin_user_metrics_refresh';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attributes']['class'][] = 'user-metrics-refresh-form';
    
    $form['filters'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['form--inline', 'clearfix']],
    ];

    $form['filters']['search'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search'),
      '#placeholder' => $this->t('Username or email...'),
      '#size' => 30,
      '#default_value' => \Drupal::request()->query->get('search', ''),
    ];

    $form['filters']['limit'] = [
      '#type' => 'select',
      '#title' => $this->t('Show'),
      '#options' => [
        '10' => '10',
        '20' => '20',
        '50' => '50',
        '100' => '100',
      ],
      '#default_value' => \Drupal::request()->query->get('limit', '20'),
    ];

    $form['filters']['actions'] = [
      '#type' => 'actions',
    ];

    $form['filters']['actions']['filter'] = [
      '#type' => 'submit',
      '#value' => $this->t('Filter'),
      '#submit' => ['::filterSubmit'],
    ];

    $form['filters']['actions']['refresh'] = [
      '#type' => 'submit',
      '#value' => $this->t('Refresh Data'),
      '#ajax' => [
        'callback' => '::refreshAjax',
        'wrapper' => 'user-metrics-wrapper',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Refreshing user data...'),
        ],
      ],
      '#attributes' => [
        'class' => ['button--primary'],
      ],
    ];

    // Add rate limiting notice
    $form['rate_limit_notice'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['messages', 'messages--status']],
      'message' => [
        '#markup' => $this->t('Data is cached for 5 minutes. The refresh button will fetch the latest data from the API.'),
      ],
    ];

    return $form;
  }

  /**
   * Handle filter submission.
   */
  public function filterSubmit(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $query = [];
    
    if (!empty($values['search'])) {
      $query['search'] = $values['search'];
    }
    
    if (!empty($values['limit'])) {
      $query['limit'] = $values['limit'];
    }
    
    $form_state->setRedirect('yt_to_article_admin.user_metrics', [], ['query' => $query]);
  }

  /**
   * AJAX callback for refresh button.
   */
  public function refreshAjax(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    
    // Clear cache to force fresh data
    $this->apiUserService->clearCache();
    
    // Get current query parameters
    $request = \Drupal::request();
    $offset = (int) $request->query->get('offset', 0);
    $limit = (int) $request->query->get('limit', 20);
    $search = $request->query->get('search');
    
    // Fetch fresh data
    $summary = $this->apiUserService->fetchUsersSummary(TRUE);
    $users_data = $this->apiUserService->fetchUsers($offset, $limit, $search, TRUE);
    
    if ($users_data) {
      // Success message
      $response->addCommand(new MessageCommand(
        $this->t('User data refreshed successfully.'),
        NULL,
        ['type' => 'status'],
        TRUE
      ));
      
      // Trigger page reload to show new data
      $response->addCommand(new \Drupal\Core\Ajax\InvokeCommand(NULL, 'location.reload'));
    }
    else {
      // Error message
      $response->addCommand(new MessageCommand(
        $this->t('Failed to refresh user data. Please check the API configuration.'),
        NULL,
        ['type' => 'error'],
        TRUE
      ));
    }
    
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // This is handled by specific submit handlers
  }

}