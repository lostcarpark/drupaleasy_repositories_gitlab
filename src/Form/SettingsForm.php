<?php

namespace Drupal\drupaleasy_repositories_gitlab\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\drupaleasy_repositories_gitlab\GitLabHost;
use Drupal\key\KeyRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Drupaleasy Repositories GitLab settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The Key repository service.
   *
   * @var \Drupal\key\KeyRepositoryInterface
   */
  protected KeyRepositoryInterface $keyRepository;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'drupaleasy_repositories_gitlab_settings';
  }

  /**
   * Constructs an SettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\key\KeyRepositoryInterface $key_repository
   *   The key manager service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, KeyRepositoryInterface $key_repository) {
    parent::__construct($config_factory);
    $this->keyRepository = $key_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    /** @var \Drupal\Core\Config\ConfigFactoryInterface $config_factory */
    $config_factory = $container->get('config.factory');
    /** @var \Drupal\key\KeyRepositoryInterface $key_repository */
    $key_repository = $container->get('key.repository');
    return new static($config_factory, $key_repository);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['drupaleasy_repositories_gitlab.instances'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['instances'] = [
      '#type' => 'table',
      '#caption' => $this->t('GitLab Instances'),
      '#header' => [
        'delete' => $this->t('Delete'),
        'key' => $this->t('Key'),
        'host' => $this->t('Host'),
        'url' => $this->t('URL'),
      ],
    ];
    foreach ($this->config('drupaleasy_repositories_gitlab.instances')->get() as $instance) {
      $form['instances'][$instance['key']] = [
        '#attributes' => [
          'class' => ['instance-row', 'key-' . $instance['key']],
        ],
        'delete' => [
          '#type' => 'checkbox',
          '#title' => $this->t('Delete'),
          '#title_display' => 'invisible',
        ],
        'key' => [
          '#markup' => $instance['key'],
        ],
        'host' => [
          '#type' => 'textfield',
          '#title' => $this->t('Host'),
          '#default_value' => $instance['host'],
          '#title_display' => 'invisible',
        ],
        'url' => [
          '#type' => 'textfield',
          '#title' => $this->t('URL'),
          '#default_value' => $instance['url'],
          '#title_display' => 'invisible',
        ],
      ];
    }
    $form['instances']['key-new'] = [
      '#attributes' => [
        'class' => ['instance-row', 'new'],
      ],
      'delete' => [
        '#markup' => '',
      ],
      'key' => [
        '#type' => 'textfield',
        '#title' => $this->t('Key'),
        '#title_display' => 'invisible',
      ],
      'host' => [
        '#type' => 'textfield',
        '#title' => $this->t('Host'),
        '#title_display' => 'invisible',
      ],
      'url' => [
        '#type' => 'textfield',
        '#title' => $this->t('URL'),
        '#title_display' => 'invisible',
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    foreach ($form_state->getValue('instances') as $key => $row) {
      if ($key == 'key-new') {
        if ($row['key'] || $row['host'] || $row['url']) {
          if (empty(trim($row['key']))) {
            $form_state->setErrorByName('instances][key-new][key', $this->t('The key must be set.'));
          }
          if (empty(trim($row['host']))) {
            $form_state->setErrorByName('instances][key-new][host', $this->t('The host must be set.'));
          }
          if (empty(trim($row['url']))) {
            $form_state->setErrorByName('instances][key-new][url', $this->t('The URL must be set.'));
          }
          $pattern = '|^[a-z0-9_\-]+$|';
          if (preg_match($pattern, $row['key']) !== 1) {
            $form_state->setErrorByName('instances][key-new][key',
              $this->t('The key can only contain lower case letters, numbers, hyphons and underscores'));
          }
          if ($row['key']) {
            if ($this->keyRepository->getKey($row['key']) === NULL) {
              $form_state->setErrorByName('instances][key-new][key', $this->t('The key must have an entry in Keys settings.'));
            }
          }
        }
      }
      if ($key != 'key-new' || $row['key']) {
        $pattern = '|^([a-zA-Z0-9\-]+\.)+[a-zA-Z0-9\-]+$|';
        if (preg_match($pattern, $row['host']) !== 1) {
          $form_state->setErrorByName('instances][' . $key . '][host',
            $this->t('The host must be a domain name (e.g. git.com).'));
        }
        $pattern = '|^https?://([a-zA-Z0-9\-]+\.)+[a-zA-Z0-9\-]+$|';
        if (preg_match($pattern, $row['url']) !== 1) {
          $form_state->setErrorByName('instances][' . $key . '][url',
            $this->t('The URL must be a valid URL (e.g. https://git.com).'));
        }
      }
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $update = $this->config('drupaleasy_repositories_gitlab.instances');
    foreach ($form_state->getValue('instances') as $key => $row) {
      if ($key != 'key-new' || $row['key']) {
        if ($row['delete']) {
          $update->clear($key);
        }
        else {
          $update->set($row['key'] ?? $key, (array) new GitLabHost($row['key'] ?? $key, $row['host'], $row['url']));
        }
      }
    }
    // ->set('example', )
    $update->save();
    parent::submitForm($form, $form_state);
  }

}
