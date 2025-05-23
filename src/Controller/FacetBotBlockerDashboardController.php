<?php

namespace Drupal\facet_bot_blocker\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The Facet Bot Blocker Dashboard controller.
 *
 * Assembles a report page showing metrics of the Facet bot blocker module.
 */
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
  public function __construct(CacheBackendInterface $cacheBackend, TimeInterface $time, ConfigFactoryInterface $configFactory) {
    $this->cacheBackend = $cacheBackend;
    $this->time = $time;
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('cache.default'),
      $container->get('datetime.time'),
      $container->get('config.factory')
    );
  }

  /**
   * Build a dashboard page showing facet blocking stats.
   */
  public function dashboard(): array {
    // Get the configured facet limit.
    $immutableConfig = $this->configFactory->get('facet_bot_blocker.settings');

    $limit = $immutableConfig->get('facets_bot_blocker_limit');

    // Retrieve counters and data from cache.
    $blocked_cache = $this->cacheBackend->get('facet_bot_blocker.blocked_requests');
    $allowed_cache = $this->cacheBackend->get('facet_bot_blocker.allowed_requests');
    $last_blocked_cache = $this->cacheBackend->get('facet_bot_blocker.last_blocked_request');
    $start_time_cache = $this->cacheBackend->get('facet_bot_blocker.metrics_start_time');

    // Extract data or use defaults if cache entries are missing.
    $blocked_requests = $blocked_cache ? $blocked_cache->data : 0;
    $allowed_requests = $allowed_cache ? $allowed_cache->data : 0;
    $last_blocked = $last_blocked_cache ? $last_blocked_cache->data : [];
    $metrics_start_time = $start_time_cache ? $start_time_cache->data : $this->time->getRequestTime();

    // Compute "time since metrics started".
    $time_since_start = $this->time->getRequestTime() - $metrics_start_time;
    $time_since_string = round($time_since_start / 3600, 2) . ' hours';

    if ($blocked_requests === 0) {
      $percent = '0%';
    }
    elseif ($allowed_requests == 0) {
      $percent = '100%';
    }
    else {
      $percent = (float) $blocked_requests / ((float) $blocked_requests + (float) $allowed_requests);
      $percent = sprintf("%.2f%%", $percent * 100);
    }

    // Prepare a small table. You can theme this however you like.
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
      $this->t('Percent blocked'),
      $percent,
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

    // Return the render array.
    return $build;
  }

}
