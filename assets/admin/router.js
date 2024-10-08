import { createRouter, createWebHistory } from 'vue-router';

import TailwindPage from './pages/TailwindPage.vue';
import SettingsPage from './pages/SettingsPage.vue';
import MigratePage from './pages/MigratePage.vue';

const router = createRouter({
    history: createWebHistory(`${window.siul.web_history}#/`),
    scrollBehavior(_, _2, savedPosition) {
        return savedPosition || { left: 0, top: 0 };
    },
    routes: [
        { path: '/', name: 'home', redirect: { name: 'windpress' } },
        {
            path: '/tailwind',
            name: 'tailwind',
            component: TailwindPage,
        },
        {
            path: '/settings',
            name: 'settings',
            component: SettingsPage,
        },
        {
            path: '/windpress',
            name: 'windpress',
            component: MigratePage,
        },
        {
            path: '/:pathMatch(.*)*',
            name: 'NotFound',
            component: () => import('./pages/NotFound.vue'),
        },
    ]
});

export default router;