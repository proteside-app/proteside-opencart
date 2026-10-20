# Changelog

All notable changes to the Proteside checkout protection extension for OpenCart.

## 1.0.0 — 2026-10-16

First release.

- Two packages from one code base: `proteside-opencart-3.ocmod.zip` (OpenCart 3.0.x) and `proteside.ocmod.zip`
  (OpenCart 4.0.2.0 and later, including 4.1).
- Inserts the inline Proteside bootstrapper and the async `shield.js` right after the opening `<head>` tag (after a
  leading `<meta charset>`), through an event on `catalog/view/common/header/after` that runs after every other
  header event, so they are the first scripts of the page.
- Bundled bootstrapper 1.1.3 (from `proteside-sdk` 1.1.3); inline configuration (`window.__PROTE_INLINE__`) with the
  active rules and allowed GTM containers, inside the same `<script>`.
- Pages detected by OpenCart route: checkout (all steps, one-page checkout extensions, payment extension pages) and
  order confirmation on by default; cart, shop and product pages, my account, login page, entire store and custom URL
  patterns optional.
- Entitlement through `/api/sdk/status` with a 48-hour tolerance for transient failures; remote configuration
  through `/api/sdk/config`; release channel follows the dashboard or can be forced.
- Revalidation without a cron: after the storefront response has been sent (PHP-FPM / LiteSpeed close the connection
  first). Optional token-protected cron URL; on OpenCart 4 also the native hourly cron job `proteside`.
- Per-store settings and status on multistore installations.
- Admin page in English, Brazilian Portuguese and Spanish: status card, notices (grace period, invalid key, suspended,
  connection error, domain mismatch), diagnostics with the CSP hash of the inline script, cron URL and a
  "Test the installation" button that checks the snippet position on the storefront of the selected store.
- Uninstall removes the event, the cron job and every stored setting.
