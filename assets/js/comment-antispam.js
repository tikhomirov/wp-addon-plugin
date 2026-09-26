(function () {
    'use strict';

    var HONEYPOT = 'antispam_website';
    var TIMESTAMP = 'antispam_ts';
    var HIDDEN_FIELD_CLASS = 'wp-addon-antispam-hp';

    function setTimestamp(form) {
        var field = form.querySelector('input[name="' + TIMESTAMP + '"]');
        if (!field || field.value) {
            return;
        }
        field.value = String(Math.floor(Date.now() / 1000));
    }

    function removeHoneypot(form) {
        var wrapper = form.querySelector('.' + HIDDEN_FIELD_CLASS);
        if (wrapper && wrapper.parentNode) {
            wrapper.parentNode.removeChild(wrapper);
        }
    }

    function bind(form) {
        if (!form || form.dataset.wpAddonAntispam === '1') {
            return;
        }
        form.dataset.wpAddonAntispam = '1';
        setTimestamp(form);
        form.addEventListener('submit', function () {
            removeHoneypot(form);
        });
    }

    function init() {
        var forms = document.querySelectorAll('#commentform, #respond form, form.comment-form');
        for (var i = 0; i < forms.length; i++) {
            bind(forms[i]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
