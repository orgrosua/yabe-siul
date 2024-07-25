<script setup>
import { ref, computed } from 'vue';
import { useColorMode } from '@vueuse/core';
import { useBusyStore } from './stores/busy.js';
import WordpressNotice from './components/WordpressNotice.vue';

import Logo from '../../siul.svg';

const busyStore = useBusyStore();

const theme = useColorMode({
    storageKey: 'theme',
    initialValue: 'light',
    onChanged: (value, defaultHandler) => {
        defaultHandler(value);
        document.documentElement.style.colorScheme = value;
    },
});

const componentRef = ref(null);

const isSaving = computed(() => {
    return busyStore.tasks.some((t) => /doSave|doPush/.test(t.task));
});

function doSave() {
    if (componentRef.value && componentRef.value.doSave) {
        componentRef.value.doSave();
    }
}
</script>

<template>
    <div>
        <div class="siul-main rel">
            <header id="siul-header" class="flex sticky align-items:center bg:white px:20 py:6 top:$(wp-admin--admin-bar--height) z:12">
                <div class="flex align-items:center fg:black_* flex-grow:1 gap:10">
                    <inline-svg :src="Logo" class="inline-svg f:40 fill:current px:2" />
                    <h1 class="">Yabe Siul</h1>
                    <div class="ml:20">
                        <div class="px:16 py:6 r:8 bg:#e5f5ff! fg:#0073e0! fg:#0073e0!_* b:1|solid|#005cc6! text:center font:medium ls:2 lh:1.5">
                            <font-awesome-icon :icon="['fas', 'bullhorn']" class="fill:current fg:#0073e0! pr:6" />
                            The <span class="ls:1 font:bold">Tailwind CSS v4</span> support is now available at <a href="https://wind.press" class="ls:1 font:bold text:underline">https://wind.press</a></div>
                    </div>
                </div>
                <div class="">
                    <div class="flex align-items:center flex:row gap:10">
                        <button @click="theme = theme === 'dark' ? 'light' : 'dark'" class="flex rounded b:0 bg:transparent bg:#f3f4f6:hover cursor:pointer f:20 fg:black p:10">
                            <font-awesome-icon v-if="theme === 'light'" :icon="['fas', 'sun-bright']" class="fill:current" />
                            <font-awesome-icon v-else-if="theme === 'dark'" :icon="['fas', 'moon-stars']" class="fill:current" />
                        </button>

                        <button v-if="componentRef && componentRef.doSave" @click="doSave" type="button" :disabled="busyStore.isBusy" class="button button-primary button-large inline-flex align-items:center gap:8 my:auto py:2">
                            <font-awesome-icon v-if="busyStore.isBusy" :icon="['fas', 'circle-notch']" class="@rotate|1s|infinite|linear" />
                            <template v-if="isSaving">
                                Updating
                            </template>
                            <template v-else-if="busyStore.isBusy">
                                Loading
                            </template>
                            <template v-else>
                                Update
                            </template>
                        </button>

                        <VDropdown :distance="12">
                            <button class="button button-secondary b:0 bg:transparent bg:gray-10:hover fg:gray-90 h:36 min-w:36 my:auto width:auto">
                                <font-awesome-icon :icon="['fas', 'ellipsis-vertical']" class="font:15" />
                            </button>

                            <template #popper>
                                <div>
                                    <div role="group" class="flex flex:column font:14 min-w:120 p:4 w:auto">
                                        <a href="https://siul.yabe.land/docs?utm_source=wordpress-plugins&utm_medium=plugin-menu&utm_campaign=yabe-siul&utm_id=pro-version" target="_blank" class="flex align-items:center bg:white bg:gray-10:hover box-shadow:none:focus cursor:pointer fg:gray-90 gap:10 px:10 py:6 r:4 text-decoration:none user-select:none">
                                            <font-awesome-icon :icon="['fas', 'book']" class="min-w:14" />
                                            Documentation
                                        </a>
                                        <a href="https://rosua.org/support-portal" target="_blank" class="flex align-items:center bg:white bg:gray-10:hover box-shadow:none:focus cursor:pointer fg:gray-90 gap:10 px:10 py:6 r:4 text-decoration:none user-select:none">
                                            <font-awesome-icon :icon="['fas', 'user-headset']" class="min-w:14" />
                                            Support
                                        </a>
                                        <a href="https://www.facebook.com/groups/1142662969627943" target="_blank" class="flex align-items:center bg:white bg:gray-10:hover box-shadow:none:focus cursor:pointer fg:gray-90 gap:10 px:10 py:6 r:4 text-decoration:none user-select:none">
                                            <font-awesome-icon :icon="['fab', 'facebook']" class="min-w:14" />
                                            Community
                                        </a>
                                    </div>
                                </div>
                            </template>
                        </VDropdown>
                    </div>
                </div>
            </header>

            <div class="mx:auto p:0">
                <div class="bb:1|solid|gray-20 bg:gray-5 mb:20">
                    <div class="flex flex:row mx:30">
                        <ul class="flex uppercase {bb:3|solid|black}>li:has(>.router-link-active) {fg:black}>li:has(>.router-link-active)>a align-items:baseline box-shadow:none>li>a:focus fg:gray-70>li>a fg:gray-90>li>a:hover flex-grow:1 font:12 font:semibold gap-x:28 m:0 m:0>li pb:6>li pt:20 pt:10>li px:4>li text-decoration:none>li>a">
                            <li><router-link :to="{ name: 'tailwind' }" activeClass="router-link-active">Tailwind CSS</router-link></li>
                            <li><router-link :to="{ name: 'settings' }" activeClass="router-link-active">Settings</router-link></li>
                        </ul>
                        <div id="navbar-right-side"></div>
                    </div>
                </div>

                <div class="siul-notice-pool b:0 mx:0">
                    <hr class="wp-header-end">
                    <WordpressNotice />
                </div>

                <div class="siul-content my:20 px:20">
                    <router-view v-slot="{ Component }">
                        <component ref="componentRef" :is="Component" />
                    </router-view>
                </div>
            </div>
        </div>
    </div>
</template>