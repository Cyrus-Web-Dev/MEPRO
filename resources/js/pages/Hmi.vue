<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import api, { errMsg } from '../lib/api';
import { listenMachines } from '../lib/echo';
import { pct, fmtTime, STATUS_LABEL } from '../lib/format';

const machines = ref([]);
const machineId = ref(Number(localStorage.getItem('hmi_machine')) || null);
const rejectReasons = ref([]);
const downtimeReasons = ref([]);
const orders = ref([]);
const error = ref('');
const info = ref('');
const busy = ref(false);

const rejectForm = ref({ reason: '', qty: 1 });
const dtReason = ref('');
const selectedWo = ref(null);

const m = computed(() => machines.value.find((x) => x.id === machineId.value) || null);
let off = () => {};
let timer = null;

async function loadMachines() {
    try {
        machines.value = (await api.get('/machines')).data;
    } catch (e) {
        error.value = errMsg(e);
    }
}

async function loadOrders() {
    if (!machineId.value) return;
    try {
        orders.value = (await api.get('/work-orders', { params: { status: 'planned,paused', machine_id: machineId.value } })).data;
    } catch (e) {
        error.value = errMsg(e);
    }
}

function upsert(x) {
    const i = machines.value.findIndex((y) => y.id === x.id);
    if (i >= 0) machines.value[i] = x;
}

async function act(fn, okMsg) {
    error.value = '';
    info.value = '';
    busy.value = true;
    try {
        await fn();
        if (okMsg) info.value = okMsg;
    } catch (e) {
        error.value = errMsg(e);
    } finally {
        busy.value = false;
        await loadMachines();
        await loadOrders();
    }
}

const startRun = () => act(() => api.post(`/work-orders/${selectedWo.value}/start`, { machine_id: machineId.value }).then(() => (selectedWo.value = null)), 'Work Order dimulai.');

function endRun(after) {
    const text = after === 'completed' ? 'Selesaikan Work Order ini?' : 'Jeda Work Order dan akhiri run ini?';
    if (!confirm(text)) return;
    act(() => api.post(`/runs/${m.value.run.id}/end`, { after }), after === 'completed' ? 'Work Order selesai.' : 'Run diakhiri.');
}

const sendReject = () =>
    act(async () => {
        await api.post(`/runs/${m.value.run.id}/rejects`, { reject_reason_id: rejectForm.value.reason, qty: rejectForm.value.qty });
        rejectForm.value = { reason: '', qty: 1 };
    }, 'Reject tercatat.');

const startDowntime = () => act(async () => { await api.post(`/machines/${machineId.value}/downtimes`, { downtime_reason_id: dtReason.value }); dtReason.value = ''; }, 'Downtime dimulai.');
const labelDowntime = () => act(async () => { await api.patch(`/downtimes/${m.value.downtime.id}`, { downtime_reason_id: dtReason.value }); dtReason.value = ''; }, 'Alasan downtime disimpan.');
const endDowntime = () => act(() => api.post(`/downtimes/${m.value.downtime.id}/end`), 'Downtime selesai.');

watch(machineId, (v) => {
    if (v) localStorage.setItem('hmi_machine', v);
    selectedWo.value = null;
    loadOrders();
});

onMounted(async () => {
    const [rr, dr] = await Promise.all([api.get('/master/reject-reasons'), api.get('/master/downtime-reasons')]);
    rejectReasons.value = rr.data.filter((x) => x.is_active);
    downtimeReasons.value = dr.data.filter((x) => x.is_active);
    await loadMachines();
    await loadOrders();
    off = listenMachines(upsert);
    timer = setInterval(loadMachines, 15000);
});
onUnmounted(() => {
    off();
    clearInterval(timer);
});
</script>

<template>
    <h1>Layar Operator</h1>
    <div v-if="error" class="alert err">{{ error }}</div>
    <div v-if="info" class="alert ok">{{ info }}</div>

    <div class="card">
        <label>Mesin</label>
        <select v-model.number="machineId">
            <option :value="null" disabled>Pilih mesin...</option>
            <option v-for="x in machines" :key="x.id" :value="x.id">{{ x.code }} - {{ x.name }}</option>
        </select>
    </div>

    <template v-if="m">
        <div class="card row spread">
            <div><span class="big">{{ m.code }}</span> <span class="muted">{{ m.name }}</span></div>
            <span class="badge" :class="m.status" style="font-size: 18px">{{ STATUS_LABEL[m.status] }}</span>
        </div>

        <!-- Tidak ada run: pilih Work Order -->
        <div v-if="!m.run" class="card">
            <h2>Mulai Work Order</h2>
            <p v-if="!orders.length" class="muted">Tidak ada Work Order yang siap dijalankan di mesin ini.</p>
            <div class="table-wrap" v-else>
                <table>
                    <tr><th></th><th>No. WO</th><th>Produk</th><th>Target</th><th>Sudah baik</th><th>Status</th></tr>
                    <tr v-for="o in orders" :key="o.id">
                        <td><input type="radio" :value="o.id" v-model="selectedWo" /></td>
                        <td>{{ o.wo_number }}</td><td>{{ o.product }}</td><td>{{ o.target_qty }}</td><td>{{ o.good_qty }}</td>
                        <td><span class="badge" :class="o.status">{{ o.status }}</span></td>
                    </tr>
                </table>
            </div>
            <p><button class="ok xl" :disabled="!selectedWo || busy" @click="startRun">Mulai Produksi</button></p>
        </div>

        <!-- Ada run -->
        <template v-else>
            <div class="card">
                <div class="row spread">
                    <div><b>{{ m.run.wo_number }}</b> - {{ m.run.product }}<div class="muted">Operator {{ m.run.operator }} | mulai {{ fmtTime(m.run.started_at) }}</div></div>
                    <div class="muted">{{ m.speed_per_minute }} unit/menit</div>
                </div>
                <div class="kpis" style="margin-top: 14px">
                    <div><span class="big">{{ m.run.output_qty }}</span><span class="muted">Output</span></div>
                    <div><span class="big">{{ m.run.reject_qty }}</span><span class="muted">Reject</span></div>
                    <div><span class="big">{{ m.run.good_qty }}</span><span class="muted">Baik</span></div>
                    <div><span class="big">{{ pct(m.run.oee) }}</span><span class="muted">OEE</span></div>
                </div>
                <div class="bar" style="margin-top: 12px"><div :style="{ width: Math.min(100, (m.run.wo_good_qty / m.run.target_qty) * 100) + '%' }"></div></div>
                <div class="muted">Progres WO: {{ m.run.wo_good_qty }} / {{ m.run.target_qty }}</div>
            </div>

            <div class="hmi-grid">
                <div class="card">
                    <h2>Catat Reject (QC)</h2>
                    <label>Alasan</label>
                    <select v-model="rejectForm.reason">
                        <option value="" disabled>Pilih alasan...</option>
                        <option v-for="r in rejectReasons" :key="r.id" :value="r.id">{{ r.name }}</option>
                    </select>
                    <label>Jumlah</label>
                    <input v-model.number="rejectForm.qty" type="number" min="1" />
                    <p><button class="danger" :disabled="!rejectForm.reason || !(rejectForm.qty >= 1) || busy" @click="sendReject">Simpan Reject</button></p>
                </div>

                <div class="card">
                    <h2>Downtime</h2>
                    <template v-if="m.downtime">
                        <div class="alert warn">
                            Berhenti sejak {{ fmtTime(m.downtime.started_at) }}
                            <span v-if="m.downtime.source === 'auto'">(terdeteksi otomatis)</span><br />
                            Alasan: <b>{{ m.downtime.reason || 'belum diisi' }}</b>
                        </div>
                        <label>{{ m.downtime.reason ? 'Ubah alasan' : 'Isi alasan' }}</label>
                        <select v-model="dtReason">
                            <option value="" disabled>Pilih alasan...</option>
                            <option v-for="r in downtimeReasons" :key="r.id" :value="r.id">{{ r.name }} ({{ r.category === 'planned' ? 'terencana' : 'tak terencana' }})</option>
                        </select>
                        <p class="row">
                            <button class="secondary" :disabled="!dtReason || busy" @click="labelDowntime">Simpan alasan</button>
                            <button class="ok" :disabled="busy" @click="endDowntime">Mesin jalan lagi</button>
                        </p>
                    </template>
                    <template v-else>
                        <label>Alasan berhenti</label>
                        <select v-model="dtReason">
                            <option value="" disabled>Pilih alasan...</option>
                            <option v-for="r in downtimeReasons" :key="r.id" :value="r.id">{{ r.name }} ({{ r.category === 'planned' ? 'terencana' : 'tak terencana' }})</option>
                        </select>
                        <p><button class="warn" :disabled="!dtReason || busy" @click="startDowntime">Mulai Downtime</button></p>
                    </template>
                </div>
            </div>

            <div class="card row">
                <button class="secondary" :disabled="busy" @click="endRun('paused')">Jeda / Akhiri Shift</button>
                <button class="danger" :disabled="busy" @click="endRun('completed')">Selesaikan Work Order</button>
            </div>
        </template>
    </template>
</template>
