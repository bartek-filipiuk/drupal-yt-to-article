<?php

declare(strict_types=1);

namespace Drupal\yt_to_article\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure YT to Article settings.
 */
final class YtToArticleSettingsForm extends ConfigFormBase {
  
  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'yt_to_article_settings';
  }
  
  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['yt_to_article.settings'];
  }
  
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('yt_to_article.settings');
    
    $form['api_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('API Settings'),
      '#open' => TRUE,
    ];
    
    $form['api_settings']['api_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Base URL'),
      '#description' => $this->t('The base URL for the PocketFlow API. Can be overridden in settings.php.'),
      '#default_value' => $config->get('api_url'),
      '#required' => TRUE,
    ];
    
    $form['api_settings']['websocket_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('WebSocket Base URL'),
      '#description' => $this->t('The base URL for WebSocket connections. Can be overridden in settings.php.'),
      '#default_value' => $config->get('websocket_url'),
      '#required' => TRUE,
    ];
    
    $form['api_settings']['timeout'] = [
      '#type' => 'number',
      '#title' => $this->t('Request Timeout'),
      '#description' => $this->t('API request timeout in seconds.'),
      '#default_value' => $config->get('timeout'),
      '#min' => 10,
      '#max' => 300,
      '#required' => TRUE,
    ];
    
    $form['api_settings']['retry_attempts'] = [
      '#type' => 'number',
      '#title' => $this->t('Retry Attempts'),
      '#description' => $this->t('Number of retry attempts for failed API requests.'),
      '#default_value' => $config->get('retry_attempts'),
      '#min' => 0,
      '#max' => 5,
      '#required' => TRUE,
    ];
    
    $form['defaults'] = [
      '#type' => 'details',
      '#title' => $this->t('Default Values'),
      '#open' => TRUE,
    ];
    
    $form['defaults']['default_max_length'] = [
      '#type' => 'number',
      '#title' => $this->t('Default Maximum Article Length'),
      '#description' => $this->t('Default maximum number of words for generated articles.'),
      '#default_value' => $config->get('default_max_length'),
      '#min' => 500,
      '#max' => 10000,
      '#step' => 100,
      '#required' => TRUE,
    ];
    
    $form['defaults']['default_style'] = [
      '#type' => 'select',
      '#title' => $this->t('Default Writing Style'),
      '#description' => $this->t('Default style for generated articles.'),
      '#options' => [
        'informative' => $this->t('Informative'),
        'casual' => $this->t('Casual'),
        'technical' => $this->t('Technical'),
        'academic' => $this->t('Academic'),
      ],
      '#default_value' => $config->get('default_style'),
      '#required' => TRUE,
    ];
    
    $form['info'] = [
      '#type' => 'details',
      '#title' => $this->t('API Token Configuration'),
      '#open' => TRUE,
    ];
    
    $form['info']['token_info'] = [
      '#markup' => $this->t('<p><strong>Important:</strong> The API token must be configured in your settings.php file for security reasons.</p>
        <p>Add the following to your settings.php file:</p>
        <pre>$settings[\'yt_to_article\'] = [
  \'api_token\' => \'yt_your_token_here\',
];</pre>'),
    ];
    
    return parent::buildForm($form, $form_state);
  }
  
  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $api_url = $form_state->getValue('api_url');
    if (!filter_var($api_url, FILTER_VALIDATE_URL)) {
      $form_state->setErrorByName('api_url', $this->t('Please enter a valid URL.'));
    }
    
    $websocket_url = $form_state->getValue('websocket_url');
    if (!preg_match('/^wss?:\/\//', $websocket_url)) {
      $form_state->setErrorByName('websocket_url', $this->t('WebSocket URL must start with ws:// or wss://'));
    }
  }
  
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('yt_to_article.settings')
      ->set('api_url', $form_state->getValue('api_url'))
      ->set('websocket_url', $form_state->getValue('websocket_url'))
      ->set('timeout', (int) $form_state->getValue('timeout'))
      ->set('retry_attempts', (int) $form_state->getValue('retry_attempts'))
      ->set('default_max_length', (int) $form_state->getValue('default_max_length'))
      ->set('default_style', $form_state->getValue('default_style'))
      ->save();
    
    parent::submitForm($form, $form_state);
  }
}