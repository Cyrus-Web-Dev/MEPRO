<script setup>
import { ref, onMounted } from 'vue';
import api, { errMsg } from '../lib/api';
import { pct, fmtTime, fmtDur, todayLocal } from '../lib/format';

const from = ref(todayLocal());
const to = ref(todayLocal());
const machineId = ref('');
const machines = ref([]);
const report = ref(null);
const error = ref('');
const loading = ref(false);

async function load() {
    error.value = '';
    loading.value = true;
    try {
        const params = { from: from.value, to: to.value };
        if (machineId.value) params.machine_id = machineId.value;
        report.value = (await api.get('/reports/oee', { params })).data;
    } catch (e) {
        error.value = errMsg(e);
    } finally {
        loading.value = false;
    }
}

onMounted(async () => {
    machines.value = (await api.get('/machines')).data;
    await load();
});
</script>

<template>
    <h1>Laporan OEE &amp; Kinerja</h1>
    <div v-if="error" class="alert err">{{ error }}</div>

    <div class="card row">
        <div><label>Dari</label><input v-model="from" type="date" /></div>
        <div><label>Sampai</label><input v-model="to" type="date" /></div>
        <div><label>Mesin</label>
            <select v-model="machineId"><option value="">Semua</option><option v-for="m in machines" :key="m.id" :value="m.id">{{ m.code }}</option></select></div>
        <div style="align-self: flex-end"><button :disabled="loading" @click="load">Tampilkan</button></div>
    </div>

    <template v-if="report">
        <div class="card">
            <h2>Ringkasan ({{ report.overall.runs }} run)</h2>
            <div class="kpis">
                <div><span class="big">{{ pct(report.overall.availability) }}</span><span class="muted">Availability</span></div>
                <div><span class="big">{{ pct(report.overall.performance) }}</span><span class="muted">Performance</span></div>
                <div><span class="big">{{ pct(report.overall.quality) }}</span><span class="muted">Quality</span></div>
                <div><span class="big">{{ pct(report.overall.oee) }}</span><span class="muted">OEE</span></div>
            </div>
            <p class="muted">Output {{ report.overall.total }} | Reject {{ report.overall.reject }} | Baik {{ report.overall.good }}. Performance "-" berarti ideal cycle belum diisi (di Master Data produk/mesin).</p>
        </div>

        <div class="card table-wrap">
            <h2>Per Mesin</h2>
            <table>
                <tr><th>Mesin</th><th>Run</th><th>Output</th><th>Reject</th><th>Avail.</th><th>Perf.</th><th>Quality</th><th>OEE</th></tr>
                <tr v-for="r in report.by_machine" :key="r.code"><td>{{ r.code }}</td><td>{{ r.runs }}</td><td>{{ r.total }}</td><td>{{ r.reject }}</td><td>{{ pct(r.availability) }}</td><td>{{ pct(r.performance) }}</td><td>{{ pct(r.quality) }}</td><td><b>{{ pct(r.oee) }}</b></td></tr>
            </table>
        </div>

        <div class="card table-wrap">
            <h2>Kinerja Operator</h2>
            <table>
                <tr><th>Operator</th><th>Run</th><th>Output</th><th>Baik</th><th>Reject</th><th>Output/jam</th><th>Avail.</th><th>Perf.</th><th>Quality</th><th>OEE</th></tr>
                <tr v-for="r in report.by_operator" :key="r.id"><td>{{ r.name }}</td><td>{{ r.runs }}</td><td>{{ r.total }}</td><td>{{ r.good }}</td><td>{{ r.reject }}</td><td>{{ r.output_per_hour }}</td><td>{{ pct(r.availability) }}</td><td>{{ pct(r.performance) }}</td><td>{{ pct(r.quality) }}</td><td><b>{{ pct(r.oee) }}</b></td></tr>
            </table>
        </div>

        <div class="hmi-grid">
            <div class="card table-wrap">
                <h2>Downtime per Alasan</h2>
                <table>
                    <tr><th>Alasan</th><th>Jenis</th><th>Kejadian</th><th>Durasi</th></tr>
                    <tr v-for="d in report.downtime_by_reason" :key="d.reason"><td>{{ d.reason }}</td><td>{{ d.category === 'planned' ? 'terencana' : 'tak terencana' }}</td><td>{{ d.count }}</td><td>{{ fmtDur(d.seconds) }}</td></tr>
                </table>
            </div>
            <div class="card table-wrap">
                <h2>Reject per Alasan</h2>
                <table>
                    <tr><th>Alasan</th><th>Jumlah</th></tr>
                    <tr v-for="d in report.reject_by_reason" :key="d.reason"><td>{{ d.reason }}</td><td>{{ d.qty }}</td></tr>
                </table>
            </div>
        </div>

        <div class="card table-wrap">
            <h2>Detail Run</h2>
            <table>
                <tr><th>Mulai</th><th>WO</th><th>Produk</th><th>Mesin</th><th>Operator</th><th>Shift</th><th>Durasi</th><th>Output</th><th>Reject</th><th>Downtime</th><th>OEE</th></tr>
                <tr v-for="r in report.runs" :key="r.id">
                    <td>{{ fmtTime(r.started_at) }}</td><td>{{ r.wo_number }}</td><td>{{ r.product }}</td><td>{{ r.machine }}</td><td>{{ r.operator }}</td><td>{{ r.shift || '-' }}</td>
                    <td>{{ fmtDur(r.metrics.duration_s) }}</td><td>{{ r.metrics.total }}</td><td>{{ r.metrics.reject }}</td>
                    <td>{{ fmtDur(r.metrics.planned_downtime_s + r.metrics.unplanned_downtime_s) }}</td><td>{{ pct(r.metrics.oee) }}</td>
                </tr>
            </table>
        </div>
    </template>
</template>
