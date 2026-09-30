<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="shopify-api-key" content="{{ $apiKey }}">
<meta name="shop-domain" content="{{ $shop }}">
<title>SaleSnap</title>
<script src="https://cdn.shopify.com/shopifycloud/app-bridge.js"></script>

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
  width: 32px;
  height: 32px;
  border-radius: 4px;
  object-fit: cover;
  background: #eee;
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

<div id="toast" class="polaris-toast" role="status"></div>

<script>
(() => {
  'use strict';

  const $ = s => document.querySelector(s);
  const $$ = s => [...document.querySelectorAll(s)];
  const apiKey = $('meta[name="shopify-api-key"]')?.content || '';
  const shopDomain = $('meta[name="shop-domain"]')?.content || '';

  let dashboard = null;
  let billingData = null;
  let selected = new Map();
  let currentProducts = [];
  let currentCollections = [];
  let activeFilter = 'all';
  let restoreId = null;
  let productTimer = null;
  let authRedirectStarted = false;
  let selectionMode = 'products';

  const pageHeaders = {
    overview: ['SaleSnap', 'Schedule selected product changes with pre-change snapshots and restore reporting.'],
    campaigns: ['Campaigns', 'Plan scheduled product promotions and review their restore status.'],
    products: ['Products & Collections', 'Search products and browse collections in your Shopify catalog.'],
    snapshots: ['Snapshots & Restores', 'Review campaign snapshots, restore outcomes, and any skipped fields.'],
    activity: ['Activity Log', 'A clear audit trail of scheduled campaigns and restore actions.'],
    billing: ['Billing & Plans', 'Manage your SaleSnap app subscription and unlock advanced capabilities.'],
    settings: ['Settings', 'Review this app’s Shopify connection and data handling.']
  };

  function esc(v) {
    return String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  }

  function toast(text) {
    let el = $('#toast');
    if (!el) return;
    el.textContent = text;
    el.classList.add('show');
    clearTimeout(window.pmToast);
    window.pmToast = setTimeout(() => el.classList.remove('show'), 3500);
  }

  function banner(text) {
    let e = $('#error-banner');
    if (!e) return;
    e.innerHTML = '<div class="polaris-banner-icon">!</div><div class="polaris-banner-content"><strong>Could not load store data</strong><p>' + esc(text) + '</p></div>';
    e.classList.remove('hidden');
  }

  async function token() {
    if (!window.shopify || typeof window.shopify.idToken !== 'function') {
      throw new Error('Open SaleSnap from Shopify Admin to securely load your store.');
    }
    return await window.shopify.idToken();
  }

  async function api(path, options = {}) {
    const idToken = await token();
    const res = await fetch('/api' + path, {
      ...options,
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'Authorization': 'Bearer ' + idToken,
        ...(options.headers || {})
      }
    });

    let data = {};
    try { data = await res.json(); } catch {}

    if (!res.ok) {
      if (res.status === 401 && !authRedirectStarted && shopDomain) {
        authRedirectStarted = true;
        const authUrl = window.location.origin + '/auth?shop=' + encodeURIComponent(shopDomain);
        if (window.shopify && typeof window.shopify.open === 'function') {
          window.shopify.open(authUrl, '_top');
        } else if (typeof open === 'function' && window !== window.top) {
          try { open(authUrl, '_top'); } catch (e) { try { window.top.location.href = authUrl; } catch (err) {} }
        } else {
          try { window.top.location.href = authUrl; } catch (e) { window.location.href = authUrl; }
        }
        throw new Error('Reauthorizing this store with Shopify…');
      }
      let msg = data.message || 'Request failed (' + res.status + ').';
      if (data.errors) msg = Object.values(data.errors).flat().join(' ');
      throw new Error(msg);
    }
    return data;
  }

  function statusBadge(status) {
    const m = {
      running: ['Live', 'polaris-badge-success'],
      applying: ['Starting', 'polaris-badge-warning'],
      scheduled: ['Scheduled', 'polaris-badge-warning'],
      completed: ['Completed', 'polaris-badge-neutral'],
      completed_with_conflicts: ['Conflicts noted', 'polaris-badge-attention'],
      restoring: ['Restoring', 'polaris-badge-warning'],
      needs_attention: ['Needs attention', 'polaris-badge-attention'],
      cancelled: ['Cancelled', 'polaris-badge-neutral']
    };
    let v = m[status] || [status, 'polaris-badge-neutral'];
    return `<span class="polaris-badge ${v[1]}">${esc(v[0])}</span>`;
  }

  function formatDate(v) {
    if (!v) return '—';
    return new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }).format(new Date(v));
  }

  function campaignAction(c) {
    if (c.snapshot_complete && ['running', 'needs_attention'].includes(c.status)) {
      return `<button class="polaris-btn polaris-btn-plain restore-now" data-id="${esc(c.id)}" data-name="${esc(c.name)}">Rollback now</button>`;
    }
    if (c.status === 'scheduled') {
      return `<button class="polaris-btn polaris-btn-plain cancel-campaign" data-id="${esc(c.id)}">Cancel</button>`;
    }
    if (c.status === 'needs_attention' && !c.snapshot_complete) {
      return `<button class="polaris-btn polaris-btn-plain retry-campaign" data-id="${esc(c.id)}">Retry</button>`;
    }
    return '';
  }

  function attachCampaignActions() {
    $$('.restore-now').forEach(b => b.onclick = () => openRestore(b.dataset.id, b.dataset.name));
    $$('.cancel-campaign').forEach(b => b.onclick = () => cancelCampaign(b.dataset.id));
    $$('.retry-campaign').forEach(b => b.onclick = () => retryCampaign(b.dataset.id));
  }

  async function cancelCampaign(id) {
    if (!window.confirm('Cancel this scheduled campaign? No product changes have been made yet.')) return;
    try {
      await api(`/campaigns/${id}/cancel`, { method: 'POST', body: '{}' });
      toast('Scheduled campaign cancelled.');
      await loadDashboard();
    } catch (e) {
      toast(e.message);
    }
  }

  async function retryCampaign(id) {
    try {
      const d = await api(`/campaigns/${id}/retry`, { method: 'POST', body: '{}' });
      toast(d.message || 'Retry queued.');
      await loadDashboard();
    } catch (e) {
      toast(e.message);
    }
  }

  function renderCampaigns() {
    let items = dashboard?.campaigns || [];
    if (activeFilter !== 'all') {
      items = items.filter(c => activeFilter === 'running' ? ['running', 'applying', 'restoring', 'needs_attention'].includes(c.status) : (activeFilter === 'completed' ? ['completed', 'completed_with_conflicts'].includes(c.status) : c.status === activeFilter));
    }
    let list = $('#campaign-list');
    if (!items.length) {
      list.innerHTML = '<div class="polaris-empty-state">No campaigns in this view. Click "Create campaign" to schedule your first promotion.</div>';
      return;
    }
    list.innerHTML = items.map(c => `
      <div class="polaris-row">
        <div>
          <div class="polaris-row-title">${esc(c.name)}</div>
          <div class="polaris-row-meta">${esc(Object.keys(c.actions || {}).join(' · ') || 'Promotion')} · ${c.snapshot_complete ? esc(c.snapshot_count) + ' snapshot items' : 'snapshot pending'}</div>
        </div>
        <div>${statusBadge(c.status)}${c.error_count ? '<div class="polaris-row-meta">' + esc(c.error_count) + ' error(s)</div>' : ''}</div>
        <div class="polaris-row-meta">${c.status === 'scheduled' ? 'Starts ' + formatDate(c.starts_at) : 'Ends ' + formatDate(c.ends_at)}</div>
        <div>${campaignAction(c)}</div>
      </div>
    `).join('');
    attachCampaignActions();
  }

  async function loadDashboard() {
    try {
      let d = await api('/dashboard');
      dashboard = d;
      $('#stat-live').textContent = d.stats.live;
      $('#stat-scheduled').textContent = d.stats.scheduled;
      $('#stat-protected').textContent = d.stats.products_protected;
      $('#stat-rollbacks').textContent = d.stats.rollbacks;
      renderCampaigns();
      $('#error-banner').classList.add('hidden');
    } catch (e) {
      banner(e.message);
      $('#campaign-list').innerHTML = '<div class="polaris-empty-state">' + esc(e.message) + '</div>';
    }
  }

  function setPage(page) {
    $$('.polaris-tab-item').forEach(n => n.classList.toggle('active', n.dataset.page === page));
    $('#page-title').textContent = pageHeaders[page]?.[0] || 'SaleSnap';
    $('#page-desc').textContent = pageHeaders[page]?.[1] || '';
    let isOverview = page === 'overview';
    $('#overview-page').classList.toggle('hidden', !isOverview);
    $('#detail-page').classList.toggle('hidden', isOverview);
    if (!isOverview) renderDetail(page);
  }

  async function renderDetail(page) {
    let title = pageHeaders[page]?.[0] || page;
    $('#detail-title').textContent = title;
    $('#detail-copy').textContent = pageHeaders[page]?.[1] || '';
    let body = $('#detail-body');

    if (page === 'campaigns') {
      body.innerHTML = '<div id="all-campaigns" class="polaris-campaign-list"></div>';
      $('#all-campaigns').innerHTML = (dashboard?.campaigns || []).length ? dashboard.campaigns.map(c => `
        <div class="polaris-row">
          <div>
            <div class="polaris-row-title">${esc(c.name)}</div>
            <div class="polaris-row-meta">${esc(Object.keys(c.actions || {}).join(' · '))} · ${c.product_count} products</div>
          </div>
          <div>${statusBadge(c.status)}</div>
          <div class="polaris-row-meta">${formatDate(c.starts_at)} – ${formatDate(c.ends_at)}</div>
          <div>${campaignAction(c)}</div>
        </div>
      `).join('') : '<div class="polaris-empty-state">No campaigns created yet.</div>';
      attachCampaignActions();
      return;
    }

    if (page === 'products') {
      body.innerHTML = `
        <div style="display:flex;gap:10px;margin-bottom:16px;">
          <input class="polaris-input" id="detail-product-search" placeholder="Search catalog by title..." style="max-width:400px;">
          <button class="polaris-btn polaris-btn-primary" id="detail-product-go">Search</button>
        </div>
        <div id="detail-products-results"><div class="polaris-empty-state">Search your catalog above to preview products.</div></div>
      `;
      $('#detail-product-go').onclick = () => searchDetailProducts();
      $('#detail-product-search').onkeydown = e => { if (e.key === 'Enter') { e.preventDefault(); searchDetailProducts(); } };
      return;
    }

    if (page === 'snapshots') {
      body.innerHTML = '<div class="polaris-empty-state">Loading snapshot records…</div>';
      try {
        let d = await api('/snapshots');
        if (!d.snapshots.length) {
          body.innerHTML = '<div class="polaris-empty-state">Snapshots will appear here automatically when a campaign runs.</div>';
          return;
        }
        body.innerHTML = `
          <div style="overflow-x:auto;">
            <table class="polaris-table">
              <thead>
                <tr><th>CAMPAIGN</th><th>PRODUCT</th><th>STATUS</th><th>CONFLICTS / DETAILS</th></tr>
              </thead>
              <tbody>
                ${d.snapshots.map(s => `
                  <tr>
                    <td><strong>${esc(s.campaign)}</strong></td>
                    <td>${esc(s.product_title || 'Product')}<div class="polaris-row-meta">GID: ${esc(s.product_gid.split('/').pop())}</div></td>
                    <td>${statusBadge(s.status)}</td>
                    <td>${esc([...(s.conflicts || []), s.last_error || ''].filter(Boolean).join('; ') || 'Protected')}</td>
                  </tr>
                `).join('')}
              </tbody>
            </table>
          </div>
        `;
      } catch (e) {
        body.innerHTML = '<div class="polaris-empty-state">' + esc(e.message) + '</div>';
      }
      return;
    }

    if (page === 'activity') {
      body.innerHTML = (dashboard?.logs || []).length ? `
        <div style="overflow-x:auto;">
          <table class="polaris-table">
            <thead>
              <tr><th>EVENT</th><th>TIMESTAMP</th><th>LEVEL</th><th>DETAILS</th></tr>
            </thead>
            <tbody>
              ${dashboard.logs.map(l => `
                <tr>
                  <td><strong>${esc(l.event.replaceAll('_', ' '))}</strong></td>
                  <td>${formatDate(l.created_at)}</td>
                  <td><span class="polaris-badge polaris-badge-neutral">${esc(l.severity)}</span></td>
                  <td><code>${esc(JSON.stringify(l.details || {}))}</code></td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
      ` : '<div class="polaris-empty-state">Activity logs will appear when promotions are scheduled.</div>';
      return;
    }

    if (page === 'billing') {
      body.innerHTML = '<div class="polaris-empty-state">Loading billing details…</div>';
      await loadBillingPage(body);
      return;
    }

    // Settings Page
    body.innerHTML = `
      <div class="polaris-banner polaris-banner-info">
        <div class="polaris-banner-icon">✓</div>
        <div class="polaris-banner-content">
          <strong>Store Connection Active</strong>
          <p>SaleSnap is connected to <strong>${esc(shopDomain)}</strong> with read/write product permissions and pre-change snapshot integrity checks.</p>
        </div>
      </div>
      <p style="font-size:12px;color:#6d7175;margin-top:16px;">SaleSnap operates using Shopify Admin GraphQL API with session tokens. Stored prices and snapshots are encrypted at rest.</p>
    `;
  }

  async function loadBillingPage(container) {
    try {
      const data = await api('/billing');
      billingData = data;
      const isPro = data.plan === 'pro' && data.subscription_status === 'ACTIVE';

      container.innerHTML = `
        <div class="polaris-banner ${isPro ? 'polaris-banner-info' : 'polaris-banner-warning'}">
          <div class="polaris-banner-icon">${isPro ? '✓' : 'ⓘ'}</div>
          <div class="polaris-banner-content">
            <strong>Current Plan: ${isPro ? 'SaleSnap Pro (Active)' : 'Free Tier'}</strong>
            <p>${isPro ? 'You have access to unlimited promotions, automatic rollback, and priority support.' : 'Upgrade to Pro to unlock unlimited campaigns and collection-wide discounts.'}</p>
          </div>
        </div>

        <div class="polaris-pricing-grid">
          <div class="polaris-plan-card">
            <h3 class="polaris-plan-title">Free Tier</h3>
            <div class="polaris-plan-price">$0 <small>/ month</small></div>
            <p class="polaris-text-subdued">Basic product price scheduling</p>
            <ul class="polaris-plan-features">
              <li><b>✓</b> Up to 3 active campaigns</li>
              <li><b>✓</b> Individual product discounts</li>
              <li><b>✓</b> Pre-change price snapshot</li>
              <li><b>✓</b> Manual rollback</li>
            </ul>
            <button class="polaris-btn" disabled>${!isPro ? 'Current Plan' : 'Free Tier'}</button>
          </div>

          <div class="polaris-plan-card featured">
            <div style="display:flex;justify-content:space-between;align-items:center;">
              <h3 class="polaris-plan-title">SaleSnap Pro</h3>
              <span class="polaris-badge polaris-badge-success">7-Day Free Trial</span>
            </div>
            <div class="polaris-plan-price">$9.99 <small>/ month</small></div>
            <p class="polaris-text-subdued">Full automated promotion automation</p>
            <ul class="polaris-plan-features">
              <li><b>✓</b> <strong>Unlimited</strong> active campaigns</li>
              <li><b>✓</b> Collection-wide bulk selection</li>
              <li><b>✓</b> Automated end-date rollback</li>
              <li><b>✓</b> Emergency conflict detection</li>
              <li><b>✓</b> Priority queue worker</li>
            </ul>
            ${isPro ? `
              <button class="polaris-btn polaris-btn-destructive" id="cancel-sub-btn">Cancel Pro Subscription</button>
            ` : `
              <button class="polaris-btn polaris-btn-primary" id="upgrade-pro-btn">Start 7-Day Free Trial</button>
            `}
          </div>
        </div>
      `;

      if ($('#upgrade-pro-btn')) {
        $('#upgrade-pro-btn').onclick = async () => {
          const btn = $('#upgrade-pro-btn');
          btn.disabled = true;
          btn.textContent = 'Redirecting to Shopify Billing…';
          try {
            const res = await api('/billing/subscribe', { method: 'POST', body: JSON.stringify({ plan: 'pro' }) });
            if (res.confirmation_url) {
              if (window.shopify && typeof window.shopify.open === 'function') {
                window.shopify.open(res.confirmation_url, '_top');
              } else {
                window.top.location.href = res.confirmation_url;
              }
            }
          } catch (e) {
            toast(e.message);
            btn.disabled = false;
            btn.textContent = 'Start 7-Day Free Trial';
          }
        };
      }

      if ($('#cancel-sub-btn')) {
        $('#cancel-sub-btn').onclick = async () => {
          if (!window.confirm('Are you sure you want to cancel your Pro plan? Your store will return to the Free Tier.')) return;
          const btn = $('#cancel-sub-btn');
          btn.disabled = true;
          try {
            const res = await api('/billing/cancel', { method: 'POST', body: '{}' });
            toast(res.message || 'Subscription cancelled.');
            await loadBillingPage(container);
          } catch (e) {
            toast(e.message);
            btn.disabled = false;
          }
        };
      }
    } catch (e) {
      container.innerHTML = '<div class="polaris-empty-state">' + esc(e.message) + '</div>';
    }
  }

  async function searchDetailProducts() {
    let target = $('#detail-products-results');
    target.innerHTML = '<div class="polaris-empty-state">Searching catalog…</div>';
    try {
      const q = $('#detail-product-search')?.value || '';
      const { products } = await api('/products?q=' + encodeURIComponent(q));
      target.innerHTML = products.length ? `
        <table class="polaris-table">
          <thead><tr><th>PRODUCT</th><th>PRICE</th><th>STATUS</th><th>HANDLE</th></tr></thead>
          <tbody>
            ${products.map(p => `
              <tr>
                <td><strong>${esc(p.title)}</strong></td>
                <td>$${esc(p.price)}</td>
                <td><span class="polaris-badge polaris-badge-success">${esc(p.status)}</span></td>
                <td><code>${esc(p.handle)}</code></td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      ` : '<div class="polaris-empty-state">No matching products found.</div>';
    } catch (e) {
      target.innerHTML = '<div class="polaris-empty-state">' + esc(e.message) + '</div>';
    }
  }

  function selectProduct(id, checked) {
    if (checked) {
      if (selected.size >= 250) {
        toast('Select up to 250 products per campaign.');
        return false;
      }
      const p = currentProducts.find(x => x.id === id);
      if (p) selected.set(id, p);
    } else {
      selected.delete(id);
    }
    $('#selected-summary').textContent = `${selected.size} product${selected.size === 1 ? '' : 's'} selected`;
    return true;
  }

  function renderPickerProducts(products) {
    currentProducts = products;
    const box = $('#product-results');
    box.innerHTML = products.length ? products.map(p => `
      <label class="polaris-product-item">
        <input type="checkbox" data-product-id="${esc(p.id)}" ${selected.has(p.id) ? 'checked' : ''}>
        ${p.image ? `<img src="${esc(p.image)}" class="polaris-product-thumb" alt="">` : '<div class="polaris-product-thumb"></div>'}
        <div style="flex:1;">
          <strong>${esc(p.title)}</strong>
          <div class="polaris-row-meta">$${esc(p.price)} · ${esc(p.status)}</div>
        </div>
      </label>
    `).join('') : '<div class="polaris-empty-picker">No products found.</div>';

    $$('[data-product-id]').forEach(cb => {
      cb.onchange = () => {
        if (!selectProduct(cb.dataset.productId, cb.checked)) cb.checked = false;
      };
    });
  }

  async function searchProducts() {
    const box = $('#product-results');
    box.innerHTML = '<div class="polaris-empty-picker">Searching Shopify products…</div>';
    try {
      const q = $('#product-search')?.value || '';
      const { products } = await api('/products?q=' + encodeURIComponent(q));
      renderPickerProducts(products);
    } catch (e) {
      box.innerHTML = '<div class="polaris-empty-picker">' + esc(e.message) + '</div>';
    }
  }

  async function loadCollections() {
    const sel = $('#collection-select');
    sel.innerHTML = '<option value="">Loading store collections…</option>';
    try {
      const { collections } = await api('/collections');
      currentCollections = collections;
      sel.innerHTML = collections.length ? `
        <option value="">Select a collection...</option>
        ${collections.map(c => `<option value="${esc(c.id)}">${esc(c.title)} (${c.products_count} products)</option>`).join('')}
      ` : '<option value="">No collections found in store.</option>';
    } catch (e) {
      sel.innerHTML = '<option value="">Failed to load collections.</option>';
    }
  }

  async function loadCollectionProducts() {
    const colId = $('#collection-select')?.value;
    if (!colId) {
      toast('Please choose a collection first.');
      return;
    }
    const box = $('#product-results');
    box.innerHTML = '<div class="polaris-empty-picker">Loading collection products…</div>';
    try {
      const { products } = await api('/products?collection_id=' + encodeURIComponent(colId));
      renderPickerProducts(products);
    } catch (e) {
      box.innerHTML = '<div class="polaris-empty-picker">' + esc(e.message) + '</div>';
    }
  }

  function setDefaultDates() {
    const zone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
    const sel = $('#timezone');
    sel.innerHTML = '';
    [zone, 'UTC', 'Asia/Kolkata', 'America/New_York', 'Europe/London'].filter((v, i, a) => a.indexOf(v) === i).forEach(z => {
      let o = document.createElement('option');
      o.value = z;
      o.textContent = z + (z === zone ? ' (store local)' : '');
      sel.append(o);
    });

    let start = new Date(Date.now() + 3600_000);
    let end = new Date(Date.now() + 48 * 3600_000);
    function localIso(d) {
      return new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
    }
    $('#starts-at').value = localIso(start);
    $('#ends-at').value = localIso(end);
  }

  function openCreate() {
    selected.clear();
    $('#selected-summary').textContent = '0 products selected';
    $('#product-results').innerHTML = '<div class="polaris-empty-picker">Search your catalog or select a collection.</div>';
    $('#form-error').classList.add('hidden');
    $('#campaign-dialog').showModal();
    setDefaultDates();
    if (selectionMode === 'collections') loadCollections();
  }

  function openRestore(id, name) {
    restoreId = id;
    $('#restore-name').textContent = name;
    $('#confirm-restore').checked = false;
    $('#restore-error').classList.add('hidden');
    $('#restore-dialog').showModal();
  }

  async function submitRestore() {
    if (!$('#confirm-restore').checked) {
      $('#restore-error').textContent = 'Please confirm that you want to restore now.';
      $('#restore-error').classList.remove('hidden');
      return;
    }
    let btn = $('#confirm-restore-button');
    btn.disabled = true;
    try {
      const d = await api(`/campaigns/${restoreId}/restore`, { method: 'POST', body: '{}' });
      $('#restore-dialog').close();
      toast(d.message || 'Rollback queued.');
      await loadDashboard();
    } catch (e) {
      $('#restore-error').textContent = e.message;
      $('#restore-error').classList.remove('hidden');
    } finally {
      btn.disabled = false;
    }
  }

  async function createCampaign(e) {
    e.preventDefault();
    const err = $('#form-error');
    err.classList.add('hidden');

    if (!selected.size) {
      err.textContent = 'Please choose at least one product.';
      err.classList.remove('hidden');
      return;
    }

    const actions = {};
    if ($('#enable-price').checked) actions.price_percent = Number($('#price-percent').value);
    if ($('#enable-tag').checked && $('#product-tag').value.trim()) actions.add_tag = $('#product-tag').value.trim();
    if ($('#enable-description').checked && $('#description-prefix').value.trim()) actions.description_prefix = $('#description-prefix').value.trim();

    if (!Object.keys(actions).length) {
      err.textContent = 'Please select at least one change to apply.';
      err.classList.remove('hidden');
      return;
    }

    const payload = {
      name: $('#campaign-name').value.trim(),
      product_ids: [...selected.keys()],
      actions,
      timezone: $('#timezone').value,
      starts_at: $('#starts-at').value,
      ends_at: $('#ends-at').value
    };

    const btn = $('#schedule-button');
    btn.disabled = true;
    btn.textContent = 'Scheduling…';

    try {
      await api('/campaigns', { method: 'POST', body: JSON.stringify(payload) });
      $('#campaign-dialog').close();
      toast('Campaign scheduled with snapshot safeguards.');
      await loadDashboard();
      setPage('campaigns');
    } catch (e) {
      err.textContent = e.message;
      err.classList.remove('hidden');
    } finally {
      btn.disabled = false;
      btn.textContent = 'Review & Schedule';
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    $('#new-campaign').onclick = openCreate;
    $('#campaign-form').addEventListener('submit', createCampaign);

    // Search and Picker Controls
    $('#search-products').onclick = searchProducts;
    $('#product-search').onkeydown = e => { if (e.key === 'Enter') { e.preventDefault(); searchProducts(); } };
    $('#product-search').oninput = () => {
      clearTimeout(productTimer);
      productTimer = setTimeout(searchProducts, 450);
    };

    // Mode Toggle (Products vs Collections)
    $('#mode-products').onclick = () => {
      selectionMode = 'products';
      $('#mode-products').classList.add('active');
      $('#mode-collections').classList.remove('active');
      $('#product-search-container').classList.remove('hidden');
      $('#collection-search-container').classList.add('hidden');
    };

    $('#mode-collections').onclick = () => {
      selectionMode = 'collections';
      $('#mode-collections').classList.add('active');
      $('#mode-products').classList.remove('active');
      $('#product-search-container').classList.add('hidden');
      $('#collection-search-container').classList.remove('hidden');
      loadCollections();
    };

    $('#load-collection-products').onclick = loadCollectionProducts;
    $('#collection-select').onchange = loadCollectionProducts;

    $('#select-all-results').onclick = () => {
      $$('[data-product-id]').forEach(cb => {
        cb.checked = true;
        selectProduct(cb.dataset.productId, true);
      });
    };

    $('#confirm-restore-button').onclick = submitRestore;

    $$('[data-close]').forEach(b => b.onclick = () => $('#' + b.dataset.close)?.close());
    $$('.polaris-tab-item').forEach(b => b.onclick = () => setPage(b.dataset.page));
    $$('[data-go]').forEach(b => b.onclick = () => setPage(b.dataset.go));

    $$('.polaris-pill-filter').forEach(b => b.onclick = () => {
      activeFilter = b.dataset.filter;
      $$('.polaris-pill-filter').forEach(x => x.classList.toggle('selected', x === b));
      renderCampaigns();
    });

    const urlParams = new URLSearchParams(window.location.search);
    const initialPage = urlParams.get('page') || 'overview';
    if (urlParams.get('subscribed')) {
      toast('Pro subscription updated successfully!');
    }
    setPage(initialPage);
    loadDashboard();
  });
})();
</script>
</body>
</html>
