=== Acta ===
Contributors: readwithacta
Tags: paywall, monetization, payments, subscriptions, content
Requires at least: 5.8
Tested up to: 6.9
Stable tag: 4.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Monetize your content with a seamless paywall. Readers pay per article using cards, Apple Pay, or Google Pay — in seconds.

== Description ==

Acta embeds a seamless checkout directly inside your content. Readers can unlock premium articles, lessons, files, and more using credit/debit cards, Apple Pay, or Google Pay — without leaving your site.

**How it works:**

1. Install the Acta plugin
2. Connect your site via the Acta setup wizard
3. Set a price per article using a simple snippet
4. Readers click to unlock — Acta handles the rest

**Features:**

* Per-article pricing — charge what each piece of content is worth
* No subscription required for readers — pay once, read once
* Apple Pay and Google Pay supported out of the box
* Automatic Stripe Connect integration — revenue paid directly to you
* Lightweight — single JS snippet, no page speed impact

== Installation ==

1. Search for "Acta" in WordPress Admin → Plugins → Add New, or upload the ZIP via Upload Plugin
2. Activate the plugin
3. Follow the on-screen setup to connect your Acta account
4. Complete Stripe onboarding to receive payments

== Frequently Asked Questions ==

= Do readers need an account? =
No. Readers pay with their card, Apple Pay, or Google Pay without creating an account.

= What is Acta's pricing model? =
Acta uses a revenue-share model: we make money only when publishers do. Stripe's processing fee included.

= Which currencies are supported? =
All major currencies supported by Stripe, including USD, GBP, EUR, CAD, AUD, and more.

= Does this work with my theme? =
Yes. Acta auto-detects your existing paywall styling and matches it.

== External Services ==

This plugin connects to the following third-party services:

**Acta (https://readwithacta.com)**

* When a publisher completes setup, the plugin sends their site URL, email, name, plugin endpoint, and a locally generated secret key to the Acta API at https://api.readwithacta.com. No personal data beyond what the publisher explicitly enters is transmitted.
* Once connected, the plugin loads a JavaScript file from https://api.readwithacta.com on all public-facing pages. This file renders the checkout UI for readers.
* Reader payment transactions are processed by Acta via Stripe.
* [Acta Terms of Service](https://readwithacta.com/terms)
* [Acta Privacy Policy](https://readwithacta.com/privacy)

**Stripe (https://stripe.com)**

* The plugin loads Stripe.js (https://js.stripe.com/v3/) on public-facing pages to handle secure payment processing.
* [Stripe Terms of Service](https://stripe.com/legal)
* [Stripe Privacy Policy](https://stripe.com/privacy)

== Screenshots ==

1. Acta prompt on an article
2. Acta payment experience
3. Acta plugin settings page in WordPress admin

== Changelog ==

= 4.0.0 =
* WordPress.org compliance fixes: sanitization, escaping, script enqueuing, i18n
* Fixed undefined ACTA_MENU_ICON constant (PHP 8.0+ fatal)

= 3.0.0 =
* Scripts now enqueued via wp_enqueue_script() per WordPress standards
* Added wp_unslash() to all POST data sanitization
* Added i18n wrappers to admin notice strings

= 2.0.2 =
* Maintenance release

= 2.0.1 =
* Maintenance release

= 2.0.0 =
* Self-service onboarding — no manual API setup required
* Auto-detection of existing paywall styles
* Custom per-article pricing via data attribute
* Jetpack paywall support

= 1.1.0 =
* Initial public release
