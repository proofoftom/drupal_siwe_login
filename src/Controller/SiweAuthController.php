<?php

namespace Drupal\siwe_login\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\siwe_login\Service\SiweAuthService;

/**
 * Controller for SIWE authentication endpoints.
 */
class SiweAuthController extends ControllerBase
{

  protected $siweAuthService;

  public function __construct(SiweAuthService $siwe_auth_service)
  {
    $this->siweAuthService = $siwe_auth_service;
  }

  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('siwe_login.auth_service')
    );
  }

  /**
   * Generates a nonce for SIWE.
   */
  public function getNonce(Request $request): JsonResponse
  {
    try {
      $nonce = $this->siweAuthService->generateNonce();

      // Store nonce in session
      $request->getSession()->set('siwe_nonce', $nonce);

      // Also store in cache for validation
      \Drupal::cache()->set('siwe_nonce_lookup:' . $nonce, TRUE, time() + 300);

      return new JsonResponse([
        'nonce' => $nonce,
        'issued_at' => date('c'),
      ]);
    } catch (\Exception $e) {
      return new JsonResponse([
        'error' => 'Failed to generate nonce',
      ], 500);
    }
  }

  /**
   * Verifies SIWE message and authenticates user.
   */
  public function verify(Request $request): JsonResponse
  {
    try {
      $data = json_decode($request->getContent(), TRUE);

      if (!$data) {
        throw new \InvalidArgumentException('Invalid request data');
      }

      // Verify SIWE message
      $user = $this->siweAuthService->authenticate($data);

      if ($user) {
        // User authenticated successfully
        user_login_finalize($user);

        return new JsonResponse([
          'success' => TRUE,
          'user' => [
            'uid' => $user->id(),
            'name' => $user->getAccountName(),
            'address' => $user->get('field_ethereum_address')->value,
          ],
        ]);
      }

      return new JsonResponse([
        'error' => 'Authentication failed',
      ], 401);
    } catch (\Exception $e) {
      $this->getLogger('siwe_login')->error('SIWE verification failed: @message', [
        '@message' => $e->getMessage(),
      ]);

      return new JsonResponse([
        'error' => $e->getMessage(),
      ], 400);
    }
  }

  /**
   * Logs out the current user.
   */
  public function logout(): JsonResponse
  {
    user_logout();

    return new JsonResponse([
      'success' => TRUE,
      'message' => 'Logged out successfully',
    ]);
  }
}