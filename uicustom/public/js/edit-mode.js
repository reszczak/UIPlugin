/*
 * UI Customizer – live edit mode (fields + tabs + component groups + columns).
 * Admin-only. Shows a floating "✎ UI" button on object pages; picks a
 * target profile, click to toggle visible/hidden, "Save" merges the
 * composed fragment into the profile rule (ajax/save_rule.php).
 */
(function () {
    'use strict';

    var AJAX = '/plugins/uicustom/ajax/';
    var UIC = window.UIC;
    var dom = UIC.dom;

    var PL = (document.documentElement.lang || '').toLowerCase().startsWith('pl');
    var T = PL ? {
        button:   '✎ UI',
        title:    'Tryb edycji UI',
        profile:  'Profil:',
        hint:     'Klikaj pola, zakładki i grupy komponentów, by je ukryć/pokazać dla wybranego profilu.',
        save:     'Zapisz',
        cancel:   'Anuluj',
        saved:    'Zapisano regułę.',
        nothing:  'Nic nie zmieniono – nie ma czego zapisać.',
        error:    'Błąd zapisu: ',
        noProfiles: 'Nie udało się pobrać profili.'
    } : {
        button:   '✎ UI',
        title:    'UI edit mode',
        profile:  'Profile:',
        hint:     'Click fields, tabs and component groups to hide/show them for the selected profile.',
        save:     'Save',
        cancel:   'Cancel',
        saved:    'Rule saved.',
        nothing:  'Nothing changed – nothing to save.',
        error:    'Save failed: ',
        noProfiles: 'Could not load profiles.'
    };

    var active = false;
    var targetPid = 0;
    var rule = null;
    var dirtyTabs = false;
    var dirtyForms = {};
    var dirtyDevices = false;
    var dirtyColumns = false;
    var dirtyCleanup = false;

    /* --------------------------- utils ------------------------------ */

    function csrf() {
        var i = document.querySelector('input[name="_glpi_csrf_token"]');
        return i ? i.value : '';
    }

    /* ------------------------- rule lookups -------------------------- */

    function ruleHidesField(action, name) {
        var type = dom.detectTabType();
        var fr = rule && rule[type] && rule[type].fields && rule[type].fields[action];
        if (!fr) return false;
        var inList = Array.isArray(fr.list) && fr.list.indexOf(name) !== -1;
        return (fr.mode || 'keep') === 'keep' ? !inList : inList;
    }

    function ruleHidesTab(cls) {
        var type = dom.detectTabType();
        var keep = rule && rule[type] && rule[type].tabs_keep;
        if (!Array.isArray(keep) || !keep.length) return false;
        return keep.indexOf(cls) === -1;
    }

    function ruleHidesDevice(cls) {
        var type = dom.detectTabType();
        var list = rule && rule[type] && rule[type].devices_hide;
        return Array.isArray(list) && list.indexOf(cls) !== -1;
    }

    function ruleHidesColumn(tabCls, idx) {
        var type = dom.detectTabType();
        var map = rule && rule[type] && rule[type].columns_hide;
        var list = map && map[tabCls];
        return Array.isArray(list) && list.indexOf(idx) !== -1;
    }

    function ruleCleanup(flag) {
        var type = dom.detectTabType();
        var c = rule && rule[type] && rule[type].cleanup;
        return !!(c && c[flag]);
    }

    /* --------------------------- marking ---------------------------- */

    function markFields(root) {
        (root.querySelectorAll ? root : document)
            .querySelectorAll(dom.FIELD_ROW_SELECTOR).forEach(function (el) {
                var form = el.closest('form');
                var action = form ? dom.formActionKey(form) : null;
                if (!action) return;
                var name = dom.fieldName(el);
                if (!name) return;
                if (!el.classList.contains('uic-editable')) {
                    el.classList.add('uic-editable');
                    el.dataset.uicAction = action;
                    if (ruleHidesField(action, name)) el.classList.add('uic-hidden');
                }
            });
    }

    function markTabs() {
        dom.listTabEntries().forEach(function (e) {
            if (e.key.endsWith('$main')) return;
            var li = e.li;
            if (!li.classList.contains('uic-editable')) {
                li.classList.add('uic-editable');
                li.dataset.uicTab = e.effectiveCls;
                if (ruleHidesTab(e.effectiveCls)) li.classList.add('uic-hidden');
            }
        });
    }

    /** Marks a cleanup toggle element with a single boolean flag. */
    function markCleanupEl(el, flag) {
        if (!el || el.classList.contains('uic-editable')) return;
        el.classList.add('uic-editable');
        el.dataset.uicCleanup = flag;
        if (ruleCleanup(flag)) el.classList.add('uic-hidden');
    }

    function markCleanup() {
        var ul = document.getElementById('tabspanel');
        var allLink = ul && ul.querySelector('a[data-show-all-tabs]');
        markCleanupEl(allLink && allLink.closest('li.nav-item'), 'hide_all_tab');
        document.querySelectorAll('.asset-pictures').forEach(function (el) {
            markCleanupEl(el.closest('[class*="col-"]') || el, 'hide_qr');
        });
        markDeviceActions();
    }

    /**
     * Marks each "Update"/"View" link in the Components tab with a shared
     * group flag (data-uic-cleanup-group): one flag, many elements, toggled
     * together (see onClick/composePayload). Scoped to the Components
     * table so it doesn't match the "Elementy Karta SIM" menu link.
     */
    function markDeviceActions() {
        var anchor = document.querySelector('tbody[id^="Device"]');
        var table = anchor ? anchor.closest('table') : null;
        if (!table) return;
        table.querySelectorAll('a[href*="item_device"]').forEach(function (a) {
            var cell = a.closest('td') || a;
            if (cell.classList.contains('uic-editable')) return;
            cell.classList.add('uic-editable');
            cell.dataset.uicCleanupGroup = 'hide_device_actions';
            if (ruleCleanup('hide_device_actions')) cell.classList.add('uic-hidden');
        });
    }

    /** Marks component group headers (td.subheader with itemtype=Device* link) as click targets. */
    function markDevices(root) {
        (root.querySelectorAll ? root : document)
            .querySelectorAll('td.subheader a[href*="itemtype=Device"]').forEach(function (a) {
                var m = (a.getAttribute('href') || '').match(/itemtype=(Device[A-Za-z0-9_]+)/);
                if (!m) return;
                var cell = a.closest('td.subheader');
                if (!cell || cell.classList.contains('uic-editable')) return;
                cell.classList.add('uic-editable');
                cell.dataset.uicDevice = m[1];
                if (ruleHidesDevice(m[1])) cell.classList.add('uic-hidden');
            });
    }

    /** Marks list column headers (<th>) as click targets, keyed by tab class and logical column index. */
    function markColumns() {
        document.querySelectorAll('#tabspanel a[href*="forcetab="][data-bs-target]').forEach(function (link) {
            var m = (link.getAttribute('href') || '').match(/forcetab=([^$&]+)\$/);
            if (!m) return;
            var tabCls = m[1];
            var pane = document.querySelector(link.getAttribute('data-bs-target'));
            var table = pane && pane.querySelector('table.table-hover, table.table-striped, table.tab_cadre_fixehov');
            var headRow = table && table.querySelector('tr');
            if (!headRow) return;
            var grouped = Array.prototype.some.call(headRow.children, function (c) {
                return (parseInt(c.getAttribute('colspan'), 10) || 1) > 1;
            });
            if (grouped) return;
            var logical = -1;
            Array.prototype.forEach.call(headRow.children, function (th) {
                if (th.tagName !== 'TH') return;
                if (th.querySelector('.massive_action_checkbox') || th.querySelector('.show_filters')) return;
                logical++;
                if (!th.textContent.trim() || th.classList.contains('uic-editable')) return;
                th.classList.add('uic-editable');
                th.dataset.uicColTab = tabCls;
                th.dataset.uicColIdx = String(logical);
                if (ruleHidesColumn(tabCls, logical)) th.classList.add('uic-hidden');
            });
        });
    }

    function unmarkAll() {
        document.querySelectorAll('.uic-editable').forEach(function (el) {
            el.classList.remove('uic-editable', 'uic-hidden');
            delete el.dataset.uicAction;
            delete el.dataset.uicTab;
            delete el.dataset.uicDevice;
            delete el.dataset.uicColTab;
            delete el.dataset.uicColIdx;
            delete el.dataset.uicCleanup;
            delete el.dataset.uicCleanupGroup;
        });
    }

    /* --------------------------- click ------------------------------- */

    function onClick(e) {
        if (!active) return;
        if (e.target.closest('#uic-toolbar') || e.target.closest('#uic-fab')) return;

        var field = e.target.closest(dom.FIELD_ROW_SELECTOR + '.uic-editable');
        if (field) {
            e.preventDefault(); e.stopPropagation();
            field.classList.toggle('uic-hidden');
            dirtyForms[field.dataset.uicAction] = true;
            return;
        }
        var tab = e.target.closest('li.uic-editable[data-uic-tab]');
        if (tab) {
            e.preventDefault(); e.stopPropagation();
            tab.classList.toggle('uic-hidden');
            dirtyTabs = true;
            return;
        }
        var cleanupEl = e.target.closest('.uic-editable[data-uic-cleanup]');
        if (cleanupEl) {
            e.preventDefault(); e.stopPropagation();
            cleanupEl.classList.toggle('uic-hidden');
            dirtyCleanup = true;
            return;
        }
        // Group flag: clicking any row toggles all elements sharing the group.
        var groupEl = e.target.closest('.uic-editable[data-uic-cleanup-group]');
        if (groupEl) {
            e.preventDefault(); e.stopPropagation();
            var group = groupEl.dataset.uicCleanupGroup;
            var next = !groupEl.classList.contains('uic-hidden');
            document.querySelectorAll('[data-uic-cleanup-group="' + group + '"]').forEach(function (el) {
                el.classList.toggle('uic-hidden', next);
            });
            dirtyCleanup = true;
            return;
        }
        var devCell = e.target.closest('td.subheader.uic-editable[data-uic-device]');
        if (devCell) {
            e.preventDefault(); e.stopPropagation();
            devCell.classList.toggle('uic-hidden');
            dirtyDevices = true;
            return;
        }
        var colTh = e.target.closest('th.uic-editable[data-uic-col-idx]');
        if (colTh) {
            e.preventDefault(); e.stopPropagation();
            colTh.classList.toggle('uic-hidden');
            dirtyColumns = true;
        }
    }

    /* --------------------------- save ------------------------------ */

    function composePayload() {
        var type = dom.detectTabType();
        var payload = { profiles_id: targetPid, itemtype: type };

        if (dirtyTabs) {
            var keep = [];
            dom.listTabEntries().forEach(function (e) {
                if (e.key.endsWith('$main') || !e.li.classList.contains('uic-hidden')) {
                    if (keep.indexOf(e.effectiveCls) === -1) keep.push(e.effectiveCls);
                }
            });
            payload.tabs_keep = keep;
        }

        var fields = {};
        Object.keys(dirtyForms).forEach(function (action) {
            var list = [];
            document.querySelectorAll('[data-uic-action="' + action + '"]').forEach(function (el) {
                if (!el.classList.contains('uic-hidden')) list.push(dom.fieldName(el));
            });
            fields[action] = { mode: 'keep', list: list };
        });
        if (Object.keys(fields).length) payload.fields = fields;

        if (dirtyDevices) {
            var dev = [];
            document.querySelectorAll('td.subheader[data-uic-device]').forEach(function (cell) {
                if (cell.classList.contains('uic-hidden') && dev.indexOf(cell.dataset.uicDevice) === -1) {
                    dev.push(cell.dataset.uicDevice);
                }
            });
            payload.devices_hide = dev;
        }

        if (dirtyColumns) {
            var cmap = {};
            document.querySelectorAll('th[data-uic-col-idx]').forEach(function (th) {
                var t = th.dataset.uicColTab;
                if (!cmap[t]) cmap[t] = [];
                if (th.classList.contains('uic-hidden')) cmap[t].push(parseInt(th.dataset.uicColIdx, 10));
            });
            payload.columns_hide = cmap;
        }

        if (dirtyCleanup) {
            var cl = {};
            document.querySelectorAll('[data-uic-cleanup]').forEach(function (el) {
                cl[el.dataset.uicCleanup] = el.classList.contains('uic-hidden');
            });
            document.querySelectorAll('[data-uic-cleanup-group]').forEach(function (el) {
                cl[el.dataset.uicCleanupGroup] = el.classList.contains('uic-hidden');
            });
            payload.cleanup = cl;
        }

        return payload;
    }

    function save() {
        if (!dirtyTabs && !dirtyDevices && !dirtyColumns && !dirtyCleanup && !Object.keys(dirtyForms).length) {
            alert(T.nothing);
            return;
        }
        fetch(AJAX + 'save_rule.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Glpi-Csrf-Token': csrf()
            },
            body: JSON.stringify(composePayload())
        })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (res) {
                if (res.ok && res.j.ok) { alert(T.saved); exit(); }
                else { alert(T.error + JSON.stringify(res.j)); }
            })
            .catch(function (e) { alert(T.error + e); });
    }

    /* ------------------------- toolbar/fab -------------------------- */

    var LAST_PROFILE_KEY = 'uicustomLastEditProfile';

    function rememberProfile(pid) {
        try { localStorage.setItem(LAST_PROFILE_KEY, String(pid)); } catch (e) { /* storage unavailable */ }
    }

    function lastRememberedProfile() {
        try { return parseInt(localStorage.getItem(LAST_PROFILE_KEY), 10); } catch (e) { return NaN; }
    }

    function buildToolbar(profiles) {
        var bar = document.createElement('div');
        bar.id = 'uic-toolbar';
        var opts = profiles.map(function (p) {
            return '<option value="' + p.id + '">' + p.name + (p.has_rule ? ' ●' : '') + '</option>';
        }).join('');
        bar.innerHTML =
            '<strong>' + T.title + '</strong>' +
            '<label>' + T.profile + ' <select id="uic-profile">' + opts + '</select></label>' +
            '<span class="uic-hint">' + T.hint + '</span>' +
            '<span class="uic-spacer"></span>' +
            '<button class="uic-btn uic-save">' + T.save + '</button>' +
            '<button class="uic-btn uic-cancel">' + T.cancel + '</button>';
        document.body.appendChild(bar);

        var sel = bar.querySelector('#uic-profile');
        // Defaults to the profile last used in edit mode on this browser.
        var last = lastRememberedProfile();
        var lastValid = profiles.some(function (p) { return p.id === last; });
        if (lastValid) {
            sel.value = String(last);
        } else {
            var withRule = profiles.find(function (p) { return p.has_rule; });
            if (withRule) sel.value = String(withRule.id);
        }
        targetPid = parseInt(sel.value, 10);
        rememberProfile(targetPid);

        sel.addEventListener('change', function () {
            targetPid = parseInt(sel.value, 10);
            rememberProfile(targetPid);
            reloadRule();
        });
        bar.querySelector('.uic-save').addEventListener('click', save);
        bar.querySelector('.uic-cancel').addEventListener('click', exit);
    }

    function reloadRule() {
        dirtyTabs = false;
        dirtyForms = {};
        dirtyDevices = false;
        dirtyColumns = false;
        dirtyCleanup = false;
        unmarkAll();
        fetch(AJAX + 'config.php?profiles_id=' + targetPid, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) { return r.ok ? r.json() : {}; })
            .then(function (forms) {
                rule = forms || {};
                markAll();
            })
            .catch(function () { rule = {}; markAll(); });
    }

    function markAll() {
        markFields(document);
        markTabs();
        markDevices(document);
        markColumns();
        markCleanup();
    }

    function onMutate(detail) {
        if (!active) return;
        (detail.addedNodes || []).forEach(function (n) { markFields(n); markDevices(n); });
        markColumns();
        markCleanup();
    }

    function enter() {
        if (active) return;
        active = true;
        fetch(AJAX + 'profiles.php', {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (profiles) {
                if (!profiles) { alert(T.noProfiles); active = false; return; }
                buildToolbar(profiles);
                reloadRule();
                UIC.on('uic:mutate', onMutate);
            })
            .catch(function () { alert(T.noProfiles); active = false; });
    }

    function exit() {
        active = false;
        UIC.off('uic:mutate', onMutate);
        unmarkAll();
        var bar = document.getElementById('uic-toolbar');
        if (bar) bar.remove();
        dirtyTabs = false;
        dirtyForms = {};
        dirtyDevices = false;
        dirtyColumns = false;
        dirtyCleanup = false;
    }

    /* ---------------------------- init ------------------------------ */

    function init() {
        if (window.__uicEditModeInit) return;
        if (!document.getElementById('tabspanel')) return;
        window.__uicEditModeInit = true;

        var fab = document.createElement('button');
        fab.id = 'uic-fab';
        fab.type = 'button';
        fab.textContent = T.button;
        fab.title = T.title;
        fab.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            active ? exit() : enter();
        });
        document.body.appendChild(fab);

        document.addEventListener('click', onClick, true);
    }

    UIC.whenReady(init);
})();
