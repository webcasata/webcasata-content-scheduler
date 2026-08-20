=== Webcasata Content Scheduler ===
Contributors: webcasata
Tags: schedule content, content automation, scheduled publishing, rollback, action scheduler
Requires at least: 6.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Schedule changes to your WordPress content — not just new posts — and automatically roll them back when the schedule ends.

== Description ==

Most scheduling plugins only publish new posts at a future date. Webcasata Content Scheduler is different: it schedules **changes to content that already exists**, and can automatically restore the original value when the schedule ends.

**Example**

Your homepage banner says "Summer Sale." You want it to say "Independence Day Sale" from August 20th to August 31st, then automatically go back to "Summer Sale" afterwards — without anyone having to remember to change it back.

= Core Concept =

Every schedule is built from four parts:

1. **When** — a start date/time, an end date/time, or both.
2. **What** — the action to perform (Phase 1 supports changing post/page/CPT status; more action types are on the roadmap).
3. **Where** — the post, page, or custom post type entry to target.
4. **Rollback** — what happens when the schedule ends: restore the original value automatically, or keep the new value.

= Built on Action Scheduler =

Webcasata Content Scheduler is built on [Action Scheduler](https://actionscheduler.org/), the same reliable, queue-based scheduling library used by WooCommerce. It's bundled with the plugin and is safe to run alongside other plugins that also bundle it — only the newest version active on your site actually runs.

= Scheduled Content Block =

Add a "Scheduled Content" block anywhere in the block editor, put any content inside it (text, images, buttons, columns, shortcodes — anything), and set a start and/or end date/time in the sidebar. The block is evaluated fresh on every server-rendered page view, so no cron job or page reload is needed for it to appear or disappear on schedule.

If your site uses full-page caching (a common setup with plugins like WP Rocket or W3 Total Cache, or a CDN), a cached page can briefly show a stale state until the cache refreshes. Choose "Keep in the page but visually hidden" in the block's sidebar and the plugin corrects this automatically in the visitor's browser, using their own clock. Choosing "Hide completely" removes the content from the page's HTML entirely when inactive, which is cleaner for SEO but means a cached page can't self-correct — best combined with a cache plugin that purges on a schedule, or with page-level caching disabled for that page.

= Roadmap =

This is an early release focused on getting the scheduling engine and the Scheduled Content Block right. Planned for future versions:

* Featured image scheduling
* Category/taxonomy scheduling
* Custom fields and ACF field scheduling
* WooCommerce price, sale, and visibility scheduling
* Recurring schedules and conditional rules
* Email notifications

== Installation ==

1. Upload the `webcasata-content-scheduler` folder to `/wp-content/plugins/`, or install directly through the Plugins screen in WordPress.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to **Webcasata Scheduler → Add New** in your admin menu to create your first schedule.

== Frequently Asked Questions ==

= Does this replace WordPress's built-in "Publish immediately" scheduling? =

No. Standard WordPress scheduling publishes a new post at a future date. This plugin changes something about a post/page that already exists — for now, its status; more change types are coming in future versions.

= What happens if I deactivate the plugin? =

Your schedules and their history are kept. Deactivating only cancels pending scheduled actions so they don't run while the plugin is inactive; reactivating resumes normally. Uninstalling (deleting) the plugin removes the plugin's own data.

= Does this plugin phone home or track usage? =

No. The plugin does not collect or transmit any data.

== Screenshots ==

1. The All Schedules dashboard, with status filters and pause/resume/delete actions.
2. The Add New Schedule form.

== Changelog ==

= 0.2.0 =
* Added the Scheduled Content Block: show/hide any block content between a start and end date/time, with a client-side correction script for sites using full-page caching.

= 0.1.0 =
* Initial architecture release: scheduling engine on Action Scheduler, Post Status action, admin dashboard and Add New Schedule form.

== Upgrade Notice ==

= 0.2.0 =
Adds the Scheduled Content Block.

= 0.1.0 =
Initial release.
