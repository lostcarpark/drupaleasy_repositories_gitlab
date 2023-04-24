<?php

namespace Drupal\drupaleasy_repositories_gitlab;

/**
 * A hosted instance of GitLab.
 */
class GitLabHost {

  /**
   * Construct the hosted GitLab instance.
   *
   * @param string $key
   *   The key identifier for the instance.
   * @param string $host
   *   The host name.
   * @param string $url
   *   The URL of the hosted GitLabInstance.
   */
  public function __construct(
    public readonly string $key,
    public readonly string $host,
    public readonly string $url
  ) {}

}
