=== AFB Update Watch ===
Contributors: aaronfozzy
Tags: security, updates, woocommerce, plugin updates, auto-updates
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Know when a plugin changes owner or is closed on WordPress.org, and hold its updates until you have checked it.

== Description ==

Plugins change hands. Most sales are harmless, but some buyers have pushed a malicious update to every site running the plugin they bought. WordPress.org does not tell you when a plugin you rely on gets a new owner, so the first you hear of it is often the hack.

AFB Update Watch checks every plugin you have installed from WordPress.org once a day and:

* **Spots a change of owner.** It records each plugin's author and committers on WordPress.org the first time it sees it, then tells you if they change.
* **Spots a closure.** If a plugin is closed on WordPress.org, for a security issue or any other reason, you hear about it straight away rather than finding out it stopped getting updates.
* **Holds updates until you have checked.** A flagged plugin's update is held back, from automatic updates, the Update button and bulk updates alike, until you press release.
* **Guards WooCommerce updates.** WooCommerce no longer holds itself back when your extensions are not marked as tested with a new version. With the guard on, a new major or minor WooCommerce release is not auto-updated until every active extension says it is tested with it, and you can see exactly which extensions are holding it up. Patch releases always go through.
* **Emails you.** Alerts go to the admin email, or an address you choose.

Everything runs on your own site. The only outside call is to the public WordPress.org plugin API.

Built by [AFB Digital Solutions](https://afbdigitalsolutions.co.uk/), a UK WordPress and WooCommerce developer.

== Installation ==

1. Install and activate the plugin. On multisite, network activate it.
2. Go to Tools, AFB Update Watch (Network Admin, Plugins, AFB Update Watch on multisite) and press Check now to take the first baseline. Otherwise it runs on its own within a minute.
3. Optionally set the alert email address.

== Frequently Asked Questions ==

= Does an owner change mean the plugin is malicious? =

No. Most ownership changes are perfectly ordinary. It means the people who can push updates to your site have changed, so it is worth reading the changelog and the support forum before you update.

= Which plugins are checked? =

Plugins installed from WordPress.org. Premium plugins and custom code are not on WordPress.org, so there is nothing to compare against.

= Will it stop security updates? =

Only for a plugin you have been warned about, and only until you release the hold. Release it as soon as you are satisfied.

= What does it send to other servers? =

It asks the public WordPress.org plugin API about each installed plugin's slug. Nothing about your site, users or content is sent.

== Changelog ==

= 0.1.0 =
* First release: ownership and closure watch, update holds, WooCommerce untested-extension guard, email alerts.
