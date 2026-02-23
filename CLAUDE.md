# WP Autopilot — Project Context

## What This Is
WordPress plugin that automates content production: fetches news from RSS feeds, writes articles with AI (OpenRouter), generates featured images (fal.ai), and publishes to WordPress.

## Tech Stack
- Pure PHP + WordPress API (no Composer)
- OOP with namespaces (`WPAutopilot\Includes`, `WPAutopilot\Admin`)
- `spl_autoload_register()` autoloader in main plugin file
- GitHub-based auto-updates via bundled `plugin-update-checker` v5.6

## Repo
- GitHub: `jansverre/wp-autopilot`
- v1.1.0 tagged and pushed, zip release asset uploaded manually to GitHub releases
- SSH auth works, `gh` CLI token has limited permissions

## File Structure
```
wp-autopilot.php          — Main plugin file, constants, autoloader, bootstrap, update checker
uninstall.php             — Clean removal of DB tables + options
includes/
  class-activator.php     — DB tables (wpa_seen_articles, wpa_internal_links, wpa_log, wpa_costs), default options, maybe_upgrade()
  class-deactivator.php   — Removes cron events
  class-settings.php      — Static Settings::get()/set()/all() with cache
  class-logger.php        — Log to wpa_log table, max 500 rows
  class-feed-fetcher.php  — RSS 2.0 + Atom, duplicate MD5 hash, 48h age filter, keyword filter
  class-article-writer.php — OpenRouter API, JSON response, clean_content(), analyze_style(), inline image prompts
  class-image-generator.php — fal.ai queue API, poll, upload to WP media, generate_inline()
  class-internal-links.php — Word overlap scoring, sync_existing_posts(), remove on delete
  class-publisher.php     — wp_insert_post, resolve_author(), insert_inline_images(), scheduled/future dates
  class-cron.php          — Custom intervals, work hours, staggered publishing, daily limits, cost tracking
  class-cost-tracker.php  — Static CostTracker: log text/image costs, summaries, per-article breakdown
admin/
  class-admin.php         — Menu, enqueue, settings save, 8 AJAX handlers (incl. analyze_style, save_writing_style)
  views/settings.php      — 9 sections: API, AI, writing style, content, publishing, images, inline images, cron, keywords
  views/feeds.php         — AJAX add/delete/toggle feeds
  views/status.php        — Status cards, cost cards, cost table, run now, reindex, log table
  partials/header.php     — Tab navigation
  partials/footer.php     — Closing divs
assets/css/admin.css      — Admin styles
assets/js/admin.js        — AJAX for feeds, run now, reindex, log refresh, author toggles, writing style AJAX
vendor/plugin-update-checker/ — GitHub release update checker (v5p6)
```

## Key Design Decisions
- Settings stored as individual `wpa_*` options (not serialized array)
- Feeds stored as JSON array in `wpa_feeds` option
- AI content cleaned with `clean_content()` to strip literal `\n` from JSON responses
- Articles start with intro paragraph, no H2 before first content
- Images: sends `image_size`, `width/height`, and `aspect_ratio` to cover all fal.ai model APIs
- AI generates `image_alt` and `image_caption` for full SEO
- Per-author writing styles stored as JSON in `wpa_writing_styles` option (author ID as key)
- Multi-author support: single/random/round_robin/percentage methods via `resolve_author()`
- Inline images use `[INLINE_IMAGE_N]` markers in AI content, replaced before publishing
- Cost tracking via `wpa_costs` table; DB upgrades handled by `maybe_upgrade()` on `plugins_loaded`
- Staggered publishing: first article now, rest spread evenly until next cron run
- Work hours: cron skips runs outside window, manual "Run Now" ignores work hours
- Post deletion/trash removes from internal links index, untrash re-adds
- Custom model override fields for both AI text and image models

## AI Models (dropdown defaults)
- Text: Gemini 3 Flash, Gemini 3.1 Pro, Claude Sonnet 4, GPT-4o Mini, GPT-5 Nano, GPT-5 Mini, Qwen 3.5 397B
- Image: FLUX 2 Pro, FLUX 2 Klein Realtime, Nano Banana Pro, Grok Imagine

## DB Tables
- `wpa_seen_articles` — hash, title, url, post_id, created_at
- `wpa_internal_links` — post_id, title, url, keywords, created_at
- `wpa_log` — level, message, context, created_at
- `wpa_costs` — post_id, type, model, tokens_in, tokens_out, cost_usd, created_at

## Current Status (v1.1.0)
- v1.1.0 released and tested on live WP site
- Features: site identity prompt, multiple authors, per-author writing style analysis, cost tracking, inline images
- DB version 2 (wpa_costs table, seamless upgrade via maybe_upgrade())
- Menu icon: dashicons-airplane
- Writing style analysis: plain text output (no markdown), long styles injected as separate block in prompt
- NOT yet submitted to WordPress.org plugin directory

## Known Issues / TODO
- Design/UI polish
- WordPress.org submission when ready (readme.txt already prepared with external service disclosure)
- Consider: admin UI in Norwegian vs English (currently mixed — admin views in Norwegian, readme in English)
- Version bump workflow: update Version in wp-autopilot.php + WPA_VERSION constant + Stable tag in readme.txt
- Inline images can cause long timeouts with many images per article (each takes 6-60s via fal.ai)
- Remember to rebuild zip after fixes: `cd /home/jansverre/Projects/wp-plugins && rm -f wp-autopilot-1.1.0.zip && zip -r wp-autopilot-1.1.0.zip WP-ai-autopilot/ -x "WP-ai-autopilot/.git/*" "WP-ai-autopilot/.claude/*" "WP-ai-autopilot/.gitignore"`

## User Preferences
- Language: Norwegian (both code comments and UI)
- Prefers practical solutions, not over-engineered
- Likes dropdown selects with custom override text fields
- Wants articles spread out, not published in bulk
- SSH for GitHub, no working `gh` CLI token
