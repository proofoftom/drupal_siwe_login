<?php

namespace Drupal\siwe_login\Plugin\Mail;

use Drupal\Core\Mail\MailInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Defines the SIWE Login mail plugin.
 *
 * @Mail(
 *   id = "siwe_login",
 *   label = @Translation("SIWE Login mailer"),
 *   description = @Translation("Sends emails for SIWE Login module.")
 * )
 */
class SiweMail implements MailInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function format(array $message) {
    // Join the body array into one string.
    $message['body'] = implode("\n\n", $message['body']);
    // Convert any HTML to plain-text.
    $message['body'] = trim(strip_tags($message['body']));
    // Wrap the mail body for sending.
    $message['body'] = wordwrap($message['body'], 77);
    return $message;
  }

  /**
   * {@inheritdoc}
   */
  public function mail(array $message) {
    // Get the default mailer.
    $default_mailer = \Drupal::service('plugin.manager.mail')->createInstance('php_mail');
    // Send the email using the default mailer.
    return $default_mailer->mail($message);
  }

}
