<?php

namespace Drupal\drupaleasy_repositories_gitlab\Plugin\DrupaleasyRepositories;

use Drupal\drupaleasy_repositories\DrupaleasyRepositories\DrupaleasyRepositoriesPluginBase;
use Drupal\drupaleasy_repositories_gitlab\GitLabHost;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\key\KeyRepositoryInterface;
use Gitlab\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the drupaleasy_repositories.
 *
 * @DrupaleasyRepositories(
 *   id = "gitlab",
 *   label = @Translation("GitLab"),
 *   description = @Translation("Repository hosted on gitlab.com.")
 * )
 */
class GitLab extends DrupaleasyRepositoriesPluginBase {

  /**
   * The configuration provider.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Array of GitLab hosted instances.
   *
   * @var array
   */
  protected array $gitlabHosts;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, string $plugin_id, mixed $plugin_definition, ConfigFactoryInterface $config_factory, MessengerInterface $messenger, KeyRepositoryInterface $key_repository) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $messenger, $key_repository);
    $this->configFactory = $config_factory;
    $this->gitlabHosts = [];
    foreach ($this->configFactory->get('drupaleasy_repositories_gitlab.instances')->get() as $instance) {
      $this->gitlabHosts[] = new GitLabHost($instance['key'], $instance['host'], $instance['url']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('messenger'),
      $container->get('key.repository'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($uri): bool {
    foreach ($this->gitlabHosts as $host) {
      $pattern = '|^' . $host->url . '/[a-zA-Z0-9_-]+/[a-zA-Z0-9_-]+|';

      if (preg_match($pattern, $uri) === 1) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function validateHelpText(): string {
    return implode('; ', array_map(fn($item) => $item->url . '/vendor/name', $this->gitlabHosts));
  }

  /**
   * {@inheritdoc}
   */
  public function getRepo(string $uri): array {
    // Parse the URI for the vendor and name.
    $all_parts = parse_url($uri);
    $parts = explode('/', $all_parts['path']);

    // Loop through all GitLab instances, and check which one the repo is using.
    foreach ($this->gitlabHosts as $host) {
      if ($host->host == $all_parts['host']) {
        // Set up authentication.
        $this->setAuthentication($host);

        // Get the repository metadata from the GitLab API.
        try {
          $repo = $this->client->projects()->show($parts[1] . '/' . $parts[2]);
        }
        catch (\Throwable $th) {
          $this->messenger->addMessage($this->t('GitLab error: @error', [
            '@error' => $th->getMessage(),
          ]));
          return [];
        }

        // Map repository data to our common format.
        return $this->mapToCommonFormat($repo['path_with_namespace'], $repo['name'], $repo['description'], $repo['open_issues_count'] ?: 0, $repo['web_url']);
      }
    }
    return [];
  }

  /**
   * Set up the authentication for the repository.
   */
  protected function setAuthentication(GitLabHost $host): void {
    $this->client = new Client();
    $this->client->setUrl($host->url);
    $gitlab_key = $this->keyRepository->getKey($host->key)->getKeyValues();
    $this->client->authenticate($gitlab_key['personal_access_token'], Client::AUTH_HTTP_TOKEN);
  }

}
