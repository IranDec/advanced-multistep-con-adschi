/*!
 * Smart MultiStep Lead Forms 2 - visual form builder.
 * State lives in S.form (schema 2, same as the server); the server normalises it again on save.
 * @author Mohammad Babaei | https://adschi.com
 */
(function () {
    'use strict';

    var root = document.getElementById('smlf-builder');
    if (!root) { return; }
    var C = JSON.parse(root.getAttribute('data-config'));
    var T = C.i18n;
    var S = { id: C.id, title: C.title, status: C.status, form: C.form, step: 0, field: null, tab: 'step', dirty: false, saving: false, saveState: '' };
    var OPS = ['eq', 'neq', 'contains', 'not_contains', 'empty', 'not_empty', 'gt', 'lt'];

    // ------------------------------------------------------------------ helpers

    function h(tag, attrs, kids) {
        var el = document.createElement(tag);
        Object.keys(attrs || {}).forEach(function (k) {
            var v = attrs[k];
            if (v === null || v === undefined || v === false) { return; }
            if (k === 'text') { el.textContent = v; }
            else if (k === 'class') { el.className = v; }
            else if (k === 'value') { el.value = v; }
            else if (k === 'checked') { el.checked = !!v; }
            else if (k.slice(0, 2) === 'on') { el.addEventListener(k.slice(2), v); }
            else { el.setAttribute(k, v === true ? '' : v); }
        });
        [].concat(kids || []).forEach(function (c) {
            if (c === null || c === undefined || c === false) { return; }
            el.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
        });
        return el;
    }

    function slug(s) {
        var map = { 'ä': 'ae', 'ö': 'oe', 'ü': 'ue', 'ß': 'ss' };
        s = String(s || '').toLowerCase().replace(/[äöüß]/g, function (c) { return map[c]; });
        return s.replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').slice(0, 30);
    }

    function steps() { return S.form.steps; }
    function curStep() { return steps()[S.step]; }

    function allFields() {
        var out = [];
        steps().forEach(function (st, si) { st.fields.forEach(function (f) { out.push({ f: f, si: si }); }); });
        return out;
    }

    function findField(id) {
        var all = allFields();
        for (var i = 0; i < all.length; i++) { if (all[i].f.id === id) { return all[i]; } }
        return null;
    }

    function uniqueFieldId(base, except) {
        base = slug(base) || 'field';
        var id = base, n = 2;
        while (allFields().some(function (x) { return x.f.id === id && x.f !== except; })) { id = base + '_' + n++; }
        return id;
    }

    function uniqueStepId() {
        var n = steps().length + 1;
        while (steps().some(function (s) { return s.id === 's' + n; })) { n++; }
        return 's' + n;
    }

    function typeLabel(type) { return C.types[type] ? C.types[type][1] : type; }
    function typeIcon(type) { return C.types[type] ? C.types[type][0] : '•'; }

    // ------------------------------------------------------------------ change tracking

    function changed(full) {
        S.dirty = true;
        S.saveState = '';
        renderTop();
        renderLeft();
        renderCanvas();
        if (full) { renderInspector(); }
        refreshPreview();
    }

    window.addEventListener('beforeunload', function (e) {
        if (S.dirty) { e.preventDefault(); e.returnValue = T.leave; }
    });

    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 's') { e.preventDefault(); save(); }
        if (e.key === 'Escape' && previewEl.classList.contains('is-open')) { closePreview(); }
    });

    // ------------------------------------------------------------------ layout

    root.textContent = '';
    var top = h('div', { class: 'smlf-b-top' });
    var left = h('aside', { class: 'smlf-b-left' });
    var canvas = h('main', { class: 'smlf-b-canvas' });
    var inspector = h('aside', { class: 'smlf-b-inspector' });
    var previewBody = h('div', { class: 'smlf-b-preview-body' });
    var previewFrame = h('div', { class: 'smlf-b-preview-frame' }, [previewBody]);
    var previewEl = h('div', { class: 'smlf-b-preview', role: 'dialog', 'aria-modal': 'true', 'aria-label': T.preview }, [
        h('div', { class: 'smlf-b-preview-bar' }, [
            h('strong', { text: T.preview }),
            h('div', { class: 'smlf-b-seg' }, [
                h('button', { type: 'button', class: 'is-on', text: T.desktop, onclick: function (e) { setDevice(e.target, ''); } }),
                h('button', { type: 'button', text: T.mobile, onclick: function (e) { setDevice(e.target, 'is-mobile'); } })
            ]),
            h('button', { type: 'button', class: 'button', text: T.close, onclick: closePreview })
        ]),
        previewFrame
    ]);
    root.appendChild(top);
    root.appendChild(h('div', { class: 'smlf-b-main' }, [left, canvas, inspector]));
    document.body.appendChild(previewEl);

    // ------------------------------------------------------------------ top bar

    function renderTop() {
        top.textContent = '';
        var stateText = S.saving ? T.saving : (S.saveState === 'error' ? T.error : (S.dirty ? T.unsaved : (S.saveState === 'saved' ? T.saved : '')));
        top.appendChild(h('a', { class: 'smlf-b-back', href: C.formsUrl }, ['← ', T.back]));
        top.appendChild(h('input', { class: 'smlf-b-title', value: S.title, 'aria-label': T.title, oninput: function (e) { S.title = e.target.value; S.dirty = true; S.saveState = ''; renderStateOnly(); } }));
        top.appendChild(h('label', { class: 'smlf-b-switch' }, [
            h('input', { type: 'checkbox', checked: S.status === 'publish', onchange: function (e) { S.status = e.target.checked ? 'publish' : 'draft'; changed(); } }),
            h('span', { text: S.status === 'publish' ? T.published : T.draft })
        ]));
        if (S.id) {
            var sc = '[smlf_form id="' + S.id + '"]';
            top.appendChild(h('button', { type: 'button', class: 'smlf-b-chip', title: T.shortcode, 'data-smlf-copy-text': sc, text: sc }));
        }
        top.appendChild(h('span', { class: 'smlf-b-state' + (S.saveState === 'error' ? ' is-error' : '') + (S.dirty ? ' is-dirty' : ''), id: 'smlf-b-state', text: stateText }));
        top.appendChild(h('button', { type: 'button', class: 'button', onclick: openPreview }, [h('span', { class: 'dashicons dashicons-visibility' }), T.preview]));
        top.appendChild(h('button', { type: 'button', class: 'button button-primary', disabled: S.saving, onclick: save }, [h('span', { class: 'dashicons dashicons-saved' }), T.save]));
    }

    function renderStateOnly() {
        var el = document.getElementById('smlf-b-state');
        if (el) { el.textContent = T.unsaved; el.className = 'smlf-b-state is-dirty'; }
    }

    // ------------------------------------------------------------------ left: steps + palette

    var drag = null;

    function renderLeft() {
        left.textContent = '';
        left.appendChild(h('div', { class: 'smlf-b-h' }, [T.steps]));
        var list = h('ol', { class: 'smlf-b-steps' });
        steps().forEach(function (st, i) {
            var item = h('li', {
                class: 'smlf-b-step' + (i === S.step ? ' is-active' : '') + (st.terminal === 'reset' ? ' is-terminal' : ''),
                draggable: 'true',
                onclick: function () { S.step = i; S.field = null; S.tab = 'step'; renderLeft(); renderCanvas(); renderInspector(); },
                ondragstart: function (e) { drag = { kind: 'step', from: i }; e.dataTransfer.effectAllowed = 'move'; },
                ondragover: function (e) { if (drag) { e.preventDefault(); item.classList.add('is-over'); } },
                ondragleave: function () { item.classList.remove('is-over'); },
                ondrop: function (e) {
                    e.preventDefault();
                    item.classList.remove('is-over');
                    if (drag && drag.kind === 'step' && drag.from !== i) {
                        var moved = steps().splice(drag.from, 1)[0];
                        steps().splice(i, 0, moved);
                        S.step = i;
                        changed(true);
                    } else if (drag && drag.kind === 'field') {
                        moveField(drag.id, i);
                    }
                    drag = null;
                }
            }, [
                h('span', { class: 'smlf-b-step-n', text: String(i + 1) }),
                h('span', { class: 'smlf-b-step-t', text: st.title || (T.step + ' ' + (i + 1)) }),
                st.routes.length ? h('span', { class: 'smlf-b-tag', title: T.jumps, text: '⤳' + st.routes.length }) : null,
                h('span', { class: 'smlf-b-step-c', text: String(st.fields.length) })
            ]);
            list.appendChild(item);
        });
        left.appendChild(list);
        left.appendChild(h('button', { type: 'button', class: 'button smlf-b-add-step', onclick: addStep }, ['+ ', T.add_step]));

        left.appendChild(h('div', { class: 'smlf-b-h' }, [T.fields]));
        var pal = h('div', { class: 'smlf-b-palette' });
        Object.keys(C.types).forEach(function (type) {
            pal.appendChild(h('button', {
                type: 'button', class: 'smlf-b-type', draggable: 'true',
                onclick: function () { addField(type); },
                ondragstart: function (e) { drag = { kind: 'type', type: type }; e.dataTransfer.effectAllowed = 'copy'; }
            }, [h('span', { class: 'smlf-b-type-i', 'aria-hidden': 'true', text: typeIcon(type) }), h('span', { text: typeLabel(type) })]));
        });
        left.appendChild(pal);
    }

    function addStep() {
        steps().push({ id: uniqueStepId(), title: T.step + ' ' + (steps().length + 1), description: '', fields: [], routes: [], next: '', terminal: '' });
        S.step = steps().length - 1;
        S.field = null;
        S.tab = 'step';
        changed(true);
    }

    function newField(type) {
        var base = { text: 'text', textarea: 'message', email: 'email', phone: 'phone', choice: 'choice', number: 'number', range: 'slider', rating: 'rating', date: 'date', address: 'address', url: 'website', file: 'files', consent: 'consent', message: 'info', hidden: 'utm_source' }[type] || type;
        var f = { id: uniqueFieldId(base), type: type, label: ['message', 'consent', 'hidden'].indexOf(type) > -1 ? '' : T.new_field, placeholder: '', help: '', required: false, width: 'full', default: '', show_if: { match: 'all', rules: [] } };
        if (type === 'choice') { f.options = [1, 2, 3].map(function (n) { return { label: T.option_label + ' ' + n, value: T.option_label + ' ' + n, icon: '', image: '' }; }); f.display = 'cards'; f.multiple = false; }
        if (type === 'number') { f.min = ''; f.max = ''; f.step = 1; f.unit = ''; }
        if (type === 'range') { f.min = 0; f.max = 100; f.step = 1; f.unit = ''; }
        if (type === 'rating') { f.max_stars = 5; }
        if (type === 'file') { f.accept = 'jpg,jpeg,png,pdf'; f.max_files = 3; }
        if (type === 'consent') { f.consent_text = ''; f.link_text = ''; f.link_url = ''; f.required = true; }
        if (type === 'message') { f.content = '<p>' + T.content + '</p>'; }
        if (type === 'hidden') { f.source = 'utm_source'; f.param = ''; }
        return f;
    }

    function addField(type, index) {
        var f = newField(type);
        var list = curStep().fields;
        list.splice(index === undefined ? list.length : index, 0, f);
        S.field = f.id;
        S.tab = 'field';
        changed(true);
    }

    function moveField(id, toStep) {
        var hit = findField(id);
        if (!hit || hit.si === toStep) { return; }
        steps()[hit.si].fields = steps()[hit.si].fields.filter(function (f) { return f.id !== id; });
        steps()[toStep].fields.push(hit.f);
        S.step = toStep;
        changed(true);
    }

    // ------------------------------------------------------------------ canvas

    function mock(f) {
        switch (f.type) {
            case 'choice':
                return h('div', { class: 'smlf-b-mock-choices is-' + f.display }, f.options.slice(0, 6).map(function (o) {
                    return h('span', { class: 'smlf-b-mock-opt' }, [o.icon ? h('span', { text: o.icon + ' ' }) : null, o.label]);
                }));
            case 'rating': return h('div', { class: 'smlf-b-mock-stars', text: '★'.repeat(f.max_stars) });
            case 'range': return h('div', { class: 'smlf-b-mock-range' }, [h('span')]);
            case 'file': return h('div', { class: 'smlf-b-mock-drop', text: '⬆ ' + (f.accept || '') });
            case 'consent': return h('div', { class: 'smlf-b-mock-consent' }, [h('span', { class: 'smlf-b-mock-box' }), f.consent_text || T.consent_text]);
            case 'message': var d = h('div', { class: 'smlf-b-mock-msg' }); d.innerHTML = f.content || ''; return d;
            case 'hidden': return h('div', { class: 'smlf-b-mock-hidden', text: T.source + ': ' + (f.source === 'url_param' ? '?' + (f.param || f.id) : f.source) });
            case 'textarea': return h('div', { class: 'smlf-b-mock-input is-tall', text: f.placeholder || '' });
            case 'address': return h('div', { class: 'smlf-b-mock-input', text: '🏠 …' });
            default: return h('div', { class: 'smlf-b-mock-input', text: f.placeholder || '' });
        }
    }

    function renderCanvas() {
        canvas.textContent = '';
        var st = curStep();
        var head = h('div', { class: 'smlf-b-canvas-head' }, [
            h('span', { class: 'smlf-b-kicker', text: T.step + ' ' + (S.step + 1) + ' / ' + steps().length }),
            h('input', { class: 'smlf-b-step-title', value: st.title, placeholder: T.step_title, oninput: function (e) { st.title = e.target.value; S.dirty = true; renderStateOnly(); renderLeft(); } })
        ]);
        var paper = h('div', { class: 'smlf-b-paper' + (st.terminal === 'reset' ? ' is-terminal' : '') }, [head]);
        if (st.description) { paper.appendChild(h('p', { class: 'smlf-b-desc', text: st.description })); }

        var grid = h('div', { class: 'smlf-b-fields' });
        if (!st.fields.length) {
            grid.appendChild(h('div', { class: 'smlf-b-empty', text: T.empty_step }));
        }
        st.fields.forEach(function (f, i) {
            var conditional = f.show_if && f.show_if.rules && f.show_if.rules.length;
            var card = h('div', {
                class: 'smlf-b-field smlf-b-w-' + f.width + (S.field === f.id ? ' is-active' : ''),
                tabindex: '0', draggable: 'true',
                onclick: function () { S.field = f.id; S.tab = 'field'; renderCanvas(); renderInspector(); },
                onkeydown: function (e) { if (e.key === 'Enter') { S.field = f.id; S.tab = 'field'; renderCanvas(); renderInspector(); } },
                ondragstart: function (e) { drag = { kind: 'field', id: f.id, from: i }; e.dataTransfer.effectAllowed = 'move'; card.classList.add('is-dragging'); },
                ondragend: function () { card.classList.remove('is-dragging'); },
                ondragover: function (e) { if (drag && drag.kind !== 'step') { e.preventDefault(); card.classList.add('is-over'); } },
                ondragleave: function () { card.classList.remove('is-over'); },
                ondrop: function (e) { e.preventDefault(); e.stopPropagation(); card.classList.remove('is-over'); dropOn(i); }
            }, [
                h('div', { class: 'smlf-b-field-top' }, [
                    h('span', { class: 'smlf-b-grip', 'aria-hidden': 'true', text: '⋮⋮' }),
                    h('span', { class: 'smlf-b-ficon', text: typeIcon(f.type) }),
                    h('strong', { text: f.label || f.consent_text || typeLabel(f.type) }),
                    f.required ? h('span', { class: 'smlf-b-req', text: '*' }) : null,
                    conditional ? h('span', { class: 'smlf-b-tag', title: T.conditional, text: '⚡ ' + T.conditional }) : null,
                    h('span', { class: 'smlf-b-fid', text: f.id })
                ]),
                mock(f),
                h('div', { class: 'smlf-b-field-actions' }, [
                    h('button', { type: 'button', class: 'smlf-b-icon', title: T.duplicate, 'aria-label': T.duplicate, text: '⧉', onclick: function (e) { e.stopPropagation(); duplicateField(i); } }),
                    h('button', { type: 'button', class: 'smlf-b-icon is-danger', title: T.delete, 'aria-label': T.delete, text: '✕', onclick: function (e) { e.stopPropagation(); deleteField(f.id); } })
                ])
            ]);
            grid.appendChild(card);
        });
        var endZone = h('div', {
            class: 'smlf-b-dropzone',
            ondragover: function (e) { if (drag && drag.kind !== 'step') { e.preventDefault(); endZone.classList.add('is-over'); } },
            ondragleave: function () { endZone.classList.remove('is-over'); },
            ondrop: function (e) { e.preventDefault(); endZone.classList.remove('is-over'); dropOn(st.fields.length); }
        }, ['+']);
        grid.appendChild(endZone);
        paper.appendChild(grid);

        // Where does the visitor go after this step?
        var flow = h('button', { type: 'button', class: 'smlf-b-flow', onclick: function () { S.tab = 'step'; renderInspector(); } });
        if (st.terminal === 'reset') {
            flow.appendChild(h('span', { text: '⛔ ' + T.terminal.split(':')[0] }));
        } else {
            st.routes.forEach(function (r) {
                var target = steps().filter(function (x) { return x.id === r.goto; })[0];
                flow.appendChild(h('span', { class: 'smlf-b-flow-rule', text: T.if + ' ' + r.when.field + ' ' + T['op_' + r.when.op] + ' “' + r.when.value + '” → ' + (target ? (target.title || target.id) : r.goto) }));
            });
            var fi = S.step + 1;
            while (steps()[fi] && steps()[fi].terminal === 'reset') { fi++; }
            var nxt = st.next === 'end' ? T.submit_here : (st.next ? ((steps().filter(function (x) { return x.id === st.next; })[0] || {}).title || st.next) : (steps()[fi] ? (steps()[fi].title || T.following) : T.submit_here));
            flow.appendChild(h('span', { class: 'smlf-b-flow-rule', text: (st.routes.length ? T.otherwise + ' → ' : '→ ') + nxt }));
        }
        paper.appendChild(flow);
        canvas.appendChild(paper);
    }

    function dropOn(index) {
        var list = curStep().fields;
        if (!drag) { return; }
        if (drag.kind === 'type') {
            addField(drag.type, index);
        } else if (drag.kind === 'field') {
            var hit = findField(drag.id);
            if (hit) {
                steps()[hit.si].fields.splice(steps()[hit.si].fields.indexOf(hit.f), 1);
                if (hit.si === S.step && hit.f && drag.from < index) { index--; }
                list.splice(index, 0, hit.f);
                changed(true);
            }
        }
        drag = null;
    }

    function duplicateField(i) {
        var copy = JSON.parse(JSON.stringify(curStep().fields[i]));
        copy.id = uniqueFieldId(copy.id);
        curStep().fields.splice(i + 1, 0, copy);
        S.field = copy.id;
        changed(true);
    }

    function deleteField(id) {
        steps().forEach(function (st) {
            st.fields = st.fields.filter(function (f) { return f.id !== id; });
            st.routes = st.routes.filter(function (r) { return r.when.field !== id; });
            st.fields.forEach(function (f) { f.show_if.rules = f.show_if.rules.filter(function (r) { return r.field !== id; }); });
        });
        if (S.field === id) { S.field = null; S.tab = 'step'; }
        changed(true);
    }

    function renameField(f, wanted) {
        var old = f.id;
        var id = uniqueFieldId(wanted || old, f);
        if (id === old) { return; }
        f.id = id;
        steps().forEach(function (st) {
            st.routes.forEach(function (r) { if (r.when.field === old) { r.when.field = id; } });
            st.fields.forEach(function (x) { x.show_if.rules.forEach(function (r) { if (r.field === old) { r.field = id; } }); });
        });
        S.field = id;
    }

    // ------------------------------------------------------------------ inspector

    function renderInspector() {
        inspector.textContent = '';
        var tabs = h('div', { class: 'smlf-b-tabs', role: 'tablist' });
        ['field', 'step', 'form'].forEach(function (k) {
            tabs.appendChild(h('button', { type: 'button', role: 'tab', 'aria-selected': S.tab === k ? 'true' : 'false', class: S.tab === k ? 'is-on' : '', text: T['tab_' + k], onclick: function () { S.tab = k; renderInspector(); } }));
        });
        inspector.appendChild(tabs);
        var body = h('div', { class: 'smlf-b-insp-body' });
        inspector.appendChild(body);
        if (S.tab === 'field') { fieldPanel(body); }
        else if (S.tab === 'step') { stepPanel(body); }
        else { formPanel(body); }
    }

    // generic controls ------------------------------------------------
    function row(label, control, help) {
        return h('div', { class: 'smlf-b-row' }, [label ? h('label', { text: label }) : null, control, help ? h('p', { class: 'smlf-b-help', text: help }) : null]);
    }
    function text(obj, key, opts) {
        opts = opts || {};
        var el = h(opts.multiline ? 'textarea' : 'input', { type: opts.type || 'text', class: 'smlf-b-input', value: obj[key] === undefined ? '' : obj[key], placeholder: opts.placeholder || null, rows: opts.multiline ? (opts.rows || 3) : null, min: opts.min, max: opts.max, step: opts.step });
        el.addEventListener('input', function () {
            obj[key] = opts.type === 'number' ? (el.value === '' ? '' : parseFloat(el.value)) : el.value;
            changed(!!opts.full);
        });
        return el;
    }
    function check(obj, key, label, full) {
        return h('label', { class: 'smlf-b-check' }, [h('input', { type: 'checkbox', checked: !!obj[key], onchange: function (e) { obj[key] = e.target.checked; changed(full); } }), h('span', { text: label })]);
    }
    function select(obj, key, options, full, onAfter) {
        var el = h('select', { class: 'smlf-b-input', onchange: function () { obj[key] = el.value; if (onAfter) { onAfter(el.value); } changed(full !== false); } },
            options.map(function (o) { return h('option', { value: o[0], text: o[1], selected: String(obj[key]) === String(o[0]) }); }));
        return el;
    }
    function seg(obj, key, options, onChange) {
        return h('div', { class: 'smlf-b-seg' }, options.map(function (o) {
            return h('button', { type: 'button', class: obj[key] === o[0] ? 'is-on' : '', text: o[1], onclick: function () {
                var from = obj[key];
                obj[key] = o[0];
                if (onChange && from !== o[0]) { onChange(from, o[0]); }
                changed(true);
            } });
        }));
    }
    function color(obj, key, label) {
        var input = h('input', { type: 'color', value: obj[key] || '#000000' });
        input.addEventListener('input', function () { obj[key] = input.value; changed(false); });
        return h('label', { class: 'smlf-b-color' }, [input, h('span', { text: label })]);
    }

    // conditions --------------------------------------------------------
    function conditionRow(cond, candidates, onRemove) {
        var field = findField(cond.field);
        var valueBox = h('span', { class: 'smlf-b-cond-value' });
        function paintValue() {
            valueBox.textContent = '';
            var f = findField(cond.field);
            if (cond.op === 'empty' || cond.op === 'not_empty') { return; }
            if (f && f.f.type === 'choice' && f.f.options.length) {
                if (!f.f.options.some(function (o) { return o.value === cond.value; })) { cond.value = f.f.options[0].value; }
                valueBox.appendChild(select(cond, 'value', f.f.options.map(function (o) { return [o.value, o.label]; }), false));
            } else {
                valueBox.appendChild(text(cond, 'value', { placeholder: '…' }));
            }
        }
        var fieldSel = h('select', { class: 'smlf-b-input', onchange: function () { cond.field = fieldSel.value; paintValue(); changed(false); } },
            candidates.map(function (c) { return h('option', { value: c.f.id, text: (c.f.label || c.f.id).slice(0, 40), selected: c.f.id === cond.field }); }));
        if (!field && candidates.length) { cond.field = candidates[0].f.id; }
        var opSel = h('select', { class: 'smlf-b-input smlf-b-op', onchange: function () { cond.op = opSel.value; paintValue(); changed(false); } },
            OPS.map(function (o) { return h('option', { value: o, text: T['op_' + o], selected: cond.op === o }); }));
        paintValue();
        return h('div', { class: 'smlf-b-cond' }, [fieldSel, opSel, valueBox, h('button', { type: 'button', class: 'smlf-b-icon is-danger', 'aria-label': T.delete, text: '✕', onclick: onRemove })]);
    }

    function candidatesFor(exceptId, upToStep) {
        return allFields().filter(function (x) {
            return x.f.id !== exceptId && ['message', 'file'].indexOf(x.f.type) < 0 && (upToStep === undefined || x.si <= upToStep);
        });
    }

    // field panel -----------------------------------------------------
    function fieldPanel(body) {
        var hit = findField(S.field);
        if (!hit) { body.appendChild(h('p', { class: 'smlf-b-hint', text: T.pick_field })); return; }
        var f = hit.f;
        body.appendChild(h('div', { class: 'smlf-b-insp-title' }, [h('span', { text: typeIcon(f.type) }), typeLabel(f.type)]));

        if (['message', 'consent', 'hidden'].indexOf(f.type) < 0) {
            body.appendChild(row(T.label, text(f, 'label')));
        }
        if (f.type === 'message') { body.appendChild(row(T.content, text(f, 'content', { multiline: true, rows: 6 }))); }
        if (f.type === 'consent') {
            body.appendChild(row(T.consent_text, text(f, 'consent_text', { multiline: true })));
            body.appendChild(row(T.link_text, text(f, 'link_text')));
            body.appendChild(row(T.link_url, text(f, 'link_url', { type: 'url', placeholder: 'https://' })));
        }
        if (f.type === 'hidden') {
            body.appendChild(row(T.source, select(f, 'source', [['utm_source', 'utm_source'], ['utm_medium', 'utm_medium'], ['utm_campaign', 'utm_campaign'], ['utm_term', 'utm_term'], ['utm_content', 'utm_content'], ['url_param', T.src_url_param], ['page_url', T.src_page_url], ['referrer', T.src_referrer], ['static', T.src_static]], true)));
            if (f.source === 'url_param') { body.appendChild(row(T.param, text(f, 'param', { placeholder: f.id }))); }
            if (f.source === 'static') { body.appendChild(row(T.default, text(f, 'default'))); }
        }
        if (['text', 'textarea', 'email', 'phone', 'number', 'url'].indexOf(f.type) > -1) {
            body.appendChild(row(T.placeholder, text(f, 'placeholder')));
        }
        if (f.type === 'choice') { optionsEditor(body, f); }
        if (f.type === 'number' || f.type === 'range') {
            body.appendChild(h('div', { class: 'smlf-b-cols' }, [row(T.min, text(f, 'min', { type: 'number' })), row(T.max, text(f, 'max', { type: 'number' })), row(T.stepsize, text(f, 'step', { type: 'number', min: 0 })), row(T.unit, text(f, 'unit', { placeholder: 'm²' }))]));
        }
        if (f.type === 'rating') { body.appendChild(row(T.stars, select(f, 'max_stars', [[3, 3], [4, 4], [5, 5], [6, 6], [7, 7], [10, 10]], false, function (v) { f.max_stars = parseInt(v, 10); }))); }
        if (f.type === 'file') {
            body.appendChild(row(T.accept, text(f, 'accept', { placeholder: 'jpg,png,pdf' })));
            body.appendChild(row(T.max_files, select(f, 'max_files', [[1, 1], [2, 2], [3, 3], [5, 5], [10, 10]], false, function (v) { f.max_files = parseInt(v, 10); })));
        }
        if (['message', 'hidden'].indexOf(f.type) < 0) {
            body.appendChild(row(T.help, text(f, 'help')));
            body.appendChild(check(f, 'required', T.required, false));
            body.appendChild(row(T.width, seg(f, 'width', [['full', T.w_full], ['half', T.w_half], ['third', T.w_third]])));
        }
        if (['text', 'number', 'range', 'choice', 'date'].indexOf(f.type) > -1) { body.appendChild(row(T.default, text(f, 'default'))); }

        var key = h('input', { class: 'smlf-b-input smlf-b-mono', value: f.id });
        key.addEventListener('change', function () { renameField(f, key.value); changed(true); });
        body.appendChild(row(T.id, key, T.id_help));

        // Conditional visibility
        var box = h('div', { class: 'smlf-b-box' }, [h('div', { class: 'smlf-b-box-h', text: '⚡ ' + T.show_if })]);
        var cands = candidatesFor(f.id);
        if (!f.show_if.rules.length) { box.appendChild(h('p', { class: 'smlf-b-help', text: T.always })); }
        else {
            box.appendChild(select(f.show_if, 'match', [['all', T.match_all], ['any', T.match_any]], false));
        }
        f.show_if.rules.forEach(function (r, i) {
            box.appendChild(conditionRow(r, cands, function () { f.show_if.rules.splice(i, 1); changed(true); }));
        });
        if (cands.length) {
            box.appendChild(h('button', { type: 'button', class: 'button button-small', text: '+ ' + T.add_condition, onclick: function () {
                f.show_if.rules.push({ field: cands[0].f.id, op: 'eq', value: '' });
                changed(true);
            } }));
        }
        body.appendChild(box);

        if (steps().length > 1) {
            body.appendChild(row(T.move_to, h('select', { class: 'smlf-b-input', onchange: function (e) { moveField(f.id, parseInt(e.target.value, 10)); } },
                steps().map(function (st, i) { return h('option', { value: i, text: (i + 1) + '. ' + (st.title || st.id), selected: i === hit.si }); }))));
        }
    }

    function optionsEditor(body, f) {
        body.appendChild(row(T.display, seg(f, 'display', [['cards', T.d_cards], ['buttons', T.d_buttons], ['list', T.d_list], ['dropdown', T.d_dropdown]])));
        if (f.display !== 'dropdown') { body.appendChild(check(f, 'multiple', T.multiple, true)); }
        var box = h('div', { class: 'smlf-b-box' }, [h('div', { class: 'smlf-b-box-h', text: T.options })]);
        f.options.forEach(function (o, i) {
            var label = h('input', { class: 'smlf-b-input', value: o.label, placeholder: T.option_label, 'aria-label': T.option_label });
            label.addEventListener('input', function () {
                var auto = o.value === '' || o.value === o.label;
                o.label = label.value;
                if (auto) { o.value = label.value; }
                changed(false);
            });
            var icon = h('input', { class: 'smlf-b-input smlf-b-emoji', value: o.icon, placeholder: '🙂', maxlength: 4, 'aria-label': T.icon });
            icon.addEventListener('input', function () { o.icon = icon.value; changed(false); });
            var up = h('button', { type: 'button', class: 'smlf-b-icon', 'aria-label': '↑', text: '↑', disabled: i === 0, onclick: function () { f.options.splice(i - 1, 0, f.options.splice(i, 1)[0]); changed(true); } });
            var del = h('button', { type: 'button', class: 'smlf-b-icon is-danger', 'aria-label': T.delete, text: '✕', onclick: function () { f.options.splice(i, 1); changed(true); } });
            var more = h('details', { class: 'smlf-b-opt-more' }, [h('summary', { text: '…' }), row(T.option_value, text(o, 'value')), row(T.image, text(o, 'image', { type: 'url', placeholder: 'https://' }))]);
            box.appendChild(h('div', { class: 'smlf-b-opt' }, [icon, label, up, del, more]));
        });
        box.appendChild(h('button', { type: 'button', class: 'button button-small', text: '+ ' + T.add_option, onclick: function () {
            var n = f.options.length + 1;
            f.options.push({ label: T.option_label + ' ' + n, value: T.option_label + ' ' + n, icon: '', image: '' });
            changed(true);
        } }));
        body.appendChild(box);
    }

    // step panel ------------------------------------------------------
    function stepPanel(body) {
        var st = curStep();
        body.appendChild(row(T.step_title, text(st, 'title')));
        body.appendChild(row(T.step_desc, text(st, 'description', { multiline: true })));
        body.appendChild(h('label', { class: 'smlf-b-check' }, [
            h('input', { type: 'checkbox', checked: st.terminal === 'reset', onchange: function (e) { st.terminal = e.target.checked ? 'reset' : ''; changed(true); } }),
            h('span', { text: T.terminal })
        ]));

        if (st.terminal !== 'reset') {
            var box = h('div', { class: 'smlf-b-box' }, [h('div', { class: 'smlf-b-box-h', text: '⤳ ' + T.routes }), h('p', { class: 'smlf-b-help', text: T.routes_help })]);
            var cands = candidatesFor(null, S.step);
            var stepOpts = steps().map(function (x, i) { return [x.id, (i + 1) + '. ' + (x.title || x.id)]; }).filter(function (o) { return o[0] !== st.id; });
            st.routes.forEach(function (r, i) {
                box.appendChild(h('div', { class: 'smlf-b-route' }, [
                    h('span', { class: 'smlf-b-route-k', text: T.if }),
                    conditionRow(r.when, cands, function () { st.routes.splice(i, 1); changed(true); }),
                    h('div', { class: 'smlf-b-route-goto' }, [h('span', { class: 'smlf-b-route-k', text: T.goto }), select(r, 'goto', stepOpts.concat([['end', T.submit_here]]), false)])
                ]));
            });
            if (cands.length && stepOpts.length) {
                box.appendChild(h('button', { type: 'button', class: 'button button-small', text: '+ ' + T.add_route, onclick: function () {
                    st.routes.push({ when: { field: cands[cands.length - 1].f.id, op: 'eq', value: '' }, goto: stepOpts[0][0] });
                    changed(true);
                } }));
            }
            box.appendChild(row(T.otherwise, select(st, 'next', [['', T.following]].concat(stepOpts).concat([['end', T.submit_here]]), false)));
            body.appendChild(box);
        }
        if (steps().length > 1) {
            body.appendChild(h('button', { type: 'button', class: 'button smlf-b-danger', text: T.delete_step, onclick: function () {
                if (!window.confirm(T.confirm_step)) { return; }
                var gone = st.id;
                steps().splice(S.step, 1);
                steps().forEach(function (x) {
                    x.routes = x.routes.filter(function (r) { return r.goto !== gone; });
                    if (x.next === gone) { x.next = ''; }
                });
                S.step = Math.max(0, S.step - 1);
                S.field = null;
                changed(true);
            } }));
        }
    }

    // form settings panel -------------------------------------------
    function group(title, open, kids) {
        return h('details', { class: 'smlf-b-group', open: open }, [h('summary', { text: title })].concat(kids));
    }

    function formPanel(body) {
        var s = S.form.settings;
        body.appendChild(group(T.g_design, true, [
            row(T.theme, seg(s, 'theme', [['light', T.t_light], ['dark', T.t_dark], ['glass', T.t_glass], ['minimal', T.t_minimal]], function (from, to) {
                // keep the colours readable when switching between dark and light themes
                if ((from === 'dark') !== (to === 'dark')) {
                    s.background = to === 'dark' ? '#0b1220' : '#ffffff';
                    s.text = to === 'dark' ? '#e2e8f0' : '#0f172a';
                }
            })),
            h('div', { class: 'smlf-b-colors' }, [color(s, 'primary', T.primary), color(s, 'accent', T.accent), color(s, 'background', T.background), color(s, 'text', T.text)]),
            row(T.radius + ' (' + s.radius + 'px)', (function () {
                var r = h('input', { type: 'range', min: 0, max: 32, value: s.radius });
                r.addEventListener('input', function () { s.radius = parseInt(r.value, 10); r.parentNode.firstChild.textContent = T.radius + ' (' + s.radius + 'px)'; changed(false); });
                return r;
            })()),
            row(T.max_width, text(s, 'max_width', { type: 'number', min: 360, max: 1400 })),
            row(T.font, text(s, 'font', { placeholder: 'inherit' })),
            row(T.progress, seg(s, 'progress', [['steps', T.p_steps], ['bar', T.p_bar], ['none', T.p_none]])),
            check(s, 'animate', T.animate)
        ]));
        body.appendChild(group(T.g_behavior, false, [
            check(s, 'save_progress', T.save_progress),
            check(s, 'review', T.review),
            row(T.language, select(s, 'language', [['auto', T.auto], ['de', 'Deutsch'], ['en', 'English'], ['fa', 'فارسی']], false)),
            row(T.success_title, text(s, 'success_title')),
            row(T.success_message, text(s, 'success_message', { multiline: true })),
            row(T.redirect_url, text(s, 'redirect_url', { type: 'url', placeholder: 'https://' })),
            h('div', { class: 'smlf-b-cols' }, [row(T.next_label, text(s, 'next_label')), row(T.back_label, text(s, 'back_label')), row(T.submit_label, text(s, 'submit_label'))])
        ]));
        body.appendChild(group(T.g_emails, false, [
            check(s, 'notify_admin', T.notify_admin),
            row(T.admin_emails, text(s, 'admin_emails', { placeholder: 'info@…' })),
            row(T.admin_subject, text(s, 'admin_subject')),
            check(s, 'confirm_customer', T.confirm_customer),
            row(T.customer_subject, text(s, 'customer_subject')),
            row(T.customer_body, text(s, 'customer_body', { multiline: true, rows: 5 }), T.customer_help)
        ]));
        body.appendChild(group(T.g_security, false, [
            row(T.captcha, select(s, 'captcha', [['inherit', T.inherit], ['none', T.none], ['turnstile', 'Cloudflare Turnstile'], ['recaptcha_v3', 'reCAPTCHA v3'], ['recaptcha_v2', 'reCAPTCHA v2']], false))
        ]));
        var secret = text(s, 'webhook_secret');
        body.appendChild(group(T.g_webhook, false, [
            row(T.webhook_url, text(s, 'webhook_url', { type: 'url', placeholder: 'https://hooks.zapier.com/…' })),
            row(T.webhook_secret, h('div', { class: 'smlf-b-inline' }, [secret, h('button', { type: 'button', class: 'button', text: T.generate, onclick: function () {
                var a = new Uint8Array(24);
                (window.crypto || window.msCrypto).getRandomValues(a);
                s.webhook_secret = Array.prototype.map.call(a, function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
                secret.value = s.webhook_secret;
                changed(false);
            } })]), T.webhook_help),
            check(s, 'webhook_partial', T.webhook_partial)
        ]));
    }

    // ------------------------------------------------------------------ preview

    var previewTimer = null;
    function openPreview() {
        previewEl.classList.add('is-open');
        document.body.classList.add('smlf-b-noscroll');
        mountPreview();
    }
    function closePreview() {
        previewEl.classList.remove('is-open');
        document.body.classList.remove('smlf-b-noscroll');
        previewBody.textContent = '';
    }
    function setDevice(btn, cls) {
        [].forEach.call(btn.parentNode.children, function (b) { b.classList.toggle('is-on', b === btn); });
        previewFrame.className = 'smlf-b-preview-frame ' + cls;
    }
    function mountPreview() {
        if (!window.SMLF || !previewEl.classList.contains('is-open')) { return; }
        var holder = h('div', { id: 'smlf-preview-root' });
        previewBody.textContent = '';
        previewBody.appendChild(holder);
        var cfg = JSON.parse(JSON.stringify(C.preview));
        cfg.form = JSON.parse(JSON.stringify(S.form));
        cfg.preview = true;
        cfg.rtl = cfg.form.settings.language === 'fa';
        window.SMLF.mount(holder, cfg);
    }
    function refreshPreview() {
        clearTimeout(previewTimer);
        previewTimer = setTimeout(mountPreview, 400);
    }

    // ------------------------------------------------------------------ save

    function save() {
        if (S.saving) { return; }
        S.saving = true;
        renderTop();
        var fd = new FormData();
        fd.append('action', 'smlf_admin_save_form');
        fd.append('nonce', C.nonce);
        fd.append('id', S.id);
        fd.append('title', S.title);
        fd.append('status', S.status);
        fd.append('form', JSON.stringify(S.form));
        fetch(C.ajax, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (res) {
            S.saving = false;
            if (!res || !res.success) { S.saveState = 'error'; renderTop(); return; }
            var stepId = curStep() ? curStep().id : '';
            S.form = res.data.form;
            S.title = res.data.title;
            S.status = res.data.status;
            if (!S.id && res.data.id) { window.history.replaceState(null, '', C.builderUrl + res.data.id); }
            S.id = res.data.id;
            S.dirty = false;
            S.saveState = 'saved';
            S.step = Math.max(0, steps().map(function (x) { return x.id; }).indexOf(stepId));
            if (S.field && !findField(S.field)) { S.field = null; }
            renderTop();
            renderLeft();
            renderCanvas();
            renderInspector();
        }, function () {
            S.saving = false;
            S.saveState = 'error';
            renderTop();
        });
    }

    // Copy shortcode
    top.addEventListener('click', function (e) {
        var b = e.target.closest('[data-smlf-copy-text]');
        if (!b) { return; }
        var txt = b.getAttribute('data-smlf-copy-text');
        (navigator.clipboard ? navigator.clipboard.writeText(txt) : Promise.reject()).then(function () {
            b.classList.add('is-copied');
            setTimeout(function () { b.classList.remove('is-copied'); }, 1200);
        }, function () { window.prompt(T.shortcode, txt); });
    });

    renderTop();
    renderLeft();
    renderCanvas();
    renderInspector();
})();
