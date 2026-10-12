/** Serialize rapid scans without letting a lost response strand the queue. */
export function createAllocationQueue({ send, onChange = () => {}, onResult = () => {}, timeoutMs = 15000 }) {
    const entries = [];
    let active = null;
    let closed = false;
    let abort = null;
    const snapshot = () => ({ active, pending: entries.filter((e) => e.status === 'queued').length, entries: entries.map((e) => ({ ...e })) });
    const emit = () => { if (!closed) onChange(snapshot()); };
    const hasCode = (code) => entries.some((e) => e.code === code && ['queued', 'processing'].includes(e.status));

    async function processNext() {
        if (closed || active) return;
        const entry = entries.find((e) => e.status === 'queued');
        if (!entry) return;
        active = entry;
        entry.status = 'processing';
        const controller = new AbortController();
        let timer;
        emit();
        try {
            const timeout = new Promise((resolve, reject) => {
                abort = () => {
                    reject(new Error('Belum ada konfirmasi dari server. Coba ulang kode ini; aset yang sudah tersimpan tidak dihitung dua kali.'));
                    controller.abort();
                };
                timer = setTimeout(abort, timeoutMs);
            });
            const data = await Promise.race([send(entry.code, controller.signal), timeout]);
            if (closed) return;
            onResult(data);
            entry.status = data.duplicate ? 'duplicate' : 'success';
            entry.message = data.message;
        } catch (error) {
            entry.status = 'failed';
            entry.message = error.message || 'Scan belum terkonfirmasi. Coba ulang kode ini.';
        } finally {
            clearTimeout(timer);
            abort = null;
            active = null;
            emit();
            void processNext();
        }
    }

    return {
        enqueue(code) {
            code = String(code || '').trim();
            if (closed || !code || hasCode(code)) return false;
            entries.push({ id: entries.length + 1, code, status: 'queued', message: '' });
            emit();
            void processNext();
            return true;
        },
        retry(id) {
            const entry = entries.find((e) => e.id === id);
            if (closed || !entry || entry.status !== 'failed' || hasCode(entry.code)) return false;
            entry.status = 'queued';
            entry.message = '';
            emit();
            void processNext();
            return true;
        },
        hasPending: () => Boolean(active) || entries.some((e) => e.status === 'queued'),
        snapshot,
        destroy() { closed = true; abort?.(); },
    };
}

export function initAllocationScanner(root, { fetchImpl = (...args) => fetch(...args), timeoutMs = 15000 } = {}) {
    if (!root || root.dataset.editable !== '1') return null;
    const doc = root.ownerDocument;
    const win = doc.defaultView;
    const messageBox = root.querySelector('#scan-message');
    const scannerState = root.querySelector('#scanner-state-text');
    const lastScan = root.querySelector('#last-scan');
    const history = root.querySelector('#scan-history');
    const manualForm = root.querySelector('#allocation-manual-form');
    const input = root.querySelector('#allocation-manual-code');
    let scanBuffer = '';
    let lastKeyAt = 0;
    let audioContext;

    function message(text, error = false) {
        messageBox.hidden = false;
        messageBox.className = 'mt-4 rounded-md px-4 py-3 text-sm font-semibold ' + (error ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800');
        messageBox.textContent = text;
    }

    function beep() {
        try {
            const AudioContext = win.AudioContext || win.webkitAudioContext;
            if (!AudioContext) return;
            audioContext ||= new AudioContext();
            if (audioContext.state === 'suspended') audioContext.resume().catch(() => {});
            const oscillator = audioContext.createOscillator();
            const gain = audioContext.createGain();
            oscillator.frequency.value = 880;
            gain.gain.value = 0.04;
            oscillator.connect(gain);
            gain.connect(audioContext.destination);
            oscillator.onended = () => { oscillator.disconnect(); gain.disconnect(); };
            oscillator.start();
            oscillator.stop(audioContext.currentTime + 0.08);
        } catch {
            // The visible per-code result remains available without sound.
        }
    }

    function updateItem(item) {
        const card = root.querySelector(`[data-item-id="${item.id}"]`);
        if (!card) return;
        for (const [selector, value] of [['data-allocated-count', item.allocated], ['data-free-count', item.free_available], ['data-shortage-count', item.shortage]]) {
            const element = card.querySelector(`[${selector}]`);
            if (element) element.textContent = value;
        }
        card.querySelector('[data-shortage-count]')?.classList.toggle('text-red-700', Number(item.shortage) > 0);
        const badge = card.querySelector('[data-complete-badge]');
        badge.textContent = item.complete ? 'COMPLETE' : 'WAITING SCAN';
        badge.className = 'rounded-full px-3 py-1 text-xs font-bold ' + (item.complete ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700');
        const list = card.querySelector('[data-asset-list]');
        list.replaceChildren();
        for (const asset of item.assets) {
            const chip = doc.createElement('span');
            chip.className = 'rounded-full bg-blue-100 px-3 py-1.5 text-xs font-bold text-blue-700';
            chip.dataset.assetId = asset.id;
            chip.textContent = `${asset.code} ✓`;
            list.appendChild(chip);
        }
        const reset = card.querySelector('[data-allocation-reset]');
        if (reset) reset.hidden = Number(item.allocated) === 0;
    }

    async function send(code, signal) {
        const response = await fetchImpl(root.dataset.endpoint, {
            method: 'PUT', signal, credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': root.dataset.csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ barcode: code }),
        });
        if (response.redirected || [401, 403, 419].includes(response.status)) {
            throw new Error('Sesi atau izin tidak valid. Login ulang lalu coba kode yang belum terkonfirmasi.');
        }
        let data;
        try { data = await response.json(); } catch {
            throw new Error('Server belum mengirim konfirmasi yang valid. Coba ulang kode ini.');
        }
        if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'Scan gagal. Coba lagi.');
        if (!data.success || !data.item || !Array.isArray(data.item.assets)) throw new Error('Konfirmasi alokasi belum lengkap. Coba ulang kode ini.');
        return data;
    }

    const labels = { queued: 'MENUNGGU', processing: 'DIPROSES', success: 'TERSIMPAN', duplicate: 'SUDAH TERSIMPAN', failed: 'PERLU DICEK' };
    const queue = createAllocationQueue({
        send, timeoutMs,
        onResult(data) { updateItem(data.item); message(data.message); beep(); },
        onChange(state) {
            const failed = state.entries.filter((e) => e.status === 'failed').length;
            scannerState.textContent = state.active
                ? `Memproses ${state.active.code} · ${state.pending} menunggu`
                : `READY - siap scan berikutnya${failed ? ` · ${failed} kode perlu dicek` : ''}`;
            const latest = state.entries.at(-1);
            if (latest) lastScan.textContent = `Kode terakhir: ${latest.code}`;
            history.replaceChildren();
            for (const entry of state.entries.slice(-20).reverse()) {
                const row = doc.createElement('li');
                row.className = 'rounded-md border p-3 text-xs ' + (entry.status === 'failed' ? 'border-red-200 bg-red-50 text-red-800' : 'border-gray-200 bg-white text-gray-800');
                const text = doc.createElement('span');
                text.textContent = `${entry.code} — ${labels[entry.status]}${entry.message ? ': ' + entry.message : ''}`;
                row.appendChild(text);
                if (entry.status === 'failed') {
                    const retry = doc.createElement('button');
                    retry.type = 'button';
                    retry.className = 'secondary-button ml-2';
                    retry.dataset.retryId = entry.id;
                    retry.textContent = 'Coba lagi';
                    row.appendChild(retry);
                }
                history.appendChild(row);
            }
        },
    });

    function submitManual(event) {
        event.preventDefault();
        const code = input.value.trim();
        if (!code) { input.focus(); return; }
        if (!queue.enqueue(code)) message(`${code} masih dalam antrean. Tunggu hasilnya.`);
        input.value = '';
        scanBuffer = '';
        input.focus();
    }
    function keydown(event) {
        if (event.ctrlKey || event.altKey || event.metaKey || event.isComposing) return;
        if (event.target?.closest?.('input, textarea, select, [contenteditable="true"], [data-allow-typing]')) {
            scanBuffer = ''; return;
        }
        const now = Date.now();
        if (lastKeyAt && now - lastKeyAt > 800) scanBuffer = '';
        lastKeyAt = now;
        if (event.key === 'Enter') {
            if (scanBuffer.trim()) { event.preventDefault(); queue.enqueue(scanBuffer); scanBuffer = ''; }
        } else if (event.key === 'Escape') scanBuffer = '';
        else if (event.key.length === 1 && !event.repeat) scanBuffer += event.key;
    }
    function retry(event) {
        const button = event.target.closest('[data-retry-id]');
        if (button) queue.retry(Number(button.dataset.retryId));
    }
    function guardSubmit(event) {
        if (event.target !== manualForm && queue.hasPending()) {
            event.preventDefault();
            message('Tunggu antrean scan selesai sebelum menyimpan quantity atau mereset alokasi.', true);
        }
    }
    function beforeUnload(event) {
        if (!queue.hasPending()) return;
        event.preventDefault(); event.returnValue = '';
    }
    manualForm.addEventListener('submit', submitManual);
    root.addEventListener('submit', guardSubmit);
    history.addEventListener('click', retry);
    doc.addEventListener('keydown', keydown);
    win.addEventListener('beforeunload', beforeUnload);
    input.focus();
    return {
        queue,
        destroy() {
            queue.destroy();
            manualForm.removeEventListener('submit', submitManual);
            root.removeEventListener('submit', guardSubmit);
            history.removeEventListener('click', retry);
            doc.removeEventListener('keydown', keydown);
            win.removeEventListener('beforeunload', beforeUnload);
            audioContext?.close().catch(() => {});
        },
    };
}
