<?php

namespace Drupal\siwe_login\Form;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for email verification during SIWE authentication.
 */
class EmailVerificationForm extends FormBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The private tempstore.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStore;

  /**
   * Constructs a new EmailVerificationForm.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The tempstore factory.
   */
  public function __construct(AccountProxyInterface $current_user, PrivateTempStoreFactory $temp_store_factory) {
    $this->currentUser = $current_user;
    $this->tempStore = $temp_store_factory->get('siwe_login');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('tempstore.private')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'siwe_email_verification_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Check if we have a pending SIWE authentication.
    $siwe_data = $this->tempStore->get('pending_siwe_data');
    if (!$siwe_data) {
      $this->messenger()->addError($this->t('No pending SIWE authentication found.'));
      return $this->redirect('<front>');
    }

    $form['#title'] = $this->t('Email Verification');

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email Address'),
      '#description' => $this->t('Please provide your email address. This will be used to send you updates and notifications.'),
      '#required' => TRUE,
      '#default_value' => '',
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Verify and Continue'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $email = $form_state->getValue('email');

    // Check if email is already in use by another user.
    $user_storage = \Drupal::entityTypeManager()->getStorage('user');
    $existing_users = $user_storage->loadByProperties(['mail' => $email]);

    // Remove the current user from the list if they have the same email.
    if ($this->currentUser->isAuthenticated()) {
      unset($existing_users[$this->currentUser->id()]);
    }

    if (!empty($existing_users)) {
      $form_state->setErrorByName('email', $this->t('This email address is already in use.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $email = $form_state->getValue('email');

    // Get the pending SIWE data.
    $siwe_data = $this->tempStore->get('pending_siwe_data');
    if (!$siwe_data) {
      $this->messenger()->addError($this->t('No pending SIWE authentication found.'));
      $form_state->setRedirect('<front>');
      return;
    }

    // Extract ENS name from the raw message.
    $ensName = NULL;
    if (isset($siwe_data['message'])) {
      try {
        $validator = \Drupal::service('siwe_login.message_validator');
        $parsed = $validator->parseSiweMessage($siwe_data['message']);
        
        if (isset($parsed['resources']) && !empty($parsed['resources'])) {
          foreach ($parsed['resources'] as $resource) {
            if (strpos($resource, 'ens:') === 0) {
              $ensName = substr($resource, 4); // Remove 'ens:' prefix
              break;
            }
          }
        }
      } catch (\Exception $e) {
        \Drupal::logger('siwe_login')->warning('Failed to extract ENS name from SIWE message: @message', [
          '@message' => $e->getMessage(),
        ]);
      }
    }

    // Add ENS name and email to the data passed to user manager.
    $siwe_data['ensName'] = $ensName;
    $siwe_data['email'] = $email;

    // Create a temporary user account with the provided email.
    $user_manager = \Drupal::service('siwe_login.user_manager');
    $user = $user_manager->createTempUserWithEmail($siwe_data['address'], $siwe_data);

    if ($user) {
      // Send verification email
      if ($this->sendVerificationEmail($user, $siwe_data)) {
        // Clear the tempstore.
        $this->tempStore->delete('pending_siwe_data');
        
        $this->messenger()->addStatus($this->t('A verification email has been sent to @email. Please check your inbox and click the verification link to complete your registration.', [
          '@email' => $email,
        ]));
        
        // Redirect to homepage with message
        $form_state->setRedirect('<front>');
      } else {
        $this->messenger()->addError($this->t('Unable to send verification email. Please try again later.'));
        $form_state->setRedirect('siwe_login.email_verification_form');
      }
    }
    else {
      $this->messenger()->addError($this->t('Unable to create temporary user account.'));
      $form_state->setRedirect('siwe_login.email_verification_form');
    }
  }

  /**
   * Sends a verification email to the user.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user account.
   * @param array $siwe_data
   *   The SIWE data.
   *
   * @return bool
   *   TRUE if the email was sent successfully, FALSE otherwise.
   */
  protected function sendVerificationEmail(UserInterface $user, array $siwe_data) {
    try {
      // Generate verification URL
      $verification_url = $this->generateVerificationUrl($user, $siwe_data);
      
      // Prepare email parameters
      $params = [
        'account' => $user,
        'siwe_data' => $siwe_data,
        'verification_url' => $verification_url,
      ];
      
      // Get the custom site notification email to use as the from email address
      // if it has been set.
      $site_mail = \Drupal::config('system.site')->get('mail_notification');
      // If the custom site notification email has not been set, we use the site
      // default for this.
      if (empty($site_mail)) {
        $site_mail = \Drupal::config('system.site')->get('mail');
      }
      if (empty($site_mail)) {
        $site_mail = ini_get('sendmail_from');
      }
      
      // Send the email
      $mail = \Drupal::service('plugin.manager.mail')->mail(
        'siwe_login',
        'email_verification',
        $user->getEmail(),
        $user->getPreferredLangcode(),
        $params,
        $site_mail
      );
      
      return !empty($mail['result']);
    } catch (\Exception $e) {
      \Drupal::logger('siwe_login')->error('Failed to send verification email: @message', [
        '@message' => $e->getMessage(),
      ]);
      return FALSE;
    }
  }

  /**
   * Generates a verification URL for the user.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user account.
   * @param array $siwe_data
   *   The SIWE data.
   *
   * @return string
   *   The verification URL.
   */
  protected function generateVerificationUrl(UserInterface $user, array $siwe_data) {
    $timestamp = \Drupal::time()->getRequestTime();
    
    // Create a hash based on user data and SIWE data
    $data = $timestamp . ':' . $user->id() . ':' . $user->getEmail();
    if (isset($siwe_data['address'])) {
      $data .= ':' . $siwe_data['address'];
    }
    $hash = Crypt::hmacBase64($data, \Drupal::service('private_key')->get() . $user->getPassword());
    
    // Store SIWE data in tempstore with a key based on the hash
    $tempstore = \Drupal::service('tempstore.private')->get('siwe_login');
    $tempstore->set('verification_' . $hash, $siwe_data);
    
    // Generate URL - make sure uid is an integer
    $uid = $user->id() ?: 0;
    
    // Ensure timestamp and hash are not null
    $timestamp = $timestamp ?: time();
    $hash = $hash ?: uniqid();
    
    // Generate URL
    return Url::fromRoute('siwe_login.email_verification_confirm', [
      'uid' => (int) $uid,
      'timestamp' => (int) $timestamp,
      'hash' => $hash,
    ], [
      'absolute' => TRUE,
    ])->toString();
  }

}