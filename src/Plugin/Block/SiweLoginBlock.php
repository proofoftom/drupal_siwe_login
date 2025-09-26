<?php

namespace Drupal\siwe_login\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a 'SIWE Login' block.
 *
 * @Block(
 *   id = "siwe_login_block",
 *   admin_label = @Translation("SIWE Login Block"),
 *   category = @Translation("Authentication"),
 * )
 */
class SiweLoginBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'button_text' => 'Sign in with Ethereum',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['button_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Button text'),
      '#default_value' => $this->configuration['button_text'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['button_text'] = $form_state->getValue('button_text');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      'siwe_login_button' => [
        '#type' => 'button',
        '#id' => 'siwe-login-button',
        '#value' => $this->configuration['button_text'],
        '#attributes' => [
          'class' => ['button', 'button--small', 'button--primary'],
        ],
        '#attached' => [
          'library' => [
            'siwe_login/siwe_login_js',
            'siwe_login/siwe_login_styles',
          ],
        ],
      ],
    ];
  }

}
