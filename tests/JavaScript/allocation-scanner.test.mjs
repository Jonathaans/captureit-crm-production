import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createAllocationQueue, initAllocationScanner } from '../../packages/Webkul/Admin/src/Resources/assets/js/delivery-order-allocation.js';

const result = (code, duplicate = false) => ({ message: code, duplicate });
async function idle(queue) {
    const deadline = Date.now() + 1000;
    while (queue.hasPending()) {
        assert.ok(Date.now() < deadline, 'Queue must not remain stuck');
        await new Promise((resolve) => setTimeout(resolve, 5));
    }
}

test('four rapid scans wait in FIFO order, with one active request and no duplicate pending codes', async () => {
    const calls = [], received = [];
    let finishFirst;
    const queue = createAllocationQueue({
        send(code) {
            calls.push(code);
            return code === 'A' ? new Promise((resolve) => { finishFirst = resolve; }) : Promise.resolve(result(code));
        },
        onResult: (data) => received.push(data.message),
    });
    for (const code of ['A', 'B', 'C', 'D']) assert.equal(queue.enqueue(code), true);
    assert.equal(queue.enqueue(' A '), false);
    assert.deepEqual(calls, ['A']);
    assert.equal(queue.snapshot().pending, 3);
    finishFirst(result('A'));
    await idle(queue);
    assert.deepEqual(calls, ['A', 'B', 'C', 'D']);
    assert.deepEqual(received, calls);
    assert.ok(queue.snapshot().entries.every((e) => e.status === 'success'));
});

test('a lost response times out, preserves a retryable result, and allows later scans on the same order', async () => {
    let attempts = 0, firstSignal;
    const queue = createAllocationQueue({ timeoutMs: 15, send(code, signal) {
        if (code === 'A' && attempts++ === 0) {
            firstSignal = signal;
            return new Promise(() => {});
        }
        return Promise.resolve(result(code, code === 'A'));
    } });
    queue.enqueue('A'); queue.enqueue('B'); queue.enqueue('C');
    await idle(queue);
    assert.equal(firstSignal.aborted, true);
    assert.deepEqual(queue.snapshot().entries.map((e) => e.status), ['failed', 'success', 'success']);
    assert.equal(queue.retry(1), true);
    assert.equal(queue.retry(1), false);
    await idle(queue);
    assert.equal(queue.snapshot().entries[0].status, 'duplicate');
});

test('validation errors remain visible and do not block the next valid code', async () => {
    const queue = createAllocationQueue({ send: async (code) => {
        if (code === 'BAD') throw new Error('Asset dipakai di SJ lain');
        return result(code);
    } });
    queue.enqueue('BAD'); queue.enqueue('GOOD');
    await idle(queue);
    assert.equal(queue.snapshot().entries[0].message, 'Asset dipakai di SJ lain');
    assert.equal(queue.snapshot().entries[1].status, 'success');
});

test('teardown aborts the active scan and prevents queued or late responses from updating another page', async () => {
    let finish, signal;
    const calls = [], received = [];
    const queue = createAllocationQueue({ send(code, requestSignal) {
        calls.push(code); signal = requestSignal;
        return new Promise((resolve) => { finish = resolve; });
    }, onResult: (data) => received.push(data) });
    queue.enqueue('A'); queue.enqueue('B'); queue.destroy();
    finish(result('A'));
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(signal.aborted, true);
    assert.deepEqual(calls, ['A']);
    assert.deepEqual(received, []);
    assert.equal(queue.enqueue('C'), false);
});

test('read-only orders and unrelated pages never initialize a scanner', () => {
    assert.equal(initAllocationScanner(null), null);
    assert.equal(initAllocationScanner({ dataset: { editable: '0' } }), null);
});
