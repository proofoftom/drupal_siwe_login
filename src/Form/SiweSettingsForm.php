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

    // Note: The expected_domain setting is managed automatically by SIWE Server
    // when present, or defaults to the current host when SIWE Server is not used.
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
      '#description' => $this->t('Require users to set a username if they don\'t have an ENS name.'),
    ];

    $form['require_email_verification'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Require Email Verification'),
      '#default_value' => $config->get('require_email_verification'),
      '#description' => $this->t('Require email verification for new users.'),
    ];

    $form['session_timeout'] = [
      '#type' => 'number',
      '#title' => $this->t('Session Timeout'),
      '#default_value' => $config->get('session_timeout'),
      '#description' => $this->t('Session timeout in seconds.'),
      '#min' => 1,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Save all settings except expected_domain, which is managed automatically.
    $this->config('siwe_login.settings')
      ->set('nonce_ttl', $form_state->getValue('nonce_ttl'))
      ->set('message_ttl', $form_state->getValue('message_ttl'))
      ->set('require_email_verification', $form_state->getValue('require_email_verification'))
      ->set('require_ens_or_username', $form_state->getValue('require_ens_or_username'))
      ->set('session_timeout', $form_state->getValue('session_timeout'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
