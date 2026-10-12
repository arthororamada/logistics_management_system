import test from 'node:test';
import assert from 'node:assert/strict';
import { initializeTripWaybills, manifestWaybillRows, syncWaybillPreview } from '../../resources/js/trip-waybills.js';
import { tripFormPayload, tripHasUnsavedChanges } from '../../resources/js/trip-validation.js';

test('a DM produces individual rows for every waybill without adding duplicates', () => {
    const rows = manifestWaybillRows({ id: 17, waybill_numbers: ['00123', 'WB-2', 'WB-3'] }, [{ waybill_number: 'WB-2' }]);
    assert.deepEqual(rows.map(row => [row.dm_id, row.waybill_number]), [[17, '00123'], [17, 'WB-3']]);
    assert.equal(rows[0].quantity, '');
    assert.equal(rows[0].kilogram, '');
    assert.equal(rows[0].drops_number, '');
    assert.equal(rows[0].cbm, '');
});

test('waybill payload preserves separate strings and all six fields and tracks unsaved edits', () => {
    const original = globalThis.FormData;
    globalThis.FormData = class {
        *[Symbol.iterator]() {
            yield ['waybills[0][dm_id]', '17'];
            yield ['waybills[0][waybill_number]', '00123'];
            yield ['waybills[0][drops_number]', '1'];
            yield ['waybills[0][quantity]', '2'];
            yield ['waybills[0][kilogram]', '0'];
            yield ['waybills[0][cbm]', '0.25'];
            yield ['waybills[1][dm_id]', '17'];
            yield ['waybills[1][waybill_number]', 'WB-2'];
        }
    };
    try {
        const payload = tripFormPayload({ querySelectorAll: () => [] });
        assert.equal(payload.waybills.length, 2);
        assert.equal(payload.waybills[0].waybill_number, '00123');
        assert.equal(payload.waybills[0].cbm, '0.25');
        const saved = structuredClone(payload);
        assert.equal(tripHasUnsavedChanges(payload, saved), false);
        payload.waybills[0].quantity = '3';
        assert.equal(tripHasUnsavedChanges(payload, saved), true);
    } finally { globalThis.FormData = original; }
});

test('document preview reflects additions, edits and removals with safe text cells', () => {
    const original = globalThis.document;
    const node = () => ({ children: [], append(...children) { this.children.push(...children); }, replaceChildren() { this.children = []; } });
    globalThis.document = { createElement: node };
    let data = [{ dm_id: '17', waybill_number: '<WB-1>', drops_number: '1', quantity: '2', kilogram: '3', cbm: '0.5' }];
    const editor = { querySelectorAll: () => data.map(row => ({ querySelector: selector => ({ value: row[selector.match(/\[name\$="\[([^\]]+)\]/)[1]] }) })) };
    const preview = node();
    try {
        syncWaybillPreview(editor, preview);
        assert.deepEqual(preview.children[0].children.map(cell => cell.textContent), ['17', '<WB-1>', '1', '2', '3', '0.5']);
        data[0].quantity = '9';
        data.push({ ...data[0], waybill_number: 'WB-2' });
        syncWaybillPreview(editor, preview);
        assert.equal(preview.children.length, 2);
        assert.equal(preview.children[0].children[3].textContent, '9');
        data = [];
        syncWaybillPreview(editor, preview);
        assert.equal(preview.children[0].children[0].textContent, 'No waybills added.');
    } finally { globalThis.document = original; }
});

test('pending DM selection reloads for each trip type and ignores stale responses', async () => {
    const originals = Object.fromEntries(['document', 'window', 'Option', 'fetch'].map(key => [key, globalThis[key]]));
    const control = () => Object.assign(new EventTarget(), {
        value: '', children: [],
        replaceChildren(...children) { this.children = children; this.value = children[0]?.value ?? ''; },
        append(child) { this.children.push(child); },
        querySelectorAll() { return []; },
    });
    const type = control(); type.value = 'pickup';
    const select = control();
    const container = control();
    const message = control();
    const button = control();
    const rate = control();
    const editor = control();
    editor.dataset = { manifestsUrl: '/trips/1/manifests' };
    editor.querySelector = selector => ({ '[name="trip_type"]': type, '[data-trip-dm]': select,
        '[data-trip-collection="waybills"]': container, '[data-trip-dm-message]': message,
        '[data-trip-add-dm]': button, '[data-trip-rate]': rate }[selector]);
    globalThis.document = { querySelector: () => null };
    globalThis.window = { location: { origin: 'http://localhost' } };
    globalThis.Option = class { constructor(text, value) { this.textContent = text; this.value = value; } };
    const requests = [];
    globalThis.fetch = url => new Promise(resolve => requests.push({ type: url.searchParams.get('trip_type'), resolve }));
    const complete = async (request, id) => {
        request.resolve({ ok: true, json: async () => [{ id, waybill_numbers: ['WB-'+id] }] });
        await new Promise(resolve => setImmediate(resolve));
    };
    try {
        initializeTripWaybills(editor, () => {});
        assert.equal(requests[0].type, 'pickup');
        await complete(requests[0], 10);
        assert.equal(select.children[1].value, '10');
        for (const [index, value] of ['delivery', 'transfer', 'return'].entries()) {
            type.value = value; type.dispatchEvent(new Event('change'));
            assert.equal(select.disabled, true);
            assert.equal(select.children.length, 1);
            assert.equal(requests.at(-1).type, value);
            await complete(requests.at(-1), index + 20);
            assert.equal(select.disabled, false);
            assert.equal(select.children[1].value, String(index + 20));
        }
        type.value = 'delivery'; type.dispatchEvent(new Event('change'));
        const stale = requests.at(-1);
        type.value = 'pickup'; type.dispatchEvent(new Event('change'));
        await complete(requests.at(-1), 30);
        await complete(stale, 99);
        assert.equal(select.children[1].value, '30');
    } finally { Object.assign(globalThis, originals); }
});
