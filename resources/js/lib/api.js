import axios from 'axios';

const api = axios.create({ baseURL: '/api', headers: { Accept: 'application/json' } });

api.interceptors.request.use((config) => {
    const token = localStorage.getItem('token');
    if (token) config.headers.Authorization = 'Bearer ' + token;
    return config;
});

api.interceptors.response.use(
    (r) => r,
    (e) => {
        if (e.response?.status === 401 && !location.pathname.startsWith('/login')) {
            localStorage.removeItem('token');
            location.href = '/login';
        }
        return Promise.reject(e);
    },
);

export function errMsg(e) {
    const d = e.response?.data;
    if (d?.errors) return Object.values(d.errors).flat().join(' ');
    return d?.message || e.message || 'Terjadi kesalahan';
}

export default api;
