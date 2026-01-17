<?php

declare(strict_types=1);

namespace Drupal\yt_to_article\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\SettingsCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\yt_to_article\Ajax\WebSocketConnectCommand;
use Drupal\yt_to_article\Exception\ApiException;
use Drupal\yt_to_article\Exception\InsufficientFundsException;
use Drupal\yt_to_article\Exception\RateLimitException;
use Drupal\yt_to_article\Service\YtToArticleApiClient;
use Drupal\yt_to_article\ValueObject\ArticleResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for converting YouTube videos to articles.
 *
 * This form provides a user interface for submitting YouTube URLs and
 * configuring article generation options. It uses AJAX for asynchronous
 * submission and WebSocket for real-time progress updates.
 */
final class YtToArticleForm extends FormBase {

  /**
   * Constructs a new YtToArticleForm.
   *
   * @param \Drupal\yt_to_article\Service\YtToArticleApiClient $apiClient
   *   The API client for article generation.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  public function __construct(
    private readonly YtToArticleApiClient $apiClient,
    private readonly LoggerInterface $logger,
    private readonly RendererInterface $renderer,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      apiClient: $container->get('yt_to_article.api_client'),
      logger: $container->get('logger.channel.yt_to_article'),
      renderer: $container->get('renderer'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'yt_to_article_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#theme'] = 'yt_to_article_form';
    $form['#attached']['library'][] = 'yt_to_article/websocket';

    $form['youtube_url'] = $this->buildYoutubeUrlField();
    $form['generation_options'] = $this->buildGenerationOptionsContainer();
    $form['actions'] = $this->buildActionsElement();
    $form['result_container'] = $this->buildResultContainer($form_state);
    $form['messages_container'] = $this->buildMessagesContainer();

    return $form;
  }

  /**
   * Builds the YouTube URL input field.
   *
   * @return array
   *   The form element array.
   */
  private function buildYoutubeUrlField(): array {
    return [
      '#type' => 'textfield',
      '#title' => $this->t('YouTube URL'),
      '#description' => $this->t('Enter a YouTube video URL to convert to an article.'),
      '#required' => TRUE,
      '#attributes' => [
        'placeholder' => 'https://www.youtube.com/watch?v=...',
        'class' => ['yt-to-article-url-input'],
      ],
      '#maxlength' => 255,
    ];
  }

  /**
   * Builds the generation options container with all option fields.
   *
   * @return array
   *   The form element array.
   */
  private function buildGenerationOptionsContainer(): array {
    $container = [
      '#type' => 'details',
      '#title' => $this->t('Article Generation Options'),
      '#open' => TRUE,
    ];

    $container['style'] = $this->buildStyleField();
    $container['style_instructions'] = $this->buildStyleInstructionsField();
    $container['audience'] = $this->buildAudienceField();
    $container['audience_instructions'] = $this->buildAudienceInstructionsField();
    $container['length'] = $this->buildLengthField();
    $container['output_format'] = $this->buildOutputFormatField();
    $container['language'] = $this->buildLanguageField();

    return $container;
  }

  /**
   * Builds the writing style dropdown field.
   *
   * @return array
   *   The form element array.
   */
  private function buildStyleField(): array {
    return [
      '#type' => 'select',
      '#title' => $this->t('Writing style'),
      '#options' => [
        'casual' => $this->t('Casual - Conversational and engaging'),
        'formal' => $this->t('Formal - Professional and objective'),
        'technical' => $this->t('Technical - Precise with detailed explanations'),
        'custom' => $this->t('Custom - Define your own style'),
      ],
      '#default_value' => 'casual',
      '#description' => $this->t('Choose the tone and voice for the article.'),
    ];
  }

  /**
   * Builds the custom style instructions textarea field.
   *
   * @return array
   *   The form element array.
   */
  private function buildStyleInstructionsField(): array {
    return [
      '#type' => 'textarea',
      '#title' => $this->t('Custom style instructions'),
      '#description' => $this->t('Describe your desired writing style in detail.'),
      '#rows' => 3,
      '#states' => [
        'visible' => [
          ':input[name="style"]' => ['value' => 'custom'],
        ],
        'required' => [
          ':input[name="style"]' => ['value' => 'custom'],
        ],
      ],
    ];
  }

  /**
   * Builds the target audience dropdown field.
   *
   * @return array
   *   The form element array.
   */
  private function buildAudienceField(): array {
    return [
      '#type' => 'select',
      '#title' => $this->t('Target audience'),
      '#options' => [
        'general' => $this->t('General - Clear explanations for everyone'),
        'expert' => $this->t('Expert - Advanced concepts and technical details'),
        'young' => $this->t('Young - Engaging for younger readers'),
        'custom' => $this->t('Custom - Define your own audience'),
      ],
      '#default_value' => 'general',
      '#description' => $this->t('Tailor content complexity for specific readers.'),
    ];
  }

  /**
   * Builds the custom audience instructions textarea field.
   *
   * @return array
   *   The form element array.
   */
  private function buildAudienceInstructionsField(): array {
    return [
      '#type' => 'textarea',
      '#title' => $this->t('Custom audience instructions'),
      '#description' => $this->t('Describe your target audience in detail.'),
      '#rows' => 3,
      '#states' => [
        'visible' => [
          ':input[name="audience"]' => ['value' => 'custom'],
        ],
        'required' => [
          ':input[name="audience"]' => ['value' => 'custom'],
        ],
      ],
    ];
  }

  /**
   * Builds the article length dropdown field.
   *
   * @return array
   *   The form element array.
   */
  private function buildLengthField(): array {
    return [
      '#type' => 'select',
      '#title' => $this->t('Article length'),
      '#options' => [
        'rating' => $this->t('Rate video by AI - check if video is worth to see'),
        'article' => $this->t('Create a full article based on video'),
        'fight' => $this->t('Fight - Two AI battles against best quotes from video'),
        'brief_focused' => $this->t('Focused Brief'),
        'tutorial' => $this->t('Make a full tutorial from a video'),
      ],
      '#default_value' => 'standard',
      '#description' => $this->t('Choose the appropriate length for your needs.'),
    ];
  }

  /**
   * Builds the output format dropdown field.
   *
   * @return array
   *   The form element array.
   */
  private function buildOutputFormatField(): array {
    return [
      '#type' => 'select',
      '#title' => $this->t('Output format'),
      '#options' => [
        'html' => $this->t('HTML - Clean HTML with semantic tags'),
      ],
      '#default_value' => 'html',
      '#description' => $this->t('Choose how you want the content formatted.'),
    ];
  }

  /**
   * Builds the language dropdown field.
   *
   * @return array
   *   The form element array.
   */
  private function buildLanguageField(): array {
    return [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => [
        'en' => $this->t('English'),
        'pl' => $this->t('Polish'),
      ],
      '#default_value' => 'en',
      '#description' => $this->t('Choose the language for the generated article.'),
    ];
  }

  /**
   * Builds the form actions element with submit button.
   *
   * @return array
   *   The form element array.
   */
  private function buildActionsElement(): array {
    return [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Generate Article'),
        '#ajax' => [
          'callback' => '::ajaxSubmitCallback',
          'wrapper' => 'yt-to-article-result',
          'progress' => [
            'type' => 'throbber',
            'message' => $this->t('Submitting request...'),
          ],
        ],
        '#attributes' => [
          'class' => ['yt-to-article-submit'],
        ],
      ],
    ];
  }

  /**
   * Builds the result container for AJAX responses.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return array
   *   The form element array.
   */
  private function buildResultContainer(FormStateInterface $form_state): array {
    $container = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'yt-to-article-result',
        'class' => ['yt-to-article-result-container'],
      ],
    ];

    if ($form_state->has('result_content')) {
      $container['content'] = $form_state->get('result_content');
    }

    return $container;
  }

  /**
   * Builds the messages container for real-time updates.
   *
   * @return array
   *   The form element array.
   */
  private function buildMessagesContainer(): array {
    return [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'yt-to-article-messages-container',
        'class' => ['yt-to-article-messages-container'],
      ],
      '#markup' => '<div class="yt-to-article-messages" data-drupal-messages aria-live="polite"><div class="messages__wrapper"></div></div>',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $youtubeUrl = $form_state->getValue('youtube_url');
    $pattern = '/^(https?:\/\/)?(www\.)?(youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)[\w-]+(&[\w=]*)?$/i';

    if (!preg_match($pattern, $youtubeUrl)) {
      $form_state->setErrorByName('youtube_url', $this->t('Please enter a valid YouTube URL.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // This method is not used in AJAX submissions.
  }

  /**
   * AJAX callback for form submission.
   *
   * Handles the asynchronous article generation request, including API calls,
   * WebSocket setup for progress updates, and error handling.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response with commands.
   */
  public function ajaxSubmitCallback(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();

    if ($form_state->hasAnyErrors()) {
      $messages = ['#type' => 'status_messages'];
      $response->addCommand(new HtmlCommand('#yt-to-article-result', $messages));
      return $response;
    }

    try {
      $youtubeUrl = $form_state->getValue('youtube_url');
      $options = $this->buildApiOptions($form_state);
      $apiResponse = $this->apiClient->generateArticle($youtubeUrl, $options);

      $this->handleApiSuccess($response, $apiResponse, $youtubeUrl);

    }
    catch (InsufficientFundsException $e) {
      $this->handleInsufficientFundsError($response, $e);
    }
    catch (RateLimitException $e) {
      $this->handleRateLimitError($response, $e);
    }
    catch (ApiException $e) {
      $this->handleApiError($response, $e);
    }
    catch (\Exception $e) {
      $this->handleUnexpectedError($response, $e);
    }

    return $response;
  }

  /**
   * Builds the API options array from form values.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return array
   *   The options array for the API call.
   */
  private function buildApiOptions(FormStateInterface $form_state): array {
    $config = $this->buildApiConfig($form_state);
    $options = ['config' => $config];

    $webhookUrl = $this->apiClient->getWebhookUrl();
    if ($webhookUrl) {
      $options['webhook_url'] = $webhookUrl;
      $options['webhook_config'] = [
        'content_type' => 'markdown',
        'include_metadata' => TRUE,
      ];

      $this->logger->info('Including webhook URL in article generation request: {url}', [
        'url' => $webhookUrl,
      ]);
    }

    return $options;
  }

  /**
   * Builds the API configuration array from form values.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return array
   *   The configuration array.
   */
  private function buildApiConfig(FormStateInterface $form_state): array {
    $style = $form_state->getValue('style', 'casual');
    $audience = $form_state->getValue('audience', 'general');

    $config = [
      'style' => $style,
      'audience' => $audience,
      'length' => $form_state->getValue('length', 'standard'),
      'output_format' => $form_state->getValue('output_format', 'markdown'),
      'language' => $form_state->getValue('language', 'en'),
      'llm_provider' => 'openrouter',
      'llm_model' => 'google/gemini-2.5-flash',
    ];

    if ($style === 'custom') {
      $config['style_instructions'] = $form_state->getValue('style_instructions', '');
    }

    if ($audience === 'custom') {
      $config['audience_instructions'] = $form_state->getValue('audience_instructions', '');
    }

    return $config;
  }

  /**
   * Handles a successful API response.
   *
   * Sets up the progress UI, WebSocket connection, and session tracking.
   *
   * @param \Drupal\Core\Ajax\AjaxResponse $response
   *   The AJAX response to add commands to.
   * @param \Drupal\yt_to_article\ValueObject\ArticleResponse $apiResponse
   *   The API response object.
   * @param string $youtubeUrl
   *   The YouTube URL being processed.
   */
  private function handleApiSuccess(AjaxResponse $response, ArticleResponse $apiResponse, string $youtubeUrl): void {
    $this->renderProgressResponse($response, $apiResponse);
    $this->clearPreviousState($response);
    $this->setupWebSocketConnection($response, $apiResponse);
    $this->updateAnonymousUserSession($apiResponse->requestId, $youtubeUrl);

    $this->logger->info('Article generation started for {url} with request ID {id}', [
      'url' => $youtubeUrl,
      'id' => $apiResponse->requestId,
    ]);
  }

  /**
   * Renders the progress template and updates the result container.
   *
   * @param \Drupal\Core\Ajax\AjaxResponse $response
   *   The AJAX response to add commands to.
   * @param \Drupal\yt_to_article\ValueObject\ArticleResponse $apiResponse
   *   The API response object.
   */
  private function renderProgressResponse(AjaxResponse $response, ArticleResponse $apiResponse): void {
    $progressElement = [
      '#theme' => 'yt_to_article_progress',
      '#request_id' => $apiResponse->requestId,
      '#initial_status' => $apiResponse->status,
    ];

    $renderedProgress = $this->renderer->render($progressElement);
    $response->addCommand(new HtmlCommand('#yt-to-article-result', $renderedProgress));
  }

  /**
   * Clears previous messages and action buttons from the UI.
   *
   * @param \Drupal\Core\Ajax\AjaxResponse $response
   *   The AJAX response to add commands to.
   */
  private function clearPreviousState(AjaxResponse $response): void {
    $response->addCommand(new InvokeCommand(
      '#yt-to-article-messages-container .messages__wrapper',
      'empty'
    ));

    $response->addCommand(new InvokeCommand(
      '.yt-to-article-actions',
      'remove'
    ));
  }

  /**
   * Sets up the WebSocket connection for real-time progress updates.
   *
   * @param \Drupal\Core\Ajax\AjaxResponse $response
   *   The AJAX response to add commands to.
   * @param \Drupal\yt_to_article\ValueObject\ArticleResponse $apiResponse
   *   The API response object.
   */
  private function setupWebSocketConnection(AjaxResponse $response, ArticleResponse $apiResponse): void {
    $wsSettings = [
      'ytToArticle' => [
        'requestId' => $apiResponse->requestId,
        'wsUrl' => $this->getWebSocketUrl(),
        'token' => $this->getApiToken(),
      ],
    ];
    $response->addCommand(new SettingsCommand($wsSettings));

    $response->addCommand(new WebSocketConnectCommand(
      $apiResponse->requestId,
      $this->getWebSocketUrl(),
      $this->getApiToken()
    ));
  }

  /**
   * Updates the session for anonymous users to track their articles.
   *
   * @param string $requestId
   *   The request ID from the API.
   * @param string $youtubeUrl
   *   The YouTube URL being processed.
   */
  private function updateAnonymousUserSession(string $requestId, string $youtubeUrl): void {
    $currentUser = \Drupal::currentUser();
    if (!$currentUser->isAnonymous()) {
      return;
    }

    $session = \Drupal::request()->getSession();
    $anonymousArticles = $session->get('yt_to_article_anonymous', ['articles' => []]);

    if (!isset($anonymousArticles['articles'])) {
      $anonymousArticles['articles'] = [];
    }

    array_unshift($anonymousArticles['articles'], [
      'request_id' => $requestId,
      'timestamp' => time(),
      'youtube_url' => $youtubeUrl,
      'title' => NULL,
    ]);

    $anonymousArticles['articles'] = array_slice($anonymousArticles['articles'], 0, 10);
    $session->set('yt_to_article_anonymous', $anonymousArticles);
  }

  /**
   * Handles insufficient funds exception.
   *
   * @param \Drupal\Core\Ajax\AjaxResponse $response
   *   The AJAX response to add commands to.
   * @param \Drupal\yt_to_article\Exception\InsufficientFundsException $e
   *   The exception that was thrown.
   */
  private function handleInsufficientFundsError(AjaxResponse $response, InsufficientFundsException $e): void {
    $message = $this->t('Insufficient funds to generate article. You need either credits (current: @credits) or minimum balance of $@min_balance (current: $@balance).', [
      '@credits' => $e->getCurrentCredits(),
      '@min_balance' => number_format($e->getMinimumBalance(), 2),
      '@balance' => number_format($e->getCurrentBalance(), 2),
    ]);

    $this->addErrorToResponse($response, $message);

    $response->addCommand(new InvokeCommand(NULL, 'eval', [
      'if (window.YtToArticleWebSocket && window.YtToArticleWebSocket.ws && window.YtToArticleWebSocket.ws.readyState === WebSocket.OPEN) {
        window.YtToArticleWebSocket.showError("' . addslashes($message) . '");
      }',
    ]));

    $this->logger->warning('Insufficient funds for user to generate article', [
      'credits' => $e->getCurrentCredits(),
      'balance' => $e->getCurrentBalance(),
      'minimum_balance' => $e->getMinimumBalance(),
    ]);
  }

  /**
   * Handles rate limit exception.
   *
   * @param \Drupal\Core\Ajax\AjaxResponse $response
   *   The AJAX response to add commands to.
   * @param \Drupal\yt_to_article\Exception\RateLimitException $e
   *   The exception that was thrown.
   */
  private function handleRateLimitError(AjaxResponse $response, RateLimitException $e): void {
    $message = $this->t('Rate limit exceeded. Please wait @seconds seconds before trying again.', [
      '@seconds' => $e->getRetryAfter() ?? 60,
    ]);

    $this->addErrorToResponse($response, $message);
  }

  /**
   * Handles general API exceptions.
   *
   * @param \Drupal\Core\Ajax\AjaxResponse $response
   *   The AJAX response to add commands to.
   * @param \Drupal\yt_to_article\Exception\ApiException $e
   *   The exception that was thrown.
   */
  private function handleApiError(AjaxResponse $response, ApiException $e): void {
    $message = $this->determineApiErrorMessage($e);
    $this->addErrorToResponse($response, $message);

    $this->logger->error('API error: {message}', [
      'message' => $e->getMessage(),
      'context' => $e->getContext(),
    ]);
  }

  /**
   * Determines the appropriate error message for an API exception.
   *
   * @param \Drupal\yt_to_article\Exception\ApiException $e
   *   The exception that was thrown.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string
   *   The error message to display.
   */
  private function determineApiErrorMessage(ApiException $e): mixed {
    if (str_contains($e->getMessage(), '402 Payment Required')) {
      return $this->t('Unable to generate article: Insufficient funds. Please contact your administrator to purchase credits or add funds to your account.');
    }

    if ($e->getCode() === 422) {
      return $this->determineValidationErrorMessage($e);
    }

    if (str_contains($e->getMessage(), '422 Unprocessable Entity')) {
      return $this->t('Invalid input: Please check your custom instructions and ensure they meet the requirements (10-500 characters, plain text only).');
    }

    return $this->t('Unable to connect to the article generation service. Please try again later.');
  }

  /**
   * Determines the error message for validation errors (422).
   *
   * @param \Drupal\yt_to_article\Exception\ApiException $e
   *   The exception that was thrown.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string
   *   The error message to display.
   */
  private function determineValidationErrorMessage(ApiException $e): mixed {
    $context = $e->getContext();

    if (isset($context['error_type']) && $context['error_type'] === 'security_violation') {
      return $this->t('Security violation detected: Your custom instructions contain prohibited patterns that attempt to override system behavior. Please rephrase your instructions without trying to manipulate the system.');
    }

    if (isset($context['response_body'])) {
      $responseData = json_decode($context['response_body'], TRUE);
      if (isset($responseData['error_type']) && $responseData['error_type'] === 'security_violation') {
        return $this->t('Security violation detected: Your custom instructions contain prohibited patterns that attempt to override system behavior. Please rephrase your instructions without trying to manipulate the system.');
      }
    }

    return $this->t('Invalid input: Please check your custom instructions and ensure they meet the requirements (10-500 characters, plain text only).');
  }

  /**
   * Handles unexpected exceptions.
   *
   * @param \Drupal\Core\Ajax\AjaxResponse $response
   *   The AJAX response to add commands to.
   * @param \Exception $e
   *   The exception that was thrown.
   */
  private function handleUnexpectedError(AjaxResponse $response, \Exception $e): void {
    $message = $this->t('An unexpected error occurred. Please try again later.');
    $this->addErrorToResponse($response, $message);

    $this->logger->error('Unexpected error: {message}', ['message' => $e->getMessage()]);
  }

  /**
   * Adds an error message to the AJAX response.
   *
   * @param \Drupal\Core\Ajax\AjaxResponse $response
   *   The AJAX response to add commands to.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|string $message
   *   The error message to display.
   */
  private function addErrorToResponse(AjaxResponse $response, mixed $message): void {
    $this->messenger()->addError($message);
    $response->addCommand(new HtmlCommand(
      '#yt-to-article-result',
      ['#markup' => '<div class="messages messages--error">' . $message . '</div>']
    ));
  }

  /**
   * Gets the WebSocket URL from configuration.
   *
   * Includes fallback protocol detection for mobile compatibility. When the
   * configured URL uses localhost (inaccessible from mobile devices), this
   * method generates a WebSocket URL based on the current request's host.
   *
   * @return string
   *   The WebSocket URL.
   */
  private function getWebSocketUrl(): string {
    $settings = \Drupal::service('settings')->get('yt_to_article', []);
    $wsUrl = $settings['websocket_url'] ?? NULL;

    $isLocalhost = $wsUrl && str_contains($wsUrl, 'localhost');
    if (!$wsUrl || ($isLocalhost && $this->isMobileRequest())) {
      $request = \Drupal::request();
      $protocol = $request->isSecure() ? 'wss' : 'ws';
      $host = $request->getHost();
      $wsUrl = $protocol . '://' . $host . '/api/v1/ws';

      \Drupal::logger('yt_to_article')->info('Generated WebSocket URL for mobile: @url', ['@url' => $wsUrl]);
    }

    return $wsUrl;
  }

  /**
   * Checks if the current request is likely from a mobile device.
   *
   * @return bool
   *   TRUE if the request appears to be from a mobile device.
   */
  private function isMobileRequest(): bool {
    $userAgent = \Drupal::request()->headers->get('User-Agent', '');
    return preg_match('/Mobile|Android|iPhone|iPad/i', $userAgent) !== 0;
  }

  /**
   * Gets the API token from configuration.
   *
   * @return string
   *   The API token.
   */
  private function getApiToken(): string {
    $settings = \Drupal::service('settings')->get('yt_to_article', []);
    return $settings['api_token'] ?? '';
  }

}
