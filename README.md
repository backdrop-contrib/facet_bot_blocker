## INTRODUCTION

The **Facet Bot Blocker** module blocks requests that exceed a defined facet parameter limit. Specifically, it detects and blocks crawlers/bots that keep requesting deeper levels of facet parameters (e.g., `f[3]`, `f[4]`, etc.), which can lead to performance and SEO issues if left unchecked.

## REQUIREMENTS

- This module does not strictly require any other contributed modules.
- **Optional**: Installing either the [Memcache](https://www.drupal.org/project/memcache) or [Redis](https://www.drupal.org/project/redis) module allows storing tracking counters and config in memory (instead of the database), improving performance in high-traffic environments.

## INSTALLATION

Install as you would normally install a contributed Drupal module.
See: [Installing contributed modules](https://www.drupal.org/docs/8/extending-drupal-8/installing-contributed-modules) for further information.

## CONFIGURATION

1. **Enable the module**: Enable the **Facet Bot Blocker** module from the **Extend** page (`/admin/modules`) or using Drush (`drush en facet_bot_blocker`).
2. **Configure the module**:
  - Go to the module’s settings form (e.g., `/admin/config/system/facet-bot-blocker`).
  - Set the facet parameter limit, decide whether to return `410 Gone` or `403 Forbidden`, and optionally customize the blocking message.
3. **(Optional) Check the dashboard**:
  - A dashboard page (e.g., `/admin/reports/facet-bot-blocker`) displays counts of blocked and allowed requests, the last blocked IP, and other metrics. This data is stored in cache if memcache/redis is installed.

## MAINTAINERS

Current maintainers for Drupal 10:

- **John Brandenburg (bburg) @ [Forum One](https://www.forumone.com/)** - <https://www.drupal.org/u/bburg>

