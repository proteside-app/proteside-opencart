# VERIFICATION — Proteside checkout protection for OpenCart

Date: 2026-10-16 · Extension `proteside` 1.0.0 · Bundled bootstrapper 1.1.3 (from `proteside-sdk` 1.1.3)

## What was implemented

```
plugin-opencart/
  README.md · README.pt-BR.md · README.es.md · CHANGELOG.md · LICENSE (GPL-3.0) · VERIFICATION.md
  lib/                         shared library (source of truth, plain PHP 7.0+, no OpenCart dependency)
    snippet.php                two-tag composition, inline config, injection after <head>, position inspector, CSP hash
    entitlement.php            state and decision table (48 h tolerance, interval, lock, key binding)
    page_matcher.php           page groups by OpenCart route (3.0 and 4.x route formats) + custom patterns
    settings.php               module_proteside_* settings: defaults, validation, sanitising
    api_client.php             GET /api/sdk/status and /api/sdk/config (cURL, stream fallback, 5 s timeout)
    storage.php                persistence on the core `setting` table (per-store state map, cron token, purge)
    service.php                refresh, refreshAll (cron), decision, rendering, deferred revalidation after the response
    admin_view.php             status card / diagnostics data, storefront probe client and messages
    proteside.php              loader
  bootstrapper/                bootstrapper.min.js, .src.index.ts, .src.match.ts, VERSION (byte-identical to proteside-sdk 1.1.3), SOURCE.md
  lang/                        en-gb.php · pt-br.php · es-es.php (admin texts; shared glossary wording)
  opencart-3/upload/           OpenCart 3.0.x package (admin + catalog controllers, Twig view; lib/lang copies generated)
  opencart-4/proteside/        OpenCart 4.0.2+ package (install.json, admin + catalog module controllers, cron controller, Twig view; lib/lang copies generated)
  dist/proteside-opencart-3.ocmod.zip   installable on 3.0.x (Extensions > Installer)
  dist/proteside.ocmod.zip              installable on 4.0.2+ (name must stay proteside.ocmod.zip)
  tests/                       7 suites, plain PHP (php tests/run.php)
  tools/build.php              syncs lib/, bootstrapper/, lang/ into both packages and writes dist/ (--check to verify)
  tools/php70-compat-check.php token scan: the 3.0 package and lib/ must parse on PHP 7.0
  tools/mock-api.php           mock of /api/sdk/status and /api/sdk/config for staging tests
  tools/inspect-head.php       reports where the snippet sits in a page read from stdin
```

### Behaviour

- **Mechanism:** event `proteside` on `catalog/view/common/header/after`, sort order 9999 (runs after other header
  events). The handler inserts `<!-- Proteside Start -->`, the inline bootstrapper and the async `shield.js` right after
  the opening `<head>` tag, after a `<meta charset>`/`http-equiv Content-Type` that immediately follows it (kept within
  the first 1024 bytes; it is not a script, so the order check is unaffected). Idempotent (marker check).
- **Tags:** inline `<script data-cfasync="false" data-no-optimize="1" data-nowprocket data-pagespeed-no-defer>`,
  content `"use strict";` + optional `window.__PROTE_INLINE__={"rules":[…],"gtm":[…]};` + rest of the bootstrapper, in
  the same script; then `<script async src="…/v1/shield.js" data-key data-api [data-calibration]>`. No other attribute.
  JSON uses `JSON_HEX_TAG`, so rules cannot close the script.
- **Entitlement:** `/api/sdk/status` with `key, site, platform=opencart, version`, UA `Proteside-OpenCart/1.0.0; <site>`;
  `/api/sdk/config` only after a positive answer. Only 200/401 change the decision; 48 h tolerance; fail-closed when
  never validated. State is bound to the key (sha1), so changing the key forgets the old state.
- **Storage:** merchant settings in `setting` code `module_proteside` per store (OpenCart's own editSetting); state as
  one JSON map `store_id => state` in `setting` code `proteside_state` store 0 (read on the storefront from the already
  loaded configuration, no extra query); cron token in code `proteside_cron`.
- **Revalidation:** never on the render path. On save / "Check status"; deferred after the response (shutdown
  function + `fastcgi_finish_request`/`litespeed_finish_request`, else output flush) when the interval elapsed, with a
  120 s lock; token-protected cron URL (`…/cron&token=`, `&force=1`); OpenCart 4 native hourly cron job `proteside`.
- **Probe:** admin "Test the installation" calls the storefront route `…/probe&token=` of the selected store, which
  renders `common/header` with injection forced and returns `ProtesideSnippet::inspect()`.
- **Install / uninstall:** install adds the event (and the OC4 cron job), default settings (only if none exist) and the
  cron token; uninstall deletes the event, the cron job and every row with codes `module_proteside`,
  `proteside_state`, `proteside_cron`.
- **Staging seam:** a `PROTESIDE_API_BASE` constant defined in OpenCart's `config.php` / `admin/config.php` replaces
  `https://app.proteside.com/api/sdk` for the server-side status calls (the `data-api` attribute of `shield.js` never
  changes). Used only by the end-to-end run below.

## How it was verified

### 1. Offline checks (run on 2026-10-16, PHP 8.5.10)

```
$ php tools/build.php --check
Packages are in sync with lib/, bootstrapper/ and lang/.

$ php tests/run.php
api_client: 14 passed, 0 failed
entitlement: 44 passed, 0 failed
package: 110 passed, 0 failed
page_matcher: 56 passed, 0 failed
service: 56 passed, 0 failed
settings: 31 passed, 0 failed
snippet: 54 passed, 0 failed

All suites passed

$ php tools/php70-compat-check.php
14 files parse with PHP 7.0 syntax.

php -l on all 54 PHP files: no syntax errors · install.json: valid JSON · unzip -t on both zips: no errors
```

What the suites cover: key format (including a trailing newline, which the regex rejects with `/D`); rule and GTM
filtering; inline JS equals the bootstrapper byte-for-byte without rules (hash
`sha256-t2ypUgwBPt6N82k2gXq5D5ZHIdxOXxhj6ZISJatVbg8=`) and starts with `"use strict";window.__PROTE_INLINE__={` with
rules; `</script>` cannot appear; exact shield tag and allowed attributes; channel / cdnBase handling; injection after
`<head>` and `<meta charset>`, idempotency, `<header>` not mistaken for `<head>`; every row of the decision table;
transient vs definitive answers; key binding; 3.0 and 4.x route formats (`.` and `|` method separators), payment
extension routes, one-page checkout routes; custom patterns; settings round trip; refresh / refreshAll / deferred
refresh with a fake HTTP layer; admin view model and probe messages; language key parity (en-gb, pt-br, es-es) and
every key used by templates/controllers; package sync; version agreement; no mention of other platforms.

### 2. End-to-end in real OpenCart installs (2026-10-16)

Environment: macOS, PHP 8.5.10 built-in server (`PHP_CLI_SERVER_WORKERS=4`), MariaDB 13.0.2, official OpenCart
releases from GitHub tags **3.0.5.0**, **4.0.2.3** and **4.1.0.4**, installed with their CLI installers and sample
data. The extension was installed from `dist/*.ocmod.zip` through the real **Extensions → Installer** upload/install
endpoints and **Extensions → Extensions → Modules → Install**. After the runs, the files inside each store were
compared with the final packages: identical.

| Check | 3.0.5.0 | 4.0.2.3 | 4.1.0.4 |
| --- | --- | --- | --- |
| Installer accepts the package; files land in the right folders | ✓ | ✓ | ✓ |
| Module install: event `catalog/view/common/header/after` → `…injectHeader`, sort 9999; defaults; cron token | ✓ | ✓ | ✓ |
| OC4 cron job `proteside` (hour) registered | — | ✓ | ✓ |
| Settings page renders (en-gb; pt-br module strings on 4.1) | ✓ | ✓ | ✓ |
| Invalid form: key and calibration errors shown inline | ✓ | — | ✓ (also in the browser, AJAX form) |
| Save with a fake key against the **real** `app.proteside.com`: HTTP 401 → "Invalid key", not injected | — | — | ✓ |
| Save with mock API: status + config called with `platform=opencart`, UA `Proteside-OpenCart/1.0.0; <site>`; card "Active", plan, decision active | ✓ | ✓ | ✓ |
| Checkout and success pages: snippet right after `<head><meta charset>`, `first_in_head: true`, 0 scripts before | ✓ | ✓ (success) | ✓ |
| Home, cart, login, product: not injected (defaults) | ✓ | ✓ (home) | ✓ |
| CSP hash in Diagnostics equals the hash of the inline script actually served | ✓ | — | ✓ |
| Admin "Test the installation" → "first script after the opening head tag" | ✓ | ✓ | ✓ (also in the browser) |
| Cron URL: bad token 403; not due → skipped; `force=1` → checked | ✓ | — | ✓ |
| Deferred revalidation: stale state + one storefront visit → status and config requested after the page; second visit → no request | ✓ | — | ✓ |
| OC4 native cron: admin **Run** and `index.php?route=cron/cron` refresh the status | — | — | ✓ |
| Grace period: warning + still injected · API 503: "Connection error" + still injected (tolerance) | — | — | ✓ |
| Suspended key: not injected · protection disabled: not injected | — | — | ✓ |
| Custom URL pattern, calibration 45 and channel `latest` reflected in the tags; inline rules off → no `__PROTE_INLINE__` | — | — | ✓ |
| Multistore: second store inherits, then gets its own key; per-store state; cron refreshes both; probe per store | — | — | ✓ |
| Custom URL with `&` stored decoded and shown escaped | ✓ | — | — |
| Direct request to the event/cron routes does nothing | ✓ | — | ✓ |
| Uninstall removes event, cron job and all settings; storefront stops injecting; reinstall works | ✓ | — | ✓ |
| No PHP notice/warning from the extension in `system/storage/logs/error.log` | ✓ | ✓ | ✓ |

Real browser check (Chromium, OpenCart 4.1.0.4, order confirmation page): the real
`shield.js` loaded from `https://cdn.proteside.com/v1/shield.js` and `Proteside.getStatus()` returned
`version: "1.1.3"`, `installationOrderValid: true`, `rulesApplied: true`, with `window.__PROTE_BOOT_SCRIPTS__ === 0`,
2 inline rules and `GTM-TEST123` from the inline config. First child of `<head>`: `<meta charset>`; second: the
Proteside inline script.

Timing of the deferred revalidation with the mock answering in 3 s (PHP built-in server, no FastCGI): time to first
byte 24 ms, full page delivered before the checks; the connection stayed open until both checks finished (6 s).
Under PHP-FPM the connection is closed by `fastcgi_finish_request()` instead.

Bugs found by the end-to-end run and fixed before packaging:

- `curl_close()` is deprecated in PHP 8.5 and the notice, printed before the JSON, broke the admin AJAX save. It is
  now called only on PHP < 8.0 (it is a no-op since 8.0).
- The key regex accepted a trailing newline (`$` without `/D`).

### How to repeat the end-to-end run

```sh
# 1. a MariaDB/MySQL database and an OpenCart install (php install/cli_install.php install …), install/ removed
# 2. the mock API, and in config.php + admin/config.php of the test store:
php -S 127.0.0.1:8199 tools/mock-api.php
#    define('PROTESIDE_API_BASE', 'http://127.0.0.1:8199/api/sdk');
# 3. serve the store with several workers (the probe calls the storefront from the admin):
PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:8104 -t /path/to/opencart
# 4. install dist/*.ocmod.zip through Extensions > Installer, then Extensions > Extensions > Modules
# 5. inspect a page:
curl -s 'http://127.0.0.1:8104/index.php?route=checkout/success' | php tools/inspect-head.php
```

## What was NOT tested

- OpenCart 3.0.2.0 – 3.0.4.x and 4.0.2.0 – 4.0.2.2, 4.1.0.0 – 4.1.0.3: only 3.0.5.0, 4.0.2.3 and 4.1.0.4 ran. The APIs
  used were compared in the 3.0.2.0 source (addEvent with sort order, event loading, view after-event with the
  original trigger, settings loading, installer allowed folders): identical.
  3.0.5.1 (2026-10-16): the same eleven core files used by the extension are byte-identical to 3.0.5.0.
- PHP 7.x: the 3.0 package passes the PHP 7.0 syntax scan, but no PHP 7 interpreter was run.
- Apache / Nginx with PHP-FPM, LiteSpeed: only the PHP built-in server. `fastcgi_finish_request()` path not exercised.
- Third-party themes (Journal 3, etc.) and one-page checkout extensions: routes are covered by rule, not by an install.
- SEO URLs switched on (routes come from OpenCart's SEO resolver; not exercised).
- Full-page cache, minification and CDN extensions that rewrite the HTML.
- A real Proteside key: the active/grace/suspended paths ran against the mock; the real API was called only with a
  fake key (401). On 3.0.5.0 the admin was exercised by HTTP requests and one screenshot, not by clicking every control.
- OpenCart 4.1.0.4 `php cron.php`: fails in OpenCart itself (Twig is not autoloaded by that script), before any
  extension runs; documented in the README.
- Marketplace submission (opencart.com) and the official logo: not done.

## Next steps

1. Install in a staging copy of a real store with its real `pk_live_` key; confirm the card shows **Active** and that
   `Proteside.getStatus().installationOrderValid === true` on the checkout with the store's own theme.
2. Run on the merchant's PHP-FPM stack to confirm the deferred check closes the connection first.
3. Try the popular one-page checkout extensions and Journal 3 with **Test the installation**.
4. When `proteside-sdk` publishes a new bootstrapper, follow `bootstrapper/SOURCE.md`.
5. Publish on the OpenCart Marketplace (two downloads: 3.0.x and 4.x).
