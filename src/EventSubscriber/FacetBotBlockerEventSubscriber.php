<?php

namespace Drupal\facet_bot_blocker\EventSubscriber;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event Subscriber to block requests with excessive facet parameters.
 */
class FacetBotBlockerEventSubscriber implements EventSubscriberInterface {

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackend;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The time service (for metrics start, etc.).
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a new FacetBotBlockerEventSubscriber.
   */
  public function __construct(
    ModuleHandlerInterface $moduleHandler,
    CacheBackendInterface $cacheBackend,
    ConfigFactoryInterface $configFactory,
    TimeInterface $time,
    AccountProxyInterface $currentUser,
  ) {
    $this->moduleHandler = $moduleHandler;
    $this->cacheBackend = $cacheBackend;
    $this->configFactory = $configFactory;
    $this->time = $time;
    $this->currentUser = $currentUser;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[KernelEvents::REQUEST][] = ['onKernelRequest', 101];
    return $events;
  }

  /**
   * Kernel Request listener to block requests with too many facet parameters.
   */
  public function onKernelRequest(RequestEvent $requestEvent) {
    // Only act on the main request (Drupal 9/10 => isMainRequest()).
    if (!$requestEvent->isMainRequest()) {
      return;
    }

    // If the user is logged in, and has a role with the "bypass facet bot
    // blocker" permission.
    if ($this->currentUser->hasPermission('bypass facet bot blocker')) {
      return;
    }

    // Determine if we should use cache for storing config values. We'll only
    // do so if memcache or redis is installed.
    $use_cache = ($this->moduleHandler->moduleExists('memcache') || $this->moduleHandler->moduleExists('redis'));

    // Retrieve config from cache or config system.
    $immutableConfig = $this->configFactory->get('facet_bot_blocker.settings');

    // The limit.
    $limit_cache = $this->cacheBackend->get('facet_bot_blocker.limit');
    if ($use_cache && $limit_cache) {
      $limit = $limit_cache->data;
    }
    else {
      $limit = $immutableConfig->get('facets_bot_blocker_limit');
      // Fallback if config is missing:
      if (empty($limit)) {
        $limit = 1;
      }

      // If we should cache it:
      if ($use_cache) {
        $this->cacheBackend->set('facet_bot_blocker.limit', $limit);
      }
    }

    // -- Return Gone? --
    $gone_cache = $this->cacheBackend->get('facet_bot_blocker.return_gone');
    if ($use_cache && $gone_cache) {
      $return_gone = $gone_cache->data;
    }
    else {
      $return_gone = (bool) $immutableConfig->get('facet_bot_blocker_return_gone');
      if ($use_cache) {
        $this->cacheBackend->set('facet_bot_blocker.return_gone', $return_gone);
      }
    }

    // Blocked Message.
    $message_cache = $this->cacheBackend->get('facet_bot_blocker.html');
    if ($use_cache && $message_cache) {
      $blocked_message = $message_cache->data;
    }
    else {
      $blocked_message = $immutableConfig->get('facet_bot_blocker_html');
      if (empty($blocked_message)) {
        $blocked_message = '<h1>Excessive crawling detected</h1><p>We have blocked your request.</p>';
      }

      if ($use_cache) {
        $this->cacheBackend->set('facet_bot_blocker.html', $blocked_message);
      }
    }

    // Check if the request is "over the limit" => blocked.
    $request = $requestEvent->getRequest();
    $is_blocked = FALSE;
    if (isset($_GET['f'][$limit])) {
      $is_blocked = TRUE;
    }

    // If blocked, build and set a response. Otherwise, increment "allowed"
    // counter if there's a facet param.
    if ($is_blocked) {
      $status_code = $return_gone ? Response::HTTP_GONE : Response::HTTP_FORBIDDEN;
      $formattableMarkup = new FormattableMarkup($blocked_message, ['@path' => $request->getPathInfo()]);
      $response = new Response($formattableMarkup, $status_code);
      // Add some counters if using cache.
      if ($use_cache) {
        // Increment blocked requests count.
        $blocked_cache = $this->cacheBackend->get('facet_bot_blocker.blocked_requests');
        $blocked_count = $blocked_cache ? $blocked_cache->data : 0;
        $blocked_count++;
        $this->cacheBackend->set('facet_bot_blocker.blocked_requests', $blocked_count);

        // Save last blocked info.
        $last_blocked = [
          'ip' => $request->getClientIp(),
          'path' => $request->getUri(),
          'user_agent' => $request->headers->get('User-Agent'),
        ];
        $this->cacheBackend->set('facet_bot_blocker.last_blocked_request', $last_blocked);

        // If metrics start time not set, set it now.
        if (!$this->cacheBackend->get('facet_bot_blocker.metrics_start_time')) {
          $this->cacheBackend->set('facet_bot_blocker.metrics_start_time', $this->time->getRequestTime());
        }
      }
      $requestEvent->setResponse($response);
      $requestEvent->stopPropagation();
    }
    elseif (!empty($_GET['f']) && $use_cache) {
      $allowed_cache = $this->cacheBackend->get('facet_bot_blocker.allowed_requests');
      $allowed_count = $allowed_cache ? $allowed_cache->data : 0;
      $allowed_count++;
      $this->cacheBackend->set('facet_bot_blocker.allowed_requests', $allowed_count);
      if (!$this->cacheBackend->get('facet_bot_blocker.metrics_start_time')) {
        $this->cacheBackend->set('facet_bot_blocker.metrics_start_time', $this->time->getRequestTime());
      }
    }
  }

}
