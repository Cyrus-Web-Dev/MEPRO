<script setup>
import { useRouter } from 'vue-router';
import { auth, logout } from './lib/auth';

const router = useRouter();
async function doLogout() {
    await logout();
    router.push('/login');
}
</script>

<template>
    <header v-if="auth.user" class="top">
        <span class="brand">MEPRO</span>
        <nav>
            <router-link v-if="auth.user.role !== 'operator'" to="/">Dashboard</router-link>
            <router-link to="/hmi">Layar Operator</router-link>
            <router-link to="/work-orders">Work Order</router-link>
            <router-link v-if="['admin', 'supervisor'].includes(auth.user.role)" to="/reports">Laporan OEE</router-link>
            <router-link v-if="auth.user.role === 'admin'" to="/master">Master Data</router-link>
        </nav>
        <span class="muted">{{ auth.user.name }} ({{ auth.user.role }})</span>
        <button class="secondary" @click="doLogout">Keluar</button>
    </header>
    <main><router-view /></main>
</template>
