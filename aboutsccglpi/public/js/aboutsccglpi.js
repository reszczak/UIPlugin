/**
 * Redirects the GLPI logo, breadcrumb home link, and central dashboard
 * to the documentation page. Also redirects the user menu "Help" link
 * to GLPI's own FAQ/knowledge base, which is reachable even when logged out.
 */
(function () {
    'use strict';

    function targetUrl() {
        var root = (typeof CFG_GLPI !== 'undefined' && CFG_GLPI.root_doc) ? CFG_GLPI.root_doc : '';
        return root + '/plugins/aboutsccglpi/front/documentation.php';
    }

    function helpUrl() {
        var root = (typeof CFG_GLPI !== 'undefined' && CFG_GLPI.root_doc) ? CFG_GLPI.root_doc : '';
        return root + '/front/helpdesk.faq.php';
    }

    function isCentralDashboard() {
        var path = window.location.pathname;
        var isCentralPath = path.indexOf('/front/central.php') !== -1 || path === '/Central';
        // Excludes embedded dashboard links.
        return isCentralPath && window.location.search.indexOf('embed=') === -1;
    }

    if (isCentralDashboard()) {
        window.location.replace(targetUrl());
        return;
    }

    function rewriteHomeLinks() {
        var url = targetUrl();
        document.querySelectorAll('span.glpi-logo, i.ti-home-2').forEach(function (icon) {
            var link = icon.closest('a');
            if (link) {
                link.setAttribute('href', url);
            }
        });
    }

    function rewriteHelpLink() {
        var url = helpUrl();
        document.querySelectorAll('i.ti-help').forEach(function (icon) {
            var link = icon.closest('a');
            if (link) {
                link.setAttribute('href', url);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            rewriteHomeLinks();
            rewriteHelpLink();
        });
    } else {
        rewriteHomeLinks();
        rewriteHelpLink();
    }

    document.addEventListener('click', function (event) {
        var target = event.target.closest ? event.target.closest('span.glpi-logo, i.ti-home-2') : null;
        if (target) {
            var link = target.closest('a');
            if (link && link.getAttribute('href') !== targetUrl()) {
                event.preventDefault();
                window.location.href = targetUrl();
            }
        }
    }, true);

    document.addEventListener('click', function (event) {
        var target = event.target.closest ? event.target.closest('i.ti-help') : null;
        if (target) {
            var link = target.closest('a');
            if (link && link.getAttribute('href') !== helpUrl()) {
                event.preventDefault();
                window.location.href = helpUrl();
            }
        }
    }, true);

    /** Copies text via a hidden textarea and document.execCommand('copy'). */
    function legacyCopy(text) {
        var textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.focus();
        textarea.select();
        var ok = false;
        try {
            ok = document.execCommand('copy');
        } catch (e) {
            ok = false;
        }
        document.body.removeChild(textarea);
        return ok;
    }

    /** Copies text to the clipboard, falling back to legacyCopy(). */
    function copyText(text) {
        if (window.isSecureContext && navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text).catch(function () {
                return legacyCopy(text);
            });
        }
        return Promise.resolve(legacyCopy(text));
    }

    document.addEventListener('click', function (event) {
        var btn = event.target.closest ? event.target.closest('.aboutsccglpi-copy-link') : null;
        if (!btn) {
            return;
        }
        event.preventDefault();
        var url = btn.getAttribute('data-copy-url') || '';
        copyText(url).then(function (result) {
            var icon = btn.querySelector('i');
            if (!icon) {
                return;
            }
            var original = icon.className;
            var originalTitle = btn.getAttribute('title');
            if (result === false) {
                // Shows the link in the tooltip for manual copying.
                icon.className = 'ti ti-alert-triangle';
                btn.setAttribute('title', url);
            } else {
                icon.className = 'ti ti-check';
            }
            setTimeout(function () {
                icon.className = original;
                if (originalTitle !== null) {
                    btn.setAttribute('title', originalTitle);
                }
            }, result === false ? 4000 : 1500);
        });
    });
})();
