<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthLayout from '../../../layouts/AuthLayout.vue';

defineProps({
    organization: { type: Object, required: true },
    applications: { type: Object, required: true },
});

const statusLabels = {
    submitted: 'Dikirim',
    under_review: 'Sedang ditinjau',
    info_requested: 'Perlu informasi',
    rejected: 'Ditolak',
    approved: 'Disetujui',
};

function formatDate(value) {
    return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeZone: 'Asia/Jakarta' }).format(new Date(value));
}
</script>

<template>
    <Head :title="`Pengajuan penyewa — ${organization.name}`" />
    <AuthLayout eyebrow="Workspace pemilik" :title="organization.name" description="Tinjau pengajuan dari pencari hunian dan kelengkapan dokumen identitas mereka.">
        <div class="mb-6 flex items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-semibold tracking-[-0.05em] text-[#20372b]">Pengajuan penyewa</h2>
                <p class="mt-1.5 text-sm text-[#768078]">{{ applications.total }} pengajuan</p>
            </div>
            <Link href="/dashboard" class="shrink-0 text-xs font-semibold text-[#47785a] hover:text-[#244d39]">Dashboard</Link>
        </div>

        <ul v-if="applications.data.length" class="space-y-3">
            <li v-for="application in applications.data" :key="application.id">
                <Link :href="`/organizations/${organization.id}/applications/${application.id}`" class="group block rounded-2xl border border-[#e5eae3] p-4 transition hover:border-[#b9cdbb] hover:bg-[#fbfcfa]">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="truncate text-sm font-semibold text-[#35483c]">{{ application.applicant_name }}</h3>
                            <p class="mt-1 truncate text-xs text-[#7a857d]">{{ application.applicant_email }}</p>
                            <p class="mt-2 text-xs text-[#647069]">{{ application.property_name }} · {{ application.unit_name }}</p>
                            <p class="mt-1 text-[11px] text-[#89928b]">Tanggal masuk diminta {{ formatDate(application.requested_move_in) }}</p>
                        </div>
                        <span class="shrink-0 rounded-full bg-[#f0f4ee] px-2.5 py-1 text-[10px] font-semibold text-[#607269]">{{ statusLabels[application.status] }}</span>
                    </div>
                    <span class="mt-3 inline-flex text-[11px] font-semibold text-[#47785a] group-hover:underline">Tinjau pengajuan →</span>
                </Link>
            </li>
        </ul>
        <p v-else class="rounded-2xl border border-dashed border-[#d7e0d6] px-4 py-8 text-center text-sm text-[#748077]">Belum ada pengajuan untuk properti di organisasi ini.</p>
    </AuthLayout>
</template>
