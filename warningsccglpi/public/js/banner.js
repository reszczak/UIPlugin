(function () {
    'use strict';

    var CONFIG_URL = '/plugins/warningsccglpi/ajax/config.php';

    function renderBanner(cfg) {
        document.documentElement.style.setProperty('--warningsccglpi-color', cfg.color);
        document.body.classList.add('warningsccglpi-active');

        var banner = document.createElement('div');
        banner.id = 'warningsccglpi-banner';
        banner.textContent = cfg.label;
        document.body.appendChild(banner);

        ['top', 'bottom', 'left', 'right'].forEach(function (side) {
            var el = document.createElement('div');
            el.id = 'warningsccglpi-border-' + side;
            document.body.appendChild(el);
        });
    }

    function init() {
        fetch(CONFIG_URL, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) {
                if (!r.ok) {
                    console.warn('warningsccglpi: config fetch HTTP ' + r.status);
                    return {};
                }
                return r.json();
            })
            .then(function (cfg) {
                if (cfg && cfg.color) renderBanner(cfg);
            })
            .catch(function (e) {
                console.warn('warningsccglpi: config fetch failed', e);
            });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
