<?php

namespace Drupal\siwe_login\EventSubscriber;

use Drupal\siwe_login\Service\SiweAuthService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event subscriber for SIWE authentication.
 */
class SiweAuthenticationSubscriber implements EventSubscriberInterface {

  protected $siweAuthService;

  public function __construct(SiweAuthService $siwe_auth_service) {
    $this->siweAuthService = $siwe_auth_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // Run early to authenticate user before other subscribers.
    return [
      KernelEvents::REQUEST => ['onKernelRequest', 200],
    ];
  }

  /**
   * Handles the kernel request event.
   */
  public function onKernelRequest(RequestEvent $event) {
    // Implementation for handling authentication on each request
    // This would typically check for a JWT token or session
  }
}