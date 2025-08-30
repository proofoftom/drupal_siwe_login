<?php

namespace Drupal\siwe_login\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\user\UserInterface;

/**
 * Manages Ethereum wallet-based user accounts.
 */
class EthereumUserManager
{

  protected $entityTypeManager;
  protected $logger;
  protected $currentUser;
  protected $languageManager;

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $logger_factory,
    AccountProxyInterface $current_user,
    LanguageManagerInterface $language_manager
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger_factory->get('siwe_login');
    $this->currentUser = $current_user;
    $this->languageManager = $language_manager;
  }

  /**
   * Finds or creates a user by Ethereum address.
   *
   * @param string $address
   *   The Ethereum wallet address.
   * @param array $additional_data
   *   Additional user data from SIWE message.
   *
   * @return \Drupal\user\UserInterface
   *   The user entity.
   */
  public function findOrCreateUser(string $address, array $additional_data = []): UserInterface
  {
    $address = $this->normalizeAddress($address);

    // Try to find existing user
    $user = $this->findUserByAddress($address);

    if (!$user) {
      $user = $this->createUserFromAddress($address, $additional_data);
    } else {
      // Update last login and any additional data
      $this->updateUserData($user, $additional_data);
    }

    return $user;
  }

  /**
   * Finds a user by Ethereum address.
   */
  public function findUserByAddress(string $address): ?UserInterface
  {
    $users = $this->entityTypeManager
      ->getStorage('user')
      ->loadByProperties(['field_ethereum_address' => $this->normalizeAddress($address)]);

    return $users ? reset($users) : NULL;
  }

  /**
   * Creates a new user from an Ethereum address.
   */
  protected function createUserFromAddress(string $address, array $data): UserInterface
  {
    $normalized_address = $this->normalizeAddress($address);

    // Use ENS name from the verified SIWE message
    $ens_name = $data['ensName'] ?? NULL;
    $username = $ens_name ?: $this->generateUsername($normalized_address);

    $user_storage = $this->entityTypeManager->getStorage('user');

    $user = $user_storage->create([
      'name' => $username,
      'mail' => $this->generateEmail($normalized_address),
      'field_ethereum_address' => $normalized_address,
      'status' => 1,
      'langcode' => $this->languageManager->getCurrentLanguage()->getId(),
    ]);

    $user->save();

    $this->logger->info('Created new user <strong>@name</strong> for Ethereum address @address', [
      '@name' => $username,
      '@address' => $normalized_address,
    ]);

    return $user;
  }

  /**
   * Updates user data from SIWE message.
   */
  protected function updateUserData(UserInterface $user, array $data): void
  {
    // Update ENS name from SIWE data
    if (isset($data['ensName']) && $data['ensName'] !== $user->get('name')->value) {
      $user->set('name', $data['ensName']);

      // Save the user
      $user->save();

      $this->logger->info('Updated ENS for Ethereum address @address to @ens_name', [
        '@address' => $user->get('field_ethereum_address')->value,
        '@ens_name' => $data['ensName'],
      ]);
    }
  }

  /**
   * Normalizes an Ethereum address.
   */
  protected function normalizeAddress(string $address): string
  {
    // Convert to checksummed address format
    return strtolower(trim($address));
  }

  /**
   * Generates a unique username from address.
   */
  protected function generateUsername(string $address): string
  {
    $base_username = 'eth_' . substr($address, 2, 8);
    $username = $base_username;
    $i = 1;

    while ($this->usernameExists($username)) {
      $username = $base_username . '_' . $i++;
    }

    return $username;
  }

  /**
   * Generates email from address.
   */
  protected function generateEmail(string $address): string
  {
    return substr($address, 2, 12) . '@ethereum.local';
  }

  /**
   * Checks if username exists.
   */
  protected function usernameExists(string $username): bool
  {
    $users = $this->entityTypeManager
      ->getStorage('user')
      ->loadByProperties(['name' => $username]);

    return !empty($users);
  }
}