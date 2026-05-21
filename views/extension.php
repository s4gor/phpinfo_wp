<?php
defined('ABSPATH') or die('Unauthorized Access');

$loaded = get_loaded_extensions();
sort($loaded, SORT_STRING | SORT_FLAG_CASE);

// Extensions WordPress/WooCommerce officially requires or recommends
$recommended = [
    'curl', 'dom', 'exif', 'fileinfo', 'gd', 'hash', 'iconv',
    'imagick', 'intl', 'json', 'mbstring', 'mysqli', 'openssl',
    'pcre', 'pdo_mysql', 'SimpleXML', 'sodium', 'xml', 'xmlreader', 'zip', 'zlib',
];

$missing = array_filter($recommended, fn($e) => !in_array($e, $loaded, true) && !in_array(strtolower($e), array_map('strtolower', $loaded), true));
sort($missing);
?>

<div class="phpinfowp-ext-page">

  <div class="phpinfowp-page-header">
    <div>
      <h1>PHP Extensions</h1>
      <p class="phpinfowp-page-subtitle">
        <strong><?php echo count($loaded); ?></strong> loaded
        <?php if ($missing): ?>&nbsp;&middot;&nbsp;
          <strong style="color:#d63638"><?php echo count($missing); ?></strong> recommended not loaded
        <?php endif; ?>
        &nbsp;&middot;&nbsp; PHP <?php echo esc_html(PHP_VERSION); ?>
      </p>
    </div>
    <div style="position:relative">
      <input type="text" id="phpinfowp-ext-search" placeholder="Filter extensions…"
             class="regular-text" autocomplete="off" oninput="phpinfowpExtFilter(this.value)"
             style="padding-right:30px">
      <button type="button" onclick="document.getElementById('phpinfowp-ext-search').value='';phpinfowpExtFilter('')"
              style="display:none;position:absolute;right:6px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#999;font-size:16px"
              id="phpinfowp-ext-clear">&times;</button>
    </div>
  </div>

  <?php if ($missing): ?>
  <div class="phpinfowp-ext-section" id="phpinfowp-missing-section">
    <h3 class="phpinfowp-ext-heading phpinfowp-ext-heading-missing">
      <span class="dashicons dashicons-warning" style="color:#d63638;vertical-align:middle;margin-right:4px"></span>
      Recommended — Not Loaded <span class="phpinfowp-ext-heading-count"><?php echo count($missing); ?></span>
    </h3>
    <div class="phpinfowp-ext-grid" id="phpinfowp-missing-grid">
      <?php foreach ($missing as $ext): ?>
        <div class="phpinfowp-ext-item phpinfowp-ext-missing" data-name="<?php echo esc_attr(strtolower($ext)); ?>">
          <span class="phpinfowp-ext-dot phpinfowp-ext-dot-missing"></span>
          <span class="phpinfowp-ext-name"><?php echo esc_html($ext); ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="phpinfowp-ext-section">
    <h3 class="phpinfowp-ext-heading phpinfowp-ext-heading-loaded">
      <span class="dashicons dashicons-yes-alt" style="color:#00a32a;vertical-align:middle;margin-right:4px"></span>
      Loaded Extensions <span class="phpinfowp-ext-heading-count"><?php echo count($loaded); ?></span>
    </h3>
    <div class="phpinfowp-ext-grid" id="phpinfowp-loaded-grid">
      <?php foreach ($loaded as $ext):
        $ver = phpversion($ext);
      ?>
        <div class="phpinfowp-ext-item phpinfowp-ext-loaded" data-name="<?php echo esc_attr(strtolower($ext)); ?>">
          <span class="phpinfowp-ext-dot phpinfowp-ext-dot-loaded"></span>
          <span class="phpinfowp-ext-name"><?php echo esc_html($ext); ?></span>
          <?php if ($ver && $ver !== PHP_VERSION): ?>
            <span class="phpinfowp-ext-ver"><?php echo esc_html($ver); ?></span>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

</div>

<script>
function phpinfowpExtFilter(q) {
  q = q.toLowerCase().trim();
  document.getElementById('phpinfowp-ext-clear').style.display = q ? '' : 'none';
  var items = document.querySelectorAll('.phpinfowp-ext-item');
  items.forEach(function(el) {
    el.style.display = (!q || el.dataset.name.includes(q)) ? '' : 'none';
  });
  ['phpinfowp-missing-grid', 'phpinfowp-loaded-grid'].forEach(function(id) {
    var grid = document.getElementById(id);
    if (!grid) return;
    var section = grid.closest('.phpinfowp-ext-section');
    var visible = grid.querySelectorAll('.phpinfowp-ext-item:not([style*="none"])').length;
    if (section) section.style.display = visible ? '' : 'none';
  });
}
</script>
