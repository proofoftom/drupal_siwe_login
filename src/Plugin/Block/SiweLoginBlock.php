<?php

namespace Drupal\siwe_login\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

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
      '#markup' => '<button id="siwe-login-button">' . $this->configuration['button_text'] . '</button>',
      '#attached' => [
        'library' => [
          'siwe_login/login',
        ],
      ],
    ];
  }
}