# Shopify App Store listing draft — SaleSnap

**Status: DRAFT.** Confirm the final name in the Shopify Dev Dashboard, clear it with your business/legal advisor, and replace every placeholder before submission. Shopify's naming guidance calls for a distinctive, brand-led name of 30 characters or fewer; a quick web search isn't a trademark or final name-availability check. [Shopify naming and listing guidance](https://shopify.dev/docs/apps/launch/shopify-app-store/best-practices)

## App identity

- **Proposed app name:** SaleSnap (8 characters; final availability and trademark checks still required)
- **App card subtitle:** Schedule product sales with snapshot and restore checks.
- **Short introduction:** Schedule selected-product sales with snapshots and conflict-aware restore reports.
- **Brand note:** Keep `SaleSnap` identical in the Dev Dashboard, `shopify.app.toml`, website, and App Store listing. Do not put “Shopify” first in the app name or use Shopify's logo in the icon/screenshots.

## App details (425 / 500 characters)

> SaleSnap schedules percentage price discounts, product tags, and description announcements for selected Shopify products. Before changes begin, it saves a snapshot of the original product and variant values. At the scheduled end—or when a merchant requests an emergency restore—it compares current values, restores eligible fields, and reports conflicts for review. Manage campaigns and snapshot history inside Shopify Admin.

This copy describes only the current implementation: individual product selection, percentage discounts, one added tag, a description prefix, schedules, snapshots, and rollback reporting. It does **not** claim collection selection, metafields, product status changes, paid plans, or guaranteed/instant restoration.

## Feature list

1. Schedule percentage discounts on selected products
2. Add a campaign tag and description announcement
3. Save a product snapshot before edits begin
4. Review restore conflicts and request emergency rollback

## Category and search suggestions

- **Category:** Choose the current Dev Dashboard category that best matches pricing / sales and promotions or product management. Verify against the available taxonomy; don't select a marketing category only to gain reach.
- **Structured features / tags:** Use only available accurate options related to product price changes, scheduled campaigns, product tags, and snapshot/restore history.
- **Search terms (up to five, if shown):** sale scheduler; scheduled product sales; product price changes; product tags; restore conflicts.

## Pricing / distribution

- **Current code status:** Free-to-install; Shopify Billing and paid plans are not implemented.
- **Suggested listing setting for this release:** Free to install / no app charge, if that matches your business decision.
- If charging later, implement and test Shopify Billing before adding paid plans to the listing. Don't promise features that aren't built.

## Support and legal fields — owner must fill

- **Support email:** `[MONITORED SUPPORT EMAIL]` — `SHOPIFY_SUPPORT_EMAIL`
- **Support URL:** `https://[YOUR-DOMAIN]/support`
- **Privacy policy URL:** `https://[YOUR-DOMAIN]/privacy` — current route is an explicit placeholder, not submission-ready
- **Terms URL:** `https://[YOUR-DOMAIN]/terms` — current route is an explicit placeholder, not submission-ready
- **App homepage:** `https://[YOUR-DOMAIN]/`
- **App listing / install redirect:** `https://apps.shopify.com/[APP-HANDLE]` — set as `SHOPIFY_APP_STORE_URL` only after the listing exists
- **Legal entity, business address, data retention, hosting region, and subprocessors:** `[ADD ACCURATE OPERATOR DETAILS]`
- **API contact email:** `[DEVELOPER CONTACT EMAIL]`; Shopify's submission guidance says the API contact email shouldn't contain the word “Shopify.”

## Image files and alt text

The icon is 1200×1200 PNG, square, text-free, and doesn't use Shopify branding. Four desktop screen compositions are 1600×900 PNGs and show different app states; one responsive-layout composition is 900×1600. Current Shopify guidance calls for a 1200×1200 icon and 3–6 unique 1600×900 desktop screenshots with alt text, and says to include mobile/POS experiences when relevant. Confirm the upload fields and mobile image dimensions in the Partner Dashboard before submission. See the [App Store best practices](https://shopify.dev/docs/apps/launch/shopify-app-store/best-practices) and [App Store requirements](https://shopify.dev/docs/apps/launch/shopify-app-store/app-store-requirements).

1. `assets/app-store/salesnap-app-icon-1200.png` — **Alt:** “SaleSnap app icon: a saved product sheet with a rollback arrow and verified check.”
2. `assets/app-store/screenshots/01-overview-dashboard-1600x900.png` — **Alt:** “SaleSnap dashboard with demo campaigns, schedule statuses, and snapshot status summary.”
3. `assets/app-store/screenshots/02-create-campaign-1600x900.png` — **Alt:** “Campaign setup showing product selection, a percentage discount, tag, description text, and start and end schedule.”
4. `assets/app-store/screenshots/03-snapshot-history-1600x900.png` — **Alt:** “Snapshot history table showing restored, pending, and conflict-review states for demo products.”
5. `assets/app-store/screenshots/04-emergency-restore-1600x900.png` — **Alt:** “Emergency restore confirmation for a demo campaign, including a verified snapshot and conflict warning.”
6. `assets/app-store/screenshots/05-mobile-overview-900x1600.png` — **Alt:** “Mobile SaleSnap dashboard showing campaign schedules, product snapshot status, and the responsive navigation menu button.”

### Screenshot submission warning

The four desktop images and one portrait mobile image are **source-matched visual mockups rendered from the current UI design and synthetic demo data**, not screen captures from a running, tested Laravel installation. Use them for review of visual direction only. Shopify asks for focused images of the actual functioning app UI; after PHP/Composer setup, real Shopify development-store testing, and final UI changes, capture fresh screenshots from the deployed app, remove all merchant PII, confirm every control/label matches the final product, verify portrait-image dimensions in the Dashboard, then replace these mockups before submitting.

## Reviewer setup notes to prepare

Provide a working development-store reviewer path and screencast that shows: install/authenticate → search and select products → create a scheduled campaign → confirm snapshot before writes → observe live change → request emergency restore → review any conflicts. Use demo products and non-sensitive data. Ensure the published reviewer URL, test credentials, and support contact are ready before submission.
