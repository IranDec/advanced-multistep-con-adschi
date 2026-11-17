/*!
 * Smart MultiStep Lead Forms 2 - frontend renderer (no dependencies).
 * Also used by the builder for the live preview: SMLF.mount(el, config).
 * @author Mohammad Babaei | https://adschi.com
 */
(function () {
    'use strict';

    var SMLF = window.SMLF = window.SMLF || {};

    // ------------------------------------------------------------------ helpers

    function h(tag, attrs, kids) {
        var el = document.createElement(tag);
        Object.keys(attrs || {}).forEach(function (k) {
            var v = attrs[k];
            if (v === null || v === undefined || v === false) { return; }
            if (k === 'text') { el.textContent = v; }
            else if (k === 'html') { el.innerHTML = v; }
            else if (k === 'class') { el.className = v; }
            else if (k.slice(0, 2) === 'on') { el.addEventListener(k.slice(2), v); }
            else { el.setAttribute(k, v === true ? '' : v); }
        });
        [].concat(kids || []).forEach(function (c) {
            if (c === null || c === undefined || c === false) { return; }
            el.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
        });
        return el;
    }

    function fmt(s) {
        var args = [].slice.call(arguments, 1), i = 0;
        return String(s).replace(/%(\d+\$)?[sd]/g, function (m, pos) {
            return pos ? args[parseInt(pos, 10) - 1] : args[i++];
        });
    }

    function isEmpty(v) {
        if (Array.isArray(v)) { return !v.filter(function (x) { return String(x).trim() !== ''; }).length; }
        if (v && typeof v === 'object') { return !Object.keys(v).filter(function (k) { return String(v[k]).trim() !== ''; }).length; }
        return v === undefined || v === null || String(v).trim() === '';
    }

    // ------------------------------------------------------------------ logic (same rules as class-smlf-logic.php)

    function test(c, data) {
        var v = data[c.field], x = String(c.value || '');
        v = (v === undefined || v === null) ? '' : v;
        switch (c.op) {
            case 'eq': return Array.isArray(v) ? v.map(String).indexOf(x) > -1 : String(v).toLowerCase() === x.toLowerCase();
            case 'neq': return !test({ field: c.field, op: 'eq', value: x }, data);
            case 'contains': return Array.isArray(v) ? v.map(String).indexOf(x) > -1 : (x !== '' && String(v).toLowerCase().indexOf(x.toLowerCase()) > -1);
            case 'not_contains': return !test({ field: c.field, op: 'contains', value: x }, data);
            case 'empty': return isEmpty(v);
            case 'not_empty': return !isEmpty(v);
            case 'gt': return v !== '' && !isNaN(v) && x !== '' && !isNaN(x) && parseFloat(v) > parseFloat(x);
            case 'lt': return v !== '' && !isNaN(v) && x !== '' && !isNaN(x) && parseFloat(v) < parseFloat(x);
        }
        return false;
    }

    function visible(field, data) {
        var g = field.show_if || { rules: [] };
        if (!g.rules || !g.rules.length) { return true; }
        if (g.match === 'any') { return g.rules.some(function (r) { return test(r, data); }); }
        return g.rules.every(function (r) { return test(r, data); });
    }

    /** "Next step in the list" skips dead-end steps: they are only reached through an explicit jump. */
    function following(steps, i) {
        var j = i + 1;
        while (steps[j] && steps[j].terminal === 'reset') { j++; }
        return j;
    }

    function nextTarget(step, data) {
        for (var i = 0; i < step.routes.length; i++) {
            if (test(step.routes[i].when, data)) { return step.routes[i].goto; }
        }
        return step.next || '';
    }

    // ------------------------------------------------------------------ mount

    SMLF.mount = function (root, cfg) {
        if (root._smlf) { root._smlf.destroy(); }
        var form = cfg.form, steps = form.steps, s = form.settings, t = cfg.i18n;
        var index = {};
        steps.forEach(function (st, i) { index[st.id] = i; });
        var state = { answers: {}, files: {}, history: [], current: 0, token: '', started: false, done: false, sending: false, reviewing: false, seen: {} };
        var storeKey = 'smlf_' + cfg.id;
        var fieldEls = {};
        var captchaWidget = null;

        // Defaults + hidden fields
        steps.forEach(function (st) {
            st.fields.forEach(function (f) {
                if (f.type === 'hidden') { state.answers[f.id] = hiddenValue(f); }
                else if (f.default !== '' && f.default !== undefined) {
                    state.answers[f.id] = f.type === 'choice' && f.multiple ? [f.default] : f.default;
                }
            });
        });

        // ------------------------------------------------------------------ shell
        var wrap = h('div', {
            class: 'smlf smlf-theme-' + s.theme + (s.animate ? ' smlf-animate' : ''),
            dir: cfg.rtl ? 'rtl' : null,
            style: '--smlf-primary:' + s.primary + ';--smlf-accent:' + s.accent + ';--smlf-bg:' + s.background + ';--smlf-text:' + s.text +
                ';--smlf-radius:' + s.radius + 'px;--smlf-max:' + s.max_width + 'px;' + (s.font && s.font !== 'inherit' ? '--smlf-font:' + s.font + ';' : '')
        });
        var progress = h('div', { class: 'smlf-progress', 'aria-hidden': 'true' });
        var progressText = h('div', { class: 'smlf-sr', 'aria-live': 'polite' });
        var body = h('div', { class: 'smlf-body' });
        var status = h('div', { class: 'smlf-status', role: 'alert', 'aria-live': 'assertive' });
        var back = h('button', { type: 'button', class: 'smlf-btn smlf-btn-ghost', text: t.back, onclick: goBack });
        var next = h('button', { type: 'button', class: 'smlf-btn smlf-btn-primary', onclick: goNext });
        var nav = h('div', { class: 'smlf-nav' }, [back, next]);
        var hp = h('input', { type: 'text', name: 'smlf_hp', tabindex: '-1', autocomplete: 'off', class: 'smlf-hp', 'aria-hidden': 'true' });
        var formEl = h('form', { class: 'smlf-card', novalidate: true, onsubmit: function (e) { e.preventDefault(); goNext(); } },
            [progress, progressText, cfg.preview ? h('div', { class: 'smlf-preview-note', text: t.preview }) : null, body, status, nav, hp]);
        wrap.appendChild(formEl);
        root.innerHTML = '';
        root.appendChild(wrap);

        // ------------------------------------------------------------------ fields
        function hiddenValue(f) {
            var p = new URLSearchParams(window.location.search);
            switch (f.source) {
                case 'page_url': return window.location.href.slice(0, 255);
                case 'referrer': return document.referrer.slice(0, 255);
                case 'static': return f.default || '';
                case 'url_param': return (p.get(f.param || f.id) || '').slice(0, 255);
                default: return (p.get(f.source) || '').slice(0, 255);
            }
        }

        function setValue(id, v) {
            state.answers[id] = v;
            if (!state.started && !cfg.preview) { state.started = true; event('start'); }
            refresh();
        }

        function fieldId(f, suffix) { return root.id + '-' + f.id + (suffix ? '-' + suffix : ''); }

        function renderField(f) {
            var val = state.answers[f.id];
            var label = f.label ? h('label', { class: 'smlf-label', for: fieldId(f), id: fieldId(f, 'label') }, [
                f.label, f.required ? h('span', { class: 'smlf-req', 'aria-hidden': 'true', text: ' *' }) : null
            ]) : null;
            var help = f.help ? h('div', { class: 'smlf-help', id: fieldId(f, 'help'), text: f.help }) : null;
            var err = h('div', { class: 'smlf-error', id: fieldId(f, 'err'), 'aria-live': 'polite' });
            var describedBy = (f.help ? fieldId(f, 'help') + ' ' : '') + fieldId(f, 'err');
            var input;

            function bind(el, ev) {
                el.addEventListener(ev || 'input', function () { setValue(f.id, el.value); clearError(f.id); });
            }
            var common = { id: fieldId(f), name: f.id, 'aria-describedby': describedBy, 'aria-required': f.required ? 'true' : null, placeholder: f.placeholder || null };

            switch (f.type) {
                case 'message':
                    return h('div', { class: 'smlf-field smlf-w-full smlf-message', 'data-field': f.id, html: f.content || '' });
                case 'hidden':
                    return null;
                case 'textarea':
                    input = h('textarea', Object.assign({ class: 'smlf-input', rows: 4, maxlength: 5000 }, common));
                    input.value = val || '';
                    bind(input);
                    break;
                case 'choice':
                    input = renderChoice(f, val, describedBy);
                    break;
                case 'range':
                    var out = h('output', { class: 'smlf-range-out' });
                    input = h('input', Object.assign({ type: 'range', class: 'smlf-range', min: f.min, max: f.max, step: f.step }, common));
                    input.value = val !== undefined && val !== '' ? val : (f.default || f.min);
                    var sync = function () { out.textContent = input.value + (f.unit ? ' ' + f.unit : ''); };
                    input.addEventListener('input', function () { sync(); setValue(f.id, input.value); });
                    sync();
                    if (state.answers[f.id] === undefined) { state.answers[f.id] = input.value; }
                    input = h('div', { class: 'smlf-range-wrap' }, [input, out]);
                    break;
                case 'rating':
                    input = renderRating(f, val);
                    break;
                case 'address':
                    var a = val && typeof val === 'object' ? val : {};
                    var mk = function (k, ph, cls, ac) {
                        var el = h('input', { type: 'text', class: 'smlf-input ' + cls, placeholder: ph, 'aria-label': ph, autocomplete: ac, id: k === 'street' ? fieldId(f) : null });
                        el.value = a[k] || '';
                        el.addEventListener('input', function () {
                            var cur = Object.assign({}, state.answers[f.id] || {});
                            cur[k] = el.value;
                            setValue(f.id, cur);
                            clearError(f.id);
                        });
                        return el;
                    };
                    input = h('div', { class: 'smlf-address' }, [mk('street', t.street, 'smlf-a-street', 'street-address'), mk('zip', t.zip, 'smlf-a-zip', 'postal-code'), mk('city', t.city, 'smlf-a-city', 'address-level2')]);
                    break;
                case 'file':
                    input = renderFiles(f, describedBy);
                    break;
                case 'consent':
                    var cb = h('input', { type: 'checkbox', id: fieldId(f), 'aria-describedby': describedBy });
                    cb.checked = !!val;
                    cb.addEventListener('change', function () { setValue(f.id, cb.checked ? '1' : ''); clearError(f.id); });
                    var link = f.link_url ? h('a', { href: f.link_url, target: '_blank', rel: 'noopener', text: f.link_text || f.link_url }) : null;
                    input = h('label', { class: 'smlf-consent', for: fieldId(f) }, [cb, h('span', {}, [f.consent_text || f.label, link ? ' ' : null, link, f.required ? h('span', { class: 'smlf-req', text: ' *' }) : null])]);
                    label = null;
                    break;
                default:
                    var types = { email: 'email', phone: 'tel', number: 'number', url: 'url', date: 'date', text: 'text' };
                    var auto = { email: 'email', phone: 'tel' };
                    input = h('input', Object.assign({
                        type: types[f.type] || 'text', class: 'smlf-input', autocomplete: auto[f.type] || (/name/.test(f.id) ? 'name' : null),
                        inputmode: f.type === 'phone' ? 'tel' : (f.type === 'number' ? 'decimal' : null),
                        min: f.type === 'number' && f.min !== '' ? f.min : null, max: f.type === 'number' && f.max !== '' ? f.max : null,
                        step: f.type === 'number' ? f.step : null
                    }, common));
                    input.value = val || '';
                    bind(input);
                    if (f.type === 'number' && f.unit) { input = h('div', { class: 'smlf-unit-wrap' }, [input, h('span', { class: 'smlf-unit', text: f.unit })]); }
            }

            var box = h('div', { class: 'smlf-field smlf-w-' + f.width + ' smlf-t-' + f.type, 'data-field': f.id }, [label, input, help, err]);
            fieldEls[f.id] = box;
            return box;
        }

        function renderChoice(f, val, describedBy) {
            var values = Array.isArray(val) ? val.slice() : (val ? [val] : []);
            if (f.display === 'dropdown') {
                var sel = h('select', { class: 'smlf-input', id: fieldId(f), 'aria-describedby': describedBy, name: f.id }, [h('option', { value: '', text: t.choose + ' …' })]
                    .concat(f.options.map(function (o) { return h('option', { value: o.value, text: o.label, selected: values.indexOf(o.value) > -1 }); })));
                sel.addEventListener('change', function () { setValue(f.id, sel.value); clearError(f.id); });
                return sel;
            }
            var type = f.multiple ? 'checkbox' : 'radio';
            var group = h('div', { class: 'smlf-choices smlf-choices-' + f.display, role: f.multiple ? 'group' : 'radiogroup', 'aria-labelledby': fieldId(f, 'label'), 'aria-describedby': describedBy, id: fieldId(f) });
            f.options.forEach(function (o, i) {
                var inp = h('input', { type: type, name: root.id + '-' + f.id, value: o.value, id: fieldId(f, 'o' + i) });
                inp.checked = values.indexOf(o.value) > -1;
                inp.addEventListener('change', function () {
                    var cur = f.multiple ? [].slice.call(group.querySelectorAll('input:checked')).map(function (x) { return x.value; }) : inp.value;
                    setValue(f.id, cur);
                    clearError(f.id);
                    if (!f.multiple && autoAdvance()) { setTimeout(goNext, 260); }
                });
                var media = o.image ? h('img', { src: o.image, alt: '', class: 'smlf-opt-img', loading: 'lazy' }) : (o.icon ? h('span', { class: 'smlf-opt-icon', 'aria-hidden': 'true', text: o.icon }) : null);
                group.appendChild(h('label', { class: 'smlf-opt', for: fieldId(f, 'o' + i) }, [inp, media, h('span', { class: 'smlf-opt-label', text: o.label })]));
            });
            return group;
        }

        function renderRating(f, val) {
            var group = h('div', { class: 'smlf-rating', role: 'radiogroup', 'aria-labelledby': fieldId(f, 'label'), id: fieldId(f) });
            for (var i = 1; i <= f.max_stars; i++) {
                (function (n) {
                    var inp = h('input', { type: 'radio', name: root.id + '-' + f.id, value: n, id: fieldId(f, 's' + n), class: 'smlf-sr-input' });
                    inp.checked = String(val) === String(n);
                    inp.addEventListener('change', function () { setValue(f.id, String(n)); clearError(f.id); paint(); });
                    group.appendChild(inp);
                    group.appendChild(h('label', { for: fieldId(f, 's' + n), class: 'smlf-star', title: fmt(t.stars, n, f.max_stars), 'aria-label': fmt(t.stars, n, f.max_stars), text: '★' }));
                })(i);
            }
            function paint() {
                var cur = parseInt(state.answers[f.id] || 0, 10);
                [].forEach.call(group.querySelectorAll('.smlf-star'), function (s, i) { s.classList.toggle('is-on', i < cur); });
            }
            paint();
            return group;
        }

        function renderFiles(f, describedBy) {
            var list = h('ul', { class: 'smlf-file-list' });
            var inp = h('input', { type: 'file', multiple: f.max_files > 1, id: fieldId(f), class: 'smlf-file-input', 'aria-describedby': describedBy,
                accept: f.accept ? f.accept.split(',').map(function (e) { return '.' + e; }).join(',') : null });
            var zone = h('label', { class: 'smlf-drop', for: fieldId(f) }, [h('span', { class: 'smlf-drop-icon', 'aria-hidden': 'true', text: '⬆' }), h('span', { text: t.files }),
                f.accept ? h('small', { text: f.accept.toUpperCase().split(',').join(' · ') + ' · max ' + cfg.upload.max_mb + ' MB' }) : null]);
            function add(fl) {
                var cur = state.files[f.id] || [];
                [].slice.call(fl).forEach(function (file) {
                    if (file.size > cfg.upload.max_mb * 1048576) { showError(f.id, fmt(t.too_big, cfg.upload.max_mb)); return; }
                    if (cur.length >= Math.min(f.max_files, cfg.upload.max_count)) { showError(f.id, fmt(t.too_many, Math.min(f.max_files, cfg.upload.max_count))); return; }
                    cur.push(file);
                });
                state.files[f.id] = cur;
                paint();
            }
            function paint() {
                list.textContent = '';
                (state.files[f.id] || []).forEach(function (file, i) {
                    list.appendChild(h('li', {}, [h('span', { text: file.name + ' (' + Math.max(1, Math.round(file.size / 1024)) + ' KB)' }),
                        h('button', { type: 'button', class: 'smlf-file-remove', 'aria-label': t.remove + ': ' + file.name, text: '×', onclick: function () { state.files[f.id].splice(i, 1); paint(); } })]));
                });
            }
            inp.addEventListener('change', function () { clearError(f.id); add(inp.files); inp.value = ''; });
            ['dragenter', 'dragover'].forEach(function (ev) { zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.add('is-over'); }); });
            ['dragleave', 'drop'].forEach(function (ev) { zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.remove('is-over'); }); });
            zone.addEventListener('drop', function (e) { clearError(f.id); add(e.dataTransfer.files); });
            paint();
            return h('div', { class: 'smlf-files' }, [inp, zone, list]);
        }

        // ------------------------------------------------------------------ steps
        function stepFields(st) {
            return st.fields.filter(function (f) { return f.type !== 'hidden'; });
        }

        /** Steps the visitor will go through with the current answers (for the progress display). */
        function plannedPath() {
            var path = [], i = 0, guard = 0;
            while (steps[i] && path.indexOf(steps[i].id) < 0 && guard++ < 100) {
                path.push(steps[i].id);
                if (steps[i].terminal === 'reset') { break; }
                var target = nextTarget(steps[i], state.answers);
                if (target === 'end') { break; }
                i = target === '' ? following(steps, i) : (index[target] !== undefined ? index[target] : following(steps, i));
            }
            return path;
        }

        function showStep(i, focus) {
            state.current = i;
            state.reviewing = false;
            var st = steps[i];
            body.textContent = '';
            fieldEls = {};
            var heading = st.title ? h('h3', { class: 'smlf-step-title', tabindex: '-1', text: st.title }) : null;
            var grid = h('div', { class: 'smlf-grid' }, stepFields(st).map(renderField));
            var panel = h('div', { class: 'smlf-step', 'data-step': st.id }, [heading, st.description ? h('p', { class: 'smlf-step-desc', text: st.description }) : null, grid]);
            // The captcha sits on the page with the submit button (the review page when it is on).
            if (isLastStep() && !s.review && st.terminal !== 'reset') { panel.appendChild(captchaBox()); }
            body.appendChild(panel);
            requestAnimationFrame(function () { panel.classList.add('is-in'); });
            refresh();
            if (focus && heading) { heading.focus({ preventScroll: true }); }
            if (focus) { scrollIntoViewIfNeeded(); }
            if (!cfg.preview && !state.seen[st.id]) { state.seen[st.id] = true; if (state.started || i > 0) { event('step', st.id); } }
        }

        function isLastStep() {
            var st = steps[state.current];
            var target = nextTarget(st, state.answers);
            return target === 'end' || (target === '' && !steps[following(steps, state.current)]);
        }

        function refresh() {
            var st = steps[state.current];
            if (!st || state.done) { return; }
            stepFields(st).forEach(function (f) {
                var box = fieldEls[f.id];
                if (box) { box.hidden = !visible(f, state.answers); }
            });
            // Progress
            var path = plannedPath();
            var pos = Math.max(0, path.indexOf(st.id));
            var total = path.length + (s.review ? 1 : 0);
            var current = state.reviewing ? total : pos + 1;
            progress.textContent = '';
            if (s.progress === 'bar') {
                progress.appendChild(h('div', { class: 'smlf-bar' }, [h('span', { style: 'width:' + Math.round(100 * current / total) + '%' })]));
            } else if (s.progress === 'steps') {
                for (var k = 1; k <= total; k++) {
                    progress.appendChild(h('span', { class: 'smlf-dot' + (k < current ? ' is-done' : (k === current ? ' is-current' : '')) }));
                }
            }
            if (s.progress !== 'none') { progress.appendChild(h('span', { class: 'smlf-progress-label', text: fmt(t.step, current, total) })); }
            progressText.textContent = fmt(t.step, current, total);
            // Buttons
            back.hidden = !state.history.length;
            if (st.terminal === 'reset') {
                next.textContent = t.restart;
            } else {
                next.textContent = state.reviewing || (isLastStep() && !s.review) ? t.submit : (isLastStep() && s.review ? t.review : t.next);
            }
            next.classList.toggle('smlf-btn-submit', next.textContent === t.submit);
            persist();
        }

        // ------------------------------------------------------------------ validation
        function showError(id, msg) {
            var box = fieldEls[id];
            if (!box) { return; }
            box.classList.add('has-error');
            box.querySelector('.smlf-error').textContent = msg;
            var input = box.querySelector('input,select,textarea');
            if (input) { input.setAttribute('aria-invalid', 'true'); }
        }

        function clearError(id) {
            var box = fieldEls[id];
            if (!box) { return; }
            box.classList.remove('has-error');
            var e = box.querySelector('.smlf-error');
            if (e) { e.textContent = ''; }
            var input = box.querySelector('[aria-invalid]');
            if (input) { input.removeAttribute('aria-invalid'); }
            status.textContent = '';
        }

        function validateStep() {
            var st = steps[state.current], first = null;
            stepFields(st).forEach(function (f) {
                if (f.type === 'message' || !visible(f, state.answers)) { return; }
                var v = state.answers[f.id], msg = '';
                if (f.type === 'file') {
                    if (f.required && !(state.files[f.id] || []).length) { msg = t.required; }
                } else if (f.required && isEmpty(v)) {
                    msg = f.type === 'consent' ? t.confirm : t.required;
                } else if (!isEmpty(v)) {
                    if (f.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v)) { msg = t.email; }
                    if (f.type === 'phone' && (!/^[0-9+()\-\s\/.]+$/.test(v) || (String(v).match(/\d/g) || []).length < 5)) { msg = t.phone; }
                    if (f.type === 'number' && isNaN(String(v).replace(',', '.'))) { msg = t.number; }
                    if (f.type === 'url' && !/^https?:\/\/\S+\.\S+/.test(v)) { msg = t.url; }
                    if (f.type === 'address' && f.required && (isEmpty(v.zip) || isEmpty(v.city))) { msg = t.required; }
                }
                if (msg) {
                    showError(f.id, msg);
                    first = first || f.id;
                }
            });
            if (first) {
                var el = fieldEls[first].querySelector('input:not([type=hidden]),select,textarea');
                if (el) { el.focus(); }
                return false;
            }
            return true;
        }

        // ------------------------------------------------------------------ navigation
        function autoAdvance() {
            var st = steps[state.current];
            var inputs = stepFields(st).filter(function (f) { return f.type !== 'message' && visible(f, state.answers); });
            return inputs.length === 1 && inputs[0].type === 'choice' && !inputs[0].multiple && inputs[0].display !== 'dropdown' && !isLastStep();
        }

        function goNext() {
            if (state.sending || state.done) { return; }
            var st = steps[state.current];
            if (st.terminal === 'reset') { restart(); return; }
            if (state.reviewing) { submit(); return; }
            if (!validateStep()) { return; }
            var target = nextTarget(st, state.answers);
            var nextIndex = target === 'end' ? -1 : (target === '' ? following(steps, state.current) : index[target]);
            if (nextIndex === -1 || nextIndex === undefined || !steps[nextIndex]) {
                if (s.review) { showReview(); } else { submit(); }
                return;
            }
            state.history.push(state.current);
            savePartial(steps[nextIndex].id);
            showStep(nextIndex, true);
        }

        function goBack() {
            if (state.sending) { return; }
            if (state.reviewing) { showStep(state.current, true); return; }
            var prev = state.history.pop();
            if (prev !== undefined) { showStep(prev, true); }
        }

        function restart() {
            state.answers = {};
            state.files = {};
            state.history = [];
            state.token = '';
            clearStore();
            steps.forEach(function (st) { st.fields.forEach(function (f) { if (f.type === 'hidden') { state.answers[f.id] = hiddenValue(f); } }); });
            showStep(0, true);
        }

        function showReview() {
            state.reviewing = true;
            body.textContent = '';
            var path = plannedPath();
            var list = h('div', { class: 'smlf-review' });
            steps.forEach(function (st, i) {
                if (path.indexOf(st.id) < 0) { return; }
                var rows = [];
                stepFields(st).forEach(function (f) {
                    if (f.type === 'message' || !visible(f, state.answers)) { return; }
                    var v = f.type === 'file' ? (state.files[f.id] || []).map(function (x) { return x.name; }) : state.answers[f.id];
                    if (isEmpty(v)) { return; }
                    rows.push(h('div', { class: 'smlf-review-row' }, [h('dt', { text: f.label || f.consent_text || f.id }), h('dd', { text: displayValue(f, v) })]));
                });
                if (!rows.length) { return; }
                list.appendChild(h('div', { class: 'smlf-review-step' }, [
                    h('div', { class: 'smlf-review-head' }, [h('strong', { text: st.title }), h('button', { type: 'button', class: 'smlf-link', text: t.edit, onclick: function () {
                        state.history = state.history.slice(0, Math.max(0, state.history.indexOf(i)));
                        showStep(i, true);
                    } })]),
                    h('dl', {}, rows)
                ]));
            });
            var panel = h('div', { class: 'smlf-step' }, [h('h3', { class: 'smlf-step-title', tabindex: '-1', text: t.review }), list, captchaBox()]);
            body.appendChild(panel);
            requestAnimationFrame(function () { panel.classList.add('is-in'); });
            refresh();
            panel.querySelector('h3').focus({ preventScroll: true });
            scrollIntoViewIfNeeded();
        }

        function displayValue(f, v) {
            if (f.type === 'choice') {
                var map = {};
                f.options.forEach(function (o) { map[o.value] = o.label; });
                return [].concat(v).map(function (x) { return map[x] || x; }).join(', ');
            }
            if (f.type === 'address') { return [v.street, [v.zip, v.city].filter(Boolean).join(' ')].filter(Boolean).join(', '); }
            if (f.type === 'consent') { return '✓'; }
            if (f.type === 'rating') { return '★'.repeat(parseInt(v, 10) || 0); }
            if ((f.type === 'number' || f.type === 'range') && f.unit) { return v + ' ' + f.unit; }
            return [].concat(v).join(', ');
        }

        // ------------------------------------------------------------------ captcha
        function captchaBox() {
            var box = h('div', { class: 'smlf-captcha' });
            var m = cfg.captcha.method;
            if (cfg.preview || m === 'none' || m === 'recaptcha_v3') { return box; }
            var tries = 0;
            (function render() {
                if (m === 'turnstile' && window.turnstile) {
                    captchaWidget = window.turnstile.render(box, { sitekey: cfg.captcha.site_key });
                } else if (m === 'recaptcha_v2' && window.grecaptcha && window.grecaptcha.render) {
                    captchaWidget = window.grecaptcha.render(box, { sitekey: cfg.captcha.site_key });
                } else if (tries++ < 50) {
                    setTimeout(render, 200);
                }
            })();
            return box;
        }

        function captchaToken() {
            var m = cfg.captcha.method;
            return new Promise(function (resolve) {
                if (cfg.preview || m === 'none') { resolve(''); return; }
                if (m === 'turnstile') { resolve(window.turnstile && captchaWidget !== null ? window.turnstile.getResponse(captchaWidget) || '' : ''); return; }
                if (m === 'recaptcha_v2') { resolve(window.grecaptcha && captchaWidget !== null ? window.grecaptcha.getResponse(captchaWidget) : ''); return; }
                if (window.grecaptcha && window.grecaptcha.ready) {
                    window.grecaptcha.ready(function () {
                        window.grecaptcha.execute(cfg.captcha.site_key, { action: 'submit' }).then(resolve, function () { resolve(''); });
                    });
                } else { resolve(''); }
            });
        }

        // ------------------------------------------------------------------ network
        function meta(fd) {
            var p = new URLSearchParams(window.location.search);
            fd.append('page_url', window.location.href.slice(0, 500));
            fd.append('referrer', document.referrer.slice(0, 500));
            ['utm_source', 'utm_medium', 'utm_campaign'].forEach(function (k) { fd.append(k, (p.get(k) || '').slice(0, 120)); });
        }

        function post(fd) {
            return fetch(cfg.ajax, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) {
                return r.json().catch(function () { return { success: false, data: { message: t.error } }; });
            });
        }

        function event(type, step) {
            if (cfg.preview) { return; }
            var fd = new FormData();
            fd.append('action', 'smlf_event');
            fd.append('form_id', cfg.id);
            fd.append('type', type);
            if (step) { fd.append('step', step); }
            if (navigator.sendBeacon) { navigator.sendBeacon(cfg.ajax, fd); } else { post(fd); }
        }

        function savePartial(step) {
            if (cfg.preview || !cfg.partial) { return; }
            var fd = new FormData();
            fd.append('action', 'smlf_save');
            fd.append('form_id', cfg.id);
            fd.append('token', state.token);
            fd.append('step', step);
            fd.append('answers', JSON.stringify(state.answers));
            meta(fd);
            post(fd).then(function (res) { if (res && res.success && res.data && res.data.token) { state.token = res.data.token; persist(); } });
        }

        function submit() {
            if (state.sending) { return; }
            if (!validateStep()) { return; }
            state.sending = true;
            next.disabled = true;
            back.disabled = true;
            next.textContent = t.sending;
            status.textContent = '';
            if (cfg.preview) {
                setTimeout(function () { success({ title: '✓', message: t.preview }); }, 400);
                return;
            }
            captchaToken().then(function (token) {
                if (cfg.captcha.method !== 'none' && !token) { fail(t.robot); return; }
                var fd = new FormData();
                fd.append('action', 'smlf_submit');
                fd.append('form_id', cfg.id);
                fd.append('token', state.token);
                fd.append('stamp', cfg.stamp);
                fd.append('captcha', token);
                fd.append('smlf_hp', hp.value);
                fd.append('answers', JSON.stringify(state.answers));
                meta(fd);
                Object.keys(state.files).forEach(function (id) {
                    state.files[id].forEach(function (file) { fd.append('smlf_files[' + id + '][]', file, file.name); });
                });
                post(fd).then(function (res) {
                    if (res && res.success) { success(res.data || {}); return; }
                    var d = (res && res.data) || {};
                    if (d.errors && d.step !== undefined && index[d.step] !== undefined) {
                        state.sending = false;
                        next.disabled = back.disabled = false;
                        showStep(index[d.step], true);
                        Object.keys(d.errors).forEach(function (id) { showError(id, d.errors[id]); });
                    }
                    fail(d.message || t.error);
                }, function () { fail(t.error); });
            });
        }

        function fail(msg) {
            state.sending = false;
            next.disabled = back.disabled = false;
            refresh();
            status.textContent = msg;
            if (captchaWidget !== null) {
                if (cfg.captcha.method === 'turnstile' && window.turnstile) { window.turnstile.reset(captchaWidget); }
                if (cfg.captcha.method === 'recaptcha_v2' && window.grecaptcha) { window.grecaptcha.reset(captchaWidget); }
            }
        }

        function success(d) {
            state.done = true;
            clearStore();
            if (d.redirect) { window.location.href = d.redirect; return; }
            formEl.textContent = '';
            formEl.appendChild(h('div', { class: 'smlf-success', role: 'status' }, [
                h('div', { class: 'smlf-success-icon', 'aria-hidden': 'true' }, [h('span', { text: '✓' })]),
                h('h3', { tabindex: '-1', text: d.title || '' }),
                d.message ? h('p', { text: d.message }) : null
            ]));
            formEl.querySelector('h3').focus({ preventScroll: true });
            scrollIntoViewIfNeeded();
        }

        // ------------------------------------------------------------------ resume (browser storage)
        function persist() {
            if (cfg.preview || !s.save_progress || state.done) { return; }
            try {
                localStorage.setItem(storeKey, JSON.stringify({ a: state.answers, c: state.current, hst: state.history, tk: state.token, t: Date.now() }));
            } catch (e) { /* storage blocked */ }
        }

        function clearStore() {
            try { localStorage.removeItem(storeKey); } catch (e) { /* ignore */ }
        }

        function offerResume() {
            var saved = null;
            try { saved = JSON.parse(localStorage.getItem(storeKey) || 'null'); } catch (e) { saved = null; }
            if (!saved || !saved.a || Date.now() - saved.t > 7 * 864e5 || !steps[saved.c] || (saved.c === 0 && !saved.hst.length)) { return false; }
            body.textContent = '';
            nav.hidden = true;
            body.appendChild(h('div', { class: 'smlf-resume' }, [
                h('p', { text: t.resume }),
                h('div', { class: 'smlf-nav' }, [
                    h('button', { type: 'button', class: 'smlf-btn smlf-btn-ghost', text: t.resume_no, onclick: function () { nav.hidden = false; clearStore(); restart(); } }),
                    h('button', { type: 'button', class: 'smlf-btn smlf-btn-primary', text: t.resume_yes, onclick: function () {
                        nav.hidden = false;
                        state.answers = Object.assign(state.answers, saved.a);
                        state.history = saved.hst || [];
                        state.token = saved.tk || '';
                        state.started = true;
                        showStep(saved.c, true);
                    } })
                ])
            ]));
            return true;
        }

        function scrollIntoViewIfNeeded() {
            var r = wrap.getBoundingClientRect();
            if (r.top < 0 || r.top > window.innerHeight * 0.6) { wrap.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
        }

        // Enter = next (not in textareas)
        formEl.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && e.target.tagName === 'INPUT' && ['checkbox', 'radio', 'file'].indexOf(e.target.type) < 0) {
                e.preventDefault();
                goNext();
            }
        });

        // View event once the form is actually seen
        if (!cfg.preview && 'IntersectionObserver' in window) {
            var io = new IntersectionObserver(function (entries) {
                if (entries[0].isIntersecting) { event('view'); io.disconnect(); }
            }, { threshold: 0.3 });
            io.observe(wrap);
        } else if (!cfg.preview) { event('view'); }

        if (!(s.save_progress && !cfg.preview && offerResume())) { showStep(0, false); }

        var api = { destroy: function () { root.innerHTML = ''; root._smlf = null; }, state: state };
        root._smlf = api;
        return api;
    };

    function boot() {
        [].forEach.call(document.querySelectorAll('.smlf-root[data-smlf]'), function (el) {
            if (el._smlf) { return; }
            try { SMLF.mount(el, JSON.parse(el.getAttribute('data-smlf'))); } catch (e) { if (window.console) { console.error('SMLF', e); } }
        });
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
})();
