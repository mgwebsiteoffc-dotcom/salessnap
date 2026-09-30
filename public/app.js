(() => {
  'use strict';

  const $ = s => document.querySelector(s);
  const $$ = s => [...document.querySelectorAll(s)];
  const apiKey = $('meta[name="shopify-api-key"]')?.content || '';
  const shopDomain = $('meta[name="shop-domain"]')?.content || '';

  let dashboard = null;
  let billingData = null;
  let selected = new Map();
  let bundleSelected = new Map();
  let currentProducts = [];
  let currentCollections = [];
  let catalogSelectedIds = new Set();
  let activeFilter = 'all';
  let restoreId = null;
  let productTimer = null;
  let bundleProductTimer = null;
  let authRedirectStarted = false;
  let selectionMode = 'products';
  let catalogTab = 'products'; // 'products' or 'collections'
  let bundleDialogMode = 'combo'; // 'combo' or 'multipack'

  const pageHeaders = {
    overview: ['SaleSnap', 'Schedule selected product changes with pre-change snapshots and restore reporting.'],
    campaigns: ['Campaigns', 'Plan scheduled product promotions and review their restore status.'],
    products: ['Products & Collections', 'Explore store products and collections to launch flash sales or bulk bundles.'],
    bundles: ['Bundle Creator', 'Create high-converting multi-product bundles and bulk value packs in Shopify.'],
    snapshots: ['Snapshots & Restores', 'Review campaign snapshots, restore outcomes, and any skipped fields.'],
    activity: ['Activity Log', 'A clear audit trail of scheduled campaigns, restores, and bundle creations.'],
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
      ACTIVE: ['Active', 'polaris-badge-success'],
      DRAFT: ['Draft', 'polaris-badge-neutral'],
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

    if (page === 'bundles') {
      body.innerHTML = `
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
          <div style="display:flex;gap:8px;">
            <button class="polaris-btn polaris-btn-primary" id="open-bundle-combo-btn">＋ Create Combo Bundle</button>
            <button class="polaris-btn" id="open-bundle-multipack-btn">⚡ Bulk Multi-Packs</button>
          </div>
          <div style="display:flex;gap:8px;">
            <input class="polaris-input" id="bundle-search-input" placeholder="Search bundles in store..." style="max-width:260px;">
            <button class="polaris-btn" id="bundle-search-btn">Filter</button>
          </div>
        </div>
        <div id="bundle-results-box"><div class="polaris-empty-state">Loading your store bundles…</div></div>
      `;

      $('#open-bundle-combo-btn').onclick = () => openBundleModal('combo');
      $('#open-bundle-multipack-btn').onclick = () => openBundleModal('multipack');
      $('#bundle-search-btn').onclick = () => loadStoreBundles($('#bundle-search-input').value);
      $('#bundle-search-input').onkeydown = e => { if (e.key === 'Enter') { e.preventDefault(); loadStoreBundles($('#bundle-search-input').value); } };

      loadStoreBundles();
      return;
    }

    if (page === 'products') {
      catalogSelectedIds.clear();
      body.innerHTML = `
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
          <div class="polaris-segmented-control" style="max-width:300px;margin-bottom:0;">
            <button class="polaris-segment-btn ${catalogTab === 'products' ? 'active' : ''}" id="catalog-tab-products">Products</button>
            <button class="polaris-segment-btn ${catalogTab === 'collections' ? 'active' : ''}" id="catalog-tab-collections">Collections</button>
          </div>

          <div style="display:flex;gap:8px;">
            <button class="polaris-btn" id="bulk-bundle-from-catalog" disabled>🎁 Create Bundle (0)</button>
            <button class="polaris-btn" id="bulk-multipack-from-catalog" disabled>⚡ Bulk Multi-Packs (0)</button>
            <button class="polaris-btn polaris-btn-primary" id="bulk-discount-from-catalog" disabled>＋ Discount Selected (0)</button>
          </div>
        </div>

        <div id="catalog-search-bar" style="display:flex;gap:10px;max-width:500px;margin-bottom:16px;">
          <input class="polaris-input" id="catalog-search-input" placeholder="Search products by title...">
          <button class="polaris-btn" id="catalog-search-btn">Search</button>
        </div>

        <div id="catalog-results-box"><div class="polaris-empty-state">Loading your catalog…</div></div>
      `;

      $('#catalog-tab-products').onclick = () => {
        catalogTab = 'products';
        catalogSelectedIds.clear();
        $('#catalog-tab-products').classList.add('active');
        $('#catalog-tab-collections').classList.remove('active');
        $('#catalog-search-input').placeholder = 'Search products by title...';
        updateCatalogBulkButtons();
        loadCatalogProducts();
      };

      $('#catalog-tab-collections').onclick = () => {
        catalogTab = 'collections';
        catalogSelectedIds.clear();
        $('#catalog-tab-collections').classList.add('active');
        $('#catalog-tab-products').classList.remove('active');
        $('#catalog-search-input').placeholder = 'Filter collections...';
        updateCatalogBulkButtons();
        loadCatalogCollections();
      };

      $('#catalog-search-btn').onclick = () => {
        if (catalogTab === 'products') loadCatalogProducts($('#catalog-search-input').value);
        else loadCatalogCollections($('#catalog-search-input').value);
      };

      $('#catalog-search-input').onkeydown = e => {
        if (e.key === 'Enter') {
          e.preventDefault();
          if (catalogTab === 'products') loadCatalogProducts($('#catalog-search-input').value);
          else loadCatalogCollections($('#catalog-search-input').value);
        }
      };

      $('#bulk-bundle-from-catalog').onclick = () => {
        const prods = currentProducts.filter(p => catalogSelectedIds.has(p.id));
        openBundleModal('combo', prods);
      };

      $('#bulk-multipack-from-catalog').onclick = () => {
        const prods = currentProducts.filter(p => catalogSelectedIds.has(p.id));
        openBundleModal('multipack', prods);
      };

      $('#bulk-discount-from-catalog').onclick = () => {
        const prods = currentProducts.filter(p => catalogSelectedIds.has(p.id));
        openCreateWithProducts(prods);
      };

      loadCatalogProducts();
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

  function updateCatalogBulkButtons() {
    const count = catalogSelectedIds.size;
    const b1 = $('#bulk-bundle-from-catalog');
    const b2 = $('#bulk-multipack-from-catalog');
    const b3 = $('#bulk-discount-from-catalog');

    if (b1) {
      b1.disabled = count < 1;
      b1.textContent = `🎁 Create Bundle (${count})`;
    }
    if (b2) {
      b2.disabled = count < 1;
      b2.textContent = `⚡ Bulk Multi-Packs (${count})`;
    }
    if (b3) {
      b3.disabled = count < 1;
      b3.textContent = `＋ Discount Selected (${count})`;
    }
  }

  async function loadCatalogProducts(query = '') {
    const box = $('#catalog-results-box');
    if (!box) return;
    box.innerHTML = '<div class="polaris-empty-state">Searching products…</div>';
    try {
      const { products } = await api('/products?q=' + encodeURIComponent(query));
      currentProducts = products;
      if (!products.length) {
        box.innerHTML = '<div class="polaris-empty-state">No products found matching your search.</div>';
        return;
      }
      box.innerHTML = `
        <div style="overflow-x:auto;">
          <table class="polaris-table">
            <thead>
              <tr>
                <th style="width:36px;"><input type="checkbox" id="catalog-select-all"></th>
                <th>PRODUCT</th>
                <th>PRICE</th>
                <th>STATUS</th>
                <th>VARIANTS</th>
                <th>ACTIONS</th>
              </tr>
            </thead>
            <tbody>
              ${products.map(p => `
                <tr>
                  <td><input type="checkbox" class="catalog-row-check" data-id="${esc(p.id)}" ${catalogSelectedIds.has(p.id) ? 'checked' : ''}></td>
                  <td>
                    <div style="display:flex;align-items:center;gap:10px;">
                      ${p.image ? `<img src="${esc(p.image)}" class="polaris-product-thumb" alt="">` : '<div class="polaris-product-thumb"></div>'}
                      <div><strong>${esc(p.title)}</strong><div class="polaris-row-meta">${esc(p.handle)}</div></div>
                    </div>
                  </td>
                  <td><strong>$${esc(p.price)}</strong></td>
                  <td><span class="polaris-badge polaris-badge-success">${esc(p.status)}</span></td>
                  <td>${esc(p.variants_count || 1)} variant(s)</td>
                  <td>
                    <div style="display:flex;gap:6px;">
                      <button class="polaris-btn polaris-btn-plain start-promo-for-prod" data-id="${esc(p.id)}">＋ Discount</button>
                      <button class="polaris-btn polaris-btn-plain start-bundle-for-prod" data-id="${esc(p.id)}">🎁 Bundle</button>
                    </div>
                  </td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
      `;

      $('#catalog-select-all').onchange = (e) => {
        const checked = e.target.checked;
        $$('.catalog-row-check').forEach(cb => {
          cb.checked = checked;
          if (checked) catalogSelectedIds.add(cb.dataset.id);
          else catalogSelectedIds.delete(cb.dataset.id);
        });
        updateCatalogBulkButtons();
      };

      $$('.catalog-row-check').forEach(cb => {
        cb.onchange = () => {
          if (cb.checked) catalogSelectedIds.add(cb.dataset.id);
          else catalogSelectedIds.delete(cb.dataset.id);
          updateCatalogBulkButtons();
        };
      });

      $$('.start-promo-for-prod').forEach(btn => {
        btn.onclick = () => {
          const p = currentProducts.find(x => x.id === btn.dataset.id);
          openCreateWithProduct(p);
        };
      });

      $$('.start-bundle-for-prod').forEach(btn => {
        btn.onclick = () => {
          const p = currentProducts.find(x => x.id === btn.dataset.id);
          openBundleModal('combo', p ? [p] : []);
        };
      });
    } catch (e) {
      box.innerHTML = '<div class="polaris-empty-state">' + esc(e.message) + '</div>';
    }
  }

  async function loadCatalogCollections(query = '') {
    const box = $('#catalog-results-box');
    if (!box) return;
    box.innerHTML = '<div class="polaris-empty-state">Loading collections…</div>';
    try {
      const { collections } = await api('/collections?q=' + encodeURIComponent(query));
      currentCollections = collections;
      if (!collections.length) {
        box.innerHTML = '<div class="polaris-empty-state">No collections found in your store.</div>';
        return;
      }
      box.innerHTML = `
        <div style="overflow-x:auto;">
          <table class="polaris-table">
            <thead><tr><th>COLLECTION</th><th>PRODUCTS</th><th>HANDLE</th><th>ACTIONS</th></tr></thead>
            <tbody>
              ${collections.map(c => `
                <tr>
                  <td>
                    <div style="display:flex;align-items:center;gap:10px;">
                      ${c.image ? `<img src="${esc(c.image)}" class="polaris-product-thumb" alt="">` : '<div class="polaris-product-thumb"></div>'}
                      <strong>${esc(c.title)}</strong>
                    </div>
                  </td>
                  <td><strong>${esc(c.products_count)} products</strong></td>
                  <td><code>${esc(c.handle)}</code></td>
                  <td>
                    <div style="display:flex;gap:6px;">
                      <button class="polaris-btn polaris-btn-plain start-promo-for-col" data-id="${esc(c.id)}">＋ Discount All</button>
                      <button class="polaris-btn polaris-btn-plain start-bundle-for-col" data-id="${esc(c.id)}" data-title="${esc(c.title)}">🎁 Create Collection Bundle</button>
                    </div>
                  </td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
      `;

      $$('.start-promo-for-col').forEach(btn => {
        btn.onclick = () => openCreateWithCollection(btn.dataset.id);
      });

      $$('.start-bundle-for-col').forEach(btn => {
        btn.onclick = async () => {
          btn.disabled = true;
          btn.textContent = 'Loading collection…';
          try {
            const { products } = await api('/products?collection_id=' + encodeURIComponent(btn.dataset.id));
            openBundleModal('combo', products, btn.dataset.title + ' Bundle');
          } catch (e) {
            toast(e.message);
          } finally {
            btn.disabled = false;
            btn.textContent = '🎁 Create Collection Bundle';
          }
        };
      });
    } catch (e) {
      box.innerHTML = '<div class="polaris-empty-state">' + esc(e.message) + '</div>';
    }
  }

  async function loadStoreBundles(query = '') {
    const box = $('#bundle-results-box');
    if (!box) return;
    box.innerHTML = '<div class="polaris-empty-state">Loading bundles from your Shopify store…</div>';
    try {
      const { bundles } = await api('/bundles?q=' + encodeURIComponent(query));
      if (!bundles.length) {
        box.innerHTML = `
          <div class="polaris-empty-state">
            <p style="font-weight:600;font-size:14px;margin-bottom:6px;">No bundles found yet</p>
            <p style="margin-bottom:14px;">Create combo bundles or bulk multi-packs to increase your store's Average Order Value (AOV).</p>
            <button class="polaris-btn polaris-btn-primary" onclick="window.pmOpenBundleModal('combo')">＋ Create Your First Bundle</button>
          </div>
        `;
        return;
      }
      box.innerHTML = `
        <div style="overflow-x:auto;">
          <table class="polaris-table">
            <thead>
              <tr><th>BUNDLE PRODUCT</th><th>BUNDLE PRICE</th><th>REGULAR / COMPARE</th><th>STATUS</th><th>TAGS</th><th>SHOPIFY ADMIN</th></tr>
            </thead>
            <tbody>
              ${bundles.map(b => `
                <tr>
                  <td>
                    <div style="display:flex;align-items:center;gap:10px;">
                      ${b.image ? `<img src="${esc(b.image)}" class="polaris-product-thumb" alt="">` : '<div class="polaris-product-thumb"></div>'}
                      <div>
                        <strong>${esc(b.title)}</strong>
                        <div class="polaris-row-meta">${esc(b.handle)}</div>
                      </div>
                    </div>
                  </td>
                  <td><strong>$${esc(b.price)}</strong></td>
                  <td>${b.compare_at_price ? `<strike>$${esc(b.compare_at_price)}</strike> <span class="polaris-badge polaris-badge-success">Save $${(Number(b.compare_at_price) - Number(b.price)).toFixed(2)}</span>` : '—'}</td>
                  <td>${statusBadge(b.status)}</td>
                  <td>${(b.tags || []).slice(0, 3).map(t => `<span class="polaris-badge polaris-badge-neutral" style="margin-right:3px;">${esc(t)}</span>`).join('')}</td>
                  <td><a href="${esc(b.admin_url)}" target="_blank" class="polaris-btn polaris-btn-plain">Open in Admin ↗</a></td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
      `;
    } catch (e) {
      box.innerHTML = '<div class="polaris-empty-state">' + esc(e.message) + '</div>';
    }
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
    let zone = 'UTC';
    try {
      zone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
    } catch (e) {}

    const sel = $('#timezone');
    if (sel) {
      sel.innerHTML = '';
      const timezones = [
        zone,
        'UTC',
        'Asia/Kolkata',
        'Asia/Calcutta',
        'Asia/Dubai',
        'Asia/Singapore',
        'Asia/Tokyo',
        'Europe/London',
        'Europe/Paris',
        'Europe/Berlin',
        'America/New_York',
        'America/Chicago',
        'America/Denver',
        'America/Los_Angeles',
        'America/Toronto',
        'Australia/Sydney',
        'Pacific/Auckland'
      ].filter((v, i, a) => v && a.indexOf(v) === i);

      timezones.forEach(z => {
        let o = document.createElement('option');
        o.value = z;
        o.textContent = z + (z === zone ? ' (Detected Store Local)' : '');
        if (z === zone) o.selected = true;
        sel.append(o);
      });
    }

    let start = new Date(Date.now() + 3600_000);
    let end = new Date(Date.now() + 48 * 3600_000);
    function localIso(d) {
      return new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
    }
    if ($('#starts-at')) $('#starts-at').value = localIso(start);
    if ($('#ends-at')) $('#ends-at').value = localIso(end);
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

  function openCreateWithProduct(p) {
    openCreate();
    if (p) {
      selected.set(p.id, p);
      renderPickerProducts([p]);
      $('#selected-summary').textContent = '1 product selected';
    }
  }

  function openCreateWithProducts(prods) {
    openCreate();
    if (prods && prods.length) {
      prods.forEach(p => selected.set(p.id, p));
      renderPickerProducts(prods);
      $('#selected-summary').textContent = `${selected.size} products selected`;
    }
  }

  async function openCreateWithCollection(collectionId) {
    openCreate();
    selectionMode = 'collections';
    $('#mode-collections').classList.add('active');
    $('#mode-products').classList.remove('active');
    $('#product-search-container').classList.add('hidden');
    $('#collection-search-container').classList.remove('hidden');
    await loadCollections();
    if ($('#collection-select')) {
      $('#collection-select').value = collectionId;
      await loadCollectionProducts();
      $('#select-all-results')?.click();
    }
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

  // --- BUNDLE CREATOR LOGIC ---
  function updateBundlePreview() {
    let totalVal = 0;
    bundleSelected.forEach(p => {
      totalVal += Number(p.price || 0);
    });

    $('#bundle-preview-orig').textContent = '$' + totalVal.toFixed(2);

    let bundlePrice = totalVal;
    const type = $('#bundle-pricing-type')?.value || 'percentage';

    if (type === 'percentage') {
      const pct = Number($('#bundle-discount-pct')?.value || 0);
      bundlePrice = Math.max(0.01, totalVal * (1 - pct / 100));
    } else if (type === 'fixed_price') {
      bundlePrice = Number($('#bundle-fixed-price')?.value || totalVal);
    } else if (type === 'fixed_discount') {
      const off = Number($('#bundle-fixed-discount')?.value || 0);
      bundlePrice = Math.max(0.01, totalVal - off);
    }

    $('#bundle-preview-price').textContent = '$' + bundlePrice.toFixed(2);

    const savings = Math.max(0, totalVal - bundlePrice);
    const savingsPct = totalVal > 0 ? Math.round((savings / totalVal) * 100) : 0;
    $('#bundle-preview-save').textContent = `$${savings.toFixed(2)} (${savingsPct}% off)`;

    // Render Chips
    const chipsBox = $('#bundle-selected-chips');
    if (chipsBox) {
      chipsBox.innerHTML = [...bundleSelected.values()].map(p => `
        <span class="polaris-chip">
          ${p.image ? `<img src="${esc(p.image)}" alt="">` : ''}
          ${esc(p.title)} ($${esc(p.price)})
          <button type="button" class="polaris-chip-remove" data-remove-bundle-id="${esc(p.id)}">×</button>
        </span>
      `).join('');

      $$('[data-remove-bundle-id]').forEach(b => {
        b.onclick = () => {
          bundleSelected.delete(b.dataset.removeBundleId);
          updateBundlePreview();
        };
      });
    }
  }

  function openBundleModal(mode = 'combo', initialProducts = [], suggestedTitle = '') {
    bundleDialogMode = mode;
    $('#bundle-dialog-mode-combo').classList.toggle('active', mode === 'combo');
    $('#bundle-dialog-mode-multipack').classList.toggle('active', mode === 'multipack');
    $('#bundle-mode-combo-view').classList.toggle('hidden', mode !== 'combo');
    $('#bundle-mode-multipack-view').classList.toggle('hidden', mode !== 'multipack');

    bundleSelected.clear();
    if (initialProducts && initialProducts.length) {
      initialProducts.forEach(p => bundleSelected.set(p.id, p));
    }

    if (suggestedTitle) {
      $('#bundle-title').value = suggestedTitle;
    } else if (bundleSelected.size > 0) {
      const names = [...bundleSelected.values()].map(p => p.title).slice(0, 2).join(' & ');
      $('#bundle-title').value = names + (bundleSelected.size > 2 ? ' + More Bundle' : ' Value Bundle');
    } else {
      $('#bundle-title').value = 'Special Value Bundle';
    }

    $('#bundle-error').classList.add('hidden');
    updateBundlePreview();
    $('#bundle-dialog').showModal();
  }
  window.pmOpenBundleModal = openBundleModal;

  async function searchBundleProductsPicker() {
    const box = $('#bundle-picker-results');
    box.innerHTML = '<div class="polaris-empty-picker">Searching store products…</div>';
    try {
      const q = $('#bundle-product-search')?.value || '';
      const { products } = await api('/products?q=' + encodeURIComponent(q));
      box.innerHTML = products.length ? products.map(p => `
        <label class="polaris-product-item">
          <input type="checkbox" data-bundle-picker-id="${esc(p.id)}" ${bundleSelected.has(p.id) ? 'checked' : ''}>
          ${p.image ? `<img src="${esc(p.image)}" class="polaris-product-thumb" alt="">` : '<div class="polaris-product-thumb"></div>'}
          <div style="flex:1;">
            <strong>${esc(p.title)}</strong>
            <div class="polaris-row-meta">$${esc(p.price)} · ${esc(p.status)}</div>
          </div>
        </label>
      `).join('') : '<div class="polaris-empty-picker">No matching products found.</div>';

      $$('[data-bundle-picker-id]').forEach(cb => {
        cb.onchange = () => {
          const p = products.find(x => x.id === cb.dataset.bundlePickerId);
          if (cb.checked) {
            if (p) bundleSelected.set(p.id, p);
          } else {
            bundleSelected.delete(cb.dataset.bundlePickerId);
          }
          updateBundlePreview();
        };
      });
    } catch (e) {
      box.innerHTML = '<div class="polaris-empty-picker">' + esc(e.message) + '</div>';
    }
  }

  async function submitBundleCreation(e) {
    e.preventDefault();
    const err = $('#bundle-error');
    err.classList.add('hidden');

    if (bundleDialogMode === 'combo') {
      if (bundleSelected.size < 1) {
        err.textContent = 'Please select at least 1 product to include in the bundle.';
        err.classList.remove('hidden');
        return;
      }

      const payload = {
        title: $('#bundle-title').value.trim(),
        product_ids: [...bundleSelected.keys()],
        pricing_type: $('#bundle-pricing-type').value,
        discount_percent: Number($('#bundle-discount-pct').value),
        fixed_price: Number($('#bundle-fixed-price').value),
        fixed_discount: Number($('#bundle-fixed-discount').value),
        status: $('#bundle-status').value,
        custom_description: $('#bundle-custom-desc').value.trim(),
        tags: $('#bundle-tags').value.trim()
      };

      const btn = $('#create-bundle-submit-btn');
      btn.disabled = true;
      btn.textContent = 'Creating in Shopify…';

      try {
        const res = await api('/bundles', { method: 'POST', body: JSON.stringify(payload) });
        $('#bundle-dialog').close();
        toast(res.message || 'Bundle product created successfully!');
        setPage('bundles');
      } catch (e) {
        err.textContent = e.message;
        err.classList.remove('hidden');
      } finally {
        btn.disabled = false;
        btn.textContent = 'Create Bundle Product';
      }
    } else {
      // Multipack mode
      if (bundleSelected.size < 1) {
        err.textContent = 'Please choose at least 1 product to generate bulk multi-packs for.';
        err.classList.remove('hidden');
        return;
      }

      const payload = {
        product_ids: [...bundleSelected.keys()],
        pack_size: Number($('#multipack-size').value),
        discount_percent: Number($('#multipack-discount-pct').value),
        status: $('#multipack-status').value,
        tag: $('#multipack-tag').value.trim()
      };

      const btn = $('#create-bundle-submit-btn');
      btn.disabled = true;
      btn.textContent = 'Generating Multi-Packs…';

      try {
        const res = await api('/bundles/bulk-multipack', { method: 'POST', body: JSON.stringify(payload) });
        $('#bundle-dialog').close();
        toast(res.message || `Created ${res.count} multi-pack bundles!`);
        setPage('bundles');
      } catch (e) {
        err.textContent = e.message;
        err.classList.remove('hidden');
      } finally {
        btn.disabled = false;
        btn.textContent = 'Create Bundle Product';
      }
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    $('#new-campaign').onclick = openCreate;
    $('#campaign-form').addEventListener('submit', createCampaign);

    // Search and Picker Controls for Campaign Scheduler
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

    // Bundle Dialog Controls
    $('#bundle-dialog-mode-combo').onclick = () => openBundleModal('combo', [...bundleSelected.values()]);
    $('#bundle-dialog-mode-multipack').onclick = () => openBundleModal('multipack', [...bundleSelected.values()]);
    $('#bundle-form').addEventListener('submit', submitBundleCreation);

    $('#bundle-product-search-btn').onclick = searchBundleProductsPicker;
    $('#bundle-product-search').onkeydown = e => { if (e.key === 'Enter') { e.preventDefault(); searchBundleProductsPicker(); } };
    $('#bundle-product-search').oninput = () => {
      clearTimeout(bundleProductTimer);
      bundleProductTimer = setTimeout(searchBundleProductsPicker, 450);
    };

    $('#bundle-pricing-type').onchange = () => {
      const v = $('#bundle-pricing-type').value;
      $('#bundle-pricing-pct-box').classList.toggle('hidden', v !== 'percentage');
      $('#bundle-pricing-fixed-price-box').classList.toggle('hidden', v !== 'fixed_price');
      $('#bundle-pricing-fixed-discount-box').classList.toggle('hidden', v !== 'fixed_discount');
      updateBundlePreview();
    };

    $('#bundle-discount-pct').oninput = updateBundlePreview;
    $('#bundle-fixed-price').oninput = updateBundlePreview;
    $('#bundle-fixed-discount').oninput = updateBundlePreview;

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
