(function () {
    var cfg = window.phpinfowpAI;
    if (!cfg || !cfg.available) return;

    function explain(btn) {
        var topic   = btn.getAttribute('data-ai-topic');
        var context = btn.getAttribute('data-ai-context');
        var value   = btn.getAttribute('data-ai-value') || '';
        if (!topic || !context) return;

        var out = btn.nextElementSibling;
        if (!out || !out.classList.contains('phpinfowp-ai-out')) {
            out = document.createElement('div');
            out.className = 'phpinfowp-ai-out';
            btn.parentNode.insertBefore(out, btn.nextSibling);
        }

        btn.disabled = true;
        var label = btn.textContent;
        btn.textContent = cfg.i18n.thinking;
        out.textContent = '';

        var body = new FormData();
        body.append('action',  cfg.action);
        body.append('nonce',   cfg.nonce);
        body.append('topic',   topic);
        body.append('context', context);
        body.append('value',   value);

        fetch(cfg.ajax_url, { method: 'POST', credentials: 'same-origin', body: body })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (json && json.success && json.data && json.data.text) {
                    out.textContent = json.data.text;
                } else {
                    out.textContent = (json && json.data && json.data.message) || cfg.i18n.error;
                }
            })
            .catch(function () { out.textContent = cfg.i18n.error; })
            .then(function () { btn.disabled = false; btn.textContent = label; });
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.phpinfowp-ai-explain');
        if (!btn) return;
        e.preventDefault();
        explain(btn);
    });
})();
