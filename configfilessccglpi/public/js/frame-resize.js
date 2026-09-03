(function () {
    var ANCHOR_GAP = 12;

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

    function pinnedHeight() {
        var covered = 0;
        var x = Math.round(window.innerWidth / 2);
        for (var y = 0; y <= 240; y += 8) {
            var node = document.elementFromPoint(x, y);
            while (node) {
                var style = window.getComputedStyle(node);
                if (style.position === 'fixed' || style.position === 'sticky') {
                    // where the bar lands once stuck, not where it sits now
                    var offset = parseFloat(style.top);
                    if (!isNaN(offset)) {
                        covered = Math.max(covered, offset + node.getBoundingClientRect().height);
                    }
                    break;
                }
                node = node.parentElement;
            }
        }
        return covered;
    }

    function scrollingAncestor(element) {
        var node = element.parentElement;
        while (node) {
            var overflowY = window.getComputedStyle(node).overflowY;
            if ((overflowY === 'auto' || overflowY === 'scroll' || overflowY === 'overlay')
                && node.scrollHeight > node.clientHeight) {
                return node;
            }
            node = node.parentElement;
        }
        return null;
    }

    function anchorTarget(doc, href) {
        var id = href.slice(href.indexOf('#') + 1);
        try {
            id = decodeURIComponent(id);
        } catch (e) {
        }
        if (id === '') {
            return doc.documentElement;
        }
        return doc.getElementById(id) || doc.getElementsByName(id)[0] || null;
    }

    function jumpTo(iframe, target) {
        var current = iframe.getBoundingClientRect().top + target.getBoundingClientRect().top;
        var delta = current - pinnedHeight() - ANCHOR_GAP;

        var scroller = scrollingAncestor(iframe);
        if (scroller) {
            scroller.scrollTop += delta;
            return;
        }
        window.scrollBy({ top: delta, behavior: 'instant' });
    }

    function bindAnchors(iframe) {
        try {
            var doc = iframe.contentDocument;
            if (!doc) {
                return;
            }
            doc.addEventListener('click', function (event) {
                var node = event.target;
                var anchor = node && node.closest ? node.closest('a[href^="#"]') : null;
                if (!anchor) {
                    return;
                }
                var target = anchorTarget(doc, anchor.getAttribute('href'));
                if (!target) {
                    return;
                }
                event.preventDefault();
                jumpTo(iframe, target);
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
                bindAnchors(iframe);
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        bindAll();
        new MutationObserver(bindAll).observe(document.body, { childList: true, subtree: true });
    });
})();
