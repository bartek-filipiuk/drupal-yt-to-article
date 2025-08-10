<?php

declare(strict_types=1);

namespace Drupal\yt_to_article_admin\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Settings form for YT Article Admin module.
 */
class CostMetricsSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['yt_to_article_admin.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'yt_to_article_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('yt_to_article_admin.settings');

    $form['webhook_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Webhook Secret'),
      '#description' => $this->t('The shared secret for authenticating webhook requests. This must match the secret configured in the PocketFlow API.'),
      '#default_value' => $config->get('webhook_secret'),
      '#required' => TRUE,
      '#maxlength' => 255,
    ];

    $form['webhook_info'] = [
      '#type' => 'details',
      '#title' => $this->t('Webhook Information'),
      '#open' => TRUE,
    ];

    $webhook_url = \Drupal::request()->getSchemeAndHttpHost() . '/api/yt-article-metrics/webhook';
    $form['webhook_info']['url'] = [
      '#type' => 'item',
      '#title' => $this->t('Webhook URL'),
      '#markup' => '<code>' . $webhook_url . '</code>',
      '#description' => $this->t('Use this URL to configure the webhook in PocketFlow API.'),
    ];

    $form['webhook_info']['headers'] = [
      '#type' => 'item',
      '#title' => $this->t('Required Headers'),
      '#markup' => '<pre>X-Webhook-Secret: [your-secret-here]
Content-Type: application/json</pre>',
      '#description' => $this->t('Include these headers in webhook requests.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('yt_to_article_admin.settings')
      ->set('webhook_secret', $form_state->getValue('webhook_secret'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}