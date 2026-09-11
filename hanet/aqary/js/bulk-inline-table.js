(function () {
    'use strict';

    if (window.AqaryBulkInlineTable && typeof window.AqaryBulkInlineTable.scan === 'function') {
        window.AqaryBulkInlineTable.scan(document);
        return;
    }

    var DEFAULT_ENDPOINT = '/aqary/admin/include/bulk_inline.api.hnt';
    var csrfMeta = document.querySelector('meta[name="aqary-bulk-inline-csrf"]');
    var csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';
    var activeEditor = null;

    function notify(message, type) {
        if (typeof window.showBootstrapNotification === 'function') {
            window.showBootstrapNotification(message, type || 'success', 5000);
            return;
        }
        window.alert(message);
    }

    function confirmAction(message, callback) {
        if (typeof window.confirm === 'function') {
            if (window.confirm.length >= 3) {
                window.confirm(message, 'تأكيد الحذف', callback);
            } else if (window.confirm(message)) {
                callback();
            }
            return;
        }
        callback();
    }

    function parseOptions(cell) {
        try {
            var options = JSON.parse(cell.dataset.options || '[]');
            return Array.isArray(options) ? options : [];
        } catch (error) {
            return [];
        }
    }

    function rowId(row) {
        return parseInt(row && row.dataset.recordId ? row.dataset.recordId : '0', 10) || 0;
    }

    function isVisible(checkbox) {
        var row = checkbox.closest('tr');
        return !!row && row.getClientRects().length > 0;
    }

    function selectedIds(table) {
        return Array.prototype.map.call(
            Array.prototype.filter.call(
                table.querySelectorAll('.aqary-row-select:checked:not(:disabled)'),
                isVisible
            ),
            function (checkbox) { return parseInt(checkbox.value, 10) || 0; }
        ).filter(Boolean);
    }

    function updateSelection(table) {
        var enabled = Array.prototype.filter.call(table.querySelectorAll('.aqary-row-select:not(:disabled)'), isVisible);
        var checked = enabled.filter(function (checkbox) { return checkbox.checked; });
        var selectAll = table.querySelector('.aqary-select-all');
        var status = table.parentElement.querySelector('[data-aqary-selection-status]');

        enabled.forEach(function (checkbox) {
            var row = checkbox.closest('tr[data-record-id]');
            if (row) {
                row.classList.toggle('aqary-row-selected', checkbox.checked);
            }
        });
        if (selectAll) {
            selectAll.checked = enabled.length > 0 && checked.length === enabled.length;
            selectAll.indeterminate = checked.length > 0 && checked.length < enabled.length;
        }
        if (status) {
            status.textContent = checked.length ? 'تم تحديد ' + checked.length + ' سجل' : 'لم يتم تحديد سجلات';
        }
    }

    function formDataFor(table, action, ids, extra) {
        var data = new FormData();
        data.append('action', action);
        data.append('resource', table.dataset.resource || '');
        data.append('csrf_token', csrfToken);
        if (table.dataset.depId) {
            data.append('dep_id', table.dataset.depId);
        }
        ids.forEach(function (id) { data.append('record_ids[]', String(id)); });
        Object.keys(extra || {}).forEach(function (key) { data.append(key, extra[key]); });
        return data;
    }

    function request(table, action, ids, extra) {
        return fetch(table.dataset.endpoint || DEFAULT_ENDPOINT, {
            method: 'POST',
            credentials: 'same-origin',
            body: formDataFor(table, action, ids, extra),
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (response) {
            return response.json().catch(function () {
                throw new Error('استجابة الخادم غير صالحة');
            }).then(function (payload) {
                if (!response.ok || !payload.success) {
                    throw new Error(payload.message || 'تعذر تنفيذ العملية');
                }
                return payload;
            });
        });
    }

    function resultMessage(payload) {
        var message = payload.message || 'تم تنفيذ العملية';
        if (payload.skipped && payload.skipped.length) {
            var reasons = [];
            payload.skipped.forEach(function (item) {
                if (item.message && reasons.indexOf(item.message) === -1) {
                    reasons.push(item.message);
                }
            });
            if (reasons.length) {
                message += ' — ' + reasons.join('، ');
            }
        }
        return message;
    }

    function clearConfirmedSelection(table, ids) {
        var confirmed = new Set((ids || []).map(String));
        table.querySelectorAll('.aqary-row-select').forEach(function (checkbox) {
            if (confirmed.has(String(checkbox.value))) {
                checkbox.checked = false;
            }
        });
        updateSelection(table);
    }

    function removeConfirmedRows(table, ids) {
        var confirmed = new Set((ids || []).map(String));
        table.querySelectorAll('tr[data-record-id], tr[data-parent-record-id]').forEach(function (row) {
            var id = row.dataset.recordId || row.dataset.parentRecordId || '';
            if (confirmed.has(String(id))) {
                row.remove();
            }
        });
        updateSelection(table);
    }

    function cancelEditor() {
        if (!activeEditor) {
            return;
        }
        var editor = activeEditor;
        activeEditor = null;
        if (editor.cell.isConnected) {
            editor.cell.innerHTML = editor.originalHtml;
            editor.cell.dataset.value = editor.originalValue;
            editor.cell.classList.remove('aqary-cell-editing');
        }
    }

    function renderValue(cell, value, display) {
        cell.textContent = display === '' ? '—' : display;
        cell.dataset.value = value;
        cell.classList.remove('aqary-cell-editing');
    }

    function saveEditor(editor, value) {
        if (!activeEditor || activeEditor !== editor || editor.saving) {
            return;
        }
        var selected = selectedIds(editor.table);
        var ids = [editor.recordId];
        if (editor.cell.dataset.bulkSafe === '1' && selected.indexOf(editor.recordId) !== -1 && selected.length) {
            ids = selected;
        }
        editor.saving = true;
        editor.input.disabled = true;

        request(editor.table, 'inline_update', ids, {
            field: editor.cell.dataset.inlineField,
            value: value
        }).then(function (payload) {
            var confirmed = (payload.updated_ids || []).map(Number);
            editor.table.querySelectorAll('[data-inline-field]').forEach(function (cell) {
                if (cell.dataset.inlineField !== editor.cell.dataset.inlineField) {
                    return;
                }
                var id = rowId(cell.closest('tr[data-record-id]'));
                if (confirmed.indexOf(id) !== -1) {
                    renderValue(cell, String(payload.value == null ? '' : payload.value), String(payload.display == null ? payload.value : payload.display));
                }
            });
            if (confirmed.indexOf(editor.recordId) === -1 && editor.cell.isConnected) {
                editor.cell.innerHTML = editor.originalHtml;
                editor.cell.dataset.value = editor.originalValue;
                editor.cell.classList.remove('aqary-cell-editing');
            }
            activeEditor = null;
            clearConfirmedSelection(editor.table, confirmed);
            notify(resultMessage(payload), payload.skipped && payload.skipped.length ? 'warning' : 'success');
        }).catch(function (error) {
            editor.saving = false;
            editor.input.disabled = false;
            editor.input.focus();
            notify(error.message || 'تعذر حفظ التعديل', 'error');
        });
    }

    function openEditor(cell, table) {
        var row = cell.closest('tr[data-record-id]');
        var id = rowId(row);
        if (!id || cell.classList.contains('aqary-cell-editing')) {
            return;
        }
        cancelEditor();

        var type = cell.dataset.editor || 'text';
        var value = cell.hasAttribute('data-value') ? cell.dataset.value : cell.textContent.trim();
        if (value === '--' || value === '—') {
            value = '';
        }
        var input;
        if (type === 'select') {
            input = document.createElement('select');
            input.className = 'form-select form-select-sm';
            parseOptions(cell).forEach(function (option) {
                var item = document.createElement('option');
                if (option && typeof option === 'object') {
                    item.value = String(option.value == null ? '' : option.value);
                    item.textContent = String(option.label == null ? item.value : option.label);
                } else {
                    item.value = String(option);
                    item.textContent = String(option);
                }
                input.appendChild(item);
            });
            if (!Array.prototype.some.call(input.options, function (option) { return option.value === value; })) {
                var currentItem = document.createElement('option');
                currentItem.value = value;
                currentItem.textContent = value || '—';
                input.insertBefore(currentItem, input.firstChild);
            }
            input.value = value;
        } else {
            input = document.createElement('input');
            input.type = type === 'number' ? 'number' : 'text';
            input.className = 'form-control form-control-sm';
            input.value = value;
            if (type === 'number') {
                input.step = 'any';
                input.min = '0';
            }
        }

        activeEditor = {
            cell: cell,
            table: table,
            recordId: id,
            originalHtml: cell.innerHTML,
            originalValue: value,
            input: input,
            saving: false
        };
        cell.classList.add('aqary-cell-editing');
        cell.textContent = '';
        cell.appendChild(input);
        input.focus();
        if (typeof input.select === 'function' && type !== 'select') {
            input.select();
        }

        input.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                cancelEditor();
            } else if (event.key === 'Enter' && type !== 'select') {
                event.preventDefault();
                saveEditor(activeEditor, input.value);
            }
        });
        if (type === 'select') {
            input.addEventListener('change', function () {
                saveEditor(activeEditor, input.value);
            });
        }
        input.addEventListener('blur', function () {
            window.setTimeout(function () {
                if (activeEditor && activeEditor.input === input && !activeEditor.saving) {
                    cancelEditor();
                }
            }, 0);
        });
    }

    function initializeTable(table) {
        if (table.dataset.aqaryBulkReady === '1') {
            return;
        }
        table.dataset.aqaryBulkReady = '1';

        var screenId = table.dataset.screen || '';
        var canEdit = true;
        var canDelete = true;
        if (/^\d+$/.test(screenId)) {
            var editProbe = document.createElement('span');
            var deleteProbe = document.createElement('span');
            editProbe.className = 'policy_' + screenId + '_edits';
            deleteProbe.className = 'policy_' + screenId + '_deleetes';
            editProbe.style.cssText = 'position:absolute;visibility:hidden;pointer-events:none';
            deleteProbe.style.cssText = 'position:absolute;visibility:hidden;pointer-events:none';
            document.body.appendChild(editProbe);
            document.body.appendChild(deleteProbe);
            canEdit = window.getComputedStyle(editProbe).display !== 'none';
            canDelete = window.getComputedStyle(deleteProbe).display !== 'none';
            editProbe.remove();
            deleteProbe.remove();
        }
        if (!canEdit) {
            table.querySelectorAll('[data-inline-field]').forEach(function (cell) {
                cell.removeAttribute('data-inline-field');
                cell.removeAttribute('tabindex');
            });
        }
        if (!canDelete) {
            table.querySelectorAll('.aqary-delete').forEach(function (button) { button.hidden = true; });
        }
        if (!canEdit && !canDelete) {
            table.querySelectorAll('.aqary-row-select, .aqary-select-all').forEach(function (checkbox) { checkbox.disabled = true; });
        }

        var status = document.createElement('div');
        status.className = 'aqary-selection-status small text-muted mb-2';
        status.setAttribute('data-aqary-selection-status', '');
        status.setAttribute('aria-live', 'polite');
        status.textContent = 'لم يتم تحديد سجلات';
        table.parentNode.insertBefore(status, table);

        table.addEventListener('change', function (event) {
            if (event.target.classList.contains('aqary-select-all')) {
                table.querySelectorAll('.aqary-row-select:not(:disabled)').forEach(function (checkbox) {
                    if (isVisible(checkbox)) {
                        checkbox.checked = event.target.checked;
                    }
                });
                updateSelection(table);
            } else if (event.target.classList.contains('aqary-row-select')) {
                updateSelection(table);
            }
        });

        table.addEventListener('click', function (event) {
            var deleteButton = event.target.closest('.aqary-delete');
            if (deleteButton && table.contains(deleteButton)) {
                event.preventDefault();
                event.stopPropagation();
                cancelEditor();
                var row = deleteButton.closest('tr[data-record-id]');
                var clickedId = rowId(row);
                var selected = selectedIds(table);
                var checkbox = row ? row.querySelector('.aqary-row-select') : null;
                var ids = checkbox && checkbox.checked && selected.length ? selected : [clickedId];
                ids = ids.filter(Boolean);
                if (!ids.length) {
                    return;
                }
                confirmAction('هل أنت متأكد من حذف ' + ids.length + ' سجل؟', function () {
                    deleteButton.disabled = true;
                    request(table, 'delete', ids, {}).then(function (payload) {
                        removeConfirmedRows(table, payload.deleted_ids || []);
                        notify(resultMessage(payload), payload.skipped && payload.skipped.length ? 'warning' : 'success');
                    }).catch(function (error) {
                        notify(error.message || 'تعذر حذف السجل', 'error');
                    }).finally(function () {
                        if (deleteButton.isConnected) {
                            deleteButton.disabled = false;
                        }
                    });
                });
                return;
            }

            var cell = event.target.closest('[data-inline-field]');
            if (cell && table.contains(cell) && !event.target.closest('a,button,input,select,textarea')) {
                event.stopPropagation();
                openEditor(cell, table);
            }
        });

        table.addEventListener('keydown', function (event) {
            var cell = event.target.closest('[data-inline-field]');
            if (cell && (event.key === 'Enter' || event.key === ' ')) {
                event.preventDefault();
                openEditor(cell, table);
            }
        });
        table.addEventListener('dblclick', function (event) {
            if (event.target.closest('[data-inline-field], .aqary-row-select, .aqary-delete')) {
                event.stopPropagation();
            }
        });
        updateSelection(table);
    }

    function scan(root) {
        if (root.nodeType === 1 && root.matches && root.matches('table[data-aqary-bulk]')) {
            initializeTable(root);
        }
        if (root.querySelectorAll) {
            root.querySelectorAll('table[data-aqary-bulk]').forEach(initializeTable);
        }
    }

    document.addEventListener('pointerdown', function (event) {
        if (activeEditor && !activeEditor.cell.contains(event.target) && !activeEditor.saving) {
            cancelEditor();
        }
    }, true);

    var style = document.createElement('style');
    style.textContent =
        '[data-aqary-bulk] [data-inline-field]{cursor:pointer;position:relative}' +
        '[data-aqary-bulk] [data-inline-field]:hover{box-shadow:inset 0 0 0 1px #198754;background:#f2fff7}' +
        '[data-aqary-bulk] .aqary-cell-editing{min-width:135px;padding:4px}' +
        '[data-aqary-bulk] tr.aqary-row-selected>td{background:#eaf7ef!important}' +
        '[data-aqary-bulk] tr.aqary-row-selected{box-shadow:inset -4px 0 #198754}' +
        '.aqary-selection-status{border-right:3px solid #198754;padding:.35rem .65rem;background:#f8f9fa}';
    document.head.appendChild(style);

    window.AqaryBulkInlineTable = { scan: scan };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { scan(document); });
    } else {
        scan(document);
    }
    new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
            mutation.addedNodes.forEach(scan);
        });
    }).observe(document.documentElement, { childList: true, subtree: true });
})();
