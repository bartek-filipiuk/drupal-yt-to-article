<?php

declare(strict_types=1);

namespace Drupal\yt_to_article_admin\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\yt_to_article_admin\Service\ApiUserService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for managing API users.
 */
class UserManagementForm extends FormBase {

  /**
   * The API user service.
   *
   * @var \Drupal\yt_to_article_admin\Service\ApiUserService
   */
  protected $apiUserService;

  /**
   * Constructs a UserManagementForm object.
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
    return 'yt_to_article_admin_user_management';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attached']['library'][] = 'core/drupal.ajax';
    
    // Vertical tabs container
    $form['user_management_tabs'] = [
      '#type' => 'vertical_tabs',
      '#default_tab' => 'create_user',
    ];

    // Create User Tab
    $form['create_user'] = [
      '#type' => 'details',
      '#title' => $this->t('Create New User'),
      '#group' => 'user_management_tabs',
      '#open' => TRUE,
    ];

    $form['create_user']['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#required' => TRUE,
      '#description' => $this->t('User email address'),
    ];

    $form['create_user']['username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#required' => TRUE,
      '#description' => $this->t('Unique username (3-50 characters)'),
      '#maxlength' => 50,
    ];

    $form['create_user']['external_user_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('External User ID'),
      '#description' => $this->t('External system user ID (e.g., drupal_123)'),
      '#required' => FALSE,
    ];

    $form['create_user']['initial_credits'] = [
      '#type' => 'number',
      '#title' => $this->t('Initial Credits'),
      '#default_value' => 0,
      '#min' => 0,
      '#description' => $this->t('Number of credits to assign to the user'),
    ];

    $form['create_user']['initial_balance'] = [
      '#type' => 'number',
      '#title' => $this->t('Initial Balance (USD)'),
      '#default_value' => 0.00,
      '#min' => 0,
      '#step' => 0.01,
      '#description' => $this->t('Initial account balance in USD'),
    ];

    $form['create_user']['token_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('API Token Settings'),
    ];

    $form['create_user']['token_settings']['create_token'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Create API token for this user'),
      '#default_value' => TRUE,
      '#description' => $this->t('Automatically create an API token when creating the user'),
    ];

    $form['create_user']['token_settings']['token_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Token Name'),
      '#default_value' => 'Default API Key',
      '#states' => [
        'visible' => [
          ':input[name="create_token"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['create_user']['token_settings']['token_tier'] = [
      '#type' => 'select',
      '#title' => $this->t('Token Tier'),
      '#options' => [
        'basic' => $this->t('Basic'),
        'premium' => $this->t('Premium'),
      ],
      '#default_value' => 'basic',
      '#states' => [
        'visible' => [
          ':input[name="create_token"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['create_user']['token_settings']['is_admin'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Grant admin privileges'),
      '#default_value' => FALSE,
      '#description' => $this->t('WARNING: Admin tokens can access all user data and billing information'),
      '#states' => [
        'visible' => [
          ':input[name="create_token"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['create_user']['create_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create User'),
      '#ajax' => [
        'callback' => '::createUserAjax',
        'wrapper' => 'user-management-messages',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Creating user...'),
        ],
      ],
      '#attributes' => ['class' => ['button--primary']],
    ];

    // Edit User Tab
    $form['edit_user'] = [
      '#type' => 'details',
      '#title' => $this->t('Edit User'),
      '#group' => 'user_management_tabs',
    ];

    // User search/select
    $form['edit_user']['user_search'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search User'),
      '#description' => $this->t('Enter username or email to search'),
      '#autocomplete_route_name' => 'yt_to_article_admin.user_autocomplete',
    ];

    $form['edit_user']['user_id'] = [
      '#type' => 'hidden',
      '#default_value' => '',
    ];

    $form['edit_user']['load_user'] = [
      '#type' => 'submit',
      '#value' => $this->t('Load User'),
      '#ajax' => [
        'callback' => '::loadUserAjax',
        'wrapper' => 'edit-user-form-wrapper',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Loading user data...'),
        ],
      ],
      '#limit_validation_errors' => [['user_search']],
    ];

    // Container for user edit fields (populated via AJAX)
    $form['edit_user']['user_details'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'edit-user-form-wrapper'],
    ];

    // If we have a user loaded, show edit fields
    $selected_user_id = $form_state->getValue('selected_user_id');
    if ($selected_user_id) {
      $this->buildUserEditFields($form['edit_user']['user_details'], $form_state, $selected_user_id);
    }

    // Manage Tokens Tab
    $form['manage_tokens'] = [
      '#type' => 'details',
      '#title' => $this->t('Manage Tokens'),
      '#group' => 'user_management_tabs',
    ];

    $form['manage_tokens']['token_user_search'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search User for Token Management'),
      '#description' => $this->t('Enter username or email'),
      '#autocomplete_route_name' => 'yt_to_article_admin.user_autocomplete',
    ];

    $form['manage_tokens']['load_tokens'] = [
      '#type' => 'submit',
      '#value' => $this->t('Load User Tokens'),
      '#ajax' => [
        'callback' => '::loadTokensAjax',
        'wrapper' => 'tokens-list-wrapper',
      ],
      '#limit_validation_errors' => [['token_user_search']],
    ];

    $form['manage_tokens']['tokens_list'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'tokens-list-wrapper'],
    ];

    // Messages container
    $form['messages'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'user-management-messages'],
      '#weight' => -100,
    ];

    return $form;
  }

  /**
   * Build user edit fields.
   */
  protected function buildUserEditFields(&$container, FormStateInterface $form_state, $user_id) {
    // Fetch user billing info
    $user_data = $this->apiUserService->fetchUserBilling((int) $user_id);
    
    if (!$user_data) {
      $container['error'] = [
        '#markup' => '<div class="messages messages--error">' . $this->t('Unable to load user data.') . '</div>',
      ];
      return;
    }

    $container['user_info'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('User Information'),
    ];

    $container['user_info']['info'] = [
      '#theme' => 'table',
      '#rows' => [
        [$this->t('Username'), $user_data['username'] ?? '-'],
        [$this->t('Email'), $user_data['email'] ?? '-'],
        [$this->t('Current Credits'), $user_data['credits'] ?? 0],
        [$this->t('Current Balance'), '$' . number_format($user_data['balance'] ?? 0, 2)],
      ],
    ];

    // Credits management
    $container['credits_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Update Credits'),
    ];

    $container['credits_fieldset']['new_credits'] = [
      '#type' => 'number',
      '#title' => $this->t('Set Credits To'),
      '#default_value' => $user_data['credits'] ?? 0,
      '#min' => 0,
      '#description' => $this->t('This will replace the current credit amount'),
    ];

    $container['credits_fieldset']['update_credits'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update Credits'),
      '#ajax' => [
        'callback' => '::updateCreditsAjax',
        'wrapper' => 'user-management-messages',
      ],
      '#attributes' => ['class' => ['button--small']],
    ];

    // Balance management
    $container['balance_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Update Balance'),
    ];

    $container['balance_fieldset']['balance_action'] = [
      '#type' => 'radios',
      '#title' => $this->t('Action'),
      '#options' => [
        'set' => $this->t('Set balance to specific amount'),
        'add' => $this->t('Add to existing balance'),
      ],
      '#default_value' => 'set',
    ];

    $container['balance_fieldset']['balance_amount'] = [
      '#type' => 'number',
      '#title' => $this->t('Amount (USD)'),
      '#default_value' => $user_data['balance'] ?? 0,
      '#min' => 0,
      '#step' => 0.01,
      '#description' => $this->t('Enter the amount in USD'),
    ];

    $container['balance_fieldset']['balance_description'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Description'),
      '#description' => $this->t('Optional description for the balance change'),
      '#states' => [
        'visible' => [
          ':input[name="balance_action"]' => ['value' => 'add'],
        ],
      ],
    ];

    $container['balance_fieldset']['update_balance'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update Balance'),
      '#ajax' => [
        'callback' => '::updateBalanceAjax',
        'wrapper' => 'user-management-messages',
      ],
      '#attributes' => ['class' => ['button--small']],
    ];

    // Store user ID for form processing
    $container['edit_user_id'] = [
      '#type' => 'hidden',
      '#value' => $user_id,
    ];
  }

  /**
   * AJAX callback for creating a user.
   */
  public function createUserAjax(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    
    $values = $form_state->getValues();
    
    // Create the user
    $user_data = $this->apiUserService->createUser(
      $values['email'],
      $values['username'],
      $values['external_user_id'],
      (int) $values['initial_credits'],
      (float) $values['initial_balance']
    );
    
    if ($user_data && isset($user_data['id'])) {
      $message = $this->t('User @username created successfully with ID @id.', [
        '@username' => $values['username'],
        '@id' => $user_data['id'],
      ]);
      
      // Create token if requested
      if ($values['create_token']) {
        $token_data = $this->apiUserService->createUserToken(
          $user_data['id'],
          $values['token_name'],
          $values['token_tier'],
          (bool) $values['is_admin']
        );
        
        if ($token_data && isset($token_data['token'])) {
          $message .= ' ' . $this->t('API Token created: @token', [
            '@token' => $token_data['token'],
          ]);
          
          // Add a special message to save the token
          $response->addCommand(new MessageCommand(
            $this->t('IMPORTANT: Save this token now - it cannot be retrieved later: @token', [
              '@token' => $token_data['token'],
            ]),
            NULL,
            ['type' => 'warning'],
            TRUE
          ));
        }
      }
      
      $response->addCommand(new MessageCommand(
        $message,
        NULL,
        ['type' => 'status'],
        TRUE
      ));
      
      // Clear form fields
      $response->addCommand(new InvokeCommand('#edit-email', 'val', ['']));
      $response->addCommand(new InvokeCommand('#edit-username', 'val', ['']));
      $response->addCommand(new InvokeCommand('#edit-external-user-id', 'val', ['']));
    }
    else {
      $response->addCommand(new MessageCommand(
        $this->t('Failed to create user. Please check the API connection and try again.'),
        NULL,
        ['type' => 'error'],
        TRUE
      ));
    }
    
    return $response;
  }

  /**
   * AJAX callback for loading user data.
   */
  public function loadUserAjax(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    
    $user_search = $form_state->getValue('user_search');
    
    if (empty($user_search)) {
      $response->addCommand(new MessageCommand(
        $this->t('Please select a user first.'),
        NULL,
        ['type' => 'warning'],
        TRUE
      ));
      return $response;
    }
    
    // Parse the user ID from the autocomplete value (format: "id|username")
    $parts = explode('|', $user_search);
    if (count($parts) < 2 || !is_numeric($parts[0])) {
      $response->addCommand(new MessageCommand(
        $this->t('Invalid user selection. Please use the autocomplete suggestions.'),
        NULL,
        ['type' => 'error'],
        TRUE
      ));
      return $response;
    }
    
    $user_id = (int) $parts[0];
    
    // Store the selected user ID in form state
    $form_state->setValue('selected_user_id', $user_id);
    $form_state->setRebuild(TRUE);
    
    // Rebuild the user details section
    $this->buildUserEditFields($form['edit_user']['user_details'], $form_state, $user_id);
    
    // Replace the user details container
    $response->addCommand(new ReplaceCommand(
      '#edit-user-form-wrapper',
      $form['edit_user']['user_details']
    ));
    
    $response->addCommand(new MessageCommand(
      $this->t('User data loaded successfully.'),
      NULL,
      ['type' => 'status'],
      TRUE
    ));
    
    return $response;
  }

  /**
   * AJAX callback for updating credits.
   */
  public function updateCreditsAjax(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    
    $user_id = $form_state->getValue('edit_user_id');
    $new_credits = (int) $form_state->getValue('new_credits');
    
    if ($user_id) {
      $result = $this->apiUserService->updateUserCredits((int) $user_id, $new_credits);
      
      if ($result) {
        $response->addCommand(new MessageCommand(
          $this->t('Credits updated successfully to @credits.', ['@credits' => $new_credits]),
          NULL,
          ['type' => 'status'],
          TRUE
        ));
      }
      else {
        $response->addCommand(new MessageCommand(
          $this->t('Failed to update credits.'),
          NULL,
          ['type' => 'error'],
          TRUE
        ));
      }
    }
    
    return $response;
  }

  /**
   * AJAX callback for updating balance.
   */
  public function updateBalanceAjax(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    
    $user_id = $form_state->getValue('edit_user_id');
    $action = $form_state->getValue('balance_action');
    $amount = (float) $form_state->getValue('balance_amount');
    $description = $form_state->getValue('balance_description');
    
    if ($user_id) {
      if ($action === 'set') {
        $result = $this->apiUserService->updateUserBalance((int) $user_id, $amount);
      }
      else {
        $result = $this->apiUserService->addToUserBalance((int) $user_id, $amount, $description);
      }
      
      if ($result) {
        $response->addCommand(new MessageCommand(
          $this->t('Balance updated successfully.'),
          NULL,
          ['type' => 'status'],
          TRUE
        ));
      }
      else {
        $response->addCommand(new MessageCommand(
          $this->t('Failed to update balance.'),
          NULL,
          ['type' => 'error'],
          TRUE
        ));
      }
    }
    
    return $response;
  }

  /**
   * AJAX callback for loading user tokens.
   */
  public function loadTokensAjax(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    
    $token_user_search = $form_state->getValue('token_user_search');
    
    if (empty($token_user_search)) {
      $response->addCommand(new MessageCommand(
        $this->t('Please select a user first.'),
        NULL,
        ['type' => 'warning'],
        TRUE
      ));
      return $response;
    }
    
    // Parse the user ID from the autocomplete value (format: "id|username")
    $parts = explode('|', $token_user_search);
    if (count($parts) < 2 || !is_numeric($parts[0])) {
      $response->addCommand(new MessageCommand(
        $this->t('Invalid user selection. Please use the autocomplete suggestions.'),
        NULL,
        ['type' => 'error'],
        TRUE
      ));
      return $response;
    }
    
    $user_id = (int) $parts[0];
    $username = $parts[1];
    
    // Create token list container
    $tokens_container = [
      '#type' => 'container',
      '#attributes' => ['id' => 'tokens-list-wrapper'],
    ];
    
    $tokens_container['info'] = [
      '#markup' => '<h3>' . $this->t('Tokens for user: @username', ['@username' => $username]) . '</h3>',
    ];
    
    $tokens_container['create_token'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Create New Token'),
    ];
    
    $tokens_container['create_token']['new_token_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Token Name'),
      '#default_value' => 'API Key',
    ];
    
    $tokens_container['create_token']['new_token_tier'] = [
      '#type' => 'select',
      '#title' => $this->t('Tier'),
      '#options' => [
        'basic' => $this->t('Basic'),
        'premium' => $this->t('Premium'),
      ],
    ];
    
    $tokens_container['create_token']['new_token_admin'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Grant admin privileges'),
    ];
    
    $tokens_container['create_token']['create_token_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create Token'),
      '#name' => 'create_token_for_' . $user_id,
      '#ajax' => [
        'callback' => '::createTokenForUserAjax',
        'wrapper' => 'user-management-messages',
      ],
      '#attributes' => [
        'data-user-id' => $user_id,
        'class' => ['button--small'],
      ],
    ];
    
    $tokens_container['create_token']['user_id_for_token'] = [
      '#type' => 'hidden',
      '#value' => $user_id,
    ];
    
    $tokens_container['note'] = [
      '#markup' => '<p class="messages messages--warning">' . 
        $this->t('Note: Listing existing tokens is not yet implemented. You can create new tokens above.') . 
        '</p>',
    ];
    
    // Replace the tokens list container
    $response->addCommand(new ReplaceCommand(
      '#tokens-list-wrapper',
      $tokens_container
    ));
    
    return $response;
  }

  /**
   * AJAX callback for creating a token for a specific user.
   */
  public function createTokenForUserAjax(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    
    $user_id = $form_state->getValue('user_id_for_token');
    $token_name = $form_state->getValue('new_token_name');
    $token_tier = $form_state->getValue('new_token_tier');
    $is_admin = (bool) $form_state->getValue('new_token_admin');
    
    if (!$user_id) {
      $response->addCommand(new MessageCommand(
        $this->t('User ID not found. Please reload the form and try again.'),
        NULL,
        ['type' => 'error'],
        TRUE
      ));
      return $response;
    }
    
    $token_data = $this->apiUserService->createUserToken(
      (int) $user_id,
      $token_name ?: 'API Key',
      $token_tier ?: 'basic',
      $is_admin
    );
    
    if ($token_data && isset($token_data['token'])) {
      // Show the token (it's only shown once)
      $response->addCommand(new MessageCommand(
        $this->t('Token created successfully! SAVE THIS TOKEN NOW - it cannot be retrieved later: @token', [
          '@token' => $token_data['token'],
        ]),
        NULL,
        ['type' => 'warning'],
        TRUE
      ));
      
      $response->addCommand(new MessageCommand(
        $this->t('Token "@name" created with @tier tier@admin.', [
          '@name' => $token_name,
          '@tier' => $token_tier,
          '@admin' => $is_admin ? ' and admin privileges' : '',
        ]),
        NULL,
        ['type' => 'status'],
        TRUE
      ));
    }
    else {
      $response->addCommand(new MessageCommand(
        $this->t('Failed to create token. Please check the API connection.'),
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
    // Handle non-AJAX submissions if needed
  }

}