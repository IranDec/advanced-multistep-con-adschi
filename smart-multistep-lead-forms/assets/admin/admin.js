/*! Smart MultiStep Lead Forms 2 - admin pages. @author Mohammad Babaei | https://adschi.com */
(function () {
    'use strict';

    var t = window.smlfAdmin || {};

    function ask(message) {
        return new Promise(function (resolve) {
            var back = document.createElement('div');
            back.className = 'smlf-modal-backdrop';
            back.innerHTML = '<div class="smlf-modal" role="alertdialog" aria-modal="true"><div class="smlf-modal-title"></div><div class="smlf-modal-text"></div><div class="smlf-modal-actions"><button type="button" class="button smlf-no"></button><button type="button" class="button button-primary smlf-yes"></button></div></div>';
            back.querySelector('.smlf-modal-title').textContent = t.confirmTitle || 'Please confirm';
            back.querySelector('.smlf-modal-text').textContent = message;
            back.querySelector('.smlf-no').textContent = t.no || 'Cancel';
            back.querySelector('.smlf-yes').textContent = t.yes || 'Yes';
            document.body.appendChild(back);
            requestAnimationFrame(function () { back.classList.add('is-open'); });
            back.querySelector('.smlf-yes').focus();
            function close(v) { document.removeEventListener('keydown', key, true); back.remove(); resolve(v); }
            function key(e) { if (e.key === 'Escape') { close(false); } }
            document.addEventListener('keydown', key, true);
            back.querySelector('.smlf-yes').addEventListener('click', function () { close(true); });
            back.querySelector('.smlf-no').addEventListener('click', function () { close(false); });
            back.addEventListener('mousedown', function (e) { if (e.target === back) { close(false); } });
        });
    }

    document.addEventListener('click', function (e) {
        var c = e.target.closest('[data-smlf-confirm]');
        if (c) {
            if (c.dataset.smlfOk) {
                delete c.dataset.smlfOk;
            } else {
                e.preventDefault();
                ask(c.getAttribute('data-smlf-confirm')).then(function (ok) { if (ok) { c.dataset.smlfOk = '1'; c.click(); } });
                return;
            }
        }
        var copy = e.target.closest('[data-smlf-copy]');
        if (copy) {
            var input = document.querySelector(copy.getAttribute('data-smlf-copy'));
            if (input) {
                input.select();
                var label = copy.textContent;
                var done = function () { copy.textContent = t.copied || '✓'; setTimeout(function () { copy.textContent = label; }, 1200); };
                if (navigator.clipboard) { navigator.clipboard.writeText(input.value).then(done, function () { document.execCommand('copy'); done(); }); }
                else { document.execCommand('copy'); done(); }
            }
        }
        var d = e.target.closest('[data-smlf-in]');
        if (d) {
            var field = document.getElementById('smlf-follow');
            var kind = d.getAttribute('data-smlf-in');
            if (kind === 'clear') { field.value = ''; return; }
            var now = new Date();
            if (kind === '2h') { now.setHours(now.getHours() + 2); }
            if (kind === 'tomorrow') { now.setDate(now.getDate() + 1); now.setHours(9, 0, 0, 0); }
            if (kind === 'week') { now.setDate(now.getDate() + 7); now.setHours(9, 0, 0, 0); }
            var p = function (n) { return (n < 10 ? '0' : '') + n; };
            field.value = now.getFullYear() + '-' + p(now.getMonth() + 1) + '-' + p(now.getDate()) + 'T' + p(now.getHours()) + ':' + p(now.getMinutes());
        }
    });

    document.addEventListener('change', function (e) {
        if (e.target.matches('[data-smlf-check-all]')) {
            document.querySelectorAll('[data-smlf-check]').forEach(function (b) { b.checked = e.target.checked; });
        }
        if (e.target.matches('[data-smlf-autosubmit]') && e.target.form) { e.target.form.submit(); }
    });
})();
