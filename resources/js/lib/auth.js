import { reactive } from 'vue';
import api from './api';

export const auth = reactive({ user: null, ready: false });

export async function loadUser() {
    if (!localStorage.getItem('token')) {
        auth.user = null;
        auth.ready = true;
        return;
    }
    try {
        const { data } = await api.get('/me');
        auth.user = data.user;
    } catch {
        auth.user = null;
    }
    auth.ready = true;
}

export async function login(email, password) {
    const { data } = await api.post('/login', { email, password });
    localStorage.setItem('token', data.token);
    auth.user = data.user;
    auth.ready = true;
}

export async function logout() {
    try {
        await api.post('/logout');
    } catch {
        /* abaikan */
    }
    localStorage.removeItem('token');
    auth.user = null;
}
