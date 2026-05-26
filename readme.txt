=== Acta — Pay Per Article ===
Contributors: readwithacta
Tags: paywall, pay per article, monetization, micropayments, paid content
Requires at least: 5.8
Tested up to: 7.0
Stable tag: 4.1.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A pay-per-post solution for WordPress publishers. Give casual visitors a simple way to pay for content, no subscription required.

== Description ==

Monetize casual traffic without sacrificing subscriptions. Acta unlocks an additional revenue channel by embedding a seamless checkout directly inside your posts. Sell individual articles, or digital products such as playbooks, e-books, PDFs, video, podcasts, and more.

Learn more at [readwithacta.com](https://readwithacta.com/).

**Pay-per-post on your terms**

* You control the price - charge what each post is worth
* One-click payments via cards, Apple Pay, and Google Pay - fully embedded and optimized for conversion
* Revenue goes directly to your Stripe account

**We win if you win**

* No setup fees or recurring costs - simple revenue-share model
* Your customer, your data - get what you need to nurture the relationship
* Localized currencies - sell content the way your audience expects

**Easy setup**

* Install the plugin, set your price, connect your bank - you're live
* Works with any WordPress theme, including Jetpack-powered paywalls

**What customers are saying**

* "Setting up and using Acta has been so easy, it took my reader's experience to the next level." - Sports Psychology Today
* "People are purchasing specific topics of interest, which is exactly what we hoped." - Rascal News
* "Acta's been working flawlessly with us. I can only recommend it." - The G/O

== Installation ==

1. Search for "Acta" in WordPress Admin → Plugins → Add New, or upload the ZIP via Upload Plugin
2. Activate the plugin
3. Follow the on-screen setup to connect your Acta account
4. Complete Stripe onboarding to receive payments

== Frequently Asked Questions ==

= What is Acta? =
Acta is a pay-per-content platform that lets publishers sell individual posts with one-click payments.

= How does pay-per-post work? =
You set a price on any post. When a visitor hits a paywalled post, they can pay instantly with card, Apple Pay, or Google Pay and get immediate access.

= How much does Acta cost? =
There are no setup fees, monthly fees, or recurring costs. Acta uses a revenue-share model, so you only pay when you earn.

= Does Acta replace subscriptions? =
No. Acta is designed to complement subscriptions by monetizing casual visitors who would never subscribe.

= Do readers need to create an account? =
No. Readers pay and get instant access. No account, login, or sign-up required for their first purchase.

= What payment methods are supported? =
Credit and debit cards, Apple Pay, Google Pay, and localized currencies. All payments are processed securely through Stripe.

= Does this work with my theme? =
Yes. Acta auto-detects your existing paywall styling and matches it.

== External Services ==

= Privacy =

This plugin connects to external services to provide its functionality:

* During setup, the plugin sends your site URL, email, name, and a locally generated secret key to the Acta API at https://api.readwithacta.com. No data beyond what you explicitly enter is transmitted.
* On public-facing pages, the plugin loads a JavaScript file from https://api.readwithacta.com to render the checkout UI, and Stripe.js (https://js.stripe.com/v3/) for secure payment processing.
* [Acta Terms of Service](https://readwithacta.com/terms) | [Acta Privacy Policy](https://readwithacta.com/privacy)
* [Stripe Terms of Service](https://stripe.com/legal) | [Stripe Privacy Policy](https://stripe.com/privacy)

== Screenshots ==

1. Acta prompt on an article
2. Acta payment experience
3. Acta plugin settings page in WordPress admin

== Changelog ==

= 4.1.0 =
* New "Paywall mode" setting: add pay-per-article alongside your subscription paywall, or choose pay-per-article only — which replaces the subscribe prompt with just the Acta buy button (for publishers without subscriptions)
* Clearer setup guidance in the plugin settings, including which paywalls Acta works with
* Tested up to WordPress 7.0

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
* Self-service onboarding - no manual API setup required
* Auto-detection of existing paywall styles
* Custom per-article pricing via data attribute
* Jetpack paywall support

= 1.1.0 =
* Initial public release
