# SaleSnap — Laravel 12 + MySQL + Shopify Admin

An embedded Shopify app starter for time-bound product promotions. This repository is a **working foundation, not a claim of App Store approval or a finished commercial product**. It implements Shopify OAuth, per-request embedded session-token validation, scoped Admin GraphQL access, product price/tag/description campaigns, pre-write JSON snapshots with hashes, safe value-level rollback, scheduled background work, audit records, uninstall handling, and required privacy-topic webhook endpoints.

## Marketing website and install redirect

- `/` serves a responsive, self-contained public marketing website; `/app` remains the embedded Shopify Admin app.
- `public/marketing-site.html` is also a standalone preview with inline styles/scripts and no external assets.
- The website CTA points to `/install`. Configure `SHOPIFY_APP_STORE_URL=https://apps.shopify.com/YOUR-APP-HANDLE` after Shopify approves/publishes the listing. The redirect accepts only HTTPS `apps.shopify.com` URLs; it does not accept arbitrary shop domains or open redirects. Until configured, it shows an unpublished status page.
- Configure `SHOPIFY_SUPPORT_EMAIL`. `/privacy` and `/terms` intentionally show launch placeholders until replaced with real, operator-specific legal policies.
- See [`PENDING_BEFORE_LAUNCH.md`](PENDING_BEFORE_LAUNCH.md) for release blockers.

## Implemented in this starter

- Shopify embedded app shell with App Bridge loaded and app navigation.
- OAuth authorization-code installation with expiring offline tokens, App Bridge session-token verification, active-session token exchange, and serialized refresh-token rotation for background jobs.
- Session-token verification on every app API route; no third-party-cookie or local-storage auth.
- Minimal scopes: `read_products,write_products`.
- Product search and selection (individual products, up to 250 per campaign).
- Schedule in an IANA timezone; queue-driven application and automatic rollback.
- Preflight: read all selected products and up to 250 variants each, then persist every original JSON snapshot before the first product write. SHA-256 integrity check is recorded for each snapshot.
- Price percent discount, one added tag, and escaped text prepended to the product description.
- Emergency restore checks live values; it restores a price only when the current price still matches this campaign’s sale price, removes only the campaign tag, and strips only the exact campaign description prefix. Conflicts are reported instead of silently overwriting merchant edits.
- HTTPS webhook signature validation, `app/uninstalled`, and all three mandatory privacy topics. This app does not request or store customer data.
- MySQL migrations, database queue, scheduler command, Docker development setup, security headers, and operator checklist.

## Explicit v1 exclusions — do not list these as available features

Collection-wide selection, metafields, product status changes, campaign editing/cancellation, Shopify Billing, subscription plans, and Shopify App Store listing/legal materials for your company are **not implemented**. The app starts free-to-install unless you add and verify Shopify Billing. Don't claim these capabilities in your listing until built and tested.

## Prerequisites

- PHP 8.2+ (PHP 8.3 recommended), Composer 2, MySQL 8, HTTPS public domain, and a process manager for the queue worker and scheduler.
- A Shopify Partner / Dev Dashboard app configured for **public distribution / Shopify App Store** when ready. Use a development store for all functional tests.
- Shopify API client ID and client secret. Keep the secret, database credentials, and `APP_KEY` server-side only.

The app exchanges an active embedded ID token for an expiring offline access token when needed, and uses serialized refresh-token rotation for background work; keep those renewal paths mutually coordinated.

Laravel 12 is used because it was explicitly requested. **As of 2026-09-29, Laravel 12 security support ends 2027-02-24.** For a newly launched app, plan and test a Laravel 13 upgrade before that date. Shopify App Store approval is a separate review decision and cannot be guaranteed by generated code.

## Local setup

```sh
cp .env.example .env
# Fill MySQL and Shopify values. For local embedded testing use HTTPS via Shopify CLI tunnel / a trusted tunnel.
composer install
php artisan key:generate
php artisan migrate
php artisan serve --host=0.0.0.0
```

Run the queue and scheduler in separate processes (required for actual start/end automation):

```sh
php artisan queue:work database --sleep=2 --tries=3 --timeout=1800
php artisan schedule:work
```

`docker compose up --build` is included as a local baseline; the app container expects a completed `.env`. The `web` service migrates on startup, while the worker and scheduler are separate persistent processes. Do not use the sample passwords or HTTP-only local setup in production.

Generate `APP_KEY` before first use and keep it stable. Laravel's encrypted model casts protect stored Shopify access/refresh tokens at rest; losing `APP_KEY` makes those encrypted tokens unreadable. Never commit `.env`.

## Shopify Dev Dashboard configuration

1. Create/configure the app in the Shopify Dev Dashboard, set the public HTTPS app URL to `https://YOUR_DOMAIN/app`, and set the redirect URL exactly to `https://YOUR_DOMAIN/auth/callback`.
2. Copy the client ID into `SHOPIFY_API_KEY` / `shopify.app.toml`. Put the client secret in `SHOPIFY_API_SECRET` and `SHOPIFY_WEBHOOK_SECRET` **only on the server**.
3. Request only `read_products,write_products`. Re-evaluate permissions if you add collections, metafields, status edits, or billing.
4. Sync/verify the webhook subscriptions from `shopify.app.toml`, set the stable API version to the current supported stable version, and use HTTPS. Configure `SHOPIFY_APP_STORE_URL` and `SHOPIFY_SUPPORT_EMAIL` before turning on the public marketing/install flow.
5. Set `APP_URL` and `SHOPIFY_APP_URL` to the same canonical HTTPS origin. Confirm the app is embedded and that Chrome incognito / third-party-cookie restrictions work.
6. In your production database set MySQL credentials; run `php artisan migrate --force`; run exactly one scheduler (or a scheduler with a shared lock store); run durable queue workers. Monitor failed jobs and the `needs_attention` campaign state.
7. Test reinstall authorization, scopes, OAuth HMAC/state tampering, invalid session JWTs, product deletion during schedule, variant pagination boundaries, API throttling/outages, a staff price edit during promotion, partial apply failure, automatic rollback, emergency rollback, webhook signatures, uninstall deletion, and privacy requests before you submit.

`shopify.app.toml` and `.env.example` have placeholder app ID / URL values. Replace them; never publish while they are placeholders. The selected Shopify Admin API version is **2026-07**, the latest stable version on 2026-09-29 (2026-10 was still release candidate on that date). Recheck the [version schedule](https://shopify.dev/docs/api/usage/versioning) before release and update quarterly.

## Official references (recheck before submission)

- [Shopify App Store requirements](https://shopify.dev/docs/apps/launch/shopify-app-store/app-store-requirements)
- [Shopify App Store best practices](https://shopify.dev/docs/apps/launch/shopify-app-store/best-practices)
- [Session tokens / authentication](https://shopify.dev/docs/apps/build/authentication-authorization/session-tokens)
- [Access tokens and token exchange](https://shopify.dev/docs/apps/build/authentication-authorization/access-tokens)
- [HTTPS webhook delivery](https://shopify.dev/docs/apps/build/webhooks/subscribe/https)
- [Admin API version schedule](https://shopify.dev/docs/api/usage/versioning)
- [Laravel 12 support schedule](https://laravel.com/docs/12.x/releases)

## App Store submission checklist — still owner/reviewer dependent

- Complete and independently security-review OAuth/session-token validation, webhook behavior, encryption-key backups, rate-limit handling, and error/retry recovery. Run real tests on a development store; no Shopify API call is executed by the visual prototype alone.
- Provide a valid support email, public privacy policy, terms, support/contact page, data retention/deletion explanation, and a monitored support path. Fill the templates in `docs/` with your real legal entity, address, contact, retention, and subprocessors before publishing.
- Set pricing accurately. This starter is free-to-install. If charging merchants, implement Shopify Billing API (including upgrades/downgrades/cancellation) before listing; do not route app charges around Shopify billing.
- Produce truthful listing text, functional English screenshots with alt text, setup instructions, review screencast/test credentials, and answer every submission field. Do not promise guaranteed sales outcomes or imply reviews/testimonials.
- Verify app listing content and technical requirements against Shopify’s live documents at submission; policies and review criteria can change.

## Snapshot/rollback semantics

The snapshot is committed to MySQL before any Shopify write. Shopify does not offer one atomic transaction spanning many product/variant mutations, so this app applies per product and uses compensating, idempotent rollback. If a field differs from the expected promotion value at rollback, that field is skipped and reported as a conflict; this deliberately avoids silently replacing legitimate staff edits. Because Shopify product mutations do not provide a compare-and-swap transaction, a staff edit that races between our read and a write cannot be eliminated completely. Tags are plain strings: an identical tag re-added independently during the campaign cannot be distinguished from the one this app inserted. Network retries and queue restarts can leave partial campaign states, which are surfaced as `needs_attention` and require review. Maintain tested MySQL backups and queue monitoring; a snapshot is not a substitute for store backups.

## API routes

- `GET /app` — embedded shell.
- `GET /auth` and `GET /auth/callback` — Shopify installation.
- `GET /api/dashboard`, `/api/products`, `/api/snapshots` — session-token protected.
- `POST /api/campaigns` — schedule a campaign.
- `POST /api/campaigns/{id}/restore` — emergency restore request.
- `POST /webhooks/shopify` — signed Shopify webhook endpoint.

Use this as a foundation for implementation and review preparation, not as a certification that the final submitted app satisfies every current review detail.
