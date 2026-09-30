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
    themes: ['Theme & Countdown', 'Publish promo theme copies with synchronized live countdown timer announcement bars.'],
    snapshots: ['Snapshots & Restores', 'Review campaign snapshots, restore outcomes, and any skipped fields.'],
    activity: ['Activity Log', 'A clear audit trail of scheduled campaigns, restores, and bundle creations.'],
    billing: ['Billing & Plans', 'Manage your SaleSnap app subscription and unlock advanced capabilities.'],
    settings: ['Settings', 'Customize discount guardrails, price rounding, default tags, and countdown widget styling.']
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
    let actions = `<button class="polaris-btn polaris-btn-plain view-campaign-details" data-id="${esc(c.id)}" style="font-weight:600;color:#008060;">Details ↗</button>`;
    if (c.snapshot_complete && ['running', 'needs_attention'].includes(c.status)) {
      actions += ` <button class="polaris-btn polaris-btn-plain restore-now" data-id="${esc(c.id)}" data-name="${esc(c.name)}" style="color:#d72c0d;">Rollback</button>`;
    } else if (c.status === 'scheduled') {
      actions += `
        <button class="polaris-btn polaris-btn-plain start-campaign-now" data-id="${esc(c.id)}" style="color:#008060;font-weight:600;">Start now</button>
        <button class="polaris-btn polaris-btn-plain cancel-campaign" data-id="${esc(c.id)}">Cancel</button>
      `;
    } else if (c.status === 'needs_attention' && !c.snapshot_complete) {
      actions += ` <button class="polaris-btn polaris-btn-plain retry-campaign" data-id="${esc(c.id)}">Retry</button>`;
    }
    return `<div style="display:flex;align-items:center;gap:6px;">${actions}</div>`;
  }

  function attachCampaignActions() {
    $$('.view-campaign-details').forEach(b => b.onclick = (e) => { e.stopPropagation(); openCampaignDetails(b.dataset.id); });
    $$('.restore-now').forEach(b => b.onclick = (e) => { e.stopPropagation(); openRestore(b.dataset.id, b.dataset.name); });
    $$('.start-campaign-now').forEach(b => b.onclick = (e) => { e.stopPropagation(); startCampaignNow(b.dataset.id); });
    $$('.cancel-campaign').forEach(b => b.onclick = (e) => { e.stopPropagation(); cancelCampaign(b.dataset.id); });
    $$('.retry-campaign').forEach(b => b.onclick = (e) => { e.stopPropagation(); retryCampaign(b.dataset.id); });
  }

  async function startCampaignNow(id) {
    if (!window.confirm('Start this promotion now and apply discounted prices in Shopify immediately?')) return;
    try {
      const d = await api(`/campaigns/${id}/start-now`, { method: 'POST', body: '{}' });
      toast(d.message || 'Campaign started and live in store!');
      await loadDashboard();
    } catch (e) {
      toast(e.message);
    }
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
          <div class="polaris-row-title view-campaign-details" data-id="${esc(c.id)}" style="cursor:pointer;color:#008060;font-weight:600;" title="Click to view products and full campaign details">${esc(c.name)}</div>
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

    if (page === 'themes') {
      body.innerHTML = '<div class="polaris-empty-state">Loading store themes and countdown status…</div>';
      await loadThemesPage(body);
      return;
    }

    if (page === 'billing') {
      body.innerHTML = '<div class="polaris-empty-state">Loading billing details…</div>';
      await loadBillingPage(body);
      return;
    }

    if (page === 'settings') {
      body.innerHTML = '<div class="polaris-empty-state">Loading store settings…</div>';
      await loadSettingsPage(body);
      return;
    }
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

    // Render Compact Cart Items List
    const chipsBox = $('#bundle-selected-chips');
    if (chipsBox) {
      if (!bundleSelected.size) {
        chipsBox.innerHTML = '<div style="font-size:12px;color:#6d7175;padding:6px 0;">No products selected yet. Search below to add items to this bundle.</div>';
      } else {
        chipsBox.innerHTML = `
          <div style="display:flex;flex-direction:column;gap:6px;width:100%;">
            ${[...bundleSelected.values()].map(p => `
              <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding:6px 10px;background:#f6f6f7;border:1px solid #e1e3e5;border-radius:6px;">
                <div style="display:flex;align-items:center;gap:8px;min-width:0;flex:1;">
                  ${p.image ? `<img src="${esc(p.image)}" style="width:32px;height:32px;min-width:32px;max-width:32px;max-height:32px;border-radius:4px;object-fit:cover;flex-shrink:0;" alt="">` : '<div style="width:32px;height:32px;min-width:32px;max-width:32px;max-height:32px;background:#ddd;border-radius:4px;flex-shrink:0;"></div>'}
                  <div style="min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                    <div style="font-weight:600;font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${esc(p.title)}</div>
                    <div style="font-size:11px;color:#6d7175;">$${esc(p.price)}</div>
                  </div>
                </div>
                <button type="button" class="polaris-btn polaris-btn-plain" data-remove-bundle-id="${esc(p.id)}" style="color:#d72c0d;font-size:16px;padding:2px 6px;line-height:1;" title="Remove from bundle">×</button>
              </div>
            `).join('')}
          </div>
        `;

        $$('[data-remove-bundle-id]').forEach(b => {
          b.onclick = () => {
            bundleSelected.delete(b.dataset.removeBundleId);
            updateBundlePreview();
          };
        });
      }
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

      const pricingType = $('#bundle-pricing-type').value;
      const payload = {
        title: $('#bundle-title').value.trim(),
        product_ids: [...bundleSelected.keys()],
        pricing_type: pricingType,
        status: $('#bundle-status').value,
        custom_description: $('#bundle-custom-desc').value.trim(),
        tags: $('#bundle-tags').value.trim()
      };

      if (pricingType === 'percentage') {
        const pct = Number($('#bundle-discount-pct').value);
        if (isNaN(pct) || pct < 0 || pct > 99) {
          err.textContent = 'Please enter a valid discount percentage (0-99%).';
          err.classList.remove('hidden');
          return;
        }
        payload.discount_percent = pct;
      } else if (pricingType === 'fixed_price') {
        const fp = Number($('#bundle-fixed-price').value);
        if (isNaN(fp) || fp <= 0) {
          err.textContent = 'Please enter a valid bundle price greater than $0.';
          err.classList.remove('hidden');
          return;
        }
        payload.fixed_price = fp;
      } else if (pricingType === 'fixed_discount') {
        const fd = Number($('#bundle-fixed-discount').value);
        if (isNaN(fd) || fd <= 0) {
          err.textContent = 'Please enter a valid discount amount greater than $0.';
          err.classList.remove('hidden');
          return;
        }
        payload.fixed_discount = fd;
      }

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
        btn.textContent = 'Create Bundle in Shopify';
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
        btn.textContent = 'Create Bundle in Shopify';
      }
    }
  }

  // --- CAMPAIGN DETAILS DEEP VIEW ---
  let currentCampaignDetails = null;

  async function openCampaignDetails(campaignId) {
    const dialog = $('#campaign-details-dialog');
    if (!dialog) return;

    $('#cd-title').textContent = 'Loading Campaign…';
    $('#cd-status-badge').innerHTML = '';
    $('#cd-discount-val').textContent = '—';
    $('#cd-schedule-val').textContent = '—';
    $('#cd-products-count').textContent = '—';
    $('#cd-snapshot-status').textContent = 'Checking…';
    $('#cd-actions-bar').innerHTML = '';
    $('#cd-prod-count-inline').textContent = '0';
    $('#cd-products-container').innerHTML = '<div class="polaris-empty-picker">Loading campaign products and price snapshots…</div>';
    $('#cd-snapshots-logs-container').innerHTML = '<div class="polaris-empty-picker">Loading audit logs…</div>';

    dialog.showModal();

    try {
      const data = await api(`/campaigns/${campaignId}`);
      currentCampaignDetails = data;
      const c = data.campaign;
      const products = data.products || [];
      const snapshots = data.snapshots || [];
      const logs = data.logs || [];

      $('#cd-title').textContent = c.name;
      $('#cd-status-badge').innerHTML = statusBadge(c.status);

      const discountPct = c.actions?.price_percent ? `${c.actions.price_percent}% OFF` : (c.actions?.add_tag ? `Tag: ${c.actions.add_tag}` : 'Custom Edits');
      $('#cd-discount-val').textContent = discountPct;
      $('#cd-schedule-val').textContent = `${formatDate(c.starts_at)} → ${formatDate(c.ends_at)}`;
      $('#cd-products-count').textContent = `${products.length} Products`;
      $('#cd-prod-count-inline').textContent = products.length;

      const hasConflicts = snapshots.some(s => (s.conflicts && s.conflicts.length > 0));
      if (hasConflicts) {
        $('#cd-snapshot-status').innerHTML = '<span style="color:#d72c0d;">⚠️ Conflicts Detected</span>';
      } else if (c.snapshot_complete) {
        $('#cd-snapshot-status').innerHTML = '<span style="color:#0e5b38;">✓ 100% Snapshotted &amp; Safe</span>';
      } else {
        $('#cd-snapshot-status').innerHTML = '<span style="color:#6d7175;">Pending Snapshot</span>';
      }

      // Actions toolbar
      let actionsHtml = `<div style="display:flex;gap:8px;flex-wrap:wrap;">`;
      if (c.can_start_now) {
        actionsHtml += `<button type="button" class="polaris-btn polaris-btn-primary cd-action-start" data-id="${c.id}">⚡ Start Campaign Now</button>`;
      }
      if (c.can_restore) {
        actionsHtml += `<button type="button" class="polaris-btn polaris-btn-destructive cd-action-restore" data-id="${c.id}" data-name="${esc(c.name)}">↺ Rollback &amp; Restore Prices</button>`;
      }
      if (c.can_retry) {
        actionsHtml += `<button type="button" class="polaris-btn polaris-btn-primary cd-action-retry" data-id="${c.id}">↻ Retry Preflight</button>`;
      }
      if (c.can_cancel) {
        actionsHtml += `<button type="button" class="polaris-btn cd-action-cancel" data-id="${c.id}">Cancel Campaign</button>`;
      }
      actionsHtml += `</div><div style="display:flex;gap:8px;flex-wrap:wrap;">`;
      actionsHtml += `<button type="button" class="polaris-btn cd-action-theme" data-id="${c.id}">⚡ Publish Theme with Countdown</button>`;
      actionsHtml += `<button type="button" class="polaris-btn cd-action-dup" data-id="${c.id}">📋 Duplicate</button>`;
      actionsHtml += `</div>`;

      $('#cd-actions-bar').innerHTML = actionsHtml;

      // Attach actions inside modal
      const btnStart = $('#cd-actions-bar .cd-action-start');
      if (btnStart) btnStart.onclick = async () => { dialog.close(); await startCampaignNow(c.id); };

      const btnRestore = $('#cd-actions-bar .cd-action-restore');
      if (btnRestore) btnRestore.onclick = () => { dialog.close(); openRestore(c.id, c.name); };

      const btnRetry = $('#cd-actions-bar .cd-action-retry');
      if (btnRetry) btnRetry.onclick = async () => { dialog.close(); await retryCampaign(c.id); };

      const btnCancel = $('#cd-actions-bar .cd-action-cancel');
      if (btnCancel) btnCancel.onclick = async () => { dialog.close(); await cancelCampaign(c.id); };

      const btnTheme = $('#cd-actions-bar .cd-action-theme');
      if (btnTheme) btnTheme.onclick = () => { dialog.close(); openThemePublishModal(c.id); };

      const btnDup = $('#cd-actions-bar .cd-action-dup');
      if (btnDup) btnDup.onclick = () => {
        dialog.close();
        openCreateWithProducts(products);
      };

      // Render product cards / table
      renderCampaignProductsList(products);

      // Filter products input
      $('#cd-product-filter').oninput = (e) => {
        const q = e.target.value.toLowerCase().trim();
        const filtered = products.filter(p => p.title.toLowerCase().includes(q) || p.id.toLowerCase().includes(q));
        renderCampaignProductsList(filtered);
      };

      // Render Logs
      $('#cd-snapshots-logs-container').innerHTML = `
        <div style="overflow-x:auto;">
          <table class="polaris-table">
            <thead>
              <tr><th>EVENT</th><th>TIMESTAMP</th><th>LEVEL</th><th>DETAILS</th></tr>
            </thead>
            <tbody>
              ${logs.length ? logs.map(l => `
                <tr>
                  <td><strong>${esc(l.event.replaceAll('_', ' '))}</strong></td>
                  <td>${formatDate(l.created_at)}</td>
                  <td><span class="polaris-badge polaris-badge-neutral">${esc(l.severity)}</span></td>
                  <td><code>${esc(JSON.stringify(l.details || {}))}</code></td>
                </tr>
              `).join('') : '<tr><td colspan="4" class="polaris-empty-picker">No audit logs recorded for this campaign yet.</td></tr>'}
            </tbody>
          </table>
        </div>
      `;

    } catch (e) {
      $('#cd-title').textContent = 'Error loading campaign';
      $('#cd-products-container').innerHTML = '<div class="polaris-empty-picker">' + esc(e.message) + '</div>';
    }
  }
  window.pmOpenCampaignDetails = openCampaignDetails;

  function renderCampaignProductsList(products) {
    const box = $('#cd-products-container');
    if (!products.length) {
      box.innerHTML = '<div class="polaris-empty-picker">No products matched the filter.</div>';
      return;
    }

    box.innerHTML = `
      <div style="overflow-x:auto;">
        <table class="polaris-table">
          <thead>
            <tr>
              <th>PRODUCT</th>
              <th>ORIGINAL PRICE</th>
              <th>SALE PRICE</th>
              <th>SAVINGS</th>
              <th>SNAPSHOT INTEGRITY</th>
              <th>SHOPIFY ADMIN</th>
            </tr>
          </thead>
          <tbody>
            ${products.map(p => {
              const orig = Number(p.original_price || 0);
              const sale = Number(p.sale_price || 0);
              const diff = Math.max(0, orig - sale);
              const diffPct = orig > 0 ? Math.round((diff / orig) * 100) : 0;
              const hasMultiVariants = (p.variants || []).length > 1;

              return `
                <tr>
                  <td>
                    <div style="display:flex;align-items:center;gap:10px;">
                      ${p.image ? `<img src="${esc(p.image)}" class="polaris-product-thumb" alt="">` : '<div class="polaris-product-thumb"></div>'}
                      <div>
                        <strong>${esc(p.title)}</strong>
                        <div class="polaris-row-meta">${p.variants_count || 1} variant(s) · ${esc(p.status)}</div>
                      </div>
                    </div>
                  </td>
                  <td><strong>$${esc(p.original_price)}</strong></td>
                  <td><strong style="color:#008060;">$${esc(p.sale_price)}</strong></td>
                  <td>${diff > 0 ? `<span class="polaris-badge polaris-badge-success">-$${diff.toFixed(2)} (${diffPct}%)</span>` : '—'}</td>
                  <td>
                    ${p.snapshot_status === 'applied' ? '<span class="polaris-badge polaris-badge-success">Applied in Store</span>' :
                      (p.snapshot_status === 'restored' ? '<span class="polaris-badge polaris-badge-neutral">Restored to Original</span>' :
                      (p.snapshot_status === 'snapshotted' ? '<span class="polaris-badge polaris-badge-warning">Snapshot Saved</span>' :
                      `<span class="polaris-badge polaris-badge-neutral">${esc(p.snapshot_status)}</span>`))}
                  </td>
                  <td><a href="${esc(p.admin_url)}" target="_blank" class="polaris-btn polaris-btn-plain">Open in Admin ↗</a></td>
                </tr>
                ${hasMultiVariants ? `
                  <tr style="background:#fafbfb;">
                    <td colspan="6" style="padding:6px 16px 10px 48px;font-size:11px;">
                      <div style="color:#6d7175;font-weight:600;margin-bottom:4px;">Variants Breakdown:</div>
                      <div style="display:flex;flex-wrap:wrap;gap:8px;">
                        ${p.variants.map(v => `
                          <div style="background:#fff;border:1px solid #e1e3e5;border-radius:4px;padding:3px 8px;">
                            <strong>${esc(v.title)}:</strong> <strike>$${esc(v.original_price)}</strike> → <strong style="color:#008060;">$${esc(v.sale_price)}</strong>
                          </div>
                        `).join('')}
                      </div>
                    </td>
                  </tr>
                ` : ''}
              `;
            }).join('')}
          </tbody>
        </table>
      </div>
    `;
  }

  // --- THEME COPY & COUNTDOWN PUBLISHING ---
  let storeThemesList = [];

  async function openThemePublishModal(campaignId = null) {
    const dialog = $('#theme-publish-dialog');
    if (!dialog) return;

    $('#theme-publish-error').classList.add('hidden');
    const select = $('#theme-source-select');
    select.innerHTML = '<option value="">Loading store themes from Shopify…</option>';

    dialog.showModal();

    try {
      const data = await api('/themes');
      storeThemesList = data.themes || [];

      if (!storeThemesList.length) {
        select.innerHTML = '<option value="">No themes found in store.</option>';
      } else {
        select.innerHTML = storeThemesList.map(t => `
          <option value="${esc(t.id)}" ${t.is_main ? 'selected' : ''}>
            ${esc(t.name)} ${t.is_main ? '(Live Active Theme)' : '(Unpublished Theme)'}
          </option>
        `).join('');
      }

      let targetCampaign = null;
      if (campaignId) {
        targetCampaign = (dashboard?.campaigns || []).find(c => c.id == campaignId);
      }
      if (!targetCampaign) {
        targetCampaign = (dashboard?.campaigns || []).find(c => ['running', 'scheduled'].includes(c.status));
      }

      const activeTheme = storeThemesList.find(t => t.is_main) || storeThemesList[0];
      const themeName = activeTheme?.name || 'Dawn';
      $('#theme-copy-name').value = `[SaleSnap Flash Sale] ${themeName} with Countdown`;

      if (targetCampaign) {
        const discountPct = targetCampaign.actions?.price_percent || 20;
        $('#theme-bar-headline').value = `⚡ FLASH SALE IS LIVE! Extra ${discountPct}% Off Selected Items`;
      }

      updateThemePreviewFromInputs();

      $('#theme-bar-headline').oninput = updateThemePreviewFromInputs;
      $('#theme-bar-subtext').oninput = updateThemePreviewFromInputs;
      $('#theme-btn-text').oninput = updateThemePreviewFromInputs;
      $('#theme-btn-url').oninput = updateThemePreviewFromInputs;

      $('#theme-bg-color-picker').oninput = (e) => { $('#theme-bg-color-text').value = e.target.value; updateThemePreviewFromInputs(); };
      $('#theme-bg-color-text').oninput = (e) => { $('#theme-bg-color-picker').value = e.target.value; updateThemePreviewFromInputs(); };

      $('#theme-text-color-picker').oninput = (e) => { $('#theme-text-color-text').value = e.target.value; updateThemePreviewFromInputs(); };
      $('#theme-text-color-text').oninput = (e) => { $('#theme-text-color-picker').value = e.target.value; updateThemePreviewFromInputs(); };

      $('#theme-accent-color-picker').oninput = (e) => { $('#theme-accent-color-text').value = e.target.value; updateThemePreviewFromInputs(); };
      $('#theme-accent-color-text').oninput = (e) => { $('#theme-accent-color-picker').value = e.target.value; updateThemePreviewFromInputs(); };

      $('#theme-publish-form').onsubmit = async (e) => {
        e.preventDefault();
        await submitThemePublish(campaignId);
      };

    } catch (e) {
      $('#theme-publish-error').textContent = e.message;
      $('#theme-publish-error').classList.remove('hidden');
    }
  }
  window.pmOpenThemePublishModal = openThemePublishModal;

  function updateThemePreviewFromInputs() {
    const headline = $('#theme-bar-headline')?.value || '⚡ FLASH SALE IS LIVE!';
    const subtext = $('#theme-bar-subtext')?.value || 'Limited time store promotion.';
    const btnText = $('#theme-btn-text')?.value || 'Shop Deals Now';
    const bgColor = $('#theme-bg-color-text')?.value || '#111827';
    const textColor = $('#theme-text-color-text')?.value || '#ffffff';
    const accentColor = $('#theme-accent-color-text')?.value || '#f59e0b';

    const banner = $('#countdown-banner-live-preview');
    if (banner) {
      banner.style.background = bgColor;
      banner.style.color = textColor;
      banner.style.borderBottomColor = accentColor;
    }

    if ($('#prev-headline')) $('#prev-headline').textContent = headline;
    if ($('#prev-subtext')) $('#prev-subtext').textContent = subtext;
    if ($('#prev-btn')) {
      $('#prev-btn').textContent = btnText + ' →';
      $('#prev-btn').style.background = accentColor;
      $('#prev-btn').style.color = bgColor === '#ffffff' ? '#111' : '#111827';
    }

    ['#prev-d', '#prev-h', '#prev-m', '#prev-s'].forEach(id => {
      const el = $(id);
      if (el) el.style.color = accentColor;
    });
  }

  async function submitThemePublish(campaignId) {
    const err = $('#theme-publish-error');
    err.classList.add('hidden');
    const btn = $('#theme-publish-submit-btn');
    btn.disabled = true;
    btn.textContent = 'Duplicating theme in Shopify…';

    const sourceThemeId = $('#theme-source-select').value;
    const copyName = $('#theme-copy-name').value.trim();
    const shouldPublish = $('#theme-auto-publish-check').checked;

    try {
      const resDup = await api('/themes/duplicate', {
        method: 'POST',
        body: JSON.stringify({
          source_theme_id: sourceThemeId,
          name: copyName,
          with_countdown: true,
          campaign_id: campaignId || null
        })
      });

      const newThemeId = resDup.theme?.id;

      if (shouldPublish && newThemeId) {
        btn.textContent = 'Publishing as live storefront theme…';
        await api('/themes/publish', {
          method: 'POST',
          body: JSON.stringify({
            theme_id: newThemeId,
            campaign_id: campaignId || null
          })
        });
      }

      $('#theme-publish-dialog').close();
      toast(shouldPublish ? 'Theme duplicated with countdown and published live!' : 'Promo theme copy created successfully in Shopify!');
      setPage('themes');
    } catch (e) {
      err.textContent = e.message;
      err.classList.remove('hidden');
    } finally {
      btn.disabled = false;
      btn.textContent = 'Duplicate & Publish Theme Copy';
    }
  }

  // --- SETTINGS PAGE ---
  async function loadSettingsPage(container) {
    container.innerHTML = '<div class="polaris-empty-state">Loading store settings…</div>';
    try {
      const [settingsRes, themesRes] = await Promise.all([
        api('/settings'),
        api('/themes')
      ]);

      const s = settingsRes.settings || {};
      const themes = themesRes.themes || [];
      const mainTheme = themes.find(t => t.is_main) || themes[0];
      const hasPreviousTheme = Boolean(settingsRes.published_theme_id);

      container.innerHTML = `
        <div style="display:flex;flex-direction:column;gap:20px;">
          
          <!-- Card 1: Pricing & Discount Safeguards -->
          <div class="polaris-card" style="padding:20px;">
            <h3 class="polaris-heading" style="margin-top:0;">1. Pricing &amp; Discount Safeguards</h3>
            <p class="polaris-text-subdued" style="margin-bottom:16px;">Set safety boundaries and psychological charm pricing rules for all promotions.</p>

            <div class="polaris-settings-grid">
              <div class="polaris-form-group">
                <label class="polaris-label" for="setting-max-discount">Maximum Discount Safety Cap (%)</label>
                <div class="polaris-inline-field">
                  <input class="polaris-input polaris-input-inline" type="number" id="setting-max-discount" min="5" max="95" value="${esc(s.max_discount_cap ?? 80)}">
                  <span>% off maximum limit</span>
                </div>
                <div class="polaris-row-meta" style="margin-top:4px;">Prevents accidental pricing errors (e.g. 99% off typo).</div>
              </div>

              <div class="polaris-form-group">
                <label class="polaris-label" for="setting-price-rounding">Sale Price Rounding Strategy</label>
                <select class="polaris-input" id="setting-price-rounding">
                  <option value="none" ${s.price_rounding === 'none' ? 'selected' : ''}>Exact Calculated Cents (e.g. $19.42)</option>
                  <option value="99" ${s.price_rounding === '99' ? 'selected' : ''}>Round to .99 Charm Price (e.g. $19.99)</option>
                  <option value="95" ${s.price_rounding === '95' ? 'selected' : ''}>Round to .95 Charm Price (e.g. $19.95)</option>
                  <option value="round_dollar" ${s.price_rounding === 'round_dollar' ? 'selected' : ''}>Round to Nearest Dollar (e.g. $20.00)</option>
                </select>
                <div class="polaris-row-meta" style="margin-top:4px;">Automatically rounds discounted prices to convert higher.</div>
              </div>
            </div>

            <div class="polaris-form-group" style="margin-bottom:0;">
              <label class="polaris-label" for="setting-compare-at">Compare-at Price Display</label>
              <select class="polaris-input" id="setting-compare-at">
                <option value="set_original" ${s.compare_at_mode === 'set_original' ? 'selected' : ''}>Set Original Price as Compare-At Price (Shows strikethrough & "Sale" badge on storefront)</option>
                <option value="leave_unchanged" ${s.compare_at_mode === 'leave_unchanged' ? 'selected' : ''}>Leave Compare-At Price Untouched</option>
              </select>
            </div>
          </div>

          <!-- Card 2: Campaign Defaults & Snapshot Retention -->
          <div class="polaris-card" style="padding:20px;">
            <h3 class="polaris-heading" style="margin-top:0;">2. Campaign Defaults &amp; Snapshot Safeguards</h3>
            <p class="polaris-text-subdued" style="margin-bottom:16px;">Configure default parameters for new flash sale campaigns.</p>

            <div class="polaris-settings-grid">
              <div class="polaris-form-group">
                <label class="polaris-label" for="setting-default-tag">Default Promotional Product Tag</label>
                <input class="polaris-input" id="setting-default-tag" value="${esc(s.default_tag ?? 'salessnap-sale')}" placeholder="e.g. flash-sale">
              </div>

              <div class="polaris-form-group">
                <label class="polaris-label" for="setting-default-prefix">Default Description Prefix Banner</label>
                <input class="polaris-input" id="setting-default-prefix" value="${esc(s.default_desc_prefix ?? '🔥 Flash Sale Exclusive: ')}" placeholder="e.g. 🔥 Flash Sale Exclusive: ">
              </div>
            </div>

            <div class="polaris-settings-grid">
              <div class="polaris-form-group">
                <label class="polaris-label" for="setting-retention-days">Snapshot Retention Period</label>
                <select class="polaris-input" id="setting-retention-days">
                  <option value="30" ${s.snapshot_retention_days == 30 ? 'selected' : ''}>30 Days</option>
                  <option value="60" ${s.snapshot_retention_days == 60 ? 'selected' : ''}>60 Days</option>
                  <option value="90" ${s.snapshot_retention_days == 90 ? 'selected' : ''}>90 Days (Recommended)</option>
                  <option value="180" ${s.snapshot_retention_days == 180 ? 'selected' : ''}>180 Days</option>
                  <option value="365" ${s.snapshot_retention_days == 365 ? 'selected' : ''}>1 Year</option>
                </select>
              </div>

              <div class="polaris-form-group" style="display:flex;align-items:flex-end;">
                <label class="polaris-checkbox-label" style="padding-bottom:10px;">
                  <input type="checkbox" id="setting-auto-restore" ${s.auto_restore_on_end !== false ? 'checked' : ''}>
                  <span><strong>Auto-restore product prices immediately</strong> when campaign schedule ends.</span>
                </label>
              </div>
            </div>
          </div>

          <!-- Card 3: Theme Publishing & Countdown Bar Settings -->
          <div class="polaris-card" style="padding:20px;">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:12px;">
              <div>
                <h3 class="polaris-heading" style="margin:0;">3. Theme Copy &amp; Countdown Announcement Bar</h3>
                <p class="polaris-text-subdued" style="margin:2px 0 0;">Create theme copies with live ticking countdown bars and publish them during sales.</p>
              </div>
              <button type="button" class="polaris-btn polaris-btn-primary" onclick="window.pmOpenThemePublishModal()">⚡ Duplicate &amp; Publish Theme Now</button>
            </div>

            <div class="polaris-banner polaris-banner-info" style="margin-bottom:14px;">
              <div class="polaris-banner-icon">ℹ</div>
              <div class="polaris-banner-content">
                <strong>Current Live Store Theme: ${esc(mainTheme?.name || 'Active Theme')}</strong>
                ${hasPreviousTheme ? ` · <button type="button" class="polaris-btn polaris-btn-plain" id="setting-revert-theme-btn" style="color:#d72c0d;font-weight:600;">↺ Revert to Previous Original Theme</button>` : ''}
              </div>
            </div>

            <div class="polaris-settings-grid">
              <div class="polaris-form-group">
                <label class="polaris-label" for="setting-cd-headline">Banner Headline Template</label>
                <input class="polaris-input" id="setting-cd-headline" value="${esc(s.countdown_headline ?? '⚡ FLASH SALE IS LIVE! Extra %discount%% Off Selected Items')}">
                <div class="polaris-row-meta" style="margin-top:4px;">Use <code>%discount%</code> to dynamically inject active campaign discount rate.</div>
              </div>

              <div class="polaris-form-group">
                <label class="polaris-label" for="setting-cd-subtext">Urgency Subtext</label>
                <input class="polaris-input" id="setting-cd-subtext" value="${esc(s.countdown_subtext ?? 'Limited time store promotion. Discounts auto-applied in cart.')}">
              </div>
            </div>

            <div class="polaris-settings-grid">
              <div class="polaris-form-group">
                <label class="polaris-label">Banner Colors (Background / Text / Accent)</label>
                <div style="display:flex;gap:12px;flex-wrap:wrap;">
                  <div class="polaris-color-input-wrapper">
                    <input type="color" id="setting-bg-color" class="polaris-color-picker" value="${esc(s.countdown_bg_color ?? '#111827')}">
                    <input class="polaris-input" id="setting-bg-color-text" value="${esc(s.countdown_bg_color ?? '#111827')}" style="width:85px;font-family:monospace;font-size:12px;">
                  </div>
                  <div class="polaris-color-input-wrapper">
                    <input type="color" id="setting-text-color" class="polaris-color-picker" value="${esc(s.countdown_text_color ?? '#ffffff')}">
                    <input class="polaris-input" id="setting-text-color-text" value="${esc(s.countdown_text_color ?? '#ffffff')}" style="width:85px;font-family:monospace;font-size:12px;">
                  </div>
                  <div class="polaris-color-input-wrapper">
                    <input type="color" id="setting-accent-color" class="polaris-color-picker" value="${esc(s.countdown_accent_color ?? '#f59e0b')}">
                    <input class="polaris-input" id="setting-accent-color-text" value="${esc(s.countdown_accent_color ?? '#f59e0b')}" style="width:85px;font-family:monospace;font-size:12px;">
                  </div>
                </div>
              </div>

              <div class="polaris-form-group">
                <label class="polaris-label" for="setting-cd-position">Bar Position</label>
                <select class="polaris-input" id="setting-cd-position">
                  <option value="top_sticky" ${s.countdown_position === 'top_sticky' ? 'selected' : ''}>Sticky Top Header Announcement Bar</option>
                  <option value="bottom_sticky" ${s.countdown_position === 'bottom_sticky' ? 'selected' : ''}>Sticky Bottom Footer Bar</option>
                </select>
              </div>
            </div>

            <div class="polaris-settings-grid">
              <div class="polaris-form-group">
                <label class="polaris-label" for="setting-btn-text">CTA Button Text</label>
                <input class="polaris-input" id="setting-btn-text" value="${esc(s.countdown_btn_text ?? 'Shop Deals Now')}">
              </div>
              <div class="polaris-form-group">
                <label class="polaris-label" for="setting-btn-url">CTA Button Link</label>
                <input class="polaris-input" id="setting-btn-url" value="${esc(s.countdown_btn_url ?? '/collections/all')}">
              </div>
            </div>
          </div>

          <!-- Card 4: Conflict Alerts & Webhooks -->
          <div class="polaris-card" style="padding:20px;">
            <h3 class="polaris-heading" style="margin-top:0;">4. Safety Alerts &amp; Webhook Integrations</h3>
            <div class="polaris-settings-grid">
              <div class="polaris-form-group">
                <label class="polaris-checkbox-label">
                  <input type="checkbox" id="setting-notify-conflict" ${s.notify_on_conflict !== false ? 'checked' : ''}>
                  <span><strong>Alert on live product conflicts</strong> (if a product price is edited manually in Shopify admin during sale).</span>
                </label>
              </div>
              <div class="polaris-form-group">
                <label class="polaris-label" for="setting-webhook-url">External Webhook URL (Optional)</label>
                <input class="polaris-input" id="setting-webhook-url" value="${esc(s.webhook_url ?? '')}" placeholder="https://your-server.com/salessnap-webhook">
              </div>
            </div>
          </div>

          <!-- Save Button Bar -->
          <div style="display:flex;justify-content:flex-end;gap:12px;">
            <button type="button" class="polaris-btn polaris-btn-primary" id="save-settings-btn" style="padding:10px 24px;font-size:13px;font-weight:600;">Save Store Settings</button>
          </div>
        </div>
      `;

      $('#setting-bg-color').oninput = e => { $('#setting-bg-color-text').value = e.target.value; };
      $('#setting-bg-color-text').oninput = e => { $('#setting-bg-color').value = e.target.value; };
      $('#setting-text-color').oninput = e => { $('#setting-text-color-text').value = e.target.value; };
      $('#setting-text-color-text').oninput = e => { $('#setting-text-color').value = e.target.value; };
      $('#setting-accent-color').oninput = e => { $('#setting-accent-color-text').value = e.target.value; };
      $('#setting-accent-color-text').oninput = e => { $('#setting-accent-color').value = e.target.value; };

      const btnRevert = $('#setting-revert-theme-btn');
      if (btnRevert) {
        btnRevert.onclick = async () => {
          if (!window.confirm('Restore your original live theme now?')) return;
          btnRevert.disabled = true;
          try {
            await api('/themes/revert', { method: 'POST', body: '{}' });
            toast('Original theme restored as live active storefront theme.');
            await loadSettingsPage(container);
          } catch (e) {
            toast(e.message);
            btnRevert.disabled = false;
          }
        };
      }

      $('#save-settings-btn').onclick = async () => {
        const btn = $('#save-settings-btn');
        btn.disabled = true;
        btn.textContent = 'Saving Settings…';

        const payload = {
          max_discount_cap: Number($('#setting-max-discount').value),
          price_rounding: $('#setting-price-rounding').value,
          compare_at_mode: $('#setting-compare-at').value,
          default_tag: $('#setting-default-tag').value.trim(),
          default_desc_prefix: $('#setting-default-prefix').value.trim(),
          snapshot_retention_days: Number($('#setting-retention-days').value),
          auto_restore_on_end: $('#setting-auto-restore').checked,
          countdown_enabled: true,
          countdown_position: $('#setting-cd-position').value,
          countdown_headline: $('#setting-cd-headline').value.trim(),
          countdown_subtext: $('#setting-cd-subtext').value.trim(),
          countdown_bg_color: $('#setting-bg-color-text').value.trim(),
          countdown_text_color: $('#setting-text-color-text').value.trim(),
          countdown_accent_color: $('#setting-accent-color-text').value.trim(),
          countdown_btn_text: $('#setting-btn-text').value.trim(),
          countdown_btn_url: $('#setting-btn-url').value.trim(),
          notify_on_conflict: $('#setting-notify-conflict').checked,
          webhook_url: $('#setting-webhook-url').value.trim() || null
        };

        try {
          const res = await api('/settings', { method: 'POST', body: JSON.stringify(payload) });
          toast(res.message || 'Settings saved successfully!');
        } catch (e) {
          toast(e.message);
        } finally {
          btn.disabled = false;
          btn.textContent = 'Save Store Settings';
        }
      };

    } catch (e) {
      container.innerHTML = '<div class="polaris-empty-state">' + esc(e.message) + '</div>';
    }
  }

  // --- THEMES & COUNTDOWN PUBLISHING PAGE ---
  async function loadThemesPage(container) {
    container.innerHTML = '<div class="polaris-empty-state">Loading store themes from Shopify…</div>';
    try {
      const data = await api('/themes');
      const themes = data.themes || [];
      const canRevert = data.can_revert;

      container.innerHTML = `
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
          <div>
            <h3 class="polaris-heading" style="margin:0;">Store Themes &amp; Countdown Banners</h3>
            <p class="polaris-text-subdued" style="margin:2px 0 0;">Create safe duplicate theme copies with live countdown timers and publish them during active promotions.</p>
          </div>
          <div style="display:flex;gap:8px;">
            ${canRevert ? `<button type="button" class="polaris-btn" id="theme-page-revert-btn" style="color:#d72c0d;">↺ Restore Original Theme</button>` : ''}
            <button type="button" class="polaris-btn polaris-btn-primary" onclick="window.pmOpenThemePublishModal()">⚡ Duplicate Theme with Countdown</button>
          </div>
        </div>

        <div id="themes-list-container">
          ${themes.length ? themes.map(t => `
            <div class="polaris-theme-card ${t.is_main ? 'main-theme' : (t.is_salessnap_copy ? 'promo-theme' : '')}">
              <div style="flex:1;min-width:240px;">
                <div style="display:flex;align-items:center;gap:8px;">
                  <strong style="font-size:14px;">${esc(t.name)}</strong>
                  ${t.is_main ? '<span class="polaris-badge polaris-badge-success">Live Published Theme</span>' : '<span class="polaris-badge polaris-badge-neutral">Unpublished</span>'}
                  ${t.is_salessnap_copy ? '<span class="polaris-badge polaris-badge-warning">SaleSnap Promo Copy</span>' : ''}
                </div>
                <div class="polaris-row-meta" style="margin-top:4px;">Theme ID: ${esc(t.id)} · Last updated: ${formatDate(t.updated_at)}</div>
              </div>

              <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                <a href="${esc(t.preview_url)}" target="_blank" class="polaris-btn polaris-btn-plain">Preview Store ↗</a>
                <a href="${esc(t.admin_url)}" target="_blank" class="polaris-btn polaris-btn-plain">Theme Editor ↗</a>
                ${!t.is_main ? `
                  <button type="button" class="polaris-btn polaris-btn-primary publish-single-theme-btn" data-id="${esc(t.id)}" data-name="${esc(t.name)}">Publish as Live Theme</button>
                ` : `
                  <button type="button" class="polaris-btn inject-countdown-single-btn" data-id="${esc(t.id)}">⚡ Update Countdown Bar</button>
                `}
              </div>
            </div>
          `).join('') : '<div class="polaris-empty-state">No themes detected. Ensure read_themes scope is granted in Shopify.</div>'}
        </div>
      `;

      const btnRevert = $('#theme-page-revert-btn');
      if (btnRevert) {
        btnRevert.onclick = async () => {
          if (!window.confirm('Restore your original live theme now?')) return;
          btnRevert.disabled = true;
          try {
            await api('/themes/revert', { method: 'POST', body: '{}' });
            toast('Original theme restored as live active storefront theme.');
            await loadThemesPage(container);
          } catch (e) {
            toast(e.message);
            btnRevert.disabled = false;
          }
        };
      }

      $$('.publish-single-theme-btn').forEach(b => {
        b.onclick = async () => {
          if (!window.confirm(`Publish "${b.dataset.name}" as your active live storefront theme now?`)) return;
          b.disabled = true;
          b.textContent = 'Publishing…';
          try {
            await api('/themes/publish', { method: 'POST', body: JSON.stringify({ theme_id: b.dataset.id }) });
            toast(`Published "${b.dataset.name}" as live storefront theme!`);
            await loadThemesPage(container);
          } catch (e) {
            toast(e.message);
            b.disabled = false;
            b.textContent = 'Publish as Live Theme';
          }
        };
      });

      $$('.inject-countdown-single-btn').forEach(b => {
        b.onclick = async () => {
          b.disabled = true;
          b.textContent = 'Updating…';
          try {
            await api('/themes/inject', { method: 'POST', body: JSON.stringify({ theme_id: b.dataset.id }) });
            toast('Countdown banner updated on live theme!');
          } catch (e) {
            toast(e.message);
          } finally {
            b.disabled = false;
            b.textContent = '⚡ Update Countdown Bar';
          }
        };
      });

    } catch (e) {
      container.innerHTML = '<div class="polaris-empty-state">' + esc(e.message) + '</div>';
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
