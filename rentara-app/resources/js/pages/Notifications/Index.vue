<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AuthLayout from '../../layouts/AuthLayout.vue';

defineProps({ notifications: { type: Object, required: true } });

function markAllRead() {
    router.post('/notifications/read-all', {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Notifikasi — Rentara" />
    <AuthLayout title="Notifikasi" description="Ikuti perkembangan pengajuan dan sewamu.">
        <div class="mb-5 flex items-center justify-between gap-3">
            <h2 class="text-lg font-semibold text-[#20372b]">Aktivitas terbaru</h2>
            <button type="button" class="text-xs font-semibold text-[#47785a]" @click="markAllRead">Tandai semua dibaca</button>
        </div>
        <ul v-if="notifications.data.length" class="space-y-3">
            <li v-for="notification in notifications.data" :key="notification.id" class="rounded-xl border px-4 py-3" :class="notification.read_at ? 'border-[#e5eae3]' : 'border-[#a9c8ae] bg-[#f5faf4]'">
                <Link v-if="notification.action_url" :href="notification.action_url" class="block" preserve-scroll>
                    <p class="text-sm font-semibold text-[#35483c]">{{ notification.title }}</p>
                    <p class="mt-1 text-sm leading-6 text-[#69766e]">{{ notification.body }}</p>
                </Link>
                <div v-else>
                    <p class="text-sm font-semibold text-[#35483c]">{{ notification.title }}</p>
                    <p class="mt-1 text-sm leading-6 text-[#69766e]">{{ notification.body }}</p>
                </div>
                <Link v-if="!notification.read_at" :href="`/notifications/${notification.id}/read`" method="post" class="mt-2 inline-flex text-xs font-semibold text-[#47785a]">Tandai dibaca</Link>
            </li>
        </ul>
        <p v-else class="rounded-xl border border-dashed border-[#d7e0d6] px-4 py-5 text-sm text-[#748077]">Belum ada notifikasi.</p>
    </AuthLayout>
</template>
