<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { login } from '../lib/auth';
import { errMsg } from '../lib/api';

const router = useRouter();
const email = ref('');
const password = ref('');
const error = ref('');
const busy = ref(false);

async function submit() {
    error.value = '';
    busy.value = true;
    try {
        await login(email.value, password.value);
        router.push('/');
    } catch (e) {
        error.value = errMsg(e);
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <div class="card login">
        <h1>MEPRO</h1>
        <p class="muted">Manufacturing Execution System</p>
        <div v-if="error" class="alert err">{{ error }}</div>
        <label>Email</label>
        <input v-model="email" type="email" autocomplete="username" @keyup.enter="submit" />
        <label>Password</label>
        <input v-model="password" type="password" autocomplete="current-password" @keyup.enter="submit" />
        <p><button :disabled="busy" @click="submit">Masuk</button></p>
    </div>
</template>
