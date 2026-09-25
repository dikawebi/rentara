<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '../../layouts/AuthLayout.vue';

const props = defineProps({
    listings: { type: Object, required: true },
    filters: { type: Object, required: true },
});

const form = useForm({
    q: props.filters.q ?? '',
    property_type: props.filters.property_type ?? '',
    district: props.filters.district ?? '',
    min_price: props.filters.min_price ?? '',
    max_price: props.filters.max_price ?? '',
});

const typeLabels = { kos: 'Kos', kontrakan: 'Kontrakan' };

function search() {
    form.get('/listings', { preserveState: true, preserveScroll: true, replace: true });
}

function resetSearch() {
    form.reset();
    form.get('/listings', { preserveState: true, replace: true });
}

function formatPrice(amount) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(amount);
}
</script>

<template>
    <Head title="Cari kos & kontrakan di Jakarta — Rentara">
        <meta name="description" content="Temukan listing kos dan kontrakan di Jakarta yang sudah ditinjau sebelum tayang." />
    </Head>

    <AuthLayout eyebrow="Marketplace Rentara · Jakarta" title="Temukan hunianmu." description="Lihat kos dan kontrakan dengan detail yang jelas. Listing tampil setelah melewati review admin.">
        <div class="mb-6 flex items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-semibold tracking-[-0.05em] text-[#20372b]">Cari hunian</h2>
                <p class="mt-1.5 text-sm text-[#768078]">{{ listings.total }} listing tersedia</p>
            </div>
            <Link href="/" class="shrink-0 text-xs font-semibold text-[#47785a] hover:text-[#244d39]">Beranda</Link>
        </div>

        <form class="mb-7 space-y-3 rounded-2xl border border-[#e3e9e1] bg-[#f7f9f5] p-4" @submit.prevent="search">
            <div>
                <label for="listing-q" class="mb-1.5 block text-xs font-medium text-[#526157]">Kawasan atau kata kunci</label>
                <input id="listing-q" v-model="form.q" type="search" class="w-full rounded-lg border border-[#dce4dc] bg-white px-3 py-2.5 text-sm outline-none focus:border-[#58836a] focus:ring-4 focus:ring-[#548365]/10" placeholder="Jakarta Selatan, Tebet…" />
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label for="listing-type" class="mb-1.5 block text-xs font-medium text-[#526157]">Jenis</label>
                    <select id="listing-type" v-model="form.property_type" class="w-full rounded-lg border border-[#dce4dc] bg-white px-3 py-2.5 text-sm outline-none focus:border-[#58836a] focus:ring-4 focus:ring-[#548365]/10">
                        <option value="">Semua jenis</option>
                        <option value="kos">Kos</option>
                        <option value="kontrakan">Kontrakan</option>
                    </select>
                </div>
                <div>
                    <label for="listing-district" class="mb-1.5 block text-xs font-medium text-[#526157]">Kecamatan/area</label>
                    <input id="listing-district" v-model="form.district" type="text" class="w-full rounded-lg border border-[#dce4dc] bg-white px-3 py-2.5 text-sm outline-none focus:border-[#58836a] focus:ring-4 focus:ring-[#548365]/10" placeholder="Tebet" />
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="listing-min-price" class="mb-1.5 block text-xs font-medium text-[#526157]">Harga minimum/bulan</label>
                    <input id="listing-min-price" v-model="form.min_price" type="number" min="0" class="w-full rounded-lg border border-[#dce4dc] bg-white px-3 py-2.5 text-sm outline-none focus:border-[#58836a] focus:ring-4 focus:ring-[#548365]/10" placeholder="0" />
                </div>
                <div>
                    <label for="listing-max-price" class="mb-1.5 block text-xs font-medium text-[#526157]">Harga maksimum/bulan</label>
                    <input id="listing-max-price" v-model="form.max_price" type="number" min="0" class="w-full rounded-lg border border-[#dce4dc] bg-white px-3 py-2.5 text-sm outline-none focus:border-[#58836a] focus:ring-4 focus:ring-[#548365]/10" placeholder="Tanpa batas" />
                </div>
            </div>
            <div class="flex gap-2">
                <button type="submit" :disabled="form.processing" class="min-h-10 flex-1 rounded-lg bg-[#1c3d33] px-4 text-xs font-semibold text-white disabled:opacity-60">Cari listing</button>
                <button type="button" class="min-h-10 rounded-lg border border-[#dce4dc] px-4 text-xs font-semibold text-[#53645a]" @click="resetSearch">Reset</button>
            </div>
        </form>

        <div v-if="listings.data.length" class="space-y-4">
            <Link v-for="listing in listings.data" :key="listing.slug" :href="`/listings/${listing.slug}`" class="group block overflow-hidden rounded-2xl border border-[#e5eae3] transition hover:border-[#b9cdbb] hover:shadow-md hover:shadow-[#20382b]/[0.05]">
                <img v-if="listing.thumbnail_url" :src="listing.thumbnail_url" :alt="`${listing.name} di ${listing.district ?? listing.city}`" loading="lazy" class="aspect-[16/8] w-full bg-[#eef1eb] object-cover" />
                <div v-else class="grid aspect-[16/8] w-full place-items-center bg-gradient-to-br from-[#dce8df] to-[#eff1e7] text-3xl text-[#77917d]" aria-hidden="true">⌂</div>
                <div class="p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="truncate text-sm font-semibold text-[#35483c]">{{ listing.name }}</h3>
                                <span class="rounded-full bg-[#f0f4ee] px-2.5 py-1 text-[10px] font-semibold text-[#607269]">{{ typeLabels[listing.property_type] }}</span>
                            </div>
                            <p class="mt-1.5 text-xs text-[#7a857d]">{{ listing.district ? `${listing.district}, ` : '' }}{{ listing.city }}</p>
                        </div>
                        <p class="shrink-0 text-sm font-semibold text-[#42664e]">{{ formatPrice(listing.starting_price) }}<span class="text-[10px] font-normal text-[#89928b]"> / bln</span></p>
                    </div>
                    <p class="mt-3 line-clamp-2 text-xs leading-5 text-[#78837b]">{{ listing.description || listing.full_address }}</p>
                    <p class="mt-3 text-[11px] font-medium text-[#738078]">{{ listing.available_units }} unit tersedia <span class="float-right font-semibold text-[#47785a] group-hover:underline">Lihat detail →</span></p>
                </div>
            </Link>
        </div>
        <p v-else class="rounded-2xl border border-dashed border-[#d7e0d6] px-4 py-8 text-center text-sm leading-6 text-[#748077]">Belum ada listing yang cocok. Coba ubah filter atau kunjungi lagi nanti.</p>

        <nav v-if="listings.links?.length > 3" class="mt-6 flex flex-wrap justify-center gap-2" aria-label="Pagination listing">
            <Link v-for="link in listings.links" :key="link.label" :href="link.url ?? '#'" :class="['rounded-lg px-3 py-2 text-xs font-semibold', link.active ? 'bg-[#1c3d33] text-white' : 'bg-[#f0f4ee] text-[#526157]', !link.url && 'pointer-events-none opacity-40']" v-html="link.label" />
        </nav>
    </AuthLayout>
</template>
