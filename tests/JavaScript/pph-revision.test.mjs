import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import test from 'node:test';

const blade = readFileSync(new URL('../../packages/Webkul/Admin/src/Resources/views/invoices/revisions/edit.blade.php', import.meta.url), 'utf8');
const source = blade.split('<script type="module">')[1].split('</script>')[0];

function mountPreview({ price = 3750000, quantity = 2, tax = 0, dp = 3750000, paid = 3750000, proforma = false, billingType = 'down_payment', grossUp = true } = {}) {
    const input = value => ({ value: String(value) });
    const outputs = () => ({ '[data-new-amount]': {}, '[data-new-pph]': {}, '[data-new-balance]': {} });
    const line = { '[data-price]': input(price), '[data-quantity]': input(quantity), '[data-discount]': input(0), '[data-tax]': input(tax), '[data-line-total]': {} };
    const documents = (proforma ? ['quote', 'down_payment'] : ['down_payment', 'settlement']).map(kind => ({
        dataset: { documentKind: kind, paid: proforma ? '' : String(paid), proformaBillingTarget: proforma && kind === 'down_payment' ? '1' : undefined },
        fields: { ...outputs(), ...(proforma && kind === 'down_payment' ? { '[data-new-billing-label]': {} } : {}) },
        querySelector(s) { return this.fields[s]; },
    }));
    const fields = {
        '[data-document-tax]': input(0), '[data-document-discount]': input(0), '[data-adjustment]': input(0),
        '[data-pph-gross-up]': input(grossUp ? 1 : 0), '[data-dp]': input(dp), '[data-grand-total]': {}, '[data-pph-summary]': {},
        '[data-settlement]': {}, '[data-revision-error]': {}, '[type=submit]': {}, '[data-add-item]': { addEventListener() {} },
        ...(proforma ? { '[data-proforma-billing-type]': input(billingType), '[data-dp-split]': { hidden: false } } : {}),
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

for (const grossUp of [false, true]) {
    test(`full proforma preview uses 100 percent of the revised agreement with gross-up ${grossUp}`, () => {
        const ui = mountPreview({ proforma: true, billingType: 'full_payment', price: 6000000, dp: 4000000, grossUp });
        const quote = ui.documents[0];
        const proforma = ui.documents[1];
        const total = grossUp ? 12060301.51 : 12000000;
        assert.equal(number(proforma.fields['[data-new-amount]'].textContent), total);
        assert.equal(number(quote.fields['[data-new-amount]'].textContent), total);
        assert.equal(number(proforma.fields['[data-new-pph]'].textContent), grossUp ? 60301.51 : 0);
        assert.equal(proforma.fields['[data-new-billing-label]'].textContent, 'Full — 100%');
        assert.equal(ui.fields['[data-dp]'].disabled, true);
        assert.equal(ui.fields['[data-dp]'].required, false);
        assert.equal(ui.fields['[data-dp-split]'].hidden, true);
        assert.equal(ui.fields['[type=submit]'].disabled, false);
    });
}

test('switching between DP and full restores the original DP amount and validation', () => {
    const ui = mountPreview({ proforma: true, price: 5000000, dp: 10000000, grossUp: false });
    assert.equal(ui.fields['[type=submit]'].disabled, true);
    ui.fields['[data-proforma-billing-type]'].value = 'full_payment';
    ui.recalculate();
    assert.equal(ui.fields['[type=submit]'].disabled, false);
    ui.fields['[data-proforma-billing-type]'].value = 'down_payment';
    ui.recalculate();
    assert.equal(ui.fields['[data-dp]'].value, '10000000');
    assert.equal(ui.fields['[data-dp]'].disabled, false);
    assert.equal(ui.fields['[data-dp]'].required, true);
    assert.equal(ui.fields['[data-dp-split]'].hidden, false);
    assert.equal(ui.fields['[type=submit]'].disabled, true);
    ui.fields['[data-dp]'].value = '4000000';
    ui.recalculate();
    assert.equal(number(ui.documents[1].fields['[data-new-amount]'].textContent), 4000000);
    assert.equal(ui.documents[1].fields['[data-new-billing-label]'].textContent, 'DP');
    assert.equal(ui.fields['[type=submit]'].disabled, false);
});
