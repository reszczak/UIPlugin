/**
 * Rewrites the GLPI logo link to point to the documentation page.
 */
(function () {
    'use strict';

    function targetUrl() {
        var root = (typeof CFG_GLPI !== 'undefined' && CFG_GLPI.root_doc) ? CFG_GLPI.root_doc : '';
        return root + '/plugins/aboutsccglpi/front/documentation.php';
    }

    function rewriteLogo() {
        var url = targetUrl();
        document.querySelectorAll('span.glpi-logo').forEach(function (span) {
            var link = span.closest('a');
            if (link) {
                link.setAttribute('href', url);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', rewriteLogo);
    } else {
        rewriteLogo();
    }

    document.addEventListener('click', function (event) {
        var logo = event.target.closest ? event.target.closest('span.glpi-logo') : null;
        if (logo) {
            var link = logo.closest('a');
            if (link && link.getAttribute('href') !== targetUrl()) {
                event.preventDefault();
                window.location.href = targetUrl();
            }
        }
    }, true);
})();
