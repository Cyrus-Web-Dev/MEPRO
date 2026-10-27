import { createRouter, createWebHistory } from 'vue-router';
import { auth, loadUser } from './lib/auth';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/login', component: () => import('./pages/Login.vue'), meta: { guest: true } },
        { path: '/', component: () => import('./pages/Dashboard.vue') },
        { path: '/hmi', component: () => import('./pages/Hmi.vue') },
        { path: '/work-orders', component: () => import('./pages/WorkOrders.vue') },
        { path: '/reports', component: () => import('./pages/Reports.vue'), meta: { roles: ['admin', 'supervisor'] } },
        { path: '/master', component: () => import('./pages/Master.vue'), meta: { roles: ['admin'] } },
        { path: '/:pathMatch(.*)*', redirect: '/' },
    ],
});

router.beforeEach(async (to) => {
    if (!auth.ready) await loadUser();
    if (to.meta.guest) return auth.user ? '/' : true;
    if (!auth.user) return '/login';
    if (to.path === '/' && auth.user.role === 'operator') return '/hmi';
    if (to.meta.roles && !to.meta.roles.includes(auth.user.role)) return '/';
    return true;
});

export default router;
