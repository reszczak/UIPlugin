/*
 * UI Customizer – DOM helpers shared by profile-ui.js and edit-mode.js.
 */
(function () {
    'use strict';
    if (!window.UIC || window.UIC.dom) return;

    /** Selector for a field row: label + control, as rendered by GLPI's core form macro. */
    var FIELD_ROW_SELECTOR = '.form-field';

    function forcetabKey(a) {
        var m = (a.getAttribute('href') || '').match(/forcetab=([^&]+)/);
        return m ? decodeURIComponent(m[1]) : null;
    }

    /** Lowercase itemtype from the main tab ("<Itemtype>$main"). */
    function detectTabType() {
        var ul = document.getElementById('tabspanel');
        if (!ul) return null;
        var links = ul.querySelectorAll('a.nav-link[href*="forcetab="]');
        for (var i = 0; i < links.length; i++) {
            var key = forcetabKey(links[i]);
            if (key && key.endsWith('$main')) return key.split('$')[0].toLowerCase();
        }
        return null;
    }

    /** Name of a `.form-field` row's control (brackets stripped), or null if unnamed. */
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
        fieldName: fieldName,
        formActionKey: formActionKey
    };
})();
