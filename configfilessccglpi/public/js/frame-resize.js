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

    function watchResize(iframe) {
        try {
            var doc = iframe.contentDocument;
            if (!doc) {
                return;
            }
            var target = doc.body || doc.documentElement;
            if (!target || !window.ResizeObserver) {
                return;
            }
            new ResizeObserver(function () {
                resize(iframe);
            }).observe(target);
        } catch (e) {
        }
    }

    function unsmoothScroll(iframe) {
        try {
            var doc = iframe.contentDocument;
            if (!doc) {
                return;
            }
            doc.querySelectorAll('a[href^="#"]').forEach(function (a) {
                a.addEventListener('click', function () {
                    var html = document.documentElement;
                    var previous = html.style.scrollBehavior;
                    html.style.scrollBehavior = 'auto';
                    window.setTimeout(function () {
                        html.style.scrollBehavior = previous;
                    }, 0);
                });
            });
        } catch (e) {
        }
    }

    function bindAll() {
        document.querySelectorAll('iframe.plugin-configfilessccglpi-frame:not([data-scc-bound])').forEach(function (iframe) {
            iframe.dataset.sccBound = '1';
            iframe.addEventListener('load', function () {
                resize(iframe);
                watchResize(iframe);
                unsmoothScroll(iframe);
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        bindAll();
        new MutationObserver(bindAll).observe(document.body, { childList: true, subtree: true });
    });
})();
