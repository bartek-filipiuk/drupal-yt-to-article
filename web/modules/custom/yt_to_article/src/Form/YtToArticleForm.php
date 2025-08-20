<?php

declare(strict_types=1);

namespace Drupal\yt_to_article\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\SettingsCommand;
use Drupal\yt_to_article\Ajax\WebSocketConnectCommand;
use Drupal\Core\Render\RendererInterface;
use Drupal\yt_to_article\Service\YtToArticleApiClient;
use Drupal\yt_to_article\Exception\ApiException;
use Drupal\yt_to_article\Exception\RateLimitException;
use Drupal\yt_to_article\Exception\InsufficientFundsException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for converting YouTube videos to articles.
 */
final class YtToArticleForm extends FormBase {

  /**
   * Constructor with dependency injection.
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

    $form['youtube_url'] = [
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

    $form['generation_options'] = [
      '#type' => 'details',
      '#title' => $this->t('Article Generation Options'),
      '#open' => TRUE,
    ];

    // Writing Style
    $form['generation_options']['style'] = [
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

    $form['generation_options']['style_instructions'] = [
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

    // Target Audience
    $form['generation_options']['audience'] = [
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

    $form['generation_options']['audience_instructions'] = [
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

    // Article Length
    $form['generation_options']['length'] = [
      '#type' => 'select',
      '#title' => $this->t('Article length'),
      '#options' => [
        'rating' => $this->t('Hot or Not'),
        'fight' => $this->t('Two AI battles against best quotes from video'),
        //'summary' => $this->t('Summary - Executive summary (150-250 words)'),
        'brief' => $this->t('Brief - Quick read (300-500 words)'),
        //'standard' => $this->t('Standard - Comprehensive coverage (800-1200 words)'),
        'detailed' => $this->t('Detailed - In-depth analysis (1500-2500 words)'),
      ],
      '#default_value' => 'standard',
      '#description' => $this->t('Choose the appropriate length for your needs.'),
    ];

    // Output Format
    $form['generation_options']['output_format'] = [
      '#type' => 'select',
      '#title' => $this->t('Output format'),
      '#options' => [
        'markdown' => $this->t('Markdown - Standard Markdown format'),
        'html' => $this->t('HTML - Clean HTML with semantic tags'),
        'faq' => $this->t('FAQ - Question-and-answer format'),
        'listicle' => $this->t('Listicle - Numbered list with key takeaways'),
      ],
      '#default_value' => 'markdown',
      '#description' => $this->t('Choose how you want the content formatted.'),
    ];

    // Language
    $form['generation_options']['language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => [
        'en' => $this->t('English'),
        'pl' => $this->t('Polish'),
      ],
      '#default_value' => 'en',
      '#description' => $this->t('Choose the language for the generated article.'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
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
    ];

    // Container for results and progress
    $form['result_container'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'yt-to-article-result',
        'class' => ['yt-to-article-result-container'],
      ],
    ];

    // Add any existing result from form state
    if ($form_state->has('result_content')) {
      $form['result_container']['content'] = $form_state->get('result_content');
    }

    // Separate container for real-time messages that won't be replaced by AJAX
    $form['messages_container'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'yt-to-article-messages-container',
        'class' => ['yt-to-article-messages-container'],
      ],
      '#markup' => '<div class="yt-to-article-messages" data-drupal-messages aria-live="polite"><div class="messages__wrapper"></div></div>',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $youtubeUrl = $form_state->getValue('youtube_url');

    // Validate YouTube URL format
    $pattern = '/^(https?:\/\/)?(www\.)?(youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)[\w-]+(&[\w=]*)?$/i';

    if (!preg_match($pattern, $youtubeUrl)) {
      $form_state->setErrorByName('youtube_url', $this->t('Please enter a valid YouTube URL.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // This method is not used in AJAX submissions
  }

  /**
   * AJAX callback for form submission.
   */
  public function ajaxSubmitCallback(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();

    // Check for validation errors
    if ($form_state->hasAnyErrors()) {
      $messages = ['#type' => 'status_messages'];
      $response->addCommand(new HtmlCommand('#yt-to-article-result', $messages));
      return $response;
    }

    try {
      $youtubeUrl = $form_state->getValue('youtube_url');

      // Build configuration from form values
      $config = [
        'style' => $form_state->getValue('style', 'casual'),
        'audience' => $form_state->getValue('audience', 'general'),
        'length' => $form_state->getValue('length', 'standard'),
        'output_format' => $form_state->getValue('output_format', 'markdown'),
        'language' => $form_state->getValue('language', 'en'),
        // Hardcoded LLM configuration
        'llm_provider' => 'openrouter',
        'llm_model' => 'google/gemini-2.5-flash',
      ];

      // Add custom instructions if needed
      if ($config['style'] === 'custom') {
        $config['style_instructions'] = $form_state->getValue('style_instructions', '');
      }
      if ($config['audience'] === 'custom') {
        $config['audience_instructions'] = $form_state->getValue('audience_instructions', '');
      }

      $options = [
        'config' => $config,
      ];

      // Add webhook URL from settings if available
      $webhookUrl = $this->apiClient->getWebhookUrl();
      if ($webhookUrl) {
        $options['webhook_url'] = $webhookUrl;
        // Configure webhook to receive markdown content with metadata
        // Note: API only supports 'markdown' or 'json' for webhook content_type
        $options['webhook_config'] = [
          'content_type' => 'markdown',
          'include_metadata' => true,
        ];

        $this->logger->info('Including webhook URL in article generation request: {url}', [
          'url' => $webhookUrl,
        ]);
      }

      // Call the API
      $apiResponse = $this->apiClient->generateArticle($youtubeUrl, $options);


      // Prepare the progress container
      $progressElement = [
        '#theme' => 'yt_to_article_progress',
        '#request_id' => $apiResponse->requestId,
        '#initial_status' => $apiResponse->status,
      ];

      $renderedProgress = $this->renderer->render($progressElement);

      // Update the result container
      $response->addCommand(new HtmlCommand('#yt-to-article-result', $renderedProgress));


      // Pass WebSocket configuration to JavaScript
      $wsSettings = [
        'ytToArticle' => [
          'requestId' => $apiResponse->requestId,
          'wsUrl' => $this->getWebSocketUrl(),
          'token' => $this->getApiToken(),
        ],
      ];
      $response->addCommand(new SettingsCommand($wsSettings));

      // Add custom command to connect WebSocket
      $response->addCommand(new WebSocketConnectCommand(
        $apiResponse->requestId,
        $this->getWebSocketUrl(),
        $this->getApiToken()
      ));

      // Store in session for anonymous users
      $currentUser = \Drupal::currentUser();
      if ($currentUser->isAnonymous()) {
        $session = \Drupal::request()->getSession();
        $anonymousArticles = $session->get('yt_to_article_anonymous', ['articles' => []]);

        // Initialize articles array if it doesn't exist
        if (!isset($anonymousArticles['articles'])) {
          $anonymousArticles['articles'] = [];
        }

        // Add new article to the beginning
        array_unshift($anonymousArticles['articles'], [
          'request_id' => $apiResponse->requestId,
          'timestamp' => time(),
          'youtube_url' => $youtubeUrl,
          'title' => NULL, // Will be updated when node is created
        ]);

        // Keep only last 10 articles
        $anonymousArticles['articles'] = array_slice($anonymousArticles['articles'], 0, 10);

        $session->set('yt_to_article_anonymous', $anonymousArticles);
      }

      // Log successful submission
      $this->logger->info('Article generation started for {url} with request ID {id}', [
        'url' => $youtubeUrl,
        'id' => $apiResponse->requestId,
      ]);

    } catch (InsufficientFundsException $e) {
      $message = $this->t('Insufficient funds to generate article. You need either credits (current: @credits) or minimum balance of $@min_balance (current: $@balance).', [
        '@credits' => $e->getCurrentCredits(),
        '@min_balance' => number_format($e->getMinimumBalance(), 2),
        '@balance' => number_format($e->getCurrentBalance(), 2),
      ]);

      $this->messenger()->addError($message);
      $response->addCommand(new HtmlCommand('#yt-to-article-result', ['#markup' => '<div class="messages messages--error">' . $message . '</div>']));

      // Also send this message via WebSocket if connection exists
      $response->addCommand(new InvokeCommand(null, 'eval', [
        'if (window.YtToArticleWebSocket && window.YtToArticleWebSocket.ws && window.YtToArticleWebSocket.ws.readyState === WebSocket.OPEN) {
          window.YtToArticleWebSocket.showError("' . addslashes($message) . '");
        }'
      ]));

      $this->logger->warning('Insufficient funds for user to generate article', [
        'credits' => $e->getCurrentCredits(),
        'balance' => $e->getCurrentBalance(),
        'minimum_balance' => $e->getMinimumBalance(),
      ]);

    } catch (RateLimitException $e) {
      $message = $this->t('Rate limit exceeded. Please wait @seconds seconds before trying again.', [
        '@seconds' => $e->getRetryAfter() ?? 60,
      ]);

      $this->messenger()->addError($message);
      $response->addCommand(new HtmlCommand('#yt-to-article-result', ['#markup' => '<div class="messages messages--error">' . $message . '</div>']));

    } catch (ApiException $e) {
      // Check if this is a 402 error that wasn't caught by InsufficientFundsException
      if (strpos($e->getMessage(), '402 Payment Required') !== false) {
        $message = $this->t('Unable to generate article: Insufficient funds. Please contact your administrator to purchase credits or add funds to your account.');
      } else {
        // For other API errors, show a generic message
        $message = $this->t('Unable to connect to the article generation service. Please try again later.');
      }

      $this->messenger()->addError($message);
      $response->addCommand(new HtmlCommand('#yt-to-article-result', ['#markup' => '<div class="messages messages--error">' . $message . '</div>']));

      // Log the full error details for administrators
      $this->logger->error('API error: {message}', [
        'message' => $e->getMessage(),
        'context' => $e->getContext(),
      ]);

    } catch (\Exception $e) {
      $message = $this->t('An unexpected error occurred. Please try again later.');

      $this->messenger()->addError($message);
      $response->addCommand(new HtmlCommand('#yt-to-article-result', ['#markup' => '<div class="messages messages--error">' . $message . '</div>']));

      $this->logger->error('Unexpected error: {message}', ['message' => $e->getMessage()]);
    }

    return $response;
  }

  /**
   * Get the WebSocket URL from configuration.
   */
  private function getWebSocketUrl(): string {
    $settings = \Drupal::service('settings')->get('yt_to_article', []);
    return $settings['websocket_url'] ?? 'ws://localhost:8000/api/v1/ws';
  }

  /**
   * Get the API token from configuration.
   */
  private function getApiToken(): string {
    $settings = \Drupal::service('settings')->get('yt_to_article', []);
    return $settings['api_token'] ?? '';
  }
}
