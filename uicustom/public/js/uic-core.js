/*
 * UI Customizer – frontend core: one shared MutationObserver + event bus.
 * Modules (profile-ui.js, edit-mode.js) subscribe to 'uic:mutate' (UIC.on).
 */
(function () {
    'use strict';
    if (window.UIC) return;

    var listeners = {};

    function on(event, fn) {
        (listeners[event] = listeners[event] || []).push(fn);
    }

    function off(event, fn) {
        if (!listeners[event]) return;
        listeners[event] = listeners[event].filter(function (f) { return f !== fn; });
    }

    function emit(event, detail) {
        (listeners[event] || []).slice().forEach(function (fn) {
            try {
                fn(detail);
            } catch (e) {
                console.error('uicustom: listener error for ' + event, e);
            }
        });
    }

    function whenReady(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    // Debounced (80ms) observer emitting 'uic:mutate' with added nodes.
    var pendingNodes = [];
    var pendingTimer = null;

    function scheduleFlush() {
        if (pendingTimer) return;
        pendingTimer = setTimeout(function () {
            var nodes = pendingNodes;
            pendingNodes = [];
            pendingTimer = null;
            emit('uic:mutate', { addedNodes: nodes });
        }, 80);
    }

    function startObserving() {
        var observer = new MutationObserver(function (mutations) {
            for (var i = 0; i < mutations.length; i++) {
                var added = mutations[i].addedNodes;
                for (var j = 0; j < added.length; j++) {
                    if (added[j].nodeType === 1) pendingNodes.push(added[j]);
                }
            }
            scheduleFlush();
        });
        observer.observe(document.body, { childList: true, subtree: true });
        return observer;
    }

    // Polls checkFn() every frame until the DOM stops changing or maxFrames is hit.
    function stabilize(checkFn, opts) {
        opts = opts || {};
        var maxFrames = opts.maxFrames || 90;
        var quietFramesNeeded = opts.quietFrames || 3;
        var frames = 0, quiet = 0;

        function tick() {
            var changed = false;
            try {
                changed = !!checkFn();
            } catch (e) {
                console.error('uicustom: stabilize checkFn error', e);
            }
            quiet = changed ? 0 : quiet + 1;
            frames++;
            if (quiet >= quietFramesNeeded || frames >= maxFrames) return;
            requestAnimationFrame(tick);
        }
        requestAnimationFrame(tick);
    }

    window.UIC = {
        on: on,
        off: off,
        emit: emit,
        whenReady: whenReady,
        stabilize: stabilize
    };

    whenReady(startObserving);
})();
