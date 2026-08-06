(function () {
    'use strict';

    var CONFIG_URL = '/plugins/uicustom/ajax/config.php';
    var UIC = window.UIC;
    var dom = UIC.dom;
    var FORMS = null;


    function applyTabs(formCfg) {
        var ul = document.getElementById('tabspanel');
        if (!ul) return false;
        var keep = formCfg.tabs_keep;
        var changed = false;

        if (Array.isArray(keep) && keep.length) {
            dom.listTabEntries().forEach(function (e) {
                if (keep.indexOf(e.effectiveCls) === -1 && e.li.style.display !== 'none') {
                    e.li.style.setProperty('display', 'none', 'important');
                    changed = true;
                }
            });
        }

        if (formCfg.cleanup && formCfg.cleanup.hide_all_tab) {
            ul.querySelectorAll('a[data-show-all-tabs]').forEach(function (a) {
                var li = a.closest('li.nav-item');
                if (li && li.style.display !== 'none') {
                    li.style.setProperty('display', 'none', 'important');
                    changed = true;
                }
            });
        }

        return changed;
    }


    function applyOneField(field, rule) {
        var name = dom.fieldName(field);
        if (!name) return false;
        var list = Array.isArray(rule.list) ? rule.list : [];
        var isKeep = (rule.mode || 'keep') === 'keep';
        var inList = list.indexOf(name) !== -1;
        var shouldHide = isKeep ? !inList : inList;
        if (shouldHide && field.style.display !== 'none') {
            field.style.setProperty('display', 'none', 'important');
            return true;
        }
        return false;
    }

    function bestActionFor(fields, names) {
        var best = null, bestScore = 0;
        Object.keys(fields).forEach(function (action) {
            var list = Array.isArray(fields[action].list) ? fields[action].list : [];
            var score = 0;
            names.forEach(function (n) { if (list.indexOf(n) !== -1) score++; });
            if (score > bestScore) { bestScore = score; best = action; }
        });
        return best;
    }

    function applyFields(formCfg) {
        var fields = formCfg.fields || {};
        if (!Object.keys(fields).length) return false;
        var changed = false;

        Object.keys(fields).forEach(function (action) {
            document.querySelectorAll('form[action*="' + action + '"] ' + dom.FIELD_ROW_SELECTOR)
                .forEach(function (el) { if (applyOneField(el, fields[action])) changed = true; });
        });

        var orphans = [];
        document.querySelectorAll(dom.FIELD_ROW_SELECTOR).forEach(function (el) {
            if (dom.fieldName(el) && !el.closest('form[action]')) orphans.push(el);
        });
        if (!orphans.length) return changed;

        var names = orphans.map(dom.fieldName);
        var action = bestActionFor(fields, names);
        if (action) {
            orphans.forEach(function (el) { if (applyOneField(el, fields[action])) changed = true; });
        }
        return changed;
    }


    function applyDevices(formCfg) {
        var hide = Array.isArray(formCfg.devices_hide) ? formCfg.devices_hide : [];
        if (!hide.length) return false;
        var anchor = document.querySelector('tbody[id^="Device"]');
        var table = anchor ? anchor.closest('table') : null;
        if (!table) return false;

        var tbodies = Array.prototype.slice.call(table.querySelectorAll(':scope > tbody'));
        var starts = [];
        tbodies.forEach(function (tb, i) {
            if (tb.querySelector(':scope > tr > td.subheader')) starts.push(i);
        });

        var changed = false;
        for (var g = 0; g < starts.length; g++) {
            var from = starts[g];
            var to = (g + 1 < starts.length) ? starts[g + 1] : tbodies.length;
            var cls = null;
            for (var i = from; i < to && cls === null; i++) {
                var m = (tbodies[i].id || '').match(/^(Device[A-Za-z]+)_/);
                if (m) cls = m[1];
            }
            if (cls === null || hide.indexOf(cls) === -1) continue;
            for (var j = from; j < to; j++) {
                if (tbodies[j].style.display !== 'none') {
                    tbodies[j].style.setProperty('display', 'none', 'important');
                    changed = true;
                }
            }
        }
        return changed;
    }


    function findTabTable(tabCls) {
        var link = document.querySelector('#tabspanel a[href*="forcetab=' + tabCls + '$"]');
        var target = link ? link.getAttribute('data-bs-target') : null;
        var pane = target ? document.querySelector(target) : null;
        return (pane || document).querySelector('table.table-hover, table.table-striped, table.tab_cadre_fixehov');
    }

    function isGroupedHeader(headRow) {
        return Array.prototype.some.call(headRow.children, function (c) {
            return (parseInt(c.getAttribute('colspan'), 10) || 1) > 1;
        });
    }

    function dataColPositions(headRow) {
        var phys = [];
        Array.prototype.forEach.call(headRow.children, function (cell, i) {
            if (cell.querySelector && (cell.querySelector('.massive_action_checkbox') || cell.querySelector('.show_filters'))) {
                return;
            }
            phys.push(i);
        });
        return phys;
    }

    function applyColumns(formCfg) {
        var map = formCfg.columns_hide;
        if (!map || typeof map !== 'object') return false;
        var changed = false;
        Object.keys(map).forEach(function (tabCls) {
            var idxs = map[tabCls];
            if (!Array.isArray(idxs) || !idxs.length) return;
            var table = findTabTable(tabCls);
            if (!table) return;
            var rows = table.querySelectorAll(':scope > tr, :scope > thead > tr, :scope > tbody > tr');
            if (!rows.length || isGroupedHeader(rows[0])) return;
            var phys = dataColPositions(rows[0]);
            rows.forEach(function (tr) {
                idxs.forEach(function (L) {
                    var p = phys[L];
                    var cell = (p != null) ? tr.children[p] : null;
                    if (cell && cell.style.display !== 'none') {
                        cell.style.setProperty('display', 'none', 'important');
                        changed = true;
                    }
                });
            });
        });
        return changed;
    }


    function applyCleanup(formCfg) {
        var c = formCfg.cleanup || {};
        var changed = false;
        if (c.hide_qr) {
            document.querySelectorAll('.asset-pictures').forEach(function (el) {
                var col = el.closest('[class*="col-"]') || el;
                if (col.style.display !== 'none') {
                    col.style.setProperty('display', 'none', 'important');
                    changed = true;
                }
            });
        }
        if (c.hide_device_actions) {
            var devAnchor = document.querySelector('tbody[id^="Device"]');
            var devTable = devAnchor ? devAnchor.closest('table') : null;
            if (devTable) {
                devTable.querySelectorAll('a[href*="item_device"]').forEach(function (a) {
                    if (a.style.visibility !== 'hidden') {
                        a.style.setProperty('visibility', 'hidden', 'important');
                        changed = true;
                    }
                });
            }
        }
        return changed;
    }


    function apply() {
        if (!FORMS) return false;
        var type = dom.detectTabType();
        var formCfg = type ? FORMS[type] : null;
        if (!formCfg) return false;
        var changed = applyTabs(formCfg);
        if (applyFields(formCfg)) changed = true;
        if (applyDevices(formCfg)) changed = true;
        if (applyColumns(formCfg)) changed = true;
        if (applyCleanup(formCfg)) changed = true;
        return changed;
    }

    function start() {
        UIC.on('uic:mutate', function () { apply(); });
        apply();
        UIC.stabilize(apply);
    }

    function init() {
        fetch(CONFIG_URL, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) {
                if (!r.ok) {
                    console.warn('uicustom: config fetch HTTP ' + r.status);
                    return {};
                }
                return r.json();
            })
            .then(function (forms) {
                FORMS = forms || {};
                start();
            })
            .catch(function (e) {
                console.warn('uicustom: config fetch failed', e);
                FORMS = {};
            });
    }

    UIC.whenReady(init);
})();
