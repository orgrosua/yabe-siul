import { createRouter, createWebHistory } from 'vue-router';

import TailwindPage from './pages/TailwindPage.vue';
// import SettingsPage from './pages/SettingsPage.vue';

const router = createRouter({
    history: createWebHistory(`${window.siul.web_history}#/`),
    scrollBehavior(_, _2, savedPosition) {
        return savedPosition || { left: 0, top: 0 };
    },
    routes: [
        { path: '/', name: 'home', redirect: { name: 'settings' } },
        {
            path: '/tailwind',
            name: 'tailwind',
            // component: TailwindPage,
            component: () => import('./pages/TailwindPage.vue'),
        },
        // {
        //     path: '/settings',
        //     name: 'settings',
        //     component: SettingsPage,
        // },
        {
            path: '/:pathMatch(.*)*',
            name: 'NotFound',
            component: () => import('./pages/NotFound.vue'),
        },
    ]
});

export default router;