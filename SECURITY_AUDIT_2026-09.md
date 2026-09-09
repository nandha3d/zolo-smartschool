# Zolo Smart School — Security & Provenance Audit

**Prepared by:** Animazon

**Date:** 9 September 2026
**Scope:** `PHP_CODE/` (Laravel backend) — routes, middleware, controllers, config, seeders, bundled assets.
Flutter clients under `App code/` were checked only for references to the endpoints changed here.

This audit **complements** the earlier `zolo_smart_school_security_audit_and_pricing.md.resolved`
(April 2026). It re-checks that report's findings and adds what it missed.

---

## 1. Provenance — is anything "nulled"?

**No nulling artifacts were found.** Specifically, scanning application code, config, routes,
seeders, views and the bundled packages turned up:

| Check | Result |
|---|---|
| Nulled / warez / crack / release-group markers | none |
| Telegram or forum links typical of repackaged builds | none |
| Purchase-code or Envato/CodeCanyon licence verification | **none present at all** |
| Disabled or stubbed licence checks | none — there is no licence system to disable |
| Obfuscated payloads (`eval`, `base64_decode`, `gzinflate`, `str_rot13`) | none in app code |
| Shell execution (`shell_exec`, `passthru`, `proc_open`, `system`, `exec`) | none in app code |
| Unexpected outbound hosts | none — only payment gateways, FCM, Google, and `zoloschools.com` |

The only `license` hits are dompdf's own config comments about the third-party PDFlib library.

What remains is ordinary **upstream vendor naming**, not nulling: the platform descends from a
third-party product (eSchool SaaS), so that name still appears in code identifiers, comments,
migration and seeder data. Those are cosmetic. The user-visible branding — logos, page titles,
favicon, website copy — is Zolo Smart School; see the branding commit on
`redesign/phase-4-module-rollout`.

> The upstream name is stated once here **on purpose**. This section exists to record where the
> code came from, and that record is only useful while it is accurate — it is the evidence
> behind the "no nulling found" conclusion. Everywhere else the product is Zolo Smart School.

### A note on going further

Renaming internal identifiers and comments is safe to do and can be scheduled if you want it.
What should **not** be stripped is the copyright/attribution headers on bundled third-party
libraries (the admin theme, dompdf, Spatie packages, TinyMCE, CKEditor and so on). Those headers
are the licence conditions under which the code may be redistributed at all — removing them
creates legal exposure rather than removing it, independent of how the base product was
obtained. Rebranding *your* product surface is unaffected by that.

---

## 2. New findings (not in the April report)

### 🔴 CRITICAL — Unauthenticated maintenance routes — **FIXED**

`routes/web.php` registered thirteen `GET` routes with **no auth, no middleware and no
environment check**. Any anonymous visitor who knew the path could:

| Route | Effect |
|---|---|
| `/migrate-rollback` | **Roll back migrations — destroys data** |
| `/AddSuperAdminSeeder-seeder` | Create/reset the super-admin account |
| `/installation-seeder`, `/dummy-seeder`, `/dummy-sample-seeder`, `/seeder-school` | Seed/overwrite data |
| `/migrate`, `/migrate-school` | Run migrations |
| `/demo-tokens` | **Print permanent Sanctum API tokens for the demo guardian and student accounts** |
| `/clear`, `/storage-link`, `/start-websocket`, `/test-code` | Misc. artisan operations |

`/demo-tokens` chains with the exposed seeders: seed the demo users, then mint working API
tokens for them. Sanctum tokens never expire in this install (finding A-7 below), so those
tokens would be permanent.

**Fix applied:** all thirteen removed. They are artisan commands and remain available on the
CLI. `cache-flush` is kept because the admin topbar links to it, now behind `auth`.

### 🟠 HIGH — `GET /api/fees-due-notification` was unauthenticated — **FIXED**

Sat outside every middleware group. It switches to whichever tenant database the `school-code`
header names and sends fee-due notifications to that school's guardians. Unauthenticated, this
allowed notification spam against any tenant's parents (with the associated FCM/email cost) and
worked as an oracle for which school codes exist.

**Fix applied:** now behind a `cron` middleware (`VerifyCronSecret`) requiring `CRON_SECRET`,
sent as an `X-Cron-Secret` header or `?cron_secret=`. It fails closed — with no secret
configured the route 404s.

> **⚠️ Deployment action required:** set `CRON_SECRET` in `.env` and add it to whatever cron
> currently calls this URL, or fee reminders stop going out. Generate one with
> `php artisan tinker --execute="echo Str::random(48);"`.

### 🟡 MEDIUM — Files carrying credentials / stray endpoints — **FIXED**

- `create_db.php` (repo root) — standalone script with hardcoded MySQL **root** credentials
  (`root` / `admin`). Not web-reachable when the docroot is `public/`, but committed
  credentials. **Deleted.**
- `app/Services/Payment/text.php` — an unreferenced duplicate of `FlutterwavePayment` whose
  filename doesn't match its class (so it was never autoloaded), containing dummy customer data
  and a hardcoded `webhook.site` redirect URL. **Deleted.**

---

## 3. Status of the April 2026 findings

Re-verified against current code. Several were fixed since that report:

| ID | Finding | Status |
|---|---|---|
| A-1 | 2FA code used `rand()` | ✅ Fixed — now `random_int()` |
| A-3 | 2FA middleware used `&&` so the expiry check never ran | ✅ Fixed — now `\|\|` |
| D-3 | HTTPS cookie enforcement unset | ✅ Fixed — defaults to on in production |
| — | Super-admin password hardcoded as `superadmin` | ✅ Fixed — generated, shown once, forced change |
| B-1 | CORS allowed all origins | ⚠️ Partial — now `CORS_ALLOWED_ORIGINS`, but **still defaults to `*`** |
| A-7 | Sanctum tokens never expire (`'expiration' => null`) | 🔴 **Open** |
| C-3 | SQL strict mode disabled on both connections | 🔴 **Open** |
| D-1 | Session data not encrypted (`'encrypt' => false`) | 🔴 **Open** |
| G-1 | Payment API/secret keys stored plaintext in DB | 🔴 **Open** — no `$casts` encryption on `PaymentConfiguration` |
| A-4 | No login rate limiting on the web form | 🔴 **Open** (not re-verified in depth) |
| C-1 | All tenant databases share one credential pair | 🔴 **Open** — architectural |
| C-4 | Backup restore runs `DB::unprepared()` on uploaded SQL | 🔴 **Open** — high risk |
| E-1/E-2/E-3 | No FormRequests; heavy `$request->all()`; profile mass-assignment | 🔴 **Open** |

---

## 4. Recommended next actions, in order

1. **Set `CRON_SECRET`** and update the cron — required, or fee reminders silently stop.
2. **Set `CORS_ALLOWED_ORIGINS`** to your real domains; the `*` default is effectively no CORS.
3. **Expire Sanctum tokens** — `config/sanctum.php`, e.g. 60×24×14 minutes, plus a prune job.
4. **Encrypt payment credentials** — add `'api_key' => 'encrypted'` etc. to
   `PaymentConfiguration::$casts` with a migration to re-encrypt existing rows.
5. **Rate-limit login** — `throttle:5,1` on the login POST, keyed by email + IP.
6. **Lock down backup restore** (C-4) — the `DB::unprepared()` path on an uploaded file is
   arbitrary SQL execution by any user who reaches that screen.
7. Turn on session encryption and SQL strict mode; both are single-line config changes best
   made together with a regression pass.

---

## 5. Verification performed

Static analysis and `php -l` syntax checks on every file changed. The application was **not
executed** — no database is available in the audit environment — so none of the fixes were
exercised at runtime. Before merging, confirm on a real instance:

- the admin topbar's "Cache Clear" still works (it now requires a session);
- `/api/fees-due-notification` returns 404 without the secret and 200 with it;
- nothing in your deployment tooling called the removed routes over HTTP.

---

## 6. Third-party contact inventory

What this project talks to, split by **who** makes the request. The distinction matters: a
server-side call happens under your control, while a browser-side one means every user's device
contacts that company directly, exposing their IP address and user-agent.

### 6.1 Browser-side — every user's browser contacts these

| Host | Loaded on | Conditional? |
|---|---|---|
| `unpkg.com` | **Every admin page** (`layouts/include.blade.php` — bootstrap-table CSS) | Always |
| `fonts.googleapis.com` / `fonts.gstatic.com` | **Every admin page** (Instrument Sans + Figtree) | Always — **added by the redesign** |
| `www.google.com/recaptcha` | **Login page and public home page** | **Always — fires even when reCAPTCHA is not configured** |
| `cdn.jsdelivr.net` | Login, 2FA, public site (Bootstrap, Swiper) | Always on those pages |
| `cdnjs.cloudflare.com` | Public site, fee receipt, exam-result PDFs (jQuery, FontAwesome, OwlCarousel) | Always on those pages |
| `code.jquery.com` | Exam-result PDF views | Always on those pages |
| `kit.fontawesome.com/1d2a297b20.js` | Public school site | Always — **ID belongs to the upstream vendor's account** |
| `checkout.razorpay.com` | Dashboard, addon plans | Always on those pages |

### 6.2 Server-side — only when configured and triggered

| Service | Purpose | Trigger |
|---|---|---|
| `api.stripe.com`, Razorpay SDK, `api.paystack.co`, `api.flutterwave.com` | Payments | Only the gateway that is configured, on a transaction |
| `fcm.googleapis.com`, `www.googleapis.com` | Push notifications | Only when FCM credentials are set |
| SMTP host of your choice | Email | On send |
| `sqs.us-east-1.amazonaws.com` | Laravel's stock queue config default | Unused unless the SQS driver is selected |

**No update phone-home.** `SystemUpdateController` applies an **uploaded zip**; it does not
contact a vendor server for updates or licence checks. `mahesh-kerai/update-generator` is
fetched from GitHub by Composer at install time only, not at runtime.

### 6.3 Mobile apps

Contact Google Fonts (runtime font download), Google Maps, Stripe/Razorpay checkout, YouTube
(embedded video), and whatever image hosts your API returns.

**No telemetry.** No Firebase config, analytics, Crashlytics or Sentry is present in either app.

Two development tools are listed as production dependencies and should be reviewed before a
store release: `curl_logger_dio_interceptor` (logs full HTTP requests, potentially including
credentials and student data, to the device log) in both apps, and `device_preview` in the staff
app.

### 6.4 Privacy observations

1. **reCAPTCHA is unconditional.** Every view of the login page contacts Google even on
   installations that never enabled it. Should be gated on the setting.
2. **The FontAwesome Kit ID is the upstream vendor's.** Requests from your public site are
   attributed to their account, and it stops working if they revoke it. Should be replaced with
   the self-hosted FontAwesome already bundled in `public/assets/fonts/`.
3. **Google Fonts is a GDPR consideration.** German courts have held that embedding Google
   Fonts via CDN transmits visitor IPs to Google without consent. For a platform holding
   children's data this is worth taking seriously — self-hosting the two font files removes the
   issue entirely.
4. **Most libraries are already self-hosted** in `public/assets/`. The CDN references above are
   the exceptions, and each could be pointed at a local copy, which also removes a
   supply-chain-injection path and a hard dependency on those CDNs being reachable.
