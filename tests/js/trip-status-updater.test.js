import test from 'node:test';
import assert from 'node:assert/strict';
import { readStatusResponse } from '../../resources/js/trip-status-updater-api.js';

const trip = { trip_id: 'TRIP-1', current_status: 'Open', created_at: 'Oct 11, 2026 10:00', version: 0 };
const response = (data, status = 200) => ({ ok: status < 400, status, json: async () => data });

test('search and update accept confirmed saved trip data', async () => {
    assert.deepEqual(await readStatusResponse(response({ trip })), { trip });
    const saved = { message: 'Updated', trip: { ...trip, current_status: 'Cancelled', version: 1 } };
    assert.deepEqual(await readStatusResponse(response(saved), true), saved);
});

test('validation and not-found responses expose backend messages', async () => {
    await assert.rejects(readStatusResponse(response({ errors: { reason: ['Reason required'] } }, 422)), /Reason required/);
    await assert.rejects(readStatusResponse(response({ message: 'Trip does not exist.' }, 404)), /Trip does not exist/);
});

test('expired sessions and malformed successful responses cannot report success', async () => {
    await assert.rejects(readStatusResponse(response(null, 419)), /session expired/);
    for (const data of [null, {}, { trip: { ...trip, version: '0' } }]) {
        await assert.rejects(readStatusResponse(response(data)), /did not confirm/);
    }
    await assert.rejects(readStatusResponse(response({ trip }), true), /did not confirm/);
});
