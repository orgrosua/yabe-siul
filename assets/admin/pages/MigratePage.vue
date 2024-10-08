<script setup>
import { ref, onBeforeMount } from 'vue';

import { useBusyStore } from '../stores/busy';
import ExpansionPanel from '../components/ExpansionPanel.vue';
import { useNotifier } from '../library/notifier';
import { useApi } from '../library/api';


const notifier = useNotifier();
const busyStore = useBusyStore();
const api = useApi();

const confirmMigration = ref(false);

const windpress = ref({
    is_active: false,
    version: '-',
});


function doMigration() {
    if (!confirm('Are you sure you want to migrate to WindPress?')) {
        notifier.info('Migration cancelled');
        return;
    }

    busyStore.add('migrate.windpress');

    const promise = (async () => {
        await api.post('admin/settings/migration/do_migrate', {});

    })().finally(() => {
        busyStore.remove('migrate.windpress');
    });

    notifier.async(
        promise,
        resp => {
            notifier.success('Migration completed');
        },
        err => {
            notifier.alert('Migration failed');
        },
        'Migrating to WindPress...'
    );
}

onBeforeMount(() => {
    windpress.value = window.siul.windpress;
    console.log(windpress.value);
});
</script>

<template>
    <div class="flex flex:col">
        <ExpansionPanel namespace="settings" name="migration" class="border:1|solid|#e8e8eb box-shadow:none! max-w:screen-2xs mx:auto my:8 w:full">
            <template #header>
                <span class="fg:gray-90 font:18 font:semibold">
                    Migrate to WindPress
                </span>
            </template>

            <template #default>
                <div class="flex {bt:1|solid|#e8e8eb}>*+* bg:white flex:column">

                    <div class="flex flex:column gap:30 p:20">
                        <div class="flex flex:column gap:10">
                            <span class="fg:gray-60 font:15 font:medium">You are about to migrate to WindPress</span>
                            <div class="flex align-items:center gap:4">
                                <input type="checkbox" id="confirm_migration" v-model="confirmMigration" class="checkbox mt:0">
                                <label for="confirm_migration" class="font:medium">
                                    I have backed up my site and I am ready to migrate to WindPress
                                </label>
                            </div>

                            <p class="my:10">
                                WindPress is the new version of Yabe Siul. It has been rebuilt from the ground up to support Tailwind CSS versions 3 and 4, making it faster, more stable, and more feature-rich.
                            </p>

                            <div class="bg:blue-5 fg:blue-80 px:24">
                                <p class="f:14 lh:20px">
                                    If you've purchased Yabe Siul Pro, it will automatically transfer to WindPress Pro.
                                    Log in to your account at <a href="https://rosua.org/checkout/order-history" target="_blank" class="text:underline">https://rosua.org/checkout/order-history</a> to download WindPress Pro.
                                </p>
                            </div>

                            <div :class="`bg:${windpress.is_active ? 'lime' : 'amber'}-5 fg:${windpress.is_active ? 'lime' : 'amber'}-80 px:24`">
                                <h2 :class="`flex align-items:center f:16 fg:${windpress.is_active ? 'lime' : 'amber'}-95 font:semibold lh:24px mb:16`">
                                    <span :class="`rounded! b:4|solid|${windpress.is_active ? 'lime' : 'amber'}-20 bg:${windpress.is_active ? 'lime' : 'amber'}-40 box:content h:8 w:8`"></span>
                                    <span class="ml:14">
                                        {{ windpress.is_active ? 'WindPress is active' : 'WindPress is not active' }}
                                    </span>
                                    <dl :class="`rounded! bg:${windpress.is_active ? 'lime' : 'amber'}-10 f:14 fg:${windpress.is_active ? 'lime' : 'amber'}-80 font:medium lh:24px ml:16 my:0 px:12`">
                                        <dt class="inline">Version:</dt>
                                        <dd class="inline ml:4"> {{ windpress.version }} </dd>
                                    </dl>
                                </h2>
                                <p v-if="!windpress.is_active" class="f:14 font:mono lh:20px">
                                    WindPress is not detected on your site.
                                    Please install WindPress from <a href="https://wind.press" target="_blank" class="text:underline">https://wind.press</a> and activate it.
                                </p>
                            </div>

                            <div>
                                <button @click="doMigration" :disabled="busyStore.isBusy || !confirmMigration || !windpress.is_active" type="button" class="button button-secondary inline-flex align-items:center gap:8">
                                    <font-awesome-icon v-if="busyStore.isBusy && busyStore.hasTask('migrate.windpress')" :icon="['fas', 'circle-notch']" class="@rotate|1s|infinite|linear" />
                                    {{ busyStore.isBusy && busyStore.hasTask('migrate.windpress') ? 'Migrating' : 'Migrate' }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex:column gap:30 p:20">
                        <div class="flex flex:column gap:10">
                            <span class="fg:gray-60 font:15 font:medium">
                                What will the migration do?
                            </span>

                            <p class="my:2">
                                1. It will switch the Tailwind CSS version of WindPress to version 3.
                            </p>
                            <p class="my:2">
                                2. Copy your <span class="font:bold">main.css</span> content to WindPress.
                            </p>
                            <p class="my:2 line-height:2">
                                3. Copy your <span class="font:bold">preset.js</span> content to WindPress and add the <code>export default siul;</code> code to the end of content.
                            </p>
                            <p class="my:2">
                                4. Deactivate Yabe Siul (this) plugin.
                            </p>
                        </div>
                    </div>
                </div>
            </template>
        </ExpansionPanel>

    </div>
</template>

<style lang="scss" scoped>
input[type=checkbox].checkbox:checked {
    background-color: #236de7;
    border: none;
    border-radius: 4px;

    &:before {
        content: url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill-rule='evenodd' clip-rule='evenodd'%3E%3Cpath fill='none' stroke='%23fff' stroke-width='2' d='M4 8.5 7.2 11 12 5'/%3E%3C/svg%3E");
        height: 16px !important;
        margin: initial;
        position: relative;
        width: 16px !important;
    }

    &:disabled {
        background-color: #bbbdc6;
    }
}
</style>