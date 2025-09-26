<?php

namespace Drupal\siwe_login\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form for SIWE login settings.
 */
class SiweSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'siwe_login_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'siwe_login.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('siwe_login.settings');

    // Note: The expected_domain setting is managed automatically by SIWE
    // Server when present, or defaults to the current host when SIWE Server
    // is not used.
    // It is not exposed in the UI to simplify configuration.
    $form['nonce_ttl'] = [
      '#type' => 'number',
      '#title' => $this->t('Nonce TTL'),
      '#default_value' => $config->get('nonce_ttl'),
      '#description' => $this->t('Time-to-live for nonces in seconds.'),
      '#min' => 1,
    ];

    $form['message_ttl'] = [
      '#type' => 'number',
      '#title' => $this->t('Message TTL'),
      '#default_value' => $config->get('message_ttl'),
      '#description' => $this->t('Time-to-live for SIWE messages in seconds.'),
      '#min' => 1,
    ];

    $form['require_ens_or_username'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Require ENS or Username'),
      '#default_value' => $config->get('require_ens_or_username'),
      '#description' => $this->t("Require users to set a username if they don't have an ENS name."),
    ];

    $form['require_email_verification'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Require Email Verification'),
      '#default_value' => $config->get('require_email_verification'),
      '#description' => $this->t('Require email verification for new users.'),
    ];

    // Convert session timeout from seconds to hours for user-friendly display.
    $session_timeout_hours = $config->get('session_timeout') / 3600;

    $form['session_timeout_hours'] = [
      '#type' => 'number',
      '#title' => $this->t('Session Timeout (hours)'),
      '#default_value' => $session_timeout_hours,
      '#description' => $this->t('Session timeout in hours. Default is 24 hours.'),
      '#min' => 1,
      '#step' => 1,
    ];

    $form['enable_ens_validation'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable ENS Validation'),
      '#default_value' => $config->get('enable_ens_validation'),
      '#description' => $this->t('Enable validation that ENS names resolve to signing addresses. Requires a valid Ethereum provider URL.'),
    ];

    $form['ethereum_provider_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Ethereum Provider URL'),
      '#default_value' => $config->get('ethereum_provider_url'),
      '#description' => $this->t('URL for the Ethereum RPC provider (Alchemy, Infura, etc.).'),
      '#states' => [
        'visible' => [
          ':input[name="enable_ens_validation"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="enable_ens_validation"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getValue('enable_ens_validation')) {
      if (empty(trim($form_state->getValue('ethereum_provider_url')))) {
        $form_state->setErrorByName('ethereum_provider_url', $this->t('Ethereum Provider URL is required when ENS validation is enabled.'));
      }
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Save all settings except expected_domain, which is managed
    // automatically.
    // Convert session timeout from hours to seconds.
    $session_timeout_seconds = $form_state->getValue('session_timeout_hours') * 3600;

    $this->config('siwe_login.settings')
      ->set('nonce_ttl', $form_state->getValue('nonce_ttl'))
      ->set('message_ttl', $form_state->getValue('message_ttl'))
      ->set('require_email_verification', $form_state->getValue('require_email_verification'))
      ->set('require_ens_or_username', $form_state->getValue('require_ens_or_username'))
      ->set('session_timeout', $session_timeout_seconds)
      ->set('ethereum_provider_url', $form_state->getValue('ethereum_provider_url'))
      ->set('enable_ens_validation', $form_state->getValue('enable_ens_validation'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
