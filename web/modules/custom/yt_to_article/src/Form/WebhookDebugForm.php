<?php

namespace Drupal\yt_to_article\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\yt_to_article\Service\WebhookService;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Debug form to test WebhookService processArticleCompleted method.
 */
class WebhookDebugForm extends FormBase {

  /**
   * The webhook service.
   *
   * @var \Drupal\yt_to_article\Service\WebhookService
   */
  protected $webhookService;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a new WebhookDebugForm.
   *
   * @param \Drupal\yt_to_article\Service\WebhookService $webhook_service
   *   The webhook service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(WebhookService $webhook_service, MessengerInterface $messenger) {
    $this->webhookService = $webhook_service;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('yt_to_article.webhook'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'yt_to_article_webhook_debug_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['description'] = [
      '#type' => 'markup',
      '#markup' => '<p>' . $this->t('This form triggers the processArticleCompleted method with test data for debugging purposes.') . '</p>',
    ];

    $form['test_payload'] = [
      '#type' => 'details',
      '#title' => $this->t('Test Payload'),
      '#open' => TRUE,
    ];

    $form['test_payload']['request_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Request ID'),
      '#default_value' => 'test-' . uniqid(),
      '#required' => TRUE,
    ];

    $form['test_payload']['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Article Title'),
      '#default_value' => 'Test Article from Debug Form',
      '#required' => TRUE,
    ];

    $form['test_payload']['video_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Video URL'),
      '#default_value' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
      '#required' => TRUE,
    ];

    $form['test_payload']['content_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Content Type'),
      '#options' => [
        'markdown' => $this->t('Markdown'),
        'html' => $this->t('HTML'),
      ],
      '#default_value' => 'markdown',
    ];

    $form['test_payload']['content'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Content'),
      '#default_value' => $this->getDefaultContent(),
      '#rows' => 15,
      '#required' => TRUE,
    ];

    $form['test_payload']['format'] = [
      '#type' => 'select',
      '#title' => $this->t('Text Format'),
      '#options' => [
        'full_html' => $this->t('Full HTML'),
        'basic_html' => $this->t('Basic HTML'),
        'plain_text' => $this->t('Plain Text'),
      ],
      '#default_value' => 'full_html',
      '#description' => $this->t('This will be used if content type is HTML'),
    ];

    $form['metadata'] = [
      '#type' => 'details',
      '#title' => $this->t('Metadata'),
      '#open' => TRUE,
    ];

    $form['metadata']['accuracy_score'] = [
      '#type' => 'number',
      '#title' => $this->t('Accuracy Score'),
      '#default_value' => 95,
      '#min' => 0,
      '#max' => 100,
    ];

    $form['metadata']['word_count'] = [
      '#type' => 'number',
      '#title' => $this->t('Word Count'),
      '#default_value' => 500,
      '#min' => 0,
    ];

    $form['metadata']['include_cost'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include cost metadata'),
      '#default_value' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Trigger processArticleCompleted'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Build the test payload
    $content = $form_state->getValue('content');
    
    $payload = [
      'event' => 'article.completed',
      'request_id' => $form_state->getValue('request_id'),
      'data' => [
        'content' => $content,
        'content_type' => $form_state->getValue('content_type'),
        'video_info' => [
          'title' => $form_state->getValue('title'),
          'url' => $form_state->getValue('video_url'),
        ],
        'metadata' => [
          'accuracy_score' => (int) $form_state->getValue('accuracy_score'),
          'word_count' => (int) $form_state->getValue('word_count'),
          'generation_time_seconds' => 60,
        ],
      ],
    ];

    // Add cost metadata if requested
    if ($form_state->getValue('include_cost')) {
      $payload['data']['metadata']['cost_summary'] = [
        'total_usd' => 0.15,
        'llm_cost_usd' => 0.10,
        'service_costs' => [
          'supadata_transcription' => 0.05,
        ],
        'input_tokens' => 5000,
        'output_tokens' => 1000,
        'cached_tokens' => 0,
        'llm_calls' => 5,
      ];
    }

    // Call the webhook service
    try {
      $result = $this->webhookService->processArticleCompleted($payload);
      
      if ($result['success']) {
        $this->messenger->addStatus($this->t('Successfully processed article. Node ID: @nid', [
          '@nid' => $result['node_id'] ?? 'Unknown',
        ]));
      } else {
        $this->messenger->addError($this->t('Failed to process article: @error', [
          '@error' => $result['error'] ?? 'Unknown error',
        ]));
      }
    } catch (\Exception $e) {
      $this->messenger->addError($this->t('Exception occurred: @message', [
        '@message' => $e->getMessage(),
      ]));
    }
  }

  /**
   * Get default content for testing.
   */
  private function getDefaultContent() {
    return '# Test Article Content

This is a test article generated from the debug form.

## Section 1: Introduction

This is the introduction paragraph with some **bold text** and *italic text*.

### Key Points
- First key point
- Second key point
- Third key point

## Section 2: Main Content

This section contains the main content of the article. It includes various HTML elements to test the rendering.

<p>This is a paragraph with <strong>strong emphasis</strong> and <em>italic emphasis</em>.</p>

<ul>
  <li>Unordered list item 1</li>
  <li>Unordered list item 2</li>
  <li>Unordered list item 3</li>
</ul>

## Section 3: Conclusion

This is the conclusion of our test article. It demonstrates that the webhook processing is working correctly.

---

*Generated by webhook debug form*';
  }

}