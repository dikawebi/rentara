<script setup>
import { computed } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AuthLayout from '../../layouts/AuthLayout.vue';

const props = defineProps({
    organization: { type: Object, required: true },
    property: { type: Object, required: true },
    listing: { type: Object, required: true },
    unit_count: { type: Number, required: true },
    photo_count: { type: Number, required: true },
});

const page = usePage();
const form = useForm({});
const statusLabels = {
    draft: 'Draft',
    pending_review: 'Menunggu review admin',
    approved: 'Tayang di marketplace',
    rejected: 'Perlu diperbaiki',
    paused: 'Dijeda',
};
const statusStyles = {
    draft: 'bg-[#f0f3ef] text-[#647168]',
    pending_review: 'bg-amber-50 text-amber-800',
    approved: 'bg-emerald-50 text-emerald-800',
    rejected: 'bg-rose-50 text-rose-800',
    paused: 'bg-slate-100 text-slate-700',
};
const readyToSubmit = computed(() => props.unit_count > 0 && props.photo_count > 0);
const flashMessage = computed(() => ({
    'listing-submitted': 'Listing dikirim ke antrean review admin.',
    'listing-paused': 'Listing dijeda dan tidak lagi muncul di marketplace.',
}[page.props.flash?.status] ?? null));

function submitForReview() {
    form.post(`/organizations/${props.organization.id}/properties/${props.property.id}/listing/submit`, { preserveScroll: true });
}

function pauseListing() {
    if (window.confirm('Jeda listing? Listing akan disembunyikan dari marketplace.')) {
        router.post(`/organizations/${props.organization.id}/properties/${props.property.id}/listing/pause`, { preserveScroll: true });
    }
}
</script>

<template>
    <Head :title="`Listing ${property.name} — Rentara`" />
    <AuthLayout eyebrow="Marketplace Rentara" :title="property.name" description="Ajukan informasi properti untuk ditinjau moderator sebelum tampil kepada pencari hunian.">
        <div class="mb-6 flex items-start justify-between gap-4">
            <div class="min-w-0">
                <Link :href="`/organizations/${organization.id}/properties/${property.id}`" class="text-xs font-semibold text-[#47785a] hover:text-[#244d39]">← Kembali ke properti</Link>
                <h2 class="mt-3 text-2xl font-semibold tracking-[-0.05em] text-[#20372b]">Status listing</h2>
            </div>
            <span class="shrink-0 rounded-full px-3 py-1.5 text-[11px] font-semibold" :class="statusStyles[listing.status]">{{ statusLabels[listing.status] }}</span>
        </div>

        <div v-if="flashMessage" role="status" class="mb-5 rounded-xl bg-[#edf5ed] px-4 py-3 text-sm text-[#315e40]">
            {{ flashMessage }}
        </div>

        <div class="space-y-4 rounded-2xl border border-[#e3e9e1] bg-[#f7f9f5] p-5">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-[#718076]">Konten listing diambil dari detail properti</p>
                <p class="mt-2 text-sm font-semibold text-[#35483c]">{{ property.full_address }}</p>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-xl border border-[#e4e9e2] bg-white px-3 py-3">
                    <p class="text-[11px] text-[#818b83]">Unit tersedia</p>
                    <p class="mt-1 text-lg font-semibold text-[#35483c]">{{ unit_count }}</p>
                </div>
                <div class="rounded-xl border border-[#e4e9e2] bg-white px-3 py-3">
                    <p class="text-[11px] text-[#818b83]">Foto</p>
                    <p class="mt-1 text-lg font-semibold text-[#35483c]">{{ photo_count }}</p>
                </div>
            </div>
            <div v-if="listing.rejection_reason" class="rounded-xl border border-rose-100 bg-rose-50 px-4 py-3">
                <p class="text-xs font-semibold text-rose-800">Catatan moderator</p>
                <p class="mt-1 text-sm leading-5 text-rose-900">{{ listing.rejection_reason }}</p>
            </div>
            <ul class="space-y-2 text-xs leading-5 text-[#748077]">
                <li>• Listing baru hanya tayang setelah disetujui moderator.</li>
                <li>• Perubahan detail, unit, atau foto setelah pengajuan mengembalikan listing ke draft untuk diajukan ulang.</li>
                <li>• Foto tetap privat sampai listing disetujui.</li>
            </ul>
            <p v-if="form.errors.listing" class="rounded-lg bg-rose-50 px-3 py-2 text-xs text-rose-800">{{ form.errors.listing }}</p>

            <div v-if="listing.status === 'approved'" class="flex flex-col gap-3 sm:flex-row">
                <Link :href="`/listings/${listing.slug}`" class="flex min-h-11 flex-1 items-center justify-center rounded-xl bg-[#1c3d33] px-4 text-sm font-semibold text-white transition hover:bg-[#285744]">Lihat listing publik</Link>
                <button type="button" class="min-h-11 rounded-xl border border-[#dce4dc] px-4 text-sm font-semibold text-[#53645a] hover:bg-white" @click="pauseListing">Jeda listing</button>
            </div>
            <button v-else type="button" :disabled="form.processing || !readyToSubmit || listing.status === 'pending_review'" class="flex min-h-11 w-full items-center justify-center rounded-xl bg-[#1c3d33] px-4 text-sm font-semibold text-white transition hover:bg-[#285744] focus:outline-none focus:ring-2 focus:ring-[#337b63] focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50" @click="submitForReview">
                {{ form.processing ? 'Mengirim…' : listing.status === 'pending_review' ? 'Sedang menunggu review' : 'Ajukan untuk review admin' }}
            </button>
            <p v-if="!readyToSubmit && listing.status !== 'pending_review'" class="text-center text-xs text-[#7a857d]">Tambahkan setidaknya satu unit tersedia dan satu foto sebelum mengajukan.</p>
        </div>
    </AuthLayout>
</template>
