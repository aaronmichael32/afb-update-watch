# AFB Update Watch

Know when a WordPress plugin changes owner or is closed on WordPress.org, and hold its updates until you have checked it.

[Install from WordPress.org](https://wordpress.org/plugins/afb-update-watch/)

Plugins change hands. Most sales are harmless, but some buyers have pushed a malicious update to every site running the plugin they bought. WordPress.org does not tell you when a plugin you rely on gets a new owner, so the first you hear of it is often the hack.

AFB Update Watch checks every plugin you have installed from WordPress.org once a day and:

* **Spots a change of owner.** It records each plugin's author and committers the first time it sees it, then tells you if they change.
* **Spots a closure.** If a plugin is closed on WordPress.org, you hear about it straight away.
* **Holds updates until you have checked.** A flagged plugin's update is held back from automatic updates, the Update button and bulk updates until you press release.
* **Guards WooCommerce updates.** A new major or minor WooCommerce release is not auto updated until every active extension says it is tested with it.
* **Emails you.** Alerts go to the admin email, or an address you choose.

Everything runs on your own site. The only outside call is to the public WordPress.org plugin API.

## Development

```
composer install
vendor/bin/phpcs
vendor/bin/phpunit
```

`.distignore` lists what stays out of the WordPress.org build.

## Licence

GPLv2 or later. Built by [AFB Digital Solutions](https://afbdigitalsolutions.co.uk/).
