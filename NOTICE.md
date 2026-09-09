# Zolo Smart School — Ownership and Third-Party Notices

**Product:** Zolo Smart School
**Owner:** Animazon
**Components:** Laravel backend (`PHP_CODE/`), student & parent Flutter app, staff Flutter app
**Licence:** Proprietary. Not for redistribution.

---

## Application identity

| Where | Value |
|---|---|
| Composer package | `animazon/zolo-smart-school` |
| NPM package | `zolo-smart-school` |
| Android application ID | `com.zoloschools.app` (student & parent), `com.zoloschools.staff` (staff) |
| iOS bundle identifier | `com.zoloschools.app`, `com.zoloschools.staff` |
| Application name | `APP_NAME` in `.env`, defaulting to Zolo Smart School |

Schools deployed on the platform set their own name, logo and colours through system
settings; those override the defaults above at runtime.

---

## Outbound network contact

The application contacts no third party for licensing, registration, analytics or
telemetry. There is no licence check, no activation call and no usage reporting of any
kind — see `SECURITY_AUDIT_2026-09.md` for the scan that establishes this.

Fonts, icons and front-end libraries used by the admin panel are served from this
application, so an administrator's browser does not report page views to a CDN.

Network calls that do exist are limited to services **you** configure, and only when the
corresponding feature is used:

| Service | When |
|---|---|
| Stripe / Razorpay / Paystack / Flutterwave | Only the gateway you configure, on a transaction |
| Firebase Cloud Messaging | Only when push credentials are set |
| Your SMTP provider | On email send |
| Google reCAPTCHA | Only when a reCAPTCHA site key is configured |
| Google Maps (mobile apps) | Map display |

---

## Third-party components

This product incorporates open-source and third-party components that remain the property
of their respective authors and are used under their own licences. Their copyright headers
and licence files are retained in place — inside `vendor/`, `node_modules/`, `packages/`
and `public/assets/` — and must not be removed.

These include, among others: the Laravel framework, Spatie Laravel Permission, dompdf,
Guzzle, Stripe and Razorpay SDKs, Maatwebsite Excel, Intervention Image, Ratchet,
TinyMCE, CKEditor, Bootstrap, jQuery, Material Design Icons, Font Awesome, and the
Flutter packages listed in each app's `pubspec.yaml`.

Animazon's ownership covers the Zolo Smart School application code, branding and
configuration. It does not extend to these third-party components, and nothing in this
notice alters the terms under which they are licensed.
