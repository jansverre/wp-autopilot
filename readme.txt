=== WP Autopilot ===
Contributors: jansverre
Tags: ai content, rss, autopilot, content generation, automation
Requires at least: 5.8
Tested up to: 6.9
Stable tag: 2.0.0
Requires PHP: 7.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered content automation — fetches news from RSS feeds, writes unique articles, generates featured images, and publishes to WordPress.

== Description ==

WP Autopilot automates your content pipeline. It fetches news from RSS feeds you configure, uses AI to write unique articles based on those news items, generates landscape featured images, and publishes them to your WordPress site — either as drafts for review or directly.

**Free Features:**

* Up to 3 RSS feeds (RSS 2.0 and Atom)
* AI-powered article writing via OpenRouter (supports 7+ models including Gemini, Claude, GPT, and Qwen)
* AI-generated landscape featured images via fal.ai (FLUX, Grok Imagine, and more)
* Full image SEO: AI-generated alt text, captions, and descriptions
* Smart internal linking between articles using keyword overlap scoring
* Scheduled publishing — articles are spread evenly across time instead of being published in bulk
* Configurable work hours window to control when articles go live
* WP-Cron based automation with customizable intervals
* Keyword include/exclude filtering for RSS items
* Daily and per-run article limits
* Complete admin panel with three pages: Settings, Feeds, and Status
* Full cost tracking — monitor token usage and estimated costs per article
* Activity log with the last 500 entries
* Manual "Run Now" button for on-demand content generation
* Custom model support — use any model ID available on OpenRouter or fal.ai

**Pro Features (license key required):**

* Unlimited RSS feeds
* Multiple authors with random, round-robin, and weighted distribution
* Per-author writing style analysis — AI analyzes published posts and replicates each author's voice
* Inline AI-generated images within article content at H2 sections
* Facebook auto-sharing with AI-generated text and AI-generated posters

[Get WP Autopilot Pro](https://baugeit.no/wp-autopilot/)

**How It Works:**

1. You add RSS feeds and configure your preferred AI models and publishing settings
2. On each scheduled run (or manual trigger), the plugin fetches new items from your feeds
3. For each news item, it finds related existing articles for internal linking
4. The AI writes a unique, SEO-friendly article with proper HTML structure
5. A featured image is generated with AI-written alt text and caption
6. The article is published (or saved as draft) with the image and internal links
7. Articles are scheduled to appear spread out over time, not all at once

== Installation ==

1. Upload the `WP-ai-autopilot` folder to `/wp-content/plugins/`, or install the ZIP via Plugins > Add New > Upload Plugin
2. Activate the plugin through the Plugins menu in WordPress
3. Go to **WP Autopilot > Settings** and enter your API keys
4. Add RSS feeds under **WP Autopilot > Feeds**
5. Configure your preferred AI model, language, writing style, and publishing options
6. Enable automatic scheduling or use the **Run Now** button under **WP Autopilot > Status**
7. (Optional) Enter a Pro license key under **WP Autopilot > Settings** to unlock Pro features

== Frequently Asked Questions ==

= What API keys do I need? =

You need an API key from [OpenRouter](https://openrouter.ai/keys) for article writing. Optionally, you need a key from [fal.ai](https://fal.ai/dashboard/keys) for image generation. Both services offer free tiers.

= What is the difference between Free and Pro? =

The free version is a fully functional autopilot: RSS feeds, AI articles, AI images, internal linking, scheduling, and cost tracking. Pro adds unlimited feeds, multi-author support, per-author writing styles, inline images, and Facebook sharing. See the full comparison at [baugeit.no/wp-autopilot](https://baugeit.no/wp-autopilot/).

= Which AI models are supported? =

The plugin ships with presets for Gemini 3 Flash, Gemini 3.1 Pro, Claude Sonnet 4, GPT-4o Mini, GPT-5 Nano, GPT-5 Mini, and Qwen 3.5 397B. You can also enter any custom model ID available on OpenRouter.

= Which image models are supported? =

Presets include FLUX 2 Pro, FLUX 2 Klein Realtime, Nano Banana Pro, and Grok Imagine. You can also enter any custom fal.ai model ID.

= Are articles published automatically? =

You choose: publish immediately, save as draft for review, or set to pending. When set to publish, articles are scheduled with staggered times so they don't all appear at once.

= What happens when I delete a post? =

The internal links index is automatically updated. When you trash or delete a post, it is removed from the index so new articles won't link to it. If you restore a trashed post, it gets re-indexed.

= Can I control when articles are published? =

Yes. You can set a work hours window (e.g. 08:00-22:00) so the autopilot only runs during those hours. Articles are also spread evenly between runs instead of being published in bulk.

= What language are articles written in? =

You configure the language in settings. The default is Norwegian (norsk), but you can set any language — English, Swedish, German, Spanish, etc.

= Does this plugin work with any theme? =

Yes. WP Autopilot creates standard WordPress posts with proper HTML, featured images, categories, and excerpts. It works with any theme that supports standard post features.

== Screenshots ==

1. Settings page — configure API keys, AI models, publishing options, and work hours
2. Feeds page — add, activate, and manage RSS feeds
3. Status page — view statistics, run autopilot manually, and browse the activity log

== Changelog ==

= 2.0.0 =
* New: Free/Pro model — free version fully functional, Pro unlocks advanced features with a license key
* New: License activation system via Lemon Squeezy
* New: Free limit of 3 RSS feeds (unlimited with Pro)
* New: Multi-author, writing style analysis, inline images, and Facebook sharing now require Pro
* New: PRO badges and upgrade notices in settings for discoverability
* New: 30-day grace period for existing users upgrading from v1.x

= 1.2.6 =
* Facebook poster cost control

= 1.2.5 =
* Full i18n support — English defaults, Norwegian (nb_NO) translations

= 1.2.0 =
* New: Facebook auto-sharing with AI-generated text and optional AI poster

= 1.1.0 =
* New: Site identity prompt — describe your site's purpose, values, and audience for better article tone
* New: Multiple authors with single, random, round-robin, and percentage distribution methods
* New: Per-author writing style analysis — AI analyzes published posts and replicates each author's voice
* New: Cost tracking — monitor token usage and estimated costs per article on the Status page
* New: Inline images — AI-generated images within article content at H2 sections
* New: Seamless DB upgrade mechanism (no deactivation/reactivation needed for schema changes)
* Improved: AI prompt now supports inline image markers and per-author style injection

= 1.0.0 =
* Initial release
* RSS feed fetching with duplicate detection and keyword filtering
* AI article generation via OpenRouter with configurable models
* AI image generation via fal.ai with landscape format and full SEO metadata
* Smart internal linking using keyword overlap scoring
* Scheduled publishing with staggered post times
* Work hours window for controlled publishing
* Complete admin panel with Settings, Feeds, and Status pages
* Activity log with auto-pruning (max 500 rows)
* Automatic index cleanup on post deletion

== Upgrade Notice ==

= 2.0.0 =
Introduces Free/Pro model. Existing users get a 30-day grace period with all features unlocked. After that, advanced features (unlimited feeds, multi-author, inline images, Facebook sharing) require a Pro license key.

= 1.0.0 =
Initial release.

== External Services ==

This plugin connects to the following third-party services to provide its functionality:

= OpenRouter =

Article content is generated by sending prompts to the [OpenRouter](https://openrouter.ai/) API. The data sent includes the news headline, description, and your configured writing instructions. No personal user data is transmitted.

* Service URL: https://openrouter.ai/api/v1/chat/completions
* Terms of Service: https://openrouter.ai/terms
* Privacy Policy: https://openrouter.ai/privacy

= fal.ai =

Featured images are generated by sending image prompts to the [fal.ai](https://fal.ai/) API. Only the AI-generated image description is sent. No personal user data is transmitted.

* Service URL: https://queue.fal.run/
* Terms of Service: https://fal.ai/terms
* Privacy Policy: https://fal.ai/privacy

= Facebook Graph API (Pro feature) =

When Facebook sharing is enabled (Pro), the plugin sends article links, AI-generated text, and optionally AI-generated poster images to the [Facebook Graph API](https://developers.facebook.com/docs/graph-api/) to publish posts on your configured Facebook Page. Only article content and images are transmitted — no personal user data beyond your Page Access Token.

* Service URL: https://graph.facebook.com/
* Terms of Service: https://www.facebook.com/terms.php
* Privacy Policy: https://www.facebook.com/privacy/policy/

= Lemon Squeezy =

Pro license keys are validated against the [Lemon Squeezy](https://lemonsqueezy.com/) API. The data sent is the license key and your site's domain name. No other data is transmitted.

* Service URL: https://api.lemonsqueezy.com/v1/licenses/
* Terms of Service: https://www.lemonsqueezy.com/terms
* Privacy Policy: https://www.lemonsqueezy.com/privacy
