(function () {
    'use strict';
    if (!window.UIC || window.UIC.dom) return;

    var FIELD_ROW_SELECTOR = '.form-field';

    function forcetabKey(a) {
        var m = (a.getAttribute('href') || '').match(/forcetab=([^&]+)/);
        return m ? decodeURIComponent(m[1]) : null;
    }

    function detectTabType() {
        var ul = document.getElementById('tabspanel');
        if (!ul) return null;
        var links = ul.querySelectorAll('a.nav-link[href*="forcetab="]');
        for (var i = 0; i < links.length; i++) {
            var key = forcetabKey(links[i]);
            if (key && key.endsWith('$main')) return key.split('$')[0].toLowerCase();
        }
        var m = (window.location.pathname || '').match(/\/([a-z0-9_]+)\.form\.php$/i);
        return m ? m[1].toLowerCase() : null;
    }

    function listTabEntries() {
        var ul = document.getElementById('tabspanel');
        var entries = [];
        if (!ul) return entries;
        ul.querySelectorAll('li.nav-item').forEach(function (li) {
            var a = li.querySelector('a.nav-link[href*="forcetab="]');
            if (!a) return;
            var key = forcetabKey(a);
            if (!key) return;
            var parts = key.split('$');
            entries.push({ li: li, key: key, cls: parts[0], tabnum: parts[1] || '' });
        });
        var counts = {};
        entries.forEach(function (e) { counts[e.cls] = (counts[e.cls] || 0) + 1; });
        entries.forEach(function (e) {
            e.effectiveCls = counts[e.cls] > 1 ? (e.cls + '$' + e.tabnum) : e.cls;
        });
        return entries;
    }

    function fieldName(row) {
        var el = row.querySelector('[name]');
        if (!el) return null;
        var raw = el.getAttribute('name') || '';
        raw = raw.replace(/\[\]$/, '');
        return raw || null;
    }

    function formActionKey(form) {
        var m = (form.getAttribute('action') || '').match(/([a-z0-9_]+\.form\.php)/i);
        return m ? m[1].toLowerCase() : null;
    }

    window.UIC.dom = {
        FIELD_ROW_SELECTOR: FIELD_ROW_SELECTOR,
        forcetabKey: forcetabKey,
        detectTabType: detectTabType,
        listTabEntries: listTabEntries,
        fieldName: fieldName,
        formActionKey: formActionKey
    };
})();
