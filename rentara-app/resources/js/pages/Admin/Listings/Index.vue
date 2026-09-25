<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AuthLayout from '../../../layouts/AuthLayout.vue';

defineProps({ listings: { type: Object, required: true } });

const page = usePage();
const flashMessage = computed(() => ({
    'listing-approved': 'Listing disetujui dan diterbitkan.',
    'listing-rejected': 'Listing ditolak. Pemilik akan melihat catatan moderator.',
}[page.props.flash?.status] ?? null));
const typeLabels = { kos: 'Kos', kontrakan: 'Kontrakan' };

function formatDate(value) {
    if (!value) return '—';

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'Asia/Jakarta',
    }).format(new Date(value));
}
</script>

<template>
    <Head title="Review listing — Rentara Admin" />
    <AuthLayout eyebrow="Moderasi marketplace" title="Antrean review listing." description="Listing yang diajukan pemilik menunggu persetujuan moderator sebelum tampil kepada pencari hunian.">
        <div class="mb-6 flex items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-semibold tracking-[-0.05em] text-[#20372b]">Perlu ditinjau</h2>
                <p class="mt-1.5 text-sm text-[#768078]">{{ listings.total }} listing menunggu review</p>
            </div>
            <Link href="/dashboard" class="shrink-0 text-xs font-semibold text-[#47785a] hover:text-[#244d39]">Dashboard</Link>
        </div>

        <div v-if="flashMessage" role="status" class="mb-5 rounded-xl bg-[#edf5ed] px-4 py-3 text-sm text-[#315e40]">{{ flashMessage }}</div>

        <ul v-if="listings.data.length" class="space-y-3">
            <li v-for="listing in listings.data" :key="listing.slug">
                <Link :href="`/admin/listings/${listing.slug}`" class="group block rounded-2xl border border-[#e3e9e1] p-4 transition hover:border-[#b9cdbb] hover:bg-[#fbfcfa] focus:outline-none focus:ring-2 focus:ring-[#337b63]">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="truncate text-sm font-semibold text-[#35483c]">{{ listing.property.name }}</h3>
                                <span class="rounded-full bg-[#f0f4ee] px-2.5 py-1 text-[10px] font-semibold text-[#607269]">{{ typeLabels[listing.property.property_type] }}</span>
                            </div>
                            <p class="mt-1 text-xs text-[#7a857d]">{{ listing.organization.name }} · {{ listing.property.district ? `${listing.property.district}, ` : '' }}{{ listing.property.city }}</p>
                            <p class="mt-2 text-[11px] text-[#89928b]">Diajukan {{ formatDate(listing.submitted_at) }}</p>
                        </div>
                        <span class="mt-1 shrink-0 text-sm text-[#66816c] transition group-hover:translate-x-0.5" aria-hidden="true">→</span>
                    </div>
                </Link>
            </li>
        </ul>
        <p v-else class="rounded-2xl border border-dashed border-[#d7e0d6] px-4 py-8 text-center text-sm text-[#748077]">Tidak ada listing yang menunggu review.</p>

        <nav v-if="listings.links?.length > 3" class="mt-6 flex flex-wrap justify-center gap-2" aria-label="Pagination listing">
            <Link v-for="link in listings.links" :key="link.label" :href="link.url ?? '#'" :class="['rounded-lg px-3 py-2 text-xs font-semibold', link.active ? 'bg-[#1c3d33] text-white' : 'bg-[#f0f4ee] text-[#526157]', !link.url && 'pointer-events-none opacity-40']" v-html="link.label" />
        </nav>
    </AuthLayout>
</template>
