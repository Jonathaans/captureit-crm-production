/* CRM_DELIVERY_ORDER_EQUIPMENT_EXTERNAL_JS_V1_2 */
(function () {
    'use strict';

    if (window.crmDeliveryOrderEquipmentV12) {
        window.crmDeliveryOrderEquipmentV12.refreshAll();
        return;
    }

    var rootSelector = '[data-crm-equipment-editor]';

    function getRows(root) {
        var container = root.querySelector('[data-equipment-rows]');

        return container
            ? Array.prototype.slice.call(container.querySelectorAll('[data-equipment-row]'))
            : [];
    }

    function searchableValue(field) {
        if (field.tagName === 'SELECT') {
            var option = field.options[field.selectedIndex];

            return (field.value || '') + ' ' + (option ? option.textContent : '');
        }

        return field.value || '';
    }

    function applySearch(root) {
        var search = root.querySelector('[data-equipment-search]');
        var empty = root.querySelector('[data-equipment-empty-search]');
        var query = search ? String(search.value || '').trim().toLowerCase() : '';
        var visible = 0;

        getRows(root).forEach(function (row) {
            var fields = row.querySelectorAll('input, select');
            var values = Array.prototype.map.call(fields, searchableValue).join(' ').toLowerCase();
            var matches = query === '' || values.indexOf(query) !== -1;

            row.classList.toggle('hidden', ! matches);

            if (matches) {
                visible += 1;
            }
        });

        if (empty) {
            empty.classList.toggle('hidden', visible !== 0);
        }
    }

    function refresh(root) {
        var rows = getRows(root);
        var count = root.querySelector('[data-equipment-count]');

        rows.forEach(function (row, index) {
            var number = row.querySelector('[data-row-number]');

            if (number) {
                number.textContent = String(index + 1);
            }
        });

        if (count) {
            count.textContent = rows.length + ' item';
        }

        applySearch(root);
    }

    function refreshAll() {
        document.querySelectorAll(rootSelector).forEach(refresh);
    }

    function nextIndex(root) {
        var current = Number.parseInt(root.getAttribute('data-next-index') || '0', 10);

        if (! Number.isFinite(current) || current < 0) {
            current = getRows(root).length;
        }

        root.setAttribute('data-next-index', String(current + 1));

        return current;
    }

    function resetClonedRow(row, index) {
        row.classList.remove('hidden');
        row.removeAttribute('style');

        row.querySelectorAll('[name]').forEach(function (field) {
            field.name = field.name.replace(/items\[[^\]]+\]/, 'items[' + index + ']');
            field.removeAttribute('aria-invalid');
            field.removeAttribute('aria-describedby');

            if (field.tagName === 'SELECT') {
                field.selectedIndex = 0;
                return;
            }

            if (field.type === 'number' && /\[quantity\]$/.test(field.name)) {
                field.value = '1';
                return;
            }

            if (/\[unit\]$/.test(field.name)) {
                field.value = 'unit';
                return;
            }

            field.value = '';
        });

        row.querySelectorAll('p.text-red-600, [data-field-error]').forEach(function (error) {
            error.remove();
        });
    }

    function addRow(root) {
        var container = root.querySelector('[data-equipment-rows]');
        var search = root.querySelector('[data-equipment-search]');
        var existingRows = getRows(root);

        if (! container || existingRows.length === 0) {
            window.alert('Sumber baris item tidak tersedia. Silakan refresh halaman.');
            return;
        }

        var index = String(nextIndex(root));
        var newRow = existingRows[existingRows.length - 1].cloneNode(true);

        resetClonedRow(newRow, index);
        container.appendChild(newRow);

        if (search) {
            search.value = '';
        }

        refresh(root);

        newRow.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

        var name = newRow.querySelector('[data-equipment-name]');
        if (name) {
            name.focus();
        }
    }

    function rowHasMaterialValue(row) {
        var selectors = [
            '[data-equipment-name]',
            'input[name$="[description]"]',
            '[data-inventory-select]',
            'input[name$="[notes]"]'
        ];

        return selectors.some(function (selector) {
            var field = row.querySelector(selector);

            return field && String(field.value || '').trim() !== '';
        });
    }

    function removeRow(root, row) {
        if (rowHasMaterialValue(row) && ! window.confirm('Hapus item ini dari Surat Jalan?')) {
            return;
        }

        if (getRows(root).length === 1) {
            var currentIndex = String(nextIndex(root));
            resetClonedRow(row, currentIndex);
            refresh(root);
            return;
        }

        row.remove();
        refresh(root);
    }

    function targetElement(event) {
        if (! event.target) {
            return null;
        }

        return event.target.nodeType === 1
            ? event.target
            : event.target.parentElement;
    }

    document.addEventListener('click', function (event) {
        var target = targetElement(event);

        if (! target || ! target.closest) {
            return;
        }

        var add = target.closest('[data-add-equipment]');
        var remove = target.closest('[data-remove-equipment]');
        var action = add || remove;

        if (! action) {
            return;
        }

        var root = action.closest(rootSelector);

        if (! root) {
            return;
        }

        event.preventDefault();

        if (add) {
            addRow(root);
            return;
        }

        var row = remove.closest('[data-equipment-row]');

        if (row) {
            removeRow(root, row);
        }
    });

    document.addEventListener('input', function (event) {
        var target = targetElement(event);

        if (! target || ! target.closest) {
            return;
        }

        var root = target.closest(rootSelector);

        if (root) {
            applySearch(root);
        }
    });

    document.addEventListener('change', function (event) {
        var target = targetElement(event);

        if (! target || ! target.matches || ! target.matches('[data-inventory-select]')) {
            return;
        }

        var root = target.closest(rootSelector);
        var row = target.closest('[data-equipment-row]');

        if (! root || ! row) {
            return;
        }

        var option = target.options[target.selectedIndex];
        var name = row.querySelector('[data-equipment-name]');
        var unit = row.querySelector('[data-equipment-unit]');

        if (option && option.value && name && String(name.value || '').trim() === '') {
            name.value = option.getAttribute('data-name') || String(option.textContent || '').trim();
        }

        if (option && option.value && unit) {
            var currentUnit = String(unit.value || '').trim().toLowerCase();

            if (currentUnit === '' || currentUnit === 'unit') {
                unit.value = option.getAttribute('data-unit') || 'unit';
            }
        }

        applySearch(root);
    });

    window.crmDeliveryOrderEquipmentV12 = {
        refreshAll: refreshAll
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', refreshAll, { once: true });
    } else {
        refreshAll();
    }

    window.addEventListener('pageshow', refreshAll);
    document.addEventListener('turbo:load', refreshAll);
    document.addEventListener('pjax:end', refreshAll);
})();
