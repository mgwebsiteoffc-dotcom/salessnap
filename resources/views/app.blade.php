<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="shopify-api-key" content="{{ $apiKey }}">
<meta name="shop-domain" content="{{ $shop }}">
<title>SaleSnap</title>
<script src="https://cdn.shopify.com/shopifycloud/app-bridge.js"></script>
<link rel="stylesheet" href="/app.css">

<style>
:root {
  --p-bg: #f6f6f7;
  --p-surface: #ffffff;
  --p-surface-subdued: #fafbfb;
  --p-surface-hover: #f1f2f3;
  --p-text: #202223;
  --p-text-subdued: #6d7175;
  --p-border: #e1e3e5;
  --p-border-subdued: #ebebeb;
  --p-primary: #303030;
  --p-primary-hover: #1a1a1a;
  --p-green: #008060;
  --p-green-bg: #f1f8f5;
  --p-green-text: #0e5b38;
  --p-green-border: #cce8dc;
  --p-yellow-bg: #fff8e5;
  --p-yellow-text: #704800;
  --p-yellow-border: #ffea8a;
  --p-red: #d72c0d;
  --p-red-bg: #fff4f2;
  --p-red-text: #8a2116;
  --p-red-border: #fec8c0;
  --p-shadow: 0 1px 3px rgba(0, 0, 0, 0.05), 0 0 0 1px rgba(0, 0, 0, 0.05);
  --p-radius: 8px;
}

* { box-sizing: border-box; }
img {
  max-width: 100%;
  height: auto;
}
body {
  margin: 0;
  padding: 0;
  background-color: var(--p-bg);
  color: var(--p-text);
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
  font-size: 13px;
  line-height: 1.5;
  -webkit-font-smoothing: antialiased;
}

.polaris-layout {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

.polaris-page {
  max-width: 1040px;
  width: 100%;
  margin: 0 auto;
  padding: 24px 20px 48px;
}

/* Page Header */
.polaris-page-header {
  margin-bottom: 20px;
}
.polaris-breadcrumbs {
  font-size: 12px;
  color: var(--p-text-subdued);
  margin-bottom: 6px;
}
.polaris-title-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
}
.polaris-title {
  font-size: 22px;
  font-weight: 700;
  color: var(--p-text);
  margin: 0;
  letter-spacing: -0.01em;
}
.polaris-subtitle {
  font-size: 13px;
  color: var(--p-text-subdued);
  margin: 4px 0 0;
}

/* Tabs */
.polaris-tabs-bar {
  display: flex;
  gap: 8px;
  border-bottom: 1px solid var(--p-border);
  margin-bottom: 20px;
  overflow-x: auto;
}
.polaris-tab-item {
  background: none;
  border: none;
  border-bottom: 2px solid transparent;
  padding: 10px 14px;
  font-size: 13px;
  font-weight: 500;
  color: var(--p-text-subdued);
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.15s ease;
}
.polaris-tab-item:hover {
  color: var(--p-text);
}
.polaris-tab-item.active {
  color: #000;
  font-weight: 600;
  border-bottom-color: var(--p-primary);
}

/* Buttons */
.polaris-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  background: #ffffff;
  color: var(--p-text);
  border: 1px solid #c9cccf;
  border-radius: var(--p-radius);
  padding: 8px 16px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  box-shadow: 0 1px 0 rgba(0, 0, 0, 0.05);
  transition: background 0.1s ease, border-color 0.1s ease;
  text-decoration: none;
}
.polaris-btn:hover { background: #f6f6f7; border-color: #babec3; }
.polaris-btn:active { background: #edeeef; }
.polaris-btn[disabled] { opacity: 0.5; cursor: not-allowed; }

.polaris-btn-primary {
  background: var(--p-primary);
  color: #ffffff;
  border-color: var(--p-primary);
  box-shadow: 0 1px 0 rgba(0, 0, 0, 0.15);
}
.polaris-btn-primary:hover {
  background: var(--p-primary-hover);
  border-color: var(--p-primary-hover);
  color: #fff;
}

.polaris-btn-destructive {
  background: var(--p-red);
  color: #ffffff;
  border-color: var(--p-red);
}
.polaris-btn-destructive:hover {
  background: #ba250b;
  border-color: #ba250b;
  color: #fff;
}

.polaris-btn-plain {
  border: none;
  background: transparent;
  color: #005bd3;
  box-shadow: none;
  padding: 4px 8px;
  font-weight: 500;
}
.polaris-btn-plain:hover {
  text-decoration: underline;
  background: transparent;
}

/* Cards */
.polaris-card {
  background: var(--p-surface);
  border: 1px solid var(--p-border);
  border-radius: var(--p-radius);
  box-shadow: var(--p-shadow);
  margin-bottom: 16px;
}
.polaris-card-header {
  padding: 16px 20px;
  border-bottom: 1px solid var(--p-border-subdued);
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.polaris-heading {
  font-size: 15px;
  font-weight: 600;
  margin: 0;
  color: var(--p-text);
}
.polaris-text-subdued {
  font-size: 12px;
  color: var(--p-text-subdued);
  margin: 3px 0 0;
}
.polaris-card-body {
  padding: 18px 20px;
}

/* Metric Cards */
.polaris-metric-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 14px;
  margin-bottom: 20px;
}
.polaris-metric-card {
  padding: 16px 18px;
}
.polaris-metric-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--p-text-subdued);
  display: block;
}
.polaris-metric-val {
  font-size: 26px;
  font-weight: 700;
  color: var(--p-text);
  margin: 6px 0 2px;
  display: block;
}
.polaris-metric-sub {
  font-size: 11px;
  color: var(--p-text-subdued);
}

/* Filter Pills */
.polaris-filter-pills {
  display: flex;
  gap: 8px;
  padding: 12px 20px;
  border-bottom: 1px solid var(--p-border-subdued);
}
.polaris-pill-filter {
  background: var(--p-surface-hover);
  border: 1px solid transparent;
  color: var(--p-text-subdued);
  font-size: 12px;
  font-weight: 500;
  padding: 5px 12px;
  border-radius: 16px;
  cursor: pointer;
}
.polaris-pill-filter:hover { background: #e4e5e7; }
.polaris-pill-filter.selected {
  background: var(--p-primary);
  color: #fff;
  font-weight: 600;
}

/* Campaign Rows */
.polaris-campaign-list {
  padding: 0;
}
.polaris-row {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr auto;
  align-items: center;
  gap: 16px;
  padding: 14px 20px;
  border-bottom: 1px solid var(--p-border-subdued);
}
.polaris-row:last-child {
  border-bottom: none;
}
.polaris-row-title {
  font-weight: 600;
  font-size: 13px;
  color: var(--p-text);
}
.polaris-row-meta {
  font-size: 11px;
  color: var(--p-text-subdued);
  margin-top: 2px;
}

/* Badges */
.polaris-badge {
  display: inline-flex;
  align-items: center;
  padding: 3px 8px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 600;
  line-height: 1;
}
.polaris-badge-success { background: var(--p-green-bg); color: var(--p-green-text); }
.polaris-badge-warning { background: var(--p-yellow-bg); color: var(--p-yellow-text); }
.polaris-badge-attention { background: var(--p-red-bg); color: var(--p-red-text); }
.polaris-badge-neutral { background: var(--p-surface-hover); color: var(--p-text-subdued); }

/* Banners */
.polaris-banner {
  display: flex;
  gap: 12px;
  align-items: flex-start;
  padding: 14px 16px;
  border-radius: var(--p-radius);
  margin-bottom: 16px;
  font-size: 12px;
}
.polaris-banner-icon {
  font-size: 16px;
  font-weight: bold;
}
.polaris-banner-info {
  background: var(--p-green-bg);
  border: 1px solid var(--p-green-border);
  color: var(--p-green-text);
}
.polaris-banner-warning {
  background: var(--p-yellow-bg);
  border: 1px solid var(--p-yellow-border);
  color: var(--p-yellow-text);
}
.polaris-banner-critical {
  background: var(--p-red-bg);
  border: 1px solid var(--p-red-border);
  color: var(--p-red-text);
}

/* Pricing Grid */
.polaris-pricing-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 20px;
  margin-top: 16px;
}
.polaris-plan-card {
  border: 1px solid var(--p-border);
  border-radius: var(--p-radius);
  padding: 24px;
  background: #fff;
  display: flex;
  flex-direction: column;
}
.polaris-plan-card.featured {
  border-color: var(--p-green);
  box-shadow: 0 0 0 1px var(--p-green), 0 4px 12px rgba(0, 128, 96, 0.12);
}
.polaris-plan-title {
  font-size: 17px;
  font-weight: 700;
  margin: 0 0 6px;
}
.polaris-plan-price {
  font-size: 26px;
  font-weight: 800;
  color: var(--p-text);
  margin: 10px 0;
}
.polaris-plan-price small {
  font-size: 13px;
  font-weight: 500;
  color: var(--p-text-subdued);
}
.polaris-plan-features {
  list-style: none;
  padding: 0;
  margin: 18px 0 24px;
  flex: 1;
}
.polaris-plan-features li {
  padding: 6px 0;
  font-size: 12px;
  color: #444a4e;
  display: flex;
  gap: 8px;
}
.polaris-plan-features li b { color: var(--p-green); }

/* Modals */
.polaris-dialog {
  border: none;
  padding: 0;
  border-radius: 12px;
  width: min(680px, calc(100% - 32px));
  max-height: 90vh;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
}
.polaris-dialog::backdrop {
  background: rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(2px);
}
.polaris-dialog-compact {
  width: min(480px, calc(100% - 32px));
}
.polaris-dialog form {
  display: flex;
  flex-direction: column;
  max-height: 90vh;
}
.polaris-dialog-header {
  padding: 16px 20px;
  border-bottom: 1px solid var(--p-border);
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.polaris-close-btn {
  background: none;
  border: none;
  font-size: 24px;
  line-height: 1;
  color: var(--p-text-subdued);
  cursor: pointer;
}
.polaris-dialog-body {
  padding: 20px;
  overflow-y: auto;
}
.polaris-dialog-footer {
  padding: 14px 20px;
  border-top: 1px solid var(--p-border);
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

/* Forms */
.polaris-form-group {
  margin-bottom: 18px;
}
.polaris-label {
  display: block;
  font-weight: 600;
  font-size: 12px;
  margin-bottom: 6px;
}
.polaris-sublabel {
  display: block;
  font-size: 11px;
  font-weight: 500;
  color: var(--p-text-subdued);
  margin-bottom: 4px;
}
.polaris-input {
  width: 100%;
  border: 1px solid #8c9196;
  border-radius: var(--p-radius);
  padding: 8px 12px;
  font-size: 13px;
  background: #ffffff;
  color: var(--p-text);
  outline: none;
}
.polaris-input:focus {
  border-color: #005bd3;
  box-shadow: 0 0 0 2px rgba(0, 91, 211, 0.2);
}
.polaris-input-inline {
  width: 80px !important;
}

/* Segmented Control */
.polaris-segmented-control {
  display: inline-flex;
  background: var(--p-surface-hover);
  border-radius: 6px;
  padding: 3px;
  margin-bottom: 10px;
  width: 100%;
}
.polaris-segment-btn {
  flex: 1;
  background: none;
  border: none;
  padding: 6px 12px;
  font-size: 12px;
  font-weight: 500;
  color: var(--p-text-subdued);
  border-radius: 4px;
  cursor: pointer;
}
.polaris-segment-btn.active {
  background: #ffffff;
  color: var(--p-text);
  font-weight: 600;
  box-shadow: 0 1px 2px rgba(0,0,0,0.08);
}

.polaris-search-box {
  display: flex;
  gap: 8px;
  margin-bottom: 10px;
}
.polaris-product-results {
  border: 1px solid var(--p-border);
  border-radius: 6px;
  max-height: 180px;
  overflow-y: auto;
  background: #ffffff;
}
.polaris-empty-picker {
  padding: 24px;
  text-align: center;
  color: var(--p-text-subdued);
  font-size: 12px;
}
.polaris-product-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 12px;
  border-bottom: 1px solid var(--p-border-subdued);
}
.polaris-product-item:last-child {
  border-bottom: none;
}
.polaris-product-thumb {
  width: 36px;
  height: 36px;
  min-width: 36px;
  max-width: 36px;
  max-height: 36px;
  border-radius: 4px;
  object-fit: cover;
  flex-shrink: 0;
  background: #eee;
}
.polaris-product-item img {
  width: 36px;
  height: 36px;
  min-width: 36px;
  max-width: 36px;
  max-height: 36px;
  border-radius: 4px;
  object-fit: cover;
  flex-shrink: 0;
}
.polaris-chips-container {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin: 8px 0;
  max-height: 180px;
  overflow-y: auto;
  padding: 2px;
}
.polaris-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: #f1f2f3;
  border: 1px solid #e1e3e5;
  border-radius: 16px;
  padding: 4px 10px 4px 6px;
  font-size: 12px;
  font-weight: 500;
}
.polaris-chip img {
  width: 20px;
  height: 20px;
  min-width: 20px;
  max-width: 20px;
  max-height: 20px;
  border-radius: 4px;
  object-fit: cover;
  flex-shrink: 0;
}
.polaris-chip-remove {
  background: none;
  border: none;
  color: #6d7175;
  cursor: pointer;
  font-size: 14px;
  line-height: 1;
  padding: 0 0 0 4px;
}
.polaris-chip-remove:hover {
  color: #d72c0d;
}
.polaris-selection-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 8px;
  font-size: 12px;
}
.polaris-selected-text {
  font-weight: 600;
  color: var(--p-green);
}

/* Action Cards */
.polaris-action-card {
  border: 1px solid var(--p-border);
  border-radius: 6px;
  padding: 10px 14px;
  margin-bottom: 8px;
  display: flex;
  align-items: center;
  gap: 12px;
  background: #fafafa;
}
.polaris-checkbox-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}
.polaris-checkbox-label input {
  accent-color: var(--p-green);
  cursor: pointer;
}
.polaris-inline-field {
  display: flex;
  align-items: center;
  gap: 6px;
}
.polaris-grid-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

/* Table */
.polaris-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 12px;
}
.polaris-table th {
  background: #fafafa;
  color: var(--p-text-subdued);
  text-align: left;
  padding: 10px 16px;
  font-size: 11px;
  font-weight: 600;
  border-bottom: 1px solid var(--p-border);
}
.polaris-table td {
  padding: 12px 16px;
  border-bottom: 1px solid var(--p-border-subdued);
  vertical-align: middle;
}
.polaris-empty-state {
  padding: 40px 20px;
  text-align: center;
  color: var(--p-text-subdued);
  font-size: 13px;
}

/* Toast */
.polaris-toast {
  position: fixed;
  bottom: 24px;
  left: 50%;
  transform: translateX(-50%);
  background: #303030;
  color: #ffffff;
  padding: 10px 18px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 500;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
  z-index: 9999;
  display: none;
}
.polaris-toast.show {
  display: block;
}

.polaris-dialog-large {
  max-width: 960px;
  width: 94vw;
  max-height: 92vh;
}
.polaris-details-ribbon {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 12px;
  background: #f6f6f7;
  border: 1px solid #e1e3e5;
  border-radius: 8px;
  padding: 14px 16px;
  margin-bottom: 16px;
}
.polaris-details-ribbon-item {
  font-size: 12px;
}
.polaris-details-ribbon-label {
  color: #6d7175;
  font-size: 11px;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 2px;
}
.polaris-details-ribbon-value {
  font-size: 14px;
  font-weight: 700;
  color: #202223;
}
.polaris-details-actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 10px;
  padding: 12px 0;
  border-top: 1px solid #ebebeb;
  border-bottom: 1px solid #ebebeb;
  margin-bottom: 16px;
}
.polaris-theme-card {
  background: #ffffff;
  border: 1px solid #e1e3e5;
  border-radius: 8px;
  padding: 16px;
  margin-bottom: 12px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 14px;
  transition: box-shadow 0.15s ease, border-color 0.15s ease;
}
.polaris-theme-card:hover {
  border-color: #babfc3;
  box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}
.polaris-theme-card.main-theme {
  border-left: 4px solid #008060;
  background: #fafcfb;
}
.polaris-theme-card.promo-theme {
  border-left: 4px solid #f59e0b;
}
.polaris-color-input-wrapper {
  display: flex;
  align-items: center;
  gap: 8px;
}
.polaris-color-picker {
  width: 36px;
  height: 36px;
  padding: 2px;
  border: 1px solid #d2d5d8;
  border-radius: 4px;
  cursor: pointer;
  background: none;
}
.polaris-countdown-preview-box {
  border: 1px solid #e1e3e5;
  border-radius: 8px;
  overflow: hidden;
  margin: 14px 0;
  background: #ffffff;
  box-shadow: 0 2px 6px rgba(0,0,0,0.06);
}
.polaris-settings-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}
@media (max-width: 768px) {
  .polaris-settings-grid { grid-template-columns: 1fr; }
  .polaris-dialog-large { width: 98vw; }
}

.polaris-footer {
  text-align: center;
  font-size: 11px;
  color: var(--p-text-subdued);
  margin-top: 32px;
}

.hidden { display: none !important; }
.required { color: var(--p-red); }

@media (max-width: 768px) {
  .polaris-metric-grid { grid-template-columns: repeat(2, 1fr); }
  .polaris-pricing-grid { grid-template-columns: 1fr; }
  .polaris-row { grid-template-columns: 1fr auto; }
  .polaris-grid-2 { grid-template-columns: 1fr; }
}
</style>
</head>
<body>
<!-- App Bridge Native Navigation Menu (Renders in Shopify Admin top bar) -->
<ui-nav-menu>
  <a href="/app" rel="home">Overview</a>
  <a href="/app?page=campaigns">Campaigns</a>
  <a href="/app?page=products">Products &amp; Collections</a>
  <a href="/app?page=bundles">Bundle Creator</a>
  <a href="/app?page=themes">Theme &amp; Countdown</a>
  <a href="/app?page=snapshots">Snapshots &amp; Restores</a>
  <a href="/app?page=activity">Activity Log</a>
  <a href="/app?page=billing">Billing &amp; Plans</a>
  <a href="/app?page=settings">Settings</a>
</ui-nav-menu>

<div class="polaris-layout">
  <!-- Polaris Page Container -->
  <main class="polaris-page">
    <div class="polaris-page-header">
      <div class="polaris-header-title-group">
        <div class="polaris-breadcrumbs"><span class="polaris-crumb">Apps</span> / <span>SaleSnap</span></div>
        <div class="polaris-title-row">
          <h1 id="page-title" class="polaris-title">SaleSnap</h1>
          <div class="polaris-header-actions">
            <button class="polaris-btn" onclick="window.pmOpenThemePublishModal()">⚡ Theme &amp; Countdown</button>
            <button class="polaris-btn" onclick="window.pmOpenBundleModal('combo')">🎁 Create Bundle</button>
            <button class="polaris-btn polaris-btn-primary" id="new-campaign">＋ Create campaign</button>
          </div>
        </div>
        <p id="page-desc" class="polaris-subtitle">Schedule flash-sale promotions with automatic pre-change snapshots and safe rollback.</p>
      </div>
    </div>

    <!-- Polaris In-App Navigation Tabs -->
    <div class="polaris-tabs-bar">
      <button class="polaris-tab-item active" data-page="overview">Overview</button>
      <button class="polaris-tab-item" data-page="campaigns">Campaigns</button>
      <button class="polaris-tab-item" data-page="products">Products &amp; Collections</button>
      <button class="polaris-tab-item" data-page="bundles">Bundle Creator</button>
      <button class="polaris-tab-item" data-page="themes">Theme &amp; Countdown</button>
      <button class="polaris-tab-item" data-page="snapshots">Snapshots &amp; Restores</button>
      <button class="polaris-tab-item" data-page="activity">Activity Log</button>
      <button class="polaris-tab-item" data-page="billing">Billing &amp; Plans</button>
      <button class="polaris-tab-item" data-page="settings">Settings</button>
    </div>

    <!-- Alerts Banner -->
    <div id="error-banner" class="polaris-banner polaris-banner-critical hidden"></div>
    <div id="info-banner" class="polaris-banner polaris-banner-info">
      <div class="polaris-banner-icon">✓</div>
      <div class="polaris-banner-content">
        <strong>Pre-change Snapshot Safeguards Active</strong>
        <p>SaleSnap captures exact product prices and tags before applying sale edits, allowing conflict-free rollback at any time.</p>
      </div>
    </div>

    <!-- Overview Page -->
    <section id="overview-page" class="polaris-page-section">
      <div class="polaris-metric-grid">
        <div class="polaris-card polaris-metric-card">
          <span class="polaris-metric-label">Live Campaigns</span>
          <strong class="polaris-metric-val" id="stat-live">—</strong>
          <span class="polaris-metric-sub">Active promotions</span>
        </div>
        <div class="polaris-card polaris-metric-card">
          <span class="polaris-metric-label">Scheduled</span>
          <strong class="polaris-metric-val" id="stat-scheduled">—</strong>
          <span class="polaris-metric-sub">Queued to start</span>
        </div>
        <div class="polaris-card polaris-metric-card">
          <span class="polaris-metric-label">Protected Products</span>
          <strong class="polaris-metric-val" id="stat-protected">—</strong>
          <span class="polaris-metric-sub">Saved original snapshots</span>
        </div>
        <div class="polaris-card polaris-metric-card">
          <span class="polaris-metric-label">Completed Restores</span>
          <strong class="polaris-metric-val" id="stat-rollbacks">—</strong>
          <span class="polaris-metric-sub">Safe rollbacks executed</span>
        </div>
      </div>

      <div class="polaris-card polaris-content-card">
        <div class="polaris-card-header">
          <div>
            <h2 class="polaris-heading">Recent Campaigns</h2>
            <p class="polaris-text-subdued">Monitor live sale schedules, snapshot integrity, and rollback statuses.</p>
          </div>
          <button class="polaris-btn polaris-btn-plain" data-go="campaigns">View all →</button>
        </div>

        <div class="polaris-filter-pills">
          <button class="polaris-pill-filter selected" data-filter="all">All</button>
          <button class="polaris-pill-filter" data-filter="running">Live</button>
          <button class="polaris-pill-filter" data-filter="scheduled">Scheduled</button>
          <button class="polaris-pill-filter" data-filter="completed">Completed</button>
        </div>

        <div id="campaign-list" class="polaris-campaign-list">
          <div class="polaris-empty-state">Loading your store campaigns…</div>
        </div>
      </div>
    </section>

    <!-- Detail View / Dynamic Tabs (Campaigns, Products, Snapshots, Activity, Billing, Settings) -->
    <section id="detail-page" class="polaris-page-section hidden">
      <div class="polaris-card polaris-content-card">
        <div class="polaris-card-header">
          <div>
            <h2 id="detail-title" class="polaris-heading">Page</h2>
            <p id="detail-copy" class="polaris-text-subdued"></p>
          </div>
        </div>
        <div id="detail-body" class="polaris-card-body"></div>
      </div>
    </section>

    <footer class="polaris-footer">
      <span>Connected Store: <strong>{{ $shop }}</strong></span> · 
      <span>Shopify Admin API 2026-07</span> · 
      <span>Safe Promotion &amp; Snapshot Manager</span>
    </footer>
  </main>
</div>

<!-- Create Campaign Dialog (Polaris Modal) -->
<dialog id="campaign-dialog" class="polaris-dialog">
  <form id="campaign-form" method="dialog">
    <div class="polaris-dialog-header">
      <div>
        <h2 class="polaris-heading">Create a Sale Promotion</h2>
        <p class="polaris-text-subdued">Choose products or collections to discount. We’ll capture full snapshots first.</p>
      </div>
      <button type="button" class="polaris-close-btn" data-close="campaign-dialog" aria-label="Close">×</button>
    </div>

    <div class="polaris-dialog-body">
      <div class="polaris-form-group">
        <label class="polaris-label" for="campaign-name">Promotion name <span class="required">*</span></label>
        <input class="polaris-input" id="campaign-name" name="name" maxlength="120" placeholder="e.g. Weekend Flash Sale" required value="Weekend Flash Sale">
      </div>

      <div class="polaris-form-group">
        <label class="polaris-label">Select Products <span class="required">*</span></label>
        
        <!-- Selection Mode Toggle: By Product or By Collection -->
        <div class="polaris-segmented-control">
          <button type="button" class="polaris-segment-btn active" id="mode-products">Search Products</button>
          <button type="button" class="polaris-segment-btn" id="mode-collections">Browse Collections</button>
        </div>

        <div id="product-search-container" class="polaris-search-box">
          <input class="polaris-input" id="product-search" placeholder="Search products by title..." autocomplete="off">
          <button type="button" class="polaris-btn" id="search-products">Search</button>
        </div>

        <div id="collection-search-container" class="polaris-search-box hidden">
          <select class="polaris-input" id="collection-select">
            <option value="">Loading store collections…</option>
          </select>
          <button type="button" class="polaris-btn" id="load-collection-products">Load Products</button>
        </div>

        <div id="product-results" class="polaris-product-results">
          <div class="polaris-empty-picker">Search your catalog or select a collection.</div>
        </div>

        <div class="polaris-selection-bar">
          <span id="selected-summary" class="polaris-selected-text">0 products selected</span>
          <button type="button" class="polaris-btn polaris-btn-plain" id="select-all-results">Select all shown</button>
        </div>
      </div>

      <div class="polaris-form-group">
        <label class="polaris-label">Changes to Apply</label>
        
        <div class="polaris-action-card">
          <label class="polaris-checkbox-label">
            <input type="checkbox" id="enable-price" checked>
            <span>Discount price by</span>
          </label>
          <div class="polaris-inline-field">
            <input class="polaris-input polaris-input-inline" type="number" id="price-percent" min="1" max="90" value="20">
            <span>% off</span>
          </div>
        </div>

        <div class="polaris-action-card">
          <label class="polaris-checkbox-label">
            <input type="checkbox" id="enable-tag" checked>
            <span>Add product tag</span>
          </label>
          <input class="polaris-input" id="product-tag" value="flash-sale" maxlength="80" placeholder="e.g. flash-sale">
        </div>

        <div class="polaris-action-card">
          <label class="polaris-checkbox-label">
            <input type="checkbox" id="enable-description">
            <span>Prepend announcement to description</span>
          </label>
          <input class="polaris-input" id="description-prefix" value="✦ Flash Sale Discount Applied!" maxlength="240" placeholder="Announcement banner">
        </div>
      </div>

      <div class="polaris-form-group">
        <label class="polaris-label">Schedule</label>
        <div class="polaris-grid-2">
          <div>
            <label class="polaris-sublabel" for="starts-at">Starts</label>
            <input class="polaris-input" type="datetime-local" id="starts-at" required>
          </div>
          <div>
            <label class="polaris-sublabel" for="ends-at">Ends / Automatic Rollback</label>
            <input class="polaris-input" type="datetime-local" id="ends-at" required>
          </div>
        </div>
        <div style="margin-top:8px">
          <label class="polaris-sublabel" for="timezone">Store Timezone</label>
          <select class="polaris-input" id="timezone"></select>
        </div>
      </div>

      <div id="form-error" class="polaris-banner polaris-banner-critical hidden"></div>
    </div>

    <div class="polaris-dialog-footer">
      <button type="button" class="polaris-btn" data-close="campaign-dialog">Cancel</button>
      <button type="submit" class="polaris-btn polaris-btn-primary" id="schedule-button">Review &amp; Schedule</button>
    </div>
  </form>
</dialog>

<!-- Emergency Restore Modal -->
<dialog id="restore-dialog" class="polaris-dialog polaris-dialog-compact">
  <div class="polaris-dialog-header">
    <h2 class="polaris-heading">Emergency Rollback</h2>
    <button type="button" class="polaris-close-btn" data-close="restore-dialog">×</button>
  </div>
  <div class="polaris-dialog-body">
    <div class="polaris-banner polaris-banner-warning">
      <strong id="restore-name">Campaign Rollback</strong>
      <p>Original snapshot values will be checked against current live product data. Any fields edited by staff during the campaign will be safely skipped to avoid overwriting.</p>
    </div>
    <label class="polaris-checkbox-label" style="margin-top:16px;">
      <input type="checkbox" id="confirm-restore">
      <span>I understand this ends the promotion and restores snapshot prices now.</span>
    </label>
    <div id="restore-error" class="polaris-banner polaris-banner-critical hidden" style="margin-top:12px;"></div>
  </div>
  <div class="polaris-dialog-footer">
    <button type="button" class="polaris-btn" data-close="restore-dialog">Cancel</button>
    <button type="button" class="polaris-btn polaris-btn-destructive" id="confirm-restore-button">Restore from Snapshot</button>
  </div>
</dialog>

<!-- Create Bundle Dialog (Polaris Modal) -->
<dialog id="bundle-dialog" class="polaris-dialog">
  <form id="bundle-form" method="dialog">
    <div class="polaris-dialog-header">
      <div>
        <h2 class="polaris-heading">Create Bundle Product</h2>
        <p class="polaris-text-subdued">Create packaged products and bulk multi-packs in Shopify.</p>
      </div>
      <button type="button" class="polaris-close-btn" data-close="bundle-dialog" aria-label="Close">×</button>
    </div>

    <div class="polaris-dialog-body">
      <!-- Mode Toggle -->
      <div class="polaris-segmented-control" style="margin-bottom:14px;">
        <button type="button" class="polaris-segment-btn active" id="bundle-dialog-mode-combo">Custom Combo Bundle</button>
        <button type="button" class="polaris-segment-btn" id="bundle-dialog-mode-multipack">Bulk Multi-Packs (2-Pack, 3-Pack)</button>
      </div>

      <!-- COMBO BUNDLE MODE -->
      <div id="bundle-mode-combo-view">
        <div class="polaris-form-group">
          <label class="polaris-label" for="bundle-title">Bundle Title <span class="required">*</span></label>
          <input class="polaris-input" id="bundle-title" placeholder="e.g. Summer Essentials Value Bundle" required>
        </div>

        <div class="polaris-form-group">
          <label class="polaris-label">Included Products in Bundle</label>
          <div id="bundle-selected-chips" class="polaris-chips-container"></div>
          
          <div class="polaris-search-box" style="margin-top:8px;">
            <input class="polaris-input" id="bundle-product-search" placeholder="Search catalog to add products to bundle...">
            <button type="button" class="polaris-btn" id="bundle-product-search-btn">Search</button>
          </div>
          <div id="bundle-picker-results" class="polaris-product-results" style="max-height:160px;margin-top:8px;">
            <div class="polaris-empty-picker">Search above to add more items to this bundle.</div>
          </div>
        </div>

        <!-- Live Pricing Calculator -->
        <div class="polaris-bundle-summary-box">
          <div class="polaris-bundle-summary-stat">
            <span>Combined Value:</span>
            <strong id="bundle-preview-orig">$0.00</strong>
          </div>
          <div class="polaris-bundle-summary-stat">
            <span>Bundle Price:</span>
            <strong id="bundle-preview-price">$0.00</strong>
          </div>
          <div class="polaris-bundle-summary-stat savings">
            <span>Customer Saves:</span>
            <strong id="bundle-preview-save">$0.00 (0% off)</strong>
          </div>
        </div>

        <div class="polaris-form-group">
          <label class="polaris-label" for="bundle-pricing-type">Bundle Pricing Strategy</label>
          <select class="polaris-input" id="bundle-pricing-type">
            <option value="percentage">Percentage Discount (% OFF combined price)</option>
            <option value="fixed_price">Fixed Bundle Price ($ set custom price)</option>
            <option value="fixed_discount">Fixed Dollar Discount ($ OFF combined price)</option>
          </select>
        </div>

        <div id="bundle-pricing-pct-box" class="polaris-form-group">
          <label class="polaris-sublabel" for="bundle-discount-pct">Discount Percentage (%)</label>
          <div class="polaris-inline-field">
            <input class="polaris-input polaris-input-inline" type="number" id="bundle-discount-pct" min="0" max="90" value="15">
            <span>% OFF individual price</span>
          </div>
        </div>

        <div id="bundle-pricing-fixed-price-box" class="polaris-form-group hidden">
          <label class="polaris-sublabel" for="bundle-fixed-price">Set Exact Bundle Price ($)</label>
          <input class="polaris-input" type="number" step="0.01" min="0.01" id="bundle-fixed-price" placeholder="e.g. 49.99">
        </div>

        <div id="bundle-pricing-fixed-discount-box" class="polaris-form-group hidden">
          <label class="polaris-sublabel" for="bundle-fixed-discount">Discount Amount ($)</label>
          <input class="polaris-input" type="number" step="0.01" min="0.01" id="bundle-fixed-discount" placeholder="e.g. 15.00">
        </div>

        <div class="polaris-grid-2">
          <div>
            <label class="polaris-sublabel" for="bundle-status">Product Status</label>
            <select class="polaris-input" id="bundle-status">
              <option value="ACTIVE">Active (Publish to Store immediately)</option>
              <option value="DRAFT">Draft (Save as Draft in Shopify)</option>
            </select>
          </div>
          <div>
            <label class="polaris-sublabel" for="bundle-tags">Tags</label>
            <input class="polaris-input" id="bundle-tags" value="bundle, salessnap-bundle" placeholder="bundle, featured">
          </div>
        </div>

        <div class="polaris-form-group" style="margin-top:10px;">
          <label class="polaris-sublabel" for="bundle-custom-desc">Optional Marketing Note (Included in Product Description)</label>
          <input class="polaris-input" id="bundle-custom-desc" placeholder="e.g. Limited time combo pack. Perfect as a gift set!">
        </div>
      </div>

      <!-- BULK MULTIPACK MODE -->
      <div id="bundle-mode-multipack-view" class="hidden">
        <div class="polaris-banner polaris-banner-info">
          <div class="polaris-banner-icon">⚡</div>
          <div class="polaris-banner-content">
            <strong>Bulk Multi-Pack Generator</strong>
            <p>Generate 2-Pack, 3-Pack, or Family Packs for all selected products in bulk with discounted bundle pricing and compare-at rates.</p>
          </div>
        </div>

        <div class="polaris-form-group">
          <label class="polaris-label">Selected Products for Multi-Pack</label>
          <div id="bundle-multipack-chips" class="polaris-chips-container"></div>
          <div class="polaris-search-box" style="margin-top:8px;">
            <input class="polaris-input" id="multipack-product-search" placeholder="Search catalog to add items...">
            <button type="button" class="polaris-btn" id="multipack-product-search-btn">Search</button>
          </div>
          <div id="multipack-picker-results" class="polaris-product-results" style="max-height:160px;margin-top:8px;">
            <div class="polaris-empty-picker">Search above to add more products to multi-pack.</div>
          </div>
        </div>

        <div class="polaris-grid-2">
          <div>
            <label class="polaris-label" for="multipack-size">Pack Quantity</label>
            <select class="polaris-input" id="multipack-size">
              <option value="2">2-Pack (Duo Bundle)</option>
              <option value="3" selected>3-Pack (Trio Bundle)</option>
              <option value="4">4-Pack (Value Pack)</option>
              <option value="5">5-Pack (Bulk Saver)</option>
            </select>
          </div>
          <div>
            <label class="polaris-label" for="multipack-discount-pct">Discount (%)</label>
            <input class="polaris-input" type="number" id="multipack-discount-pct" min="1" max="90" value="15">
          </div>
        </div>

        <div class="polaris-grid-2" style="margin-top:10px;">
          <div>
            <label class="polaris-sublabel" for="multipack-status">Product Status</label>
            <select class="polaris-input" id="multipack-status">
              <option value="ACTIVE">Active (Publish immediately)</option>
              <option value="DRAFT">Draft (Review in Shopify Admin first)</option>
            </select>
          </div>
          <div>
            <label class="polaris-sublabel" for="multipack-tag">Custom Tag</label>
            <input class="polaris-input" id="multipack-tag" value="multipack" placeholder="e.g. multipack">
          </div>
        </div>
      </div>

      <div id="bundle-error" class="polaris-banner polaris-banner-critical hidden" style="margin-top:12px;"></div>
    </div>

    <div class="polaris-dialog-footer">
      <button type="button" class="polaris-btn" data-close="bundle-dialog">Cancel</button>
      <button type="submit" class="polaris-btn polaris-btn-primary" id="create-bundle-submit-btn">Create Bundle in Shopify</button>
    </div>
  </form>
</dialog>

<!-- Campaign Details Deep-View Modal -->
<dialog id="campaign-details-dialog" class="polaris-dialog polaris-dialog-large">
  <div class="polaris-dialog-header">
    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
      <h2 id="cd-title" class="polaris-heading" style="margin:0;">Campaign Details</h2>
      <span id="cd-status-badge"></span>
    </div>
    <button type="button" class="polaris-close-btn" data-close="campaign-details-dialog" aria-label="Close">×</button>
  </div>

  <div class="polaris-dialog-body" style="max-height:72vh;overflow-y:auto;">
    <!-- Ribbon summary cards -->
    <div class="polaris-details-ribbon">
      <div class="polaris-details-ribbon-item">
        <div class="polaris-details-ribbon-label">Discount Rate</div>
        <div class="polaris-details-ribbon-value" id="cd-discount-val">—</div>
      </div>
      <div class="polaris-details-ribbon-item">
        <div class="polaris-details-ribbon-label">Schedule Window</div>
        <div class="polaris-details-ribbon-value" id="cd-schedule-val" style="font-size:12px;font-weight:600;">—</div>
      </div>
      <div class="polaris-details-ribbon-item">
        <div class="polaris-details-ribbon-label">Protected Products</div>
        <div class="polaris-details-ribbon-value" id="cd-products-count">—</div>
      </div>
      <div class="polaris-details-ribbon-item">
        <div class="polaris-details-ribbon-label">Snapshot Safety</div>
        <div class="polaris-details-ribbon-value" id="cd-snapshot-status" style="color:#0e5b38;">100% Protected</div>
      </div>
    </div>

    <!-- Real-time Action buttons toolbar -->
    <div class="polaris-details-actions" id="cd-actions-bar"></div>

    <!-- Section Header: Included Products -->
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;flex-wrap:wrap;gap:8px;">
      <div>
        <h3 class="polaris-subheading" style="margin:0;font-size:13px;font-weight:700;">Included Store Products (<span id="cd-prod-count-inline">0</span>)</h3>
        <p class="polaris-text-subdued" style="margin:2px 0 0;font-size:11px;">Original base prices vs promotional sale prices. Click any item to view in Shopify Admin.</p>
      </div>
      <input class="polaris-input" id="cd-product-filter" placeholder="Filter items in campaign..." style="max-width:220px;font-size:12px;padding:4px 8px;">
    </div>

    <!-- Product items list container -->
    <div id="cd-products-container" style="border:1px solid #e1e3e5;border-radius:8px;overflow:hidden;margin-bottom:16px;">
      <div class="polaris-empty-picker">Loading products in this campaign…</div>
    </div>

    <!-- Snapshot & Audit History Section -->
    <details style="border:1px solid #e1e3e5;border-radius:8px;padding:10px 14px;background:#fafbfb;">
      <summary style="font-weight:600;font-size:12px;cursor:pointer;color:#202223;">🔍 View Snapshot Hashes &amp; Integrity Logs</summary>
      <div id="cd-snapshots-logs-container" style="margin-top:10px;font-size:11px;">
        <div class="polaris-empty-picker">No conflicts detected. Original data verified.</div>
      </div>
    </details>
  </div>

  <div class="polaris-dialog-footer">
    <button type="button" class="polaris-btn" data-close="campaign-details-dialog">Close</button>
  </div>
</dialog>

<!-- Theme Copy & Countdown Bar Publishing Modal -->
<dialog id="theme-publish-dialog" class="polaris-dialog polaris-dialog-large">
  <form id="theme-publish-form" method="dialog">
    <div class="polaris-dialog-header">
      <div>
        <h2 class="polaris-heading">Publish Theme Copy with Countdown</h2>
        <p class="polaris-text-subdued">Duplicate a store theme, inject a live ticking countdown announcement bar, and publish it safely.</p>
      </div>
      <button type="button" class="polaris-close-btn" data-close="theme-publish-dialog" aria-label="Close">×</button>
    </div>

    <div class="polaris-dialog-body" style="max-height:72vh;overflow-y:auto;">
      <div class="polaris-grid-2">
        <div class="polaris-form-group">
          <label class="polaris-label" for="theme-source-select">Source Theme to Duplicate <span class="required">*</span></label>
          <select class="polaris-input" id="theme-source-select">
            <option value="">Loading store themes…</option>
          </select>
        </div>
        <div class="polaris-form-group">
          <label class="polaris-label" for="theme-copy-name">New Theme Copy Name <span class="required">*</span></label>
          <input class="polaris-input" id="theme-copy-name" value="[SaleSnap Promo] Dawn with Countdown" required>
        </div>
      </div>

      <!-- Live Interactive Countdown Preview -->
      <label class="polaris-label" style="margin-top:8px;">Live Countdown Announcement Bar Preview</label>
      <div class="polaris-countdown-preview-box">
        <div id="countdown-banner-live-preview" style="background:#111827;color:#ffffff;padding:10px 16px;border-bottom:2px solid #f59e0b;">
          <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;font-family:sans-serif;">
            <div style="display:flex;align-items:center;gap:10px;">
              <span style="font-size:20px;">⚡</span>
              <div>
                <strong id="prev-headline" style="font-size:13px;display:block;">⚡ FLASH SALE IS LIVE! Extra 20% Off Selected Items</strong>
                <span id="prev-subtext" style="font-size:11px;opacity:0.85;">Limited time store promotion. Discounts auto-applied in cart.</span>
              </div>
            </div>
            <div style="display:flex;align-items:center;gap:6px;">
              <div style="background:rgba(255,255,255,0.15);border-radius:4px;padding:3px 6px;text-align:center;min-width:36px;">
                <span id="prev-d" style="color:#f59e0b;font-weight:800;font-size:14px;display:block;">01</span>
                <span style="font-size:8px;text-transform:uppercase;">Days</span>
              </div>
              <span style="color:#f59e0b;font-weight:700;">:</span>
              <div style="background:rgba(255,255,255,0.15);border-radius:4px;padding:3px 6px;text-align:center;min-width:36px;">
                <span id="prev-h" style="color:#f59e0b;font-weight:800;font-size:14px;display:block;">14</span>
                <span style="font-size:8px;text-transform:uppercase;">Hrs</span>
              </div>
              <span style="color:#f59e0b;font-weight:700;">:</span>
              <div style="background:rgba(255,255,255,0.15);border-radius:4px;padding:3px 6px;text-align:center;min-width:36px;">
                <span id="prev-m" style="color:#f59e0b;font-weight:800;font-size:14px;display:block;">32</span>
                <span style="font-size:8px;text-transform:uppercase;">Min</span>
              </div>
              <span style="color:#f59e0b;font-weight:700;">:</span>
              <div style="background:rgba(255,255,255,0.15);border-radius:4px;padding:3px 6px;text-align:center;min-width:36px;">
                <span id="prev-s" style="color:#f59e0b;font-weight:800;font-size:14px;display:block;">45</span>
                <span style="font-size:8px;text-transform:uppercase;">Sec</span>
              </div>
            </div>
            <div>
              <span id="prev-btn" style="background:#f59e0b;color:#111827;font-weight:700;font-size:11px;padding:5px 12px;border-radius:14px;display:inline-block;">Shop Deals Now →</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Countdown Bar Controls -->
      <div class="polaris-settings-grid">
        <div class="polaris-form-group">
          <label class="polaris-sublabel" for="theme-bar-headline">Banner Headline</label>
          <input class="polaris-input" id="theme-bar-headline" value="⚡ FLASH SALE IS LIVE! Extra %discount%% Off Selected Items">
        </div>
        <div class="polaris-form-group">
          <label class="polaris-sublabel" for="theme-bar-subtext">Urgency Subtext</label>
          <input class="polaris-input" id="theme-bar-subtext" value="Limited time store promotion. Discounts auto-applied in cart.">
        </div>
      </div>

      <div class="polaris-settings-grid">
        <div class="polaris-form-group">
          <label class="polaris-sublabel">Background &amp; Text Colors</label>
          <div style="display:flex;gap:12px;">
            <div class="polaris-color-input-wrapper">
              <input type="color" id="theme-bg-color-picker" class="polaris-color-picker" value="#111827">
              <input class="polaris-input" id="theme-bg-color-text" value="#111827" style="width:90px;font-family:monospace;font-size:12px;">
            </div>
            <div class="polaris-color-input-wrapper">
              <input type="color" id="theme-text-color-picker" class="polaris-color-picker" value="#ffffff">
              <input class="polaris-input" id="theme-text-color-text" value="#ffffff" style="width:90px;font-family:monospace;font-size:12px;">
            </div>
          </div>
        </div>

        <div class="polaris-form-group">
          <label class="polaris-sublabel">Accent / Timer Highlight Color</label>
          <div class="polaris-color-input-wrapper">
            <input type="color" id="theme-accent-color-picker" class="polaris-color-picker" value="#f59e0b">
            <input class="polaris-input" id="theme-accent-color-text" value="#f59e0b" style="width:90px;font-family:monospace;font-size:12px;">
          </div>
        </div>
      </div>

      <div class="polaris-settings-grid">
        <div class="polaris-form-group">
          <label class="polaris-sublabel" for="theme-btn-text">CTA Button Text</label>
          <input class="polaris-input" id="theme-btn-text" value="Shop Deals Now">
        </div>
        <div class="polaris-form-group">
          <label class="polaris-sublabel" for="theme-btn-url">CTA Button Link</label>
          <input class="polaris-input" id="theme-btn-url" value="/collections/all">
        </div>
      </div>

      <div class="polaris-form-group">
        <label class="polaris-checkbox-label">
          <input type="checkbox" id="theme-auto-publish-check" checked>
          <span><strong>Publish as live storefront theme immediately upon duplication</strong> (Previous live theme will be remembered so you can revert anytime).</span>
        </label>
      </div>

      <div id="theme-publish-error" class="polaris-banner polaris-banner-critical hidden" style="margin-top:12px;"></div>
    </div>

    <div class="polaris-dialog-footer">
      <button type="button" class="polaris-btn" data-close="theme-publish-dialog">Cancel</button>
      <button type="submit" class="polaris-btn polaris-btn-primary" id="theme-publish-submit-btn">Duplicate &amp; Publish Theme Copy</button>
    </div>
  </form>
</dialog>

<div id="toast" class="polaris-toast" role="status"></div>

<script src="/app.js"></script>
</body>
</html>
