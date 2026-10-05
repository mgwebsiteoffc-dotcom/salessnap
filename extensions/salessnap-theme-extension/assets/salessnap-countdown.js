/**
 * SaleSnap Countdown Announcement Bar & Bundle Script
 * Client-side synchronized timer component
 */
(function() {
  function initSaleSnapCountdown() {
    const banner = document.getElementById('salessnap-countdown-banner');
    if (!banner) return;

    const elD = document.getElementById('ss-days');
    const elH = document.getElementById('ss-hours');
    const elM = document.getElementById('ss-mins');
    const elS = document.getElementById('ss-secs');

    const targetDateStr = banner.getAttribute('data-ends-at') || '2026-12-31T23:59:59Z';
    let target = new Date(targetDateStr).getTime();
    if (isNaN(target)) {
      target = Date.now() + (48 * 3600 * 1000);
    }

    function tick() {
      const now = Date.now();
      const diff = Math.max(0, target - now);

      const d = Math.floor(diff / (1000 * 60 * 60 * 24));
      const h = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
      const m = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
      const s = Math.floor((diff % (1000 * 60)) / 1000);

      if (elD) elD.textContent = String(d).padStart(2, '0');
      if (elH) elH.textContent = String(h).padStart(2, '0');
      if (elM) elM.textContent = String(m).padStart(2, '0');
      if (elS) elS.textContent = String(s).padStart(2, '0');

      if (diff <= 0) {
        banner.style.transition = 'opacity 0.4s ease';
        banner.style.opacity = '0.75';
      }
    }

    tick();
    setInterval(tick, 1000);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSaleSnapCountdown);
  } else {
    initSaleSnapCountdown();
  }
})();
