# Facet Bot Blocker

The **Facet Bot Blocker** module blocks requests that exceed a defined facet parameter limit. Specifically, it detects and blocks crawlers/bots that keep requesting deeper levels of facet parameters (e.g., `f[3]`, `f[4]`, etc.), which can lead to performance and SEO issues if left unchecked.

## Requirements

- This module does not strictly require any other contributed modules.
- **Optional**: Installing either the [Memcache](https://www.drupal.org/project/memcache) or [Redis](https://www.drupal.org/project/redis) module allows storing tracking counters and config in memory (instead of the database), improving performance in high-traffic environments.

## Installation

Install this module using the official Backdrop CMS instructions at <https://backdropcms.org/guide/modules>.

## Configuration

1. **Enable the module**: Enable the **Facet Bot Blocker** module from the **Extend** page (`/admin/modules`) or using Bee (`bee en facet_bot_blocker`).
2. **Configure the module**:
    - Go to the module’s settings form (e.g., `/admin/config/system/facet-bot-blocker`).
    - Set the facet parameter limit, decide whether to return `410 Gone` or `403 Forbidden`, and optionally customize the blocking message.
3. **(Optional) Check the dashboard**:
    - A dashboard page (e.g., `/admin/reports/facet-bot-blocker`) displays counts of blocked and allowed requests, the last blocked IP, and other metrics. This data is stored in cache if memcache/redis is installed.

## Background

Website crawlers have been around for decades, but starting around 2024/2025, with the increasing presences of AI/LM tools which are being trained, we have seen a significant rise in traffic that do not:

- Follow robots.txt directives
- Respect rel=”nofollow” links
- Identify themselves via their user agent strings, and instead use what appears to be a standard OS and browser combination.
- Additionally, these come from many different IP addresses instead of a single address.

Now it is an assumption that traffic like this are from crawlers for the purpose of building their own content repositories to train AIs, and not necessarily malicious (perhaps just incompetently designed). But these become a huge problem for websites during a few circumstances.

“Crawler trap” - this is a situation where a web crawler has access to a large number of links that have many different variations. This type of setup often appears in examples of search pages using “Facets”. With sufficient complexity, crawlers can find a near endless supply of unique URLs.

This module allows site administrators to configure a maximum number of facets that can be used before the user receives a blocked response.

Note on WAFs: It is recommended that if your site has access to some CDN tool with a "Web Application Firewall" feature, that you use that to implement such a block. As these requests hitting your site, even if blocked, will still contribute to your host's rate limit. This module was created to block such requests when a WAF is not available.

## Maintainers

- [Herb v/d Dool](https://github.com/herbdool)

## License

This project is GPL v2 software. See the LICENSE.txt file in this directory for
complete text.

## Credits

Ported from Drupal by [Herb v/d Dool](https://github.com/herbdool).

Drupal maintainers: [John Brandenburg (bburg)](https://www.drupal.org/u/bburg)
