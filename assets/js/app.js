/**
 * DRXStore v1.0 — App JavaScript
 * Developed by Vineet
 */

/* ── Modal helpers (needed early by inline onclick) ── */
function openModal(id)  { var el = document.getElementById(id); if (el) el.classList.add('open'); }
function closeModal(id) { var el = document.getElementById(id); if (el) el.classList.remove('open'); }

/* ── Bar chart ── */
function drawBarChart(containerId, values, color) {
    var el = document.getElementById(containerId);
    if (!el || !values || !values.length) return;
    var max = Math.max.apply(null, values.concat([1]));
    var html = '';
    for (var i = 0; i < values.length; i++) {
        var h  = Math.max(4, Math.round((values[i] / max) * 88));
        var bg = color || '#0a2342';
        html  += '<div style="flex:1;height:' + h + 'px;background:' + bg + ';border-radius:4px 4px 0 0;opacity:.78;transition:opacity .15s;cursor:default" title="\u20b9' + Number(values[i]).toLocaleString('en-IN') + '" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=.78"></div>';
    }
    el.style.display     = 'flex';
    el.style.alignItems  = 'flex-end';
    el.style.gap         = '3px';
    el.innerHTML         = html;
}

/* ── Payment method toggle ── */
function togglePayment() {
    var method = document.getElementById('payment_method');
    if (!method) return;
    document.querySelectorAll('.pay-detail').forEach(function (el) { el.style.display = 'none'; });
    var el = document.getElementById('pay_' + method.value);
    if (el) el.style.display = 'block';
}

/* ── PO row add ── */
var _poIdx = 1;
function addPORow(medOpts) {
    var i   = _poIdx++;
    var div = document.createElement('div');
    div.className  = 'po-item-row';
    div.style.cssText = 'display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:8px;margin-bottom:8px;align-items:end';
    div.innerHTML =
        '<div><select class="form-control" name="items[' + i + '][medicine_id]"><option value="">\u2014</option>' + medOpts + '</select></div>' +
        '<div><input class="form-control" type="number" name="items[' + i + '][qty]" min="1" placeholder="Qty"></div>' +
        '<div><input class="form-control" type="number" step="0.01" name="items[' + i + '][price]" placeholder="\u20b9 Price"></div>' +
        '<div style="padding-top:22px"><button type="button" class="btn btn-ghost btn-sm" style="color:var(--red)" onclick="if(document.querySelectorAll(\'.po-item-row\').length>1)this.closest(\'.po-item-row\').remove()">\u2715</button></div>';
    var rows = document.getElementById('poRows');
    if (rows) rows.appendChild(div);
}

/* ── Batch loader for POS ── */
function loadBatches() {
    var med = document.getElementById('medicine_id');
    var bat = document.getElementById('batch_id');
    var st  = document.getElementById('stock_display');
    var mr  = document.getElementById('mrp_display');
    if (!med || !bat) return;
    bat.innerHTML = '<option value="">Loading\u2026</option>';
    if (st)  st.textContent = '\u2014';
    if (mr) mr.textContent  = '\u2014';
    if (!med.value) { bat.innerHTML = '<option value="">Select medicine first</option>'; return; }
    fetch('index.php?p=get_batches&mid=' + encodeURIComponent(med.value))
        .then(function (r) { return r.text(); })
        .then(function (h) {
            bat.innerHTML = h;
            updateBatchInfo();
            // Re-init searchable select for batch
            if (bat._srchBuilt) {
                bat._srchBuilt = false;
                // Remove old wrap div
                var wrap = bat.closest('.srch-sel-wrap');
                if (wrap) {
                    var parent = wrap.parentNode;
                    parent.insertBefore(bat, wrap);
                    wrap.remove();
                }
                // Remove old body panels for this select
                document.querySelectorAll('.srch-sel-panel').forEach(function(el){
                    if(!el.classList.contains('_srch-open')) el.remove();
                });
            }
            if (window.buildSearchSelect) window.buildSearchSelect(bat);
        })
        .catch(function () { bat.innerHTML = '<option value="">Error loading batches</option>'; });
}

function updateBatchInfo() {
    var bat = document.getElementById('batch_id');
    var st  = document.getElementById('stock_display');
    var mr  = document.getElementById('mrp_display');
    var qty = document.getElementById('qty');
    if (!bat) return;
    var opt = bat.options[bat.selectedIndex];
    var s = opt && opt.dataset ? opt.dataset.stock : '';
    var m = opt && opt.dataset ? opt.dataset.mrp   : '';
    if (st) st.textContent = s || '\u2014';
    if (mr) mr.textContent = m ? '\u20b9' + parseFloat(m).toFixed(2) : '\u2014';
    if (qty && s) qty.max = s;
}

/* ── Sidebar (global so onclick attributes can call these) ── */
function openSidebar() {
    var s = document.getElementById('sidebar');
    var o = document.getElementById('mobOverlay');
    if (s) s.classList.add('open');
    if (o) o.classList.add('open');
}
function closeSidebar() {
    var s = document.getElementById('sidebar');
    var o = document.getElementById('mobOverlay');
    if (s) s.classList.remove('open');
    if (o) o.classList.remove('open');
}

/* ── Everything that needs DOM ready ── */
document.addEventListener('DOMContentLoaded', function () {

    /* Sidebar toggle - bind events in DOMContentLoaded */
    var _sb  = document.getElementById('sidebar');
    var _ov  = document.getElementById('mobOverlay');
    var _btn = document.getElementById('menuBtn');

    if (_btn) {
        _btn.addEventListener('click', openSidebar);
        _btn.addEventListener('touchend', function (e) { e.preventDefault(); openSidebar(); });
    }
    if (_ov) {
        _ov.addEventListener('click', closeSidebar);
        _ov.addEventListener('touchend', function (e) { e.preventDefault(); closeSidebar(); });
    }

    /* Alert auto-dismiss */
    document.querySelectorAll('.alert').forEach(function (el) {
        setTimeout(function () {
            el.style.transition = 'opacity .4s';
            el.style.opacity    = '0';
            setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 400);
        }, 4500);
    });

    /* Close notification dropdown on outside click */
    var _notifBtn  = document.getElementById('notifBtn');
    var _notifDrop = document.getElementById('notifDrop');
    if (_notifBtn && _notifDrop) {
        _notifBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            _notifDrop.style.display = _notifDrop.style.display === 'block' ? 'none' : 'block';
        });
        document.addEventListener('click', function(e) {
            if (_notifDrop.style.display === 'block' && !_notifDrop.contains(e.target)) {
                _notifDrop.style.display = 'none';
            }
        });
    }

    /* data-confirm links */
    document.addEventListener('click', function (e) {
        var el = e.target.closest('[data-confirm]');
        if (el && !confirm(el.getAttribute('data-confirm'))) e.preventDefault();
    });

    /* Escape closes modals */
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.open').forEach(function (m) { m.classList.remove('open'); });
        }
    });

    /* Click outside modal closes it */
    document.querySelectorAll('.modal-overlay').forEach(function (m) {
        m.addEventListener('click', function (e) { if (e.target === m) m.classList.remove('open'); });
    });

    /* Payment method init */
    togglePayment();
    var pmEl = document.getElementById('payment_method');
    if (pmEl) pmEl.addEventListener('change', togglePayment);

    /* Highlight active nav link */
    var params = new URLSearchParams(location.search);
    var pg = params.get('p') || '';
    document.querySelectorAll('.sb-nav a').forEach(function (a) {
        var hp = new URLSearchParams(a.search || '');
        if (hp.get('p') === pg) a.classList.add('active');
    });
});

/* ── Searchable Select Component ────────────────────────────────── */
/* Desktop: floating panel below trigger                            */
/* Mobile: fullscreen modal overlay (like native mobile pickers)    */
(function(){
  var isMobile = function(){ return window.innerWidth <= 768; };

  // Shared backdrop for mobile modal mode
  var backdrop = null;
  function getBackdrop() {
    if (!backdrop) {
      backdrop = document.createElement('div');
      backdrop.style.cssText = 'display:none;position:fixed;inset:0;background:rgba(6,15,30,.55);z-index:99998;transition:opacity .15s';
      document.body.appendChild(backdrop);
    }
    return backdrop;
  }

  function buildSearchSelect(sel) {
    if(sel._srchBuilt) return;
    sel._srchBuilt = true;
    var wrap = document.createElement('div');
    wrap.className = 'srch-sel-wrap';
    sel.parentNode.insertBefore(wrap, sel);
    wrap.appendChild(sel);
    sel.classList.add('srch-sel-hidden');

    var opts = Array.from(sel.options);
    var display = document.createElement('div');
    display.className = 'srch-sel-display';
    var textEl = document.createElement('span');
    textEl.className = 'srch-sel-text' + (sel.value ? '' : ' placeholder');
    textEl.textContent = sel.value ? (sel.options[sel.selectedIndex]||{}).text : (sel.dataset.placeholder || '— Select —');
    var arrow = document.createElement('span');
    arrow.className = 'srch-sel-arrow';
    arrow.innerHTML = '<svg viewBox="0 0 12 8" width="12" height="8" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 1 6 7 11 1"/></svg>';
    display.appendChild(textEl); display.appendChild(arrow);
    wrap.appendChild(display);

    // Panel appended to BODY
    var panel = document.createElement('div');
    panel.className = 'srch-sel-panel';
    document.body.appendChild(panel);

    // Header row with title + close button (mobile only)
    var hdrRow = document.createElement('div');
    hdrRow.style.cssText = 'display:none;padding:12px 14px;border-bottom:1px solid #e4e7ed;align-items:center;justify-content:space-between;flex-shrink:0';
    var hdrTitle = document.createElement('span');
    hdrTitle.style.cssText = 'font-size:.85rem;font-weight:700;color:#1a1e2e';
    hdrTitle.textContent = sel.dataset.placeholder || 'Select Option';
    var hdrClose = document.createElement('button');
    hdrClose.type = 'button';
    hdrClose.style.cssText = 'background:none;border:1px solid #d0d5de;border-radius:50%;width:28px;height:28px;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#6b7485;font-size:.9rem;flex-shrink:0';
    hdrClose.innerHTML = '&#x2715;';
    hdrClose.addEventListener('click', function(e){ e.stopPropagation(); close(); });
    hdrRow.appendChild(hdrTitle); hdrRow.appendChild(hdrClose);
    panel.appendChild(hdrRow);

    var inputWrap = document.createElement('div');
    inputWrap.style.cssText = 'padding:8px 10px;border-bottom:1px solid #e4e7ed;flex-shrink:0';
    var input = document.createElement('input');
    input.type = 'text';
    input.style.cssText = 'width:100%;border:1.5px solid #c8cdd6;border-radius:6px;padding:8px 10px;font-size:.9rem;outline:none;box-sizing:border-box';
    input.placeholder = 'Search...'; input.autocomplete = 'off';
    inputWrap.appendChild(input); panel.appendChild(inputWrap);
    var list = document.createElement('div');
    list.style.cssText = 'flex:1;min-height:0;overflow-y:auto;-webkit-overflow-scrolling:touch';
    panel.appendChild(list);

    function applyDesktopStyle() {
      panel.style.cssText = 'display:none;position:fixed;background:#fff;border:1.5px solid #0a2342;border-radius:8px;box-shadow:0 8px 24px rgba(10,35,66,.18);z-index:99999;min-width:220px;overflow:hidden;flex-direction:column';
      hdrRow.style.display = 'none';
    }

    function applyMobileStyle() {
      panel.style.cssText = 'display:none;position:fixed;left:0;right:0;bottom:0;top:auto;background:#fff;border-radius:16px 16px 0 0;box-shadow:0 -8px 32px rgba(10,35,66,.22);z-index:99999;flex-direction:column;max-height:70vh;min-height:260px';
      hdrRow.style.display = 'flex';
    }

    applyDesktopStyle(); // default

    function positionPanel() {
      if (isMobile()) return; // mobile uses bottom sheet, no positioning needed
      var rect = display.getBoundingClientRect();
      var vpH = window.innerHeight;
      var vpW = window.innerWidth;
      var spaceBelow = vpH - rect.bottom - 8;
      var spaceAbove = rect.top - 8;
      var minH = 180;

      if (spaceBelow >= minH || spaceBelow >= spaceAbove) {
        panel.style.top = (rect.bottom + 2) + 'px';
        panel.style.bottom = 'auto';
        panel.style.maxHeight = Math.max(minH, Math.min(320, spaceBelow)) + 'px';
      } else {
        panel.style.bottom = (vpH - rect.top + 2) + 'px';
        panel.style.top = 'auto';
        panel.style.maxHeight = Math.max(minH, Math.min(320, spaceAbove)) + 'px';
      }

      var panelW = Math.max(rect.width, 240);
      var leftPos = rect.left;
      if (leftPos + panelW > vpW - 8) leftPos = vpW - panelW - 8;
      if (leftPos < 4) leftPos = 4;
      panel.style.left = leftPos + 'px';
      panel.style.width = Math.min(panelW, vpW - 8) + 'px';
    }

    function renderOpts(filter) {
      list.innerHTML = '';
      var q = (filter||'').toLowerCase();
      var shown = 0;
      var mobile = isMobile();
      opts = Array.from(sel.options);
      opts.forEach(function(o) {
        if(!o.value && !o.text.trim()) return;
        if(q && o.text.toLowerCase().indexOf(q) === -1) return;
        var item = document.createElement('div');
        var pad = mobile ? '12px 16px' : '8px 12px';
        var fs = mobile ? '.92rem' : '.83rem';
        item.style.cssText = 'padding:' + pad + ';font-size:' + fs + ';cursor:pointer;color:#374151;border-bottom:1px solid #f3f4f6';
        item.textContent = o.text;
        if(o.value === sel.value) { item.style.background='#0a2342'; item.style.color='#fff'; }
        item.addEventListener('mousedown', function(e){ e.preventDefault(); selectOpt(o); });
        item.addEventListener('touchend', function(e){ e.preventDefault(); selectOpt(o); });
        item.addEventListener('mouseover', function(){ if(o.value!==sel.value){this.style.background='#f0f4f8';this.style.color='#0a2342';} });
        item.addEventListener('mouseout',  function(){ if(o.value!==sel.value){this.style.background='';this.style.color='#374151';} });
        list.appendChild(item);
        shown++;
      });
      if(shown === 0) {
        var none = document.createElement('div');
        none.style.cssText = 'padding:10px 12px;font-size:.82rem;color:#9ca3af;font-style:italic';
        none.textContent = 'No results found';
        list.appendChild(none);
      }
    }

    function selectOpt(o) {
      sel.value = o.value;
      textEl.textContent = o.text;
      textEl.classList.remove('placeholder');
      close();
      var ev = new Event('change', {bubbles:true});
      sel.dispatchEvent(ev);
    }

    function open() {
      // Close any other open panels first
      document.querySelectorAll('._srch-open').forEach(function(p){
        p.style.display='none'; p.classList.remove('_srch-open');
      });

      if (isMobile()) {
        applyMobileStyle();
        var bk = getBackdrop();
        bk.style.display = 'block';
        bk.onclick = function(){ close(); };
        panel.style.display = 'flex';
      } else {
        applyDesktopStyle();
        positionPanel();
        panel.style.display = 'flex';
      }

      panel.classList.add('_srch-open');
      display.classList.add('open');
      renderOpts('');
      input.value = '';
      // Delay focus slightly on mobile to let animation settle
      setTimeout(function(){ input.focus(); }, isMobile() ? 100 : 0);
    }

    function close() {
      panel.style.display = 'none';
      panel.classList.remove('_srch-open');
      display.classList.remove('open');
      input.blur();
      var bk = getBackdrop();
      bk.style.display = 'none';
      bk.onclick = null;
    }

    display.addEventListener('click', function(e){
      e.stopPropagation();
      if(panel.classList.contains('_srch-open')) close(); else open();
    });
    input.addEventListener('input', function(){ renderOpts(input.value); });
    document.addEventListener('click', function(e){
      if(!wrap.contains(e.target) && !panel.contains(e.target)) close();
    });
    window.addEventListener('scroll', function(){ if(panel.classList.contains('_srch-open') && !isMobile()) positionPanel(); }, true);
    window.addEventListener('resize', function(){ if(panel.classList.contains('_srch-open')) { if(isMobile()) applyMobileStyle(); else { applyDesktopStyle(); positionPanel(); } panel.style.display='flex'; } });
  }

  // Auto-init all selects with data-searchable attribute
  function initAll() {
    document.querySelectorAll('select[data-searchable]').forEach(buildSearchSelect);
  }
  document.addEventListener('DOMContentLoaded', initAll);
  window.initSearchableSelects = initAll;
  window.buildSearchSelect = buildSearchSelect;
})();
