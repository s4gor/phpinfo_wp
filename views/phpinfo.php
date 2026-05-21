<?php defined('ABSPATH') or die('Unauthorized Access'); ?>

<div id="phpinfo-info">

  <div class="phpinfowp-toolbar">
    <div style="position:relative;flex:1;max-width:420px">
      <input type="text" id="phpinfowp-search"
             placeholder="Search directives, values, modules… (e.g. memory_limit)"
             class="regular-text"
             style="width:100%;padding-right:32px"
             autocomplete="off" spellcheck="false">
      <button type="button" id="phpinfowp-search-clear"
              style="display:none;position:absolute;right:6px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#999;font-size:18px;line-height:1;padding:0"
              title="Clear search">&times;</button>
    </div>
    <span id="phpinfowp-search-count" style="font-size:12px;color:#666;white-space:nowrap"></span>

    <select id="phpinfowp-section-jump" style="max-width:240px" title="Jump to section">
      <option value="">Jump to section…</option>
    </select>
  </div>

  <div id="phpinfo-WP">
    <?php
    ob_start();
    phpinfo(INFO_ALL & ~INFO_LICENSE & ~INFO_CREDITS);
    $phpinfo_raw = ob_get_clean();

    // Extract body content
    $phpinfo_body = preg_replace('%^.*<body>(.*)</body>.*$%ms', '$1', $phpinfo_raw);

    // Strip phpinfo's own <style> block — we replace it with our own
    $phpinfo_body = preg_replace('/<style[^>]*>.*?<\/style>/si', '', $phpinfo_body);

    // Fix Zend optimizer module name
    $phpinfo_body = str_replace('module_Zend Optimizer', 'module_Zend_Optimizer', $phpinfo_body);

    echo $phpinfo_body;
    Phpinfo_wp::thankyou();
    ?>
    <button id="topButton-phpinfo-WP" title="Go to top" style="display:none">
      <img src="<?php echo esc_url(plugin_dir_url(__FILE__) . '../assets/images/top.png'); ?>"
           alt="Top" id="topButtonImage-phpinfo-WP">
    </button>
  </div>
</div>

<script>
(function () {
  var container   = document.getElementById('phpinfo-WP');
  var searchInput = document.getElementById('phpinfowp-search');
  var countEl     = document.getElementById('phpinfowp-search-count');
  var clearBtn    = document.getElementById('phpinfowp-search-clear');
  var jumpSelect  = document.getElementById('phpinfowp-section-jump');

  if (!container) return;

  // ── Build section jump list from <h2> anchors ──────────────────────
  var sections = Array.from(container.querySelectorAll('h2'));
  sections.forEach(function (h2) {
    var anchor = h2.querySelector('a[name]');
    var id     = anchor ? anchor.getAttribute('name') : '';
    var label  = h2.textContent.trim();
    if (!label) return;
    if (id) h2.id = id; // promote anchor name to h2 id for scrolling
    var opt = document.createElement('option');
    opt.value = id || label;
    opt.textContent = label;
    opt.dataset.el = id || '';
    jumpSelect.appendChild(opt);
  });

  jumpSelect.addEventListener('change', function () {
    var id = this.value;
    var target = id ? document.getElementById(id) : null;
    if (target) {
      target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      // Brief highlight
      target.style.transition = 'background .2s';
      target.style.background = '#f0f4ff';
      setTimeout(function () { target.style.background = ''; }, 1200);
    }
    this.value = '';
  });

  // ── Pre-index all data rows ────────────────────────────────────────
  // phpinfo rows: <tr><td class="e">name</td><td class="v">value</td></tr>
  // We skip header rows (.h class)
  var allTables = Array.from(container.querySelectorAll('table'));

  allTables.forEach(function (table) {
    Array.from(table.querySelectorAll('tr')).forEach(function (row) {
      if (!row.classList.contains('h')) {
        row._searchText = row.textContent.toLowerCase();
      }
    });
  });

  // Map each table → its preceding h2 (for show/hide)
  var tableH2Map = new Map();
  sections.forEach(function (h2) {
    var next = h2.nextElementSibling;
    while (next && next.tagName !== 'TABLE') next = next.nextElementSibling;
    if (next) tableH2Map.set(next, h2);
  });

  // ── Search ─────────────────────────────────────────────────────────
  var currentHL = [];

  function applySearch(q) {
    q = q.trim().toLowerCase();
    clearBtn.style.display = q ? '' : 'none';

    // Remove previous row highlights
    currentHL.forEach(function (row) { row.classList.remove('phpinfowp-row-hl'); });
    currentHL = [];

    if (!q) {
      // Show everything
      allTables.forEach(function (t) {
        t.style.display = '';
        Array.from(t.querySelectorAll('tr')).forEach(function (r) { r.style.display = ''; });
      });
      sections.forEach(function (h) { h.style.display = ''; });
      countEl.textContent = '';
      return;
    }

    var totalMatches = 0;

    allTables.forEach(function (table) {
      var rows     = Array.from(table.querySelectorAll('tr'));
      var hasMatch = false;

      rows.forEach(function (row) {
        if (row.classList.contains('h')) {
          // Always show header row if table is shown
          row.style.display = '';
          return;
        }
        if (row._searchText && row._searchText.includes(q)) {
          row.style.display = '';
          row.classList.add('phpinfowp-row-hl');
          currentHL.push(row);
          hasMatch = true;
          totalMatches++;
        } else {
          row.style.display = 'none';
        }
      });

      table.style.display = hasMatch ? '' : 'none';
      var h2 = tableH2Map.get(table);
      if (h2) h2.style.display = hasMatch ? '' : 'none';
    });

    countEl.textContent = totalMatches
      ? totalMatches + ' row' + (totalMatches !== 1 ? 's' : '') + ' matched'
      : 'No results';

    // Scroll first match into view
    if (currentHL[0]) {
      currentHL[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  }

  searchInput.addEventListener('input', function () { applySearch(this.value); });
  searchInput.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { searchInput.value = ''; applySearch(''); }
  });
  clearBtn.addEventListener('click', function () {
    searchInput.value = '';
    applySearch('');
    searchInput.focus();
  });

  // Global access
  window.phpinfowpSearch      = applySearch;
  window.phpinfowpClearSearch = function () { searchInput.value = ''; applySearch(''); searchInput.focus(); };
})();
</script>
