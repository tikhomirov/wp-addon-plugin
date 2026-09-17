(function () {
    'use strict';

    var config = window.wpAddonCookieBanner || {};
    var storageKey = config.storageKey || 'cookie_consent';
    var dateKey = config.dateKey || 'cookie_consent_date';
    var banner = document.getElementById('wp-addon-cookie-banner');

    if (!banner) {
        return;
    }

    function onReady(callback) {
        if (document.readyState === 'complete') {
            callback();
            return;
        }

        window.addEventListener('load', callback, { once: true });
    }

    function appendAnalyticsNode(node) {
        if (!node || node.nodeType !== Node.ELEMENT_NODE) {
            return;
        }

        if (node.tagName === 'SCRIPT') {
            var script = document.createElement('script');
            Array.prototype.forEach.call(node.attributes, function (attribute) {
                script.setAttribute(attribute.name, attribute.value);
            });
            script.text = node.text || node.textContent || '';
            document.body.appendChild(script);
            return;
        }

        if (node.tagName === 'NOSCRIPT') {
            var noscript = document.createElement('noscript');
            noscript.innerHTML = node.innerHTML;
            document.body.appendChild(noscript);
            return;
        }

        document.body.appendChild(node.cloneNode(true));
    }

    function loadAnalytics() {
        var template = document.getElementById('wp-addon-cookie-analytics-code');

        if (!template || !template.content) {
            return;
        }

        onReady(function () {
            Array.prototype.forEach.call(template.content.childNodes, appendAnalyticsNode);
        });
    }

    function shouldLoadAnalytics(consent) {
        if (!consent) {
            return false;
        }

        if (config.buttonMode === 'one') {
            return consent === 'accepted';
        }

        return consent === 'all';
    }

    function hideBanner() {
        banner.style.display = 'none';
    }

    function showBanner() {
        banner.style.display = 'block';
    }

    function saveConsent(type) {
        try {
            localStorage.setItem(storageKey, type);
            localStorage.setItem(dateKey, new Date().toISOString());
        } catch (error) {
            // Ignore storage errors in private mode.
        }
    }

    function handleConsent(type) {
        saveConsent(type);
        hideBanner();

        if (shouldLoadAnalytics(type)) {
            loadAnalytics();
        }
    }

    window.wpAddonCookieAccept = handleConsent;

    var consent = null;

    try {
        consent = localStorage.getItem(storageKey);
    } catch (error) {
        consent = null;
    }

    if (!consent) {
        showBanner();
    } else if (shouldLoadAnalytics(consent)) {
        loadAnalytics();
    }

    banner.addEventListener('click', function (event) {
        var button = event.target.closest('[data-cookie-consent]');

        if (!button) {
            return;
        }

        handleConsent(button.getAttribute('data-cookie-consent'));
    });
})();
