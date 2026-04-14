/**
 * Accounting Dimensions Autocomplete
 * Reusable across all accounting module forms.
 *
 * Usage:
 *   AccountingDimAC.init(textEl, hiddenEl, items, opts)
 *
 *   items  — array of {id, label}
 *   opts   — { onSelect(item), onClear() }
 *
 * AJAX helper:
 *   AccountingDimAC.fetchItems(url, type, aqarId, callback)
 *     type: tenant | owner | property | unit | contract
 */
(function (global) {
    'use strict';

    // ── Inject CSS once ────────────────────────────────────────────────────────
    if (!document.getElementById('dim-ac-css')) {
        const style = document.createElement('style');
        style.id = 'dim-ac-css';
        style.textContent = `
.dim-ac-wrapper { position: relative; display: block; width: 100%; }
.dim-ac-dropdown {
    display: none; position: absolute; top: 100%; left: 0; right: 0;
    background: #fff; border: 1px solid #ced4da; border-top: none;
    border-radius: 0 0 6px 6px;
    box-shadow: 0 6px 16px rgba(0,0,0,.14);
    max-height: 220px; overflow-y: auto; z-index: 9999;
}
.dim-ac-dropdown.dim-ac-open { display: block; }
.dim-ac-item {
    padding: .4rem .75rem; cursor: pointer; font-size: .875rem;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    border-bottom: 1px solid #f0f0f0;
}
.dim-ac-item:last-child { border-bottom: none; }
.dim-ac-item:hover { background: #e8f0fe; }
.dim-ac-empty { padding: .4rem .75rem; color: #adb5bd; font-size: .875rem; }
`;
        document.head.appendChild(style);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────
    function esc(s) {
        return String(s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // ── Core init ──────────────────────────────────────────────────────────────
    /**
     * @param {HTMLInputElement} textEl   Visible text input (must exist in DOM)
     * @param {HTMLInputElement} hiddenEl Hidden ID input
     * @param {Array}            items    Initial [{id, label}] array
     * @param {Object}           opts     { onSelect(item), onClear() }
     * @returns controller { setItems, selectById, clear }
     */
    function init(textEl, hiddenEl, items, opts) {
        if (!textEl || !hiddenEl) return null;
        opts = opts || {};

        let pool = items ? items.slice() : [];

        // Ensure wrapper has position:relative
        const wrapper = textEl.parentElement;
        if (!wrapper.classList.contains('dim-ac-wrapper')) {
            wrapper.classList.add('dim-ac-wrapper');
        }

        // Create or reuse dropdown div
        let drop = wrapper.querySelector(':scope > .dim-ac-dropdown');
        if (!drop) {
            drop = document.createElement('div');
            drop.className = 'dim-ac-dropdown';
            wrapper.appendChild(drop);
        }

        // ── Rendering ──────────────────────────────────────────────────────────
        function showList(list) {
            if (!list.length) {
                drop.innerHTML = '<div class="dim-ac-empty">لا توجد نتائج</div>';
            } else {
                drop.innerHTML = list.slice(0, 60).map(function (item) {
                    return '<div class="dim-ac-item" data-id="' + esc(String(item.id)) +
                        '" data-label="' + esc(item.label) + '">' + esc(item.label) + '</div>';
                }).join('');
            }
            drop.classList.add('dim-ac-open');
        }

        function filterAndShow(q) {
            var lq = q ? q.toLowerCase() : '';
            showList(lq ? pool.filter(function (i) { return i.label.toLowerCase().indexOf(lq) !== -1; }) : pool);
        }

        function close() {
            drop.classList.remove('dim-ac-open');
        }

        function pick(id, label) {
            textEl.value = label;
            hiddenEl.value = id;
            close();
            if (opts.onSelect) opts.onSelect({ id: id, label: label });
        }

        function clear() {
            textEl.value = '';
            hiddenEl.value = '';
            close();
            if (opts.onClear) opts.onClear();
        }

        // ── Events ─────────────────────────────────────────────────────────────
        textEl.addEventListener('input', function () {
            if (!this.value) { hiddenEl.value = ''; close(); if (opts.onClear) opts.onClear(); return; }
            filterAndShow(this.value);
        });

        textEl.addEventListener('focus', function () {
            filterAndShow(this.value);
        });

        textEl.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') close();
        });

        // Prevent blur before click fires
        drop.addEventListener('mousedown', function (e) { e.preventDefault(); });

        drop.addEventListener('click', function (e) {
            var item = e.target.closest('.dim-ac-item');
            if (item && item.dataset.id !== undefined) {
                pick(item.dataset.id, item.dataset.label);
            }
        });

        // Close when clicking outside (capture phase to beat Bootstrap modals)
        document.addEventListener('click', function (e) {
            if (!wrapper.contains(e.target)) close();
        }, true);

        // ── Public controller ──────────────────────────────────────────────────
        var ctrl = {
            setItems: function (newItems) {
                pool = newItems ? newItems.slice() : [];
            },
            selectById: function (id) {
                if (!id) return;
                var found = pool.filter(function (i) { return String(i.id) === String(id); })[0];
                if (found) pick(found.id, found.label);
                else hiddenEl.value = id; // store ID even if label not in pool
            },
            clear: clear
        };

        textEl._dimAc = ctrl;
        hiddenEl._dimAc = ctrl;
        return ctrl;
    }

    // ── AJAX fetch ─────────────────────────────────────────────────────────────
    /**
     * Fetch dimension items from the shared get_dimensions endpoint.
     * @param {string}   url     Path to get_dimensions.hnt
     * @param {string}   type    tenant|owner|property|unit|contract
     * @param {number}   aqarId  Property filter for unit type (0 = none)
     * @param {Function} cb      callback(items)
     */
    function fetchItems(url, type, aqarId, cb) {
        var fd = new FormData();
        fd.append('type', type);
        if (aqarId) fd.append('aqar_id', aqarId);
        fetch(url, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) { if (data.success && cb) cb(data.items || []); })
            .catch(function () { if (cb) cb([]); });
    }

    // ── Export ─────────────────────────────────────────────────────────────────
    global.AccountingDimAC = { init: init, fetchItems: fetchItems };

})(window);
