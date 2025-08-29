<?php

namespace Drupal\siwe_login\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Drupal\user\UserAuthInterface;
use Drupal\user\UserInterface;

/**
 * Service for handling SIWE authentication.
 */
class SiweAuthService {

  protected $entityTypeManager;
  protected $session;
  protected $userAuth;
  protected $logger;
  protected $messageValidator;
  protected $userManager;
  protected $config;

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    SessionInterface $session,
    UserAuthInterface $user_auth,
    LoggerChannelFactoryInterface $logger_factory,
    SiweMessageValidator $message_validator,
    EthereumUserManager $user_manager,
    ConfigFactoryInterface $config_factory
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->session = $session;
    $this->userAuth = $user_auth;
    $this->logger = $logger_factory->get('siwe_login');
    $this->messageValidator = $message_validator;
    $this->userManager = $user_manager;
    $this->config = $config_factory->get('siwe_login.settings');
  }

  /**
   * Generates a nonce for SIWE.
   */
  public function generateNonce(): string {
    // Generate a cryptographically secure random nonce.
    return bin2hex(random_bytes(16));
  }

  /**
   * Authenticates a user using SIWE.
   */
  public function authenticate(array $data): ?UserInterface {
    try {
      // Validate the SIWE message
      $is_valid = $this->messageValidator->validateMessage($data);

      if (!$is_valid) {
        return NULL;
      }

      // Find or create user
      $user = $this->userManager->findOrCreateUser($data['address'], $data);

      return $user;
    }
    catch (\Exception $e) {
      $this->logger->error('SIWE authentication failed: @message', [
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
  }
}