# Proteside checkout protection for OpenCart

[Português (Brasil)](README.pt-BR.md) · [Español](README.es.md)

**Download:** the installable package is attached to the [latest release](https://github.com/proteside-app/proteside-opencart/releases/latest). This repository is published by the Proteside team for download only; it does not accept contributions. Questions: [team@proteside.com](mailto:team@proteside.com).

Loads the Proteside client-side protection (PCI DSS 4.0 requirements 6.4.3 and 11.6.1) on the payment pages of an
OpenCart store using your **public SDK key**. The extension reproduces the canonical Proteside installation: an
inline, synchronous bootstrapper followed by the async `shield.js`, placed as the **first content of `<head>`** so
that every other script on the page is observed from the first byte.

Extension code: `proteside` · Version: 1.0.0 · Bundled bootstrapper: 1.1.3 · License: GPL-3.0-or-later

## Requirements

| | OpenCart 3.0 package | OpenCart 4 package |
| --- | --- | --- |
| OpenCart | 3.0.2.0 to 3.0.5.1 | 4.0.2.0 or newer, including 4.1 |
| File | `dist/proteside-opencart-3.ocmod.zip` | `dist/proteside.ocmod.zip` (keep this exact name) |
| PHP | 7.0 or newer (whatever your OpenCart runs on) | 8.0 or newer |

Both packages need outbound HTTPS from the server to `app.proteside.com` (cURL or `allow_url_fopen`), only for the
status checks, and a Proteside account with its SDK key (`pk_live_…` or `pk_test_…`) from the dashboard under
**Settings → Pages and Domains**. Don't have an account yet? See <https://app.proteside.com/plans>.

OpenCart 4.0.0.0 and 4.0.1.x are not supported (they use a different route separator and event API).

## Installation

### OpenCart 4.0.2+ / 4.1

1. **Extensions → Installer → Upload** and choose `proteside.ocmod.zip`. The file name must stay
   `proteside.ocmod.zip`: OpenCart 4 uses it as the extension folder name.
2. In the installed list, click the green **Install** button of "Proteside checkout protection".
3. **Extensions → Extensions**, choose the type **Modules**, find "Proteside checkout protection" and click
   **Install** (+), then **Edit** (pencil).
4. Paste your SDK key and click **Save**. The page reloads with the status returned by Proteside.

### OpenCart 3.0.x

1. **Extensions → Installer → Upload** and choose `proteside-opencart-3.ocmod.zip`.
2. **Extensions → Extensions**, choose **Modules**, find "Proteside checkout protection", click **Install** (+) and
   then **Edit** (pencil).
3. Paste your SDK key and click **Save**.

Without the installer (both versions), copy the files by FTP: for 3.0 the content of `upload/` to the store root; for
4.x the content of `proteside.ocmod.zip` to `extension/proteside/`. Then continue from the **Extensions →
Extensions → Modules** step.

Installing the module registers the event `proteside` (**Extensions → Events**) and, on OpenCart 4, the cron job
`proteside` (**Extensions → Cron Jobs**). Do not disable the event: it is what inserts the protection.

You do **not** need to add the module to a layout (**Design → Layouts**). If you do, it prints nothing.

## Configuration

| Setting | Default | What it does |
| --- | --- | --- |
| Enable protection | on | Master switch. |
| SDK key | — | Public key `pk_live_`/`pk_test_` + 32 hex characters. Saved only if the format is valid. |
| Pages | checkout, order confirmation | Where the tags are inserted (see below). |
| Custom URL patterns | — | One per line, `*` as wildcard, matched against the request path and URL. |
| Release channel | Follow the dashboard | `auto` uses the channel reported by `/api/sdk/config`; `stable`/`latest` force it. |
| Embed blocking rules (inline config) | on | Writes your active rules and allowed GTM containers into the bootstrapper (`window.__PROTE_INLINE__`) so blocking starts before the remote configuration arrives. |
| Calibration (seconds) | empty | Optional `data-calibration` (10 to 120). Only used if the SDK cannot fetch its remote configuration. |
| Re-check status every | 12 h | 6, 12 or 24 hours. |
| Re-check after storefront visits | on | Deferred revalidation (see "Revalidation"). Turn it off if you call the cron URL. |

Buttons: **Save** stores the settings and refreshes the status; **Check status** refreshes it without saving.

### Pages

Pages are detected by the OpenCart route (`route`, also set when SEO URLs are on), never by the URL, except for your
custom patterns:

| Option | Routes |
| --- | --- |
| Checkout (all steps) | `checkout/checkout` and every other `checkout/…` route except cart, success and failure; payment extension pages (`extension/payment/…` on 3.0, `extension/<vendor>/payment/…` on 4.x); one-page checkout extensions whose route contains `checkout` (for example `checkout/simplecheckout`, `extension/quickcheckout/checkout`, `journal3/checkout`) |
| Order confirmation (thank you) | `checkout/success`, `checkout/failure` (the SDK uses the success page to confirm card payments) |
| Cart | `checkout/cart` |
| Shop and product pages | `common/home`, `product/…` |
| My account | `account/…` (except the login pages), `affiliate/…` |
| Login page | `account/login`, `account/forgotten`, `account/reset` |
| Entire store | every page |
| Custom URL patterns | your patterns, e.g. `/finalizar-compra` or `*route=extension/payment/mypayment*` |

Checkout steps loaded by AJAX inside the checkout page are covered by the tags of that page.

### Multistore

With more than one store, a **Settings for** selector appears at the top. Each store can have its own key, pages and
options; a store without settings of its own uses the default store's. The status is checked and stored per store,
using the store URL.

### Status card

Shows **Active**, **Grace period**, **Suspended**, **Inactive**, **Invalid key**, **Connection error** or **Not
verified yet**, the plan, when the last check ran, notices (billing in grace period, invalid key, subscription not
active, connection error, key registered for another domain) and the decision: "active — the protection loads on
the selected pages" or "inactive — the protection does NOT load".

Decision rules:

| Last definitive answer | API now | Inserts the protection? |
| --- | --- | --- |
| active / grace | OK | yes (grace shows a billing warning) |
| suspended / inactive / invalid | OK | no |
| never validated | unavailable | no (fail-closed) |
| active / grace, last success ≤ 48 h | unavailable | yes (tolerance) |
| active / grace, last success > 48 h | unavailable | no |

Only HTTP 200 and 401 change the decision; 429, 5xx and timeouts keep the previous one and show "Connection error".
A key registered for another domain is only a warning; protection keeps loading.

## Revalidation

The extension never calls Proteside while it builds a page. The status is refreshed:

1. **When you save** or click **Check status**.
2. **After storefront visits (default).** When the interval has elapsed, the next storefront page schedules a check
   that runs after the page has been delivered. With PHP-FPM or LiteSpeed the connection to the visitor is closed
   before the check starts; with other server APIs (e.g. Apache mod_php) the page is already fully sent, but the
   connection may stay open up to 10 s while Proteside answers. A lock prevents parallel checks.
3. **Cron URL (optional).** Shown in **Diagnostics** with a copy button:

   ```
   https://your-store.example/index.php?route=extension/proteside/module/proteside.cron&token=<token>   (OpenCart 4)
   https://your-store.example/index.php?route=extension/module/proteside/cron&token=<token>             (OpenCart 3.0)
   ```

   Call it from your server scheduler, for example every hour: `0 * * * * curl -fsS '<url>' >/dev/null`. It checks
   every store whose interval has elapsed and returns JSON; add `&force=1` to check all of them now. Keep the token
   private; it is generated on install.
4. **OpenCart 4 cron job.** The module registers the hourly job `proteside` in **Extensions → Cron Jobs**. It runs
   when your server calls OpenCart's cron (`index.php?route=cron/cron` or `php cron.php`) and when you click **Run**
   on that page.

## Installation order (why the position matters)

The bootstrapper records how many `<script>` elements come before it. If any script runs before it, the SDK reports
`INSTALLATION_ORDER_WARNING` and `Proteside.getStatus().installationOrderValid` is `false`; protection continues with
partial coverage.

The extension listens to the event that renders the header template (`catalog/view/common/header/after`) with sort
order 9999, so it runs after the other extensions that change the header, and inserts the two tags right after the
opening `<head>` tag (after a `<meta charset>` that comes right after it). In the default themes of OpenCart 3.0 and 4
this is before jQuery, Bootstrap and every other script.

**Verify:**

- In the admin, **Diagnostics → Test the installation** renders the header of the selected store and reports whether
  the snippet is the first script after `<head>`.
- On the storefront, open the order confirmation or checkout page, open the browser console and run
  `Proteside.getStatus()`. `installationOrderValid` must be `true`.
- In the page source, `<!-- Proteside Start -->` must come right after `<head>` (and its `<meta charset>`), then the
  inline script, then `<script async src="https://cdn.proteside.com/v1/shield.js" …>`.

Never install Proteside through Google Tag Manager. If the store uses GTM, Proteside must run before it, which this
extension guarantees.

## Content Security Policy (CSP)

OpenCart does not send a CSP by default. If your store (or its proxy or CDN) does, allow:

- `script-src`: the inline bootstrapper (hash, nonce or `'unsafe-inline'`) **and** `https://cdn.proteside.com`
- `connect-src`: `https://app.proteside.com` and `https://cdn.proteside.com` (plus `'self'`); `shield.js` hashes external scripts, its own included, with a CORS fetch

Hash of the bundled bootstrapper 1.1.3 **without** inline rules: `'sha256-t2ypUgwBPt6N82k2gXq5D5ZHIdxOXxhj6ZISJatVbg8='`.
With inline rules the script changes; the current hash is always shown in **Diagnostics → CSP hash of the inline
script** and changes whenever your rules or GTM containers change in the dashboard. If keeping the hash in sync is
impractical, turn off "Embed blocking rules" to keep the published hash.

## External services / data sent

The extension talks only to Proteside, only from the server, and only to check the status (save, "Check status",
deferred revalidation, cron). It never calls Proteside while building a page.

| Request | When | Data sent |
| --- | --- | --- |
| `GET https://app.proteside.com/api/sdk/status` | status checks | SDK key, store URL (`site`), `platform=opencart`, extension version; `User-Agent: Proteside-OpenCart/1.0.0; <store URL>` |
| `GET https://app.proteside.com/api/sdk/config` | after a positive status check | SDK key |

In the visitor's browser, `shield.js` (loaded from `https://cdn.proteside.com`) sends the SDK telemetry (observed
scripts, blocked scripts, page events) to `https://app.proteside.com/api/sdk/events`; this is done by the SDK, not by
the extension. The SDK key is public by design. The extension stores no secret apart from the token of its cron URL.

Terms: <https://proteside.com/termos> · Privacy: <https://proteside.com/privacidade> · Docs: <https://docs.proteside.com>

## Uninstalling

**Extensions → Extensions → Modules → Uninstall** (–) removes the event, the cron job (OpenCart 4) and every setting
and status stored by the extension; the storefront stops loading the protection immediately (page caches aside). To
remove the files too, uninstall the package in **Extensions → Installer**.

## Limitations

- The tags are inserted into the output of the header template. A theme whose `common/header` template does not
  contain the opening `<head>` tag cannot be covered; **Test the installation** detects it.
- Another extension that prints scripts before the header template, or that rewrites the whole page after OpenCart
  (some cache or minification extensions), can move scripts before Proteside. **Test the installation** and
  `Proteside.getStatus()` show it.
- Full-page caches serve the HTML that was cached; a status change shows after the cache expires.
- Checkouts hosted outside OpenCart (payment pages on the provider's domain, iframes) are not covered. In single-page
  checkouts that change the URL without a page load, call `Proteside.pageChanged(path)` on each route change.
- The extension does not render a trust badge. The badge is a static `<a>` copied from the dashboard (Settings →
  Store).
- The bootstrapper ships with the extension and is updated with each release.
- On OpenCart 4.1.0.4 the command-line runner `php cron.php` fails before reaching any extension (it does not load
  Twig); use `index.php?route=cron/cron`, the **Run** button or the cron URL of this extension.

## FAQ

**The status says "Not verified yet" after saving.** The key could not be checked (network). Click **Check status**.
The storefront does not load the protection until a positive answer has been received (fail-closed).

**The dashboard shows an installation-order warning.** Run **Diagnostics → Test the installation**. If another
script comes first, look for an extension that prints scripts before the header or rewrites the page, and check that
the event `proteside` is enabled in **Extensions → Events**.

**Does the key have to be secret?** No. `pk_` keys are public and appear in the HTML of every protected page.

**Does it work with SEO URLs?** Yes. Pages are detected by route, which OpenCart sets from the SEO keyword.

**Which versions were tested?** OpenCart 3.0.5.0, 4.0.2.3 and 4.1.0.4 on PHP 8.5 with MariaDB; see `VERIFICATION.md`.

## Support

<team@proteside.com> · <https://docs.proteside.com> · Dashboard: <https://app.proteside.com>

## Development

```sh
php tests/run.php                 # unit tests (plain PHP, no OpenCart needed)
php tools/build.php               # sync lib/, bootstrapper/ and lang/ into both packages and write dist/*.ocmod.zip
php tools/build.php --check       # fail if a package copy is out of date
php tools/php70-compat-check.php  # the OpenCart 3.0 package must parse on PHP 7.0
```

`lib/`, `bootstrapper/` and `lang/` are the sources; the copies inside `opencart-3/` and `opencart-4/` are generated.
`tools/mock-api.php` and `tools/inspect-head.php` support staging tests (see `VERIFICATION.md`).

## License

GNU General Public License v3.0 or later — see `LICENSE`.
