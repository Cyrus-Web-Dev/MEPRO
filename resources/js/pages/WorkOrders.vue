<script setup>
import { ref, computed, onMounted } from 'vue';
import api, { errMsg } from '../lib/api';
import { auth } from '../lib/auth';
import { pct, fmtTime, fmtDur, WO_LABEL } from '../lib/format';

const orders = ref([]);
const products = ref([]);
const machines = ref([]);
const operators = ref([]);
const status = ref('');
const error = ref('');
const info = ref('');
const detail = ref(null);

const isManager = computed(() => ['admin', 'supervisor'].includes(auth.user.role));
const form = ref({ product_id: '', machine_id: '', target_qty: 100, due_date: '', notes: '' });
const startFor = ref(null);
const startForm = ref({ machine_id: '', operator_id: '' });

async function load() {
    try {
        orders.value = (await api.get('/work-orders', { params: status.value ? { status: status.value } : {} })).data;
    } catch (e) {
        error.value = errMsg(e);
    }
}

async function run(fn, okMsg) {
    error.value = '';
    info.value = '';
    try {
        await fn();
        if (okMsg) info.value = okMsg;
    } catch (e) {
        error.value = errMsg(e);
    }
    await load();
}

const create = () =>
    run(async () => {
        const body = { ...form.value };
        Object.keys(body).forEach((k) => body[k] === '' && delete body[k]);
        await api.post('/work-orders', body);
        form.value = { product_id: '', machine_id: '', target_qty: 100, due_date: '', notes: '' };
    }, 'Work Order dibuat.');

function openStart(o) {
    startFor.value = o.id;
    startForm.value = { machine_id: o.machine_id || '', operator_id: '' };
}

const doStart = () =>
    run(async () => {
        const body = { machine_id: startForm.value.machine_id };
        if (startForm.value.operator_id) body.operator_id = startForm.value.operator_id;
        await api.post(`/work-orders/${startFor.value}/start`, body);
        startFor.value = null;
    }, 'Work Order dimulai.');

const cancelWo = (o) => confirm(`Batalkan ${o.wo_number}?`) && run(() => api.post(`/work-orders/${o.id}/cancel`), 'Work Order dibatalkan.');
const completeWo = (o) => confirm(`Tandai ${o.wo_number} selesai?`) && run(() => api.post(`/work-orders/${o.id}/complete`), 'Work Order selesai.');

async function toggleDetail(o) {
    if (detail.value?.id === o.id) {
        detail.value = null;
        return;
    }
    try {
        detail.value = (await api.get(`/work-orders/${o.id}`)).data;
    } catch (e) {
        error.value = errMsg(e);
    }
}

onMounted(async () => {
    const calls = [api.get('/machines'), api.get('/master/products')];
    if (isManager.value) calls.push(api.get('/operators'));
    const [mc, pr, op] = await Promise.all(calls);
    machines.value = mc.data;
    products.value = pr.data;
    operators.value = op ? op.data : [];
    await load();
});
</script>

<template>
    <h1>Work Order</h1>
    <div v-if="error" class="alert err">{{ error }}</div>
    <div v-if="info" class="alert ok">{{ info }}</div>

    <div v-if="isManager" class="card">
        <h2>Buat Work Order</h2>
        <div class="row">
            <div style="flex: 2; min-width: 200px"><label>Produk</label>
                <select v-model="form.product_id"><option value="" disabled>Pilih produk...</option><option v-for="p in products" :key="p.id" :value="p.id">{{ p.code }} - {{ p.name }}</option></select></div>
            <div style="flex: 1; min-width: 120px"><label>Target (unit)</label><input v-model.number="form.target_qty" type="number" min="1" /></div>
            <div style="flex: 1; min-width: 160px"><label>Mesin (opsional)</label>
                <select v-model="form.machine_id"><option value="">- bebas -</option><option v-for="x in machines" :key="x.id" :value="x.id">{{ x.code }}</option></select></div>
            <div style="flex: 1; min-width: 150px"><label>Jatuh tempo</label><input v-model="form.due_date" type="date" /></div>
        </div>
        <p><button :disabled="!form.product_id || !(form.target_qty >= 1)" @click="create">Buat</button></p>
    </div>

    <div class="card">
        <div class="row spread">
            <h2>Daftar Work Order</h2>
            <select v-model="status" style="width: 200px" @change="load">
                <option value="">Semua status</option>
                <option v-for="(l, k) in WO_LABEL" :key="k" :value="k">{{ l }}</option>
            </select>
        </div>
        <div class="table-wrap">
            <table>
                <tr><th>No. WO</th><th>Produk</th><th>Mesin</th><th>Progres</th><th>Status</th><th>Jatuh tempo</th><th></th></tr>
                <template v-for="o in orders" :key="o.id">
                    <tr>
                        <td><a href="#" @click.prevent="toggleDetail(o)">{{ o.wo_number }}</a></td>
                        <td>{{ o.product }}</td><td>{{ o.machine || '-' }}</td>
                        <td style="min-width: 160px">{{ o.good_qty }} / {{ o.target_qty }}
                            <div class="bar"><div :style="{ width: Math.min(100, o.progress * 100) + '%' }"></div></div></td>
                        <td><span class="badge" :class="o.status">{{ WO_LABEL[o.status] }}</span></td>
                        <td>{{ o.due_date || '-' }}</td>
                        <td class="row">
                            <button v-if="['planned', 'paused'].includes(o.status)" class="ok" @click="openStart(o)">Mulai</button>
                            <button v-if="isManager && ['planned', 'paused'].includes(o.status)" class="secondary" @click="completeWo(o)">Selesai</button>
                            <button v-if="isManager && ['planned', 'paused'].includes(o.status)" class="danger" @click="cancelWo(o)">Batal</button>
                        </td>
                    </tr>
                    <tr v-if="startFor === o.id">
                        <td colspan="7">
                            <div class="row">
                                <div style="min-width: 180px"><label>Mesin</label>
                                    <select v-model="startForm.machine_id"><option value="" disabled>Pilih mesin...</option><option v-for="x in machines" :key="x.id" :value="x.id">{{ x.code }} ({{ x.status }})</option></select></div>
                                <div v-if="isManager" style="min-width: 180px"><label>Operator</label>
                                    <select v-model="startForm.operator_id"><option value="">Saya sendiri</option><option v-for="u in operators" :key="u.id" :value="u.id">{{ u.name }}</option></select></div>
                                <button :disabled="!startForm.machine_id" @click="doStart">Jalankan</button>
                                <button class="secondary" @click="startFor = null">Batal</button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="detail && detail.id === o.id">
                        <td colspan="7">
                            <b>Riwayat run</b>
                            <p v-if="!detail.runs.length" class="muted">Belum ada run.</p>
                            <table v-else>
                                <tr><th>Mulai</th><th>Selesai</th><th>Mesin</th><th>Operator</th><th>Output</th><th>Reject</th><th>Downtime</th><th>OEE</th></tr>
                                <tr v-for="r in detail.runs" :key="r.id">
                                    <td>{{ fmtTime(r.started_at) }}</td><td>{{ r.ended_at ? fmtTime(r.ended_at) : 'berjalan' }}</td>
                                    <td>{{ r.machine }}</td><td>{{ r.operator }}</td><td>{{ r.metrics.total }}</td><td>{{ r.metrics.reject }}</td>
                                    <td>{{ fmtDur(r.metrics.unplanned_downtime_s + r.metrics.planned_downtime_s) }}</td><td>{{ pct(r.metrics.oee) }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </template>
            </table>
        </div>
    </div>
</template>
