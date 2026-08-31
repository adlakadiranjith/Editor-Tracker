// Small progressive-enhancement touches. The app works fully without JS;
// this just smooths out a couple of rough edges.
(function () {
    'use strict';

    // Disable the submit button after first click so a slow connection or an
    // impatient double-click can't fire the same form twice. The server
    // still enforces correctness independently (see actions/*.php).
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (form.dataset.noDisable) return;

        var buttons = form.querySelectorAll('button[type="submit"]');
        buttons.forEach(function (btn) {
            // Let the browser include the *clicked* button's name/value in
            // the submission before we disable it.
            setTimeout(function () {
                btn.disabled = true;
                btn.dataset.originalText = btn.dataset.originalText || btn.textContent;
                btn.textContent = 'Please wait…';
            }, 0);
        });
    });

    // Auto-expand a <details> that contains a field with a validation error
    // marker, so a re-rendered form with errors isn't hidden inside a
    // collapsed section.
    document.querySelectorAll('details').forEach(function (d) {
        if (d.querySelector('.alert-error')) {
            d.open = true;
        }
    });
})();
