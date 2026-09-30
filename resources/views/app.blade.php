<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="shopify-api-key" content="{{ $apiKey }}">
<meta name="shop-domain" content="{{ $shop }}">
<title>SaleSnap</title>
<link rel="stylesheet" href="/app.css">
<script src="https://cdn.shopify.com/shopifycloud/app-bridge.js"></script>
<script defer src="/app.js"></script>
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
</body>
</html>
