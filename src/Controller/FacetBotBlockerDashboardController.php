<?php

namespace Drupal\facet_bot_blocker\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Site\Settings;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Component\Datetime\TimeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class FacetBotBlockerDashboardController extends ControllerBase {

  /**
   * The cache backend to store/fetch counters.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackend;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a new FacetBotBlockerDashboardController.
   */
  public function __construct(CacheBackendInterface $cache_backend, TimeInterface $time) {
    $this->cacheBackend = $cache_backend;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('cache.default'),
      $container->get('datetime.time')
    );
  }

  /**
   * Build a dashboard page showing facet blocking stats.
   */
  public function dashboard() {
    // 1) Get the configured facet limit.
    //    Typically you'd store config in Drupal's config system, but
    //    if you are using Settings, retrieve it like this:
    $limit = Settings::get('facets_bot_blocker_limit', '1');

    // 2) Retrieve counters and data from cache.
    //    For example, your event subscriber might do something like:
    //    \Drupal::cache()->set('facet_bot_blocker.blocked_requests', $blockedCount);
    //    \Drupal::cache()->set('facet_bot_blocker.allowed_requests', $allowedCount);
    //    \Drupal::cache()->set('facet_bot_blocker.last_blocked_request', [
    //      'ip' => '...',
    //      'path' => '...',
    //      'user_agent' => '...'
    //    ]);
    //    \Drupal::cache()->set('facet_bot_blocker.metrics_start_time', time());
    //    Adjust keys to suit your actual design.

    $blocked_cache = $this->cacheBackend->get('facet_bot_blocker.blocked_requests');
    $allowed_cache = $this->cacheBackend->get('facet_bot_blocker.allowed_requests');
    $last_blocked_cache = $this->cacheBackend->get('facet_bot_blocker.last_blocked_request');
    $start_time_cache = $this->cacheBackend->get('facet_bot_blocker.metrics_start_time');

    // Extract data or use defaults if cache entries are missing.
    $blocked_requests = $blocked_cache ? $blocked_cache->data : 0;
    $allowed_requests = $allowed_cache ? $allowed_cache->data : 0;
    $last_blocked = $last_blocked_cache ? $last_blocked_cache->data : [];
    $metrics_start_time = $start_time_cache ? $start_time_cache->data : $this->time->getRequestTime();

    // 3) Compute "time since metrics started."
    $time_since_start = $this->time->getRequestTime() - $metrics_start_time;
    // Optionally convert seconds to something more readable, e.g. hours:
    $time_since_string = round($time_since_start / 3600, 2) . ' hours';

    // 4) Prepare a small table. You can theme this however you like.
    $rows = [];
    $rows[] = [
      $this->t('Current facet limit'),
      $limit,
    ];
    $rows[] = [
      $this->t('Blocked requests'),
      $blocked_requests,
    ];
    $rows[] = [
      $this->t('Allowed requests'),
      $allowed_requests,
    ];
    $rows[] = [
      $this->t('Time since metrics started'),
      $time_since_string,
    ];

    // If you tracked last blocked request data, include it:
    if (!empty($last_blocked)) {
      $ip = $last_blocked['ip'] ?? $this->t('Unknown');
      $path = $last_blocked['path'] ?? $this->t('Unknown');
      $ua = $last_blocked['user_agent'] ?? $this->t('Unknown');
      $rows[] = [
        $this->t('Last blocked IP'),
        $ip,
      ];
      $rows[] = [
        $this->t('Last blocked path'),
        $path,
      ];
      $rows[] = [
        $this->t('Last blocked User Agent'),
        $ua,
      ];
    }

    // Build a render array with a table.
    $build = [
      '#type' => 'table',
      '#header' => [
        $this->t('Metric'),
        $this->t('Value'),
      ],
      '#rows' => $rows,
      // Optionally, you could add your own #attributes here for styling, etc.
    ];

    // 5) Return the render array.
    return $build;
  }

}

