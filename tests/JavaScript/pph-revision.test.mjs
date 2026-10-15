import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import test from 'node:test';

const blade = readFileSync(new URL('../../packages/Webkul/Admin/src/Resources/views/invoices/revisions/edit.blade.php', import.meta.url), 'utf8');
const source = blade.split('<script type="module">')[1].split('</script>')[0];

function mountPreview({ price = 3750000, quantity = 2, tax = 0, dp = 3750000, paid = 3750000 } = {}) {
    const input = value => ({ value: String(value) });
    const outputs = () => ({ '[data-new-amount]': {}, '[data-new-pph]': {}, '[data-new-balance]': {} });
    const line = { '[data-price]': input(price), '[data-quantity]': input(quantity), '[data-discount]': input(0), '[data-tax]': input(tax), '[data-line-total]': {} };
    const documents = ['down_payment', 'settlement'].map(kind => ({ dataset: { documentKind: kind, paid: String(paid) }, fields: outputs(), querySelector(s) { return this.fields[s]; } }));
    const fields = {
        '[data-document-tax]': input(0), '[data-document-discount]': input(0), '[data-adjustment]': input(0),
        '[data-pph-gross-up]': input(1), '[data-dp]': input(dp), '[data-grand-total]': {}, '[data-pph-summary]': {},
        '[data-settlement]': {}, '[data-revision-error]': {}, '[type=submit]': {}, '[data-add-item]': { addEventListener() {} },
    };
    const listeners = {};
    const body = { querySelectorAll(s) { return s === '[name]' ? [] : [{ querySelector(s) { return line[s]; } }]; }, addEventListener() {} };
    const form = { querySelector(s) { return s === '#revision-items' ? body : fields[s]; }, querySelectorAll() { return documents; }, addEventListener(type, callback) { listeners[type] = callback; } };
    let directive;
    vm.runInNewContext(source, { app: { directive(name, value) { directive = value; } }, Intl });
    directive.mounted({ querySelector() { return form; } });
    return { fields, documents, line, recalculate: listeners.input };
}

const number = text => Number(text.replace(/[^\d,-]/g, '').replace(',', '.'));

test('paid DP and settlement preview has gross and PPh but zero extra cash due', () => {
    const ui = mountPreview();
    assert.equal(number(ui.fields['[data-grand-total]'].textContent), 7537688.44);
    for (const row of ui.documents) {
        assert.equal(number(row.fields['[data-new-amount]'].textContent), 3768844.22);
        assert.equal(number(row.fields['[data-new-pph]'].textContent), 18844.22);
        assert.equal(number(row.fields['[data-new-balance]'].textContent), 0);
    }
    assert.equal(ui.fields['[type=submit]'].disabled, false);
    ui.fields['[data-pph-gross-up]'].value = '0';
    ui.recalculate();
    assert.equal(number(ui.fields['[data-grand-total]'].textContent), 7500000);
    assert.equal(number(ui.documents[0].fields['[data-new-pph]'].textContent), 0);
});

test('preview keeps other tax outside the service gross-up base', () => {
    const ui = mountPreview({ tax: 825000, dp: 4162500, paid: 0 });
    assert.equal(number(ui.fields['[data-grand-total]'].textContent), 8362688.44);
    assert.equal(number(ui.documents[0].fields['[data-new-pph]'].textContent), 18844.22);
});

test('preview rejects a net settlement below receipts even when its gross covers them', () => {
    const ui = mountPreview({ price: 3745000 });
    assert.equal(ui.fields['[type=submit]'].disabled, true);
    assert.ok(number(ui.documents[1].fields['[data-new-balance]'].textContent) < 0);
});
