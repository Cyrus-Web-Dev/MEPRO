<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import api, { errMsg } from '../lib/api';

const ACTIVE = ['is_active', 'Aktif', 'bool'];
const RES = {
    machines: { label: 'Mesin', cols: ['code', 'name', 'location', 'ideal_cycle_seconds', 'is_active'], fields: [['code', 'Kode (dipakai di topic MQTT)', 'text'], ['name', 'Nama', 'text'], ['location', 'Lokasi', 'text'], ['ideal_cycle_seconds', 'Ideal cycle (detik per unit)', 'number'], ACTIVE] },
    devices: { label: 'Device ESP32', cols: ['machine.code', 'device_uid', 'mqtt_username', 'firmware_version', 'last_seen_at', 'is_active'], fields: [['machine_id', 'Mesin', 'machine'], ['device_uid', 'Device UID', 'text'], ['mqtt_username', 'Username MQTT', 'text'], ACTIVE] },
    products: { label: 'Produk & BOM', cols: ['code', 'name', 'unit', 'ideal_cycle_seconds', 'bom_items_count'], fields: [['code', 'Kode', 'text'], ['name', 'Nama', 'text'], ['unit', 'Satuan', 'text'], ['ideal_cycle_seconds', 'Ideal cycle (detik per unit)', 'number']] },
    materials: { label: 'Bahan Baku', cols: ['code', 'name', 'unit'], fields: [['code', 'Kode', 'text'], ['name', 'Nama', 'text'], ['unit', 'Satuan', 'text']] },
    shifts: { label: 'Shift', cols: ['name', 'start_time', 'end_time', 'is_active'], fields: [['name', 'Nama', 'text'], ['start_time', 'Mulai (WIB)', 'time'], ['end_time', 'Selesai (WIB)', 'time'], ACTIVE] },
    'reject-reasons': { label: 'Alasan Reject', cols: ['code', 'name', 'is_active'], fields: [['code', 'Kode', 'text'], ['name', 'Nama', 'text'], ACTIVE] },
    'downtime-reasons': { label: 'Alasan Downtime', cols: ['code', 'name', 'category', 'is_active'], fields: [['code', 'Kode', 'text'], ['name', 'Nama', 'text'], ['category', 'Kategori', 'category'], ACTIVE] },
    users: { label: 'Pengguna', cols: ['name', 'email', 'role', 'employee_code', 'is_active'], fields: [['name', 'Nama', 'text'], ['email', 'Email', 'email'], ['role', 'Role', 'role'], ['employee_code', 'Kode karyawan', 'text'], ['password', 'Password (kosongkan jika tidak diubah)', 'password'], ACTIVE] },
};

const resource = ref('machines');
const rows = ref([]);
const machines = ref([]);
const editing = ref(null); // null | 'new' | id
const form = ref({});
const error = ref('');
const info = ref('');

// BOM
const bomProduct = ref(null);
const bomItems = ref([]);
const materials = ref([]);

const cfg = computed(() => RES[resource.value]);

const pick = (row, path) => path.split('.').reduce((o, k) => (o == null ? o : o[k]), row);
const show = (v) => (v === true ? 'Ya' : v === false ? 'Tidak' : v ?? '-');

async function load() {
    error.value = '';
    try {
        rows.value = (await api.get(`/master/${resource.value}`)).data;
    } catch (e) {
        error.value = errMsg(e);
    }
}

function blank() {
    const f = {};
    cfg.value.fields.forEach(([k, , t]) => (f[k] = t === 'bool' ? true : t === 'role' ? 'operator' : t === 'category' ? 'unplanned' : ''));
    return f;
}

function openNew() {
    editing.value = 'new';
    form.value = blank();
}

function openEdit(row) {
    editing.value = row.id;
    const f = blank();
    cfg.value.fields.forEach(([k, , t]) => {
        if (k === 'password') f[k] = '';
        else if (t === 'time') f[k] = (row[k] || '').slice(0, 5);
        else f[k] = row[k] ?? (t === 'bool' ? true : '');
    });
    form.value = f;
}

async function save() {
    error.value = '';
    info.value = '';
    const body = { ...form.value };
    Object.keys(body).forEach((k) => body[k] === '' && delete body[k]);
    try {
        if (editing.value === 'new') await api.post(`/master/${resource.value}`, body);
        else await api.put(`/master/${resource.value}/${editing.value}`, body);
        editing.value = null;
        info.value = 'Tersimpan.';
        await load();
    } catch (e) {
        error.value = errMsg(e);
    }
}

async function remove(row) {
    if (!confirm('Hapus data ini?')) return;
    error.value = '';
    info.value = '';
    try {
        await api.delete(`/master/${resource.value}/${row.id}`);
        await load();
    } catch (e) {
        error.value = errMsg(e);
    }
}

async function openBom(p) {
    bomProduct.value = p;
    if (!materials.value.length) materials.value = (await api.get('/master/materials')).data;
    const items = (await api.get(`/products/${p.id}/bom`)).data;
    bomItems.value = items.map((i) => ({ material_id: i.material_id, qty_per_unit: i.qty_per_unit, scrap_percent: i.scrap_percent }));
}

async function saveBom() {
    error.value = '';
    info.value = '';
    try {
        await api.put(`/products/${bomProduct.value.id}/bom`, { items: bomItems.value });
        info.value = 'BOM tersimpan.';
        bomProduct.value = null;
        await load();
    } catch (e) {
        error.value = errMsg(e);
    }
}

watch(resource, () => {
    editing.value = null;
    bomProduct.value = null;
    info.value = '';
    load();
});

onMounted(async () => {
    machines.value = (await api.get('/machines')).data;
    await load();
});
</script>

<template>
    <h1>Master Data</h1>
    <div class="tabs">
        <button v-for="(r, k) in RES" :key="k" :class="{ active: resource === k }" @click="resource = k">{{ r.label }}</button>
    </div>
    <div v-if="error" class="alert err">{{ error }}</div>
    <div v-if="info" class="alert ok">{{ info }}</div>

    <div class="card">
        <div class="row spread"><h2>{{ cfg.label }}</h2><button @click="openNew">+ Tambah</button></div>
        <div class="table-wrap">
            <table>
                <tr><th v-for="c in cfg.cols" :key="c">{{ c }}</th><th></th></tr>
                <tr v-for="row in rows" :key="row.id">
                    <td v-for="c in cfg.cols" :key="c">{{ show(pick(row, c)) }}</td>
                    <td class="row">
                        <button class="secondary" @click="openEdit(row)">Ubah</button>
                        <button v-if="resource === 'products'" class="secondary" @click="openBom(row)">BOM</button>
                        <button class="danger" @click="remove(row)">Hapus</button>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <div v-if="editing !== null" class="card">
        <h2>{{ editing === 'new' ? 'Tambah' : 'Ubah' }} {{ cfg.label }}</h2>
        <div v-for="[k, label, type] in cfg.fields" :key="k">
            <label v-if="type !== 'bool'">{{ label }}</label>
            <select v-if="type === 'machine'" v-model="form[k]"><option value="" disabled>Pilih mesin...</option><option v-for="m in machines" :key="m.id" :value="m.id">{{ m.code }}</option></select>
            <select v-else-if="type === 'role'" v-model="form[k]"><option value="admin">admin</option><option value="supervisor">supervisor</option><option value="operator">operator</option></select>
            <select v-else-if="type === 'category'" v-model="form[k]"><option value="planned">planned (terencana)</option><option value="unplanned">unplanned (tak terencana)</option></select>
            <label v-else-if="type === 'bool'" style="margin-top: 12px"><input v-model="form[k]" type="checkbox" /> {{ label }}</label>
            <input v-else v-model="form[k]" :type="type === 'number' ? 'number' : type" :step="type === 'number' ? 'any' : undefined" />
        </div>
        <p class="row"><button @click="save">Simpan</button><button class="secondary" @click="editing = null">Batal</button></p>
    </div>

    <div v-if="bomProduct" class="card">
        <h2>BOM: {{ bomProduct.name }}</h2>
        <table>
            <tr><th>Bahan</th><th>Qty per unit produk</th><th>Scrap %</th><th></th></tr>
            <tr v-for="(it, i) in bomItems" :key="i">
                <td><select v-model="it.material_id"><option v-for="m in materials" :key="m.id" :value="m.id">{{ m.code }} - {{ m.name }} ({{ m.unit }})</option></select></td>
                <td><input v-model.number="it.qty_per_unit" type="number" step="any" min="0" /></td>
                <td><input v-model.number="it.scrap_percent" type="number" step="any" min="0" max="100" /></td>
                <td><button class="danger" @click="bomItems.splice(i, 1)">x</button></td>
            </tr>
        </table>
        <p class="row">
            <button class="secondary" @click="bomItems.push({ material_id: materials[0]?.id, qty_per_unit: 1, scrap_percent: 0 })">+ Bahan</button>
            <button @click="saveBom">Simpan BOM</button><button class="secondary" @click="bomProduct = null">Tutup</button>
        </p>
    </div>
</template>
