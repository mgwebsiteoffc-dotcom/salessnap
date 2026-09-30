# Shopify App Store release checklist (2026-09-29 baseline)

Official sources: [requirements](https://shopify.dev/docs/apps/launch/shopify-app-store/app-store-requirements), [best practices](https://shopify.dev/docs/apps/launch/shopify-app-store/best-practices), [access tokens](https://shopify.dev/docs/apps/build/authentication-authorization/access-tokens), [session tokens](https://shopify.dev/docs/apps/build/authentication-authorization/session-tokens), [webhooks](https://shopify.dev/docs/apps/build/webhooks/subscribe/https), [API versions](https://shopify.dev/docs/api/usage/versioning).

Shopify reviews the deployed app and may update or enforce requirements beyond this checklist. Read the official App Store requirements and best practices again immediately before submission.

## In this codebase

- [x] Embedded Admin UI loads Shopify App Bridge and sends a fresh ID/session token with app API requests.
- [x] Server verifies session-token HS256 signature, issuer/shop, destination, audience, and time claims. No localStorage or third-party-cookie auth.
- [x] Uses Shopify Admin GraphQL API, pinned to `2026-07` (then-current latest stable on Sep 29, 2026).
- [x] Asks only for product read/write scopes needed for current features.
- [x] Uses expiring offline tokens, active-session token exchange, and serialized refresh-token rotation for queued background work.
- [x] HTTPS webhook HMAC validation, app-uninstalled cleanup, and required customer/shop privacy webhook endpoints.
- [x] Queued scheduled changes and rollback, with product snapshots persisted before write attempts.
- [x] CSP `frame-ancestors`, secure cookie defaults, access-token encryption at rest, input validation, and per-shop campaign ownership checks.
- [x] Customer data is not requested or retained by the app; completed/cancelled campaign data and event logs are automatically purged after 90 days.

## Must be completed by the app owner before submission

- [ ] Replace app ID, domain, client ID and secret; configure public App Store distribution and HTTPS/TLS in Shopify Dev Dashboard.
- [ ] Make sure the production app is **actually embedded** and test OAuth install/reinstall in Shopify Admin and Chrome incognito. Validate app install path, redirects, callbacks, cookie restrictions, and every page/API route.
- [ ] Set up the production MySQL database, durable database backups, queue worker(s), one scheduler, shared database/Redis locks, monitoring, alerting, and failed-job operations. Test worker restarts and API throttling/network failures with real products.
- [ ] Run `composer install`, commit the generated lockfile, `composer audit`, PHP static analysis and formatting, full automated tests, security review, and Shopify API health/deprecation checks. This workspace lacked PHP/Composer, so those were **not run here**.
- [ ] Verify exact GraphQL schema and mutations against the deployed `2026-07` API and a development store. Test real Shopify currency precision, variants, HTML normalization, large catalog behavior, partial writes, and restore conflicts.
- [ ] Decide whether app is free-to-install. If paid, implement and test Shopify App Billing APIs before publishing prices; no alternate in-app app-charge flow is included.
- [ ] Replace legal policy templates with company-specific public HTTPS privacy policy and terms; enter valid support email, support URL, and contact details. State actual snapshot retention, hosting regions, subprocessors, deletion, security, and legal entity.
- [ ] Configure and deliver test webhooks in Shopify Dev Dashboard; test HMAC, retries, uninstallation deletion, `customers/data_request`, `customers/redact`, and `shop/redact`.
- [ ] Create the listing: accurate functionality/limitations, suitable category/tags, pricing, screenshots with alt text, setup instructions, support/contact, and a clear English review screencast plus test credentials.
- [ ] Test complete reviewer journeys without manual developer intervention. Ensure no 404/500/errors, dead-end states, unfinished placeholders, fake performance claims, or features described in the listing that are absent.
- [ ] Keep quarterly Admin API upgrades on a maintenance plan; select the latest stable release, not a release candidate.

## Required scope/features limitation disclosure

Current delivered implementation supports individual product selection, percentage price reductions, adding one tag, prepending text to product descriptions, scheduling, snapshot/audit views, cancellation before start, preflight retry, and emergency rollback. It does not yet support collection selection, metafields, product status changes, campaign editing, paid subscriptions, or a merchant settings screen. Keep those out of App Store screenshots/claims until implemented.
