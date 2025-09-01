<?php

namespace Drupal\siwe_login\Controller;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Controller for email verification confirmation.
 */
class EmailVerificationController extends ControllerBase {

  /**
   * The private tempstore.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStore;

  /**
   * Constructs a new EmailVerificationController.
   *
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The tempstore factory.
   */
  public function __construct(PrivateTempStoreFactory $temp_store_factory) {
    $this->tempStore = $temp_store_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('tempstore.private')
    );
  }

  /**
   * Confirms the email verification.
   *
   * @param int $uid
   *   User ID of the user requesting verification.
   * @param int $timestamp
   *   The current timestamp.
   * @param string $hash
   *   Verification link hash.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Returns a redirect to the homepage with a success message.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   If the verification information is incorrect or expired.
   */
  public function confirm($uid, $timestamp, $hash) {
    // For temporary users, $uid might be 0. We'll validate using the hash instead.
    if ($uid > 0) {
      /** @var \Drupal\user\UserInterface $user */
      $user = $this->entityTypeManager()->getStorage('user')->load($uid);
      
      if ($redirect = $this->determineErrorRedirect($user, $timestamp, $hash)) {
        return $redirect;
      }
    }

    // Get the SIWE data from tempstore
    $tempstore = $this->tempStore->get('siwe_login');
    $siwe_data = $tempstore->get('verification_' . $hash);
    
    if (!$siwe_data) {
      $this->messenger()->addError($this->t('The verification link is invalid or has expired.'));
      return $this->redirect('<front>');
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
        $this->getLogger('siwe_login')->warning('Failed to extract ENS name from SIWE message: @message', [
          '@message' => $e->getMessage(),
        ]);
      }
    }

    // Add ENS name and email to the data passed to user manager.
    $siwe_data['ensName'] = $ensName;

    // Find or create user with the provided email.
    $user_manager = \Drupal::service('siwe_login.user_manager');
    $user = $user_manager->findOrCreateUserWithEmail($siwe_data['address'], $siwe_data);

    if ($user) {
      // Check if ENS/username is required and user doesn't have an ENS name
      $config = \Drupal::config('siwe_login.settings');
      if ($config->get('require_ens_or_username')) {
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
            $this->getLogger('siwe_login')->warning('Failed to extract ENS name from SIWE message: @message', [
              '@message' => $e->getMessage(),
            ]);
          }
        }

        // If user doesn't have an ENS name and has a generated username, redirect to username creation form
        if (!$ensName && $user_manager->isGeneratedUsername($user->getAccountName(), $siwe_data['address'])) {
          // Store the SIWE data in tempstore for later use
          $tempstore->set('pending_siwe_data', $siwe_data);
          
          // Clear the verification tempstore.
          $tempstore->delete('verification_' . $hash);
          
          $this->messenger()->addStatus($this->t('Email verified successfully. Please create a username for your account.'));
          
          // Redirect to username creation form.
          return $this->redirect('siwe_login.username_creation_form');
        }
      }

      // Clear the tempstore.
      $tempstore->delete('verification_' . $hash);

      // Authenticate the user.
      user_login_finalize($user);

      $this->messenger()->addStatus($this->t('Email verified successfully. You have been logged in.'));

      // Redirect to the user's profile or homepage.
      return $this->redirect('<front>');
    }
    else {
      $this->messenger()->addError($this->t('Unable to create or authenticate user.'));
      return $this->redirect('<front>');
    }
  }

  /**
   * Validates user, hash, and timestamp.
   *
   * @param \Drupal\user\UserInterface|null $user
   *   User requesting verification. NULL if the user does not exist.
   * @param int $timestamp
   *   The current timestamp.
   * @param string $hash
   *   Verification link hash.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|null
   *   Returns a redirect if the information is incorrect. It redirects to
   *   homepage with a message for the user.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   If $uid is for a blocked user or invalid user ID.
   */
  protected function determineErrorRedirect(?UserInterface $user, int $timestamp, string $hash): ?RedirectResponse {
    // Verify that the user exists and is active.
    if ($user === NULL || !$user->isActive()) {
      // Blocked or invalid user ID, so deny access.
      throw new AccessDeniedHttpException();
    }

    // Time out, in seconds, until verification URL expires.
    $timeout = 86400; // 24 hours
    $current = \Drupal::time()->getRequestTime();
    
    // No time out for first time verification.
    if ($user->getLastLoginTime() && $current - $timestamp > $timeout) {
      $this->messenger()->addError($this->t('You have tried to use a verification link that has expired. Please request a new verification email.'));
      return $this->redirect('<front>');
    }
    elseif ($user->isAuthenticated() && $this->validatePathParameters($user, $timestamp, $hash, $timeout)) {
      // The information provided is valid.
      return NULL;
    }

    $this->messenger()->addError($this->t('You have tried to use a verification link that has either been used or is no longer valid. Please request a new verification email.'));
    return $this->redirect('<front>');
  }

  /**
   * Validates hash and timestamp.
   *
   * @param \Drupal\user\UserInterface $user
   *   User requesting verification.
   * @param int $timestamp
   *   The timestamp.
   * @param string $hash
   *   Verification link hash.
   * @param int $timeout
   *   Link expiration timeout.
   *
   * @return bool
   *   Whether the provided data are valid.
   */
  protected function validatePathParameters(UserInterface $user, int $timestamp, string $hash, int $timeout = 0): bool {
    $current = \Drupal::time()->getRequestTime();
    $timeout_valid = ((!empty($timeout) && $current - $timestamp < $timeout) || empty($timeout));
    
    // Create a hash based on user data and SIWE data
    $data = $timestamp . ':' . $user->id() . ':' . $user->getEmail();
    // We don't have the Ethereum address here, so we'll just verify the hash
    // with the user's password as the key
    $expected_hash = Crypt::hmacBase64($data, \Drupal::service('private_key')->get() . $user->getPassword());
    
    return ($timestamp >= $user->getLastLoginTime()) && $timestamp <= $current && $timeout_valid && hash_equals($expected_hash, $hash);
  }

}