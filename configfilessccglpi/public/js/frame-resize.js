(function () {
    function resize(iframe) {
        try {
            var doc = iframe.contentDocument;
            if (!doc) {
                return;
            }
            iframe.style.height = (doc.documentElement.scrollHeight || doc.body.scrollHeight) + 'px';
        } catch (e) {
        }
    }

    function bindAll() {
        document.querySelectorAll('iframe.plugin-configfilessccglpi-frame:not([data-scc-bound])').forEach(function (iframe) {
            iframe.dataset.sccBound = '1';
            iframe.addEventListener('load', function () {
                resize(iframe);
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        bindAll();
        new MutationObserver(bindAll).observe(document.body, { childList: true, subtree: true });
    });
})();
