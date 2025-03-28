<?php

namespace Drupal\facet_bot_blocker\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Component\Datetime\TimeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Defines a form that configures Facet Bot Blocker settings.
 */
class FacetBotBlockerSettingsForm extends FormBase {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackend;

  /**
   * Module handler for checking if memcache or redis is installed.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a new FacetBotBlockerSettingsForm object.
   */
  public function __construct(ConfigFactoryInterface $config_factory, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, TimeInterface $time) {
    $this->configFactory = $config_factory;
    $this->cacheBackend = $cache_backend;
    $this->moduleHandler = $module_handler;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('cache.default'),
      $container->get('module_handler'),
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'facet_bot_blocker.settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Load existing configuration.
    $config = $this->config('facet_bot_blocker.settings');

    $form['facets_bot_blocker_limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Facet parameter limit'),
      '#description' => $this->t('Block requests if a facet query parameter with this index is found, e.g., f[@limit]. Default is 1.'),
      '#default_value' => $config->get('facets_bot_blocker_limit') ?? 1,
      '#min' => 1,
      '#required' => TRUE,
    ];

    $form['facet_bot_blocker_return_gone'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Return 410 Gone instead of 403 Forbidden'),
      '#default_value' => $config->get('facet_bot_blocker_return_gone') ?? FALSE,
      '#description' => $this->t('If enabled, blocked facet requests will return HTTP 410 Gone instead of 403 Forbidden.'),
    ];

    $form['facet_bot_blocker_html'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Custom blocked message'),
      '#description' => $this->t('HTML message displayed when a request is blocked.'),
      '#default_value' => $config->get('facet_bot_blocker_html') ?? '<h1>Excessive crawling detected</h1><p>We have blocked your request.</p>',
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save configuration'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // 1) Save values to config.
    $this->configFactory->getEditable('facet_bot_blocker.settings')
      ->set('facets_bot_blocker_limit', $form_state->getValue('facets_bot_blocker_limit'))
      ->set('facet_bot_blocker_return_gone', $form_state->getValue('facet_bot_blocker_return_gone'))
      ->set('facet_bot_blocker_html', $form_state->getValue('facet_bot_blocker_html'))
      ->save();

    // 2) If memcache or redis is installed, store limit in cache to avoid DB reads.
    $use_cache = (
      $this->moduleHandler->moduleExists('memcache') ||
      $this->moduleHandler->moduleExists('redis')
    );
    if ($use_cache) {
      $limit = $form_state->getValue('facets_bot_blocker_limit');
      // Example: store in a known cache key.
      // If your event subscriber references `\Drupal::cache()->get('facet_bot_blocker.limit')`,
      // you'd do the same here:
      $this->cacheBackend->set('facet_bot_blocker.limit', $limit);
    }

    // 3) Show a status message.
    $this->messenger()->addStatus($this->t('Facet Bot Blocker configuration saved.'));

    // 4) Redirect to the same form.
    $form_state->setRedirect('<current>');
  }

}
