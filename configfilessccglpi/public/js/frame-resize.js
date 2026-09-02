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

    function showPane(card, pane) {
        card.querySelectorAll('.nav-link[data-scc-pane]').forEach(function (btn) {
            btn.classList.toggle('active', btn.dataset.sccPane === pane);
        });
        card.querySelectorAll('iframe[data-scc-pane]').forEach(function (frame) {
            var show = frame.dataset.sccPane === pane;
            frame.hidden = !show;
            if (show) {
                resize(frame);
            }
        });
    }

    function bindSubtabButtons() {
        document.querySelectorAll('.nav-link[data-scc-pane]:not([data-scc-bound])').forEach(function (btn) {
            btn.dataset.sccBound = '1';
            btn.addEventListener('click', function () {
                var card = btn.closest('.card');
                if (card) {
                    showPane(card, btn.dataset.sccPane);
                }
            });
        });
    }

    function bindFrames() {
        document.querySelectorAll('iframe.plugin-configfilessccglpi-frame:not([data-scc-bound])').forEach(function (iframe) {
            iframe.dataset.sccBound = '1';
            iframe.addEventListener('load', function () {
                resize(iframe);
                unsmoothScroll(iframe);
            });
        });
    }

    function bindAll() {
        bindSubtabButtons();
        bindFrames();
    }

    document.addEventListener('DOMContentLoaded', function () {
        bindAll();
        new MutationObserver(bindAll).observe(document.body, { childList: true, subtree: true });
    });
})();
