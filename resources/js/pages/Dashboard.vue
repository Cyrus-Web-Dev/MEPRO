<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import api from '../lib/api';
import { listenMachines } from '../lib/echo';
import { pct, fmtTime, STATUS_LABEL } from '../lib/format';

const machines = ref([]);
const wsState = ref('connecting');
let off = () => {};
let timer = null;

async function load() {
    try {
        machines.value = (await api.get('/machines')).data;
    } catch {
        /* polling berikutnya akan mencoba lagi */
    }
}

function upsert(m) {
    const i = machines.value.findIndex((x) => x.id === m.id);
    if (i >= 0) machines.value[i] = m;
    else machines.value.push(m);
}

const counts = computed(() => {
    const c = { running: 0, idle: 0, downtime: 0, offline: 0 };
    machines.value.forEach((m) => (c[m.status] = (c[m.status] || 0) + 1));
    return c;
});

onMounted(async () => {
    await load();
    off = listenMachines(upsert, (s) => (wsState.value = s));
    timer = setInterval(load, 15000); // cadangan bila WebSocket putus
});
onUnmounted(() => {
    off();
    clearInterval(timer);
});
</script>

<template>
    <div class="row spread">
        <h1>Dashboard Lantai Produksi</h1>
        <span class="muted"><span class="dot" :class="{ on: wsState === 'connected' }"></span> Real-time: {{ wsState === 'connected' ? 'tersambung' : 'terputus (data diperbarui tiap 15 detik)' }}</span>
    </div>

    <div class="row" style="margin-bottom: 16px">
        <span v-for="(n, s) in counts" :key="s" class="badge" :class="s">{{ STATUS_LABEL[s] }}: {{ n }}</span>
    </div>

    <p v-if="!machines.length" class="muted">Belum ada mesin aktif.</p>

    <div class="grid">
        <div v-for="m in machines" :key="m.id" class="card">
            <div class="row spread">
                <div><b>{{ m.code }}</b> <span class="muted">{{ m.name }}</span></div>
                <span class="badge" :class="m.status">{{ STATUS_LABEL[m.status] }}</span>
            </div>
            <div class="muted">{{ m.location || '-' }} | Terakhir hitung: {{ fmtTime(m.last_count_at) }}</div>

            <template v-if="m.run">
                <p style="margin: 10px 0 4px"><b>{{ m.run.wo_number }}</b> - {{ m.run.product }}</p>
                <div class="muted">Operator: {{ m.run.operator }} {{ m.run.shift ? '| ' + m.run.shift : '' }}</div>
                <div class="row spread" style="margin-top: 8px">
                    <span class="big">{{ m.run.output_qty }}</span>
                    <span class="muted">output run ini<br />{{ m.speed_per_minute }} /menit</span>
                </div>
                <div class="bar" style="margin-top: 6px"><div :style="{ width: Math.min(100, (m.run.wo_good_qty / m.run.target_qty) * 100) + '%' }"></div></div>
                <div class="muted">WO: {{ m.run.wo_good_qty }} / {{ m.run.target_qty }} baik</div>
                <div class="kpis">
                    <div><b>{{ pct(m.run.availability) }}</b><span class="muted">Avail.</span></div>
                    <div><b>{{ pct(m.run.performance) }}</b><span class="muted">Perf.</span></div>
                    <div><b>{{ pct(m.run.quality) }}</b><span class="muted">Quality</span></div>
                    <div><b>{{ pct(m.run.oee) }}</b><span class="muted">OEE</span></div>
                </div>
            </template>
            <p v-else class="muted" style="margin-top: 12px">Tidak ada Work Order berjalan. {{ m.speed_per_minute }} /menit</p>

            <div v-if="m.downtime" class="alert warn" style="margin: 10px 0 0">
                Downtime sejak {{ fmtTime(m.downtime.started_at) }}:
                <b>{{ m.downtime.reason || 'belum diberi alasan' }}</b>
            </div>
        </div>
    </div>
</template>
