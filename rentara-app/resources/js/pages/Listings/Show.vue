<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthLayout from '../../layouts/AuthLayout.vue';

defineProps({
    listing: { type: Object, required: true },
    property: { type: Object, required: true },
    units: { type: Array, required: true },
    photos: { type: Array, required: true },
});

const typeLabels = { kos: 'Kos', kontrakan: 'Kontrakan' };

function formatPrice(amount) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(amount);
}
</script>

<template>
    <Head :title="`${property.name} — Rentara`">
        <meta name="description" :content="property.description || property.full_address" />
    </Head>
    <AuthLayout eyebrow="Listing ditinjau admin" :title="property.name" description="Detail properti yang telah ditinjau sebelum ditampilkan di marketplace Rentara.">
        <div class="mb-5 flex items-start justify-between gap-4">
            <div>
                <Link href="/listings" class="text-xs font-semibold text-[#47785a] hover:text-[#244d39]">← Kembali ke pencarian</Link>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <h2 class="text-2xl font-semibold tracking-[-0.05em] text-[#20372b]">{{ property.name }}</h2>
                    <span class="rounded-full bg-[#f0f4ee] px-2.5 py-1 text-[10px] font-semibold text-[#607269]">{{ typeLabels[property.property_type] }}</span>
                </div>
            </div>
        </div>

        <div v-if="photos.length" class="mb-6 grid grid-cols-2 gap-2 overflow-hidden rounded-2xl sm:grid-cols-3">
            <img v-for="photo in photos" :key="photo.id" :src="photo.url" :alt="photo.alt_text" loading="lazy" class="aspect-[4/3] w-full bg-[#eef1eb] object-cover" />
        </div>

        <section class="space-y-5">
            <div>
                <h3 class="text-sm font-semibold text-[#35483c]">Lokasi</h3>
                <p class="mt-1.5 text-sm leading-6 text-[#647069]">{{ property.full_address }}</p>
                <p class="mt-1 text-xs text-[#7a857d]">{{ property.district ? `${property.district}, ` : '' }}{{ property.city }}</p>
            </div>

            <div v-if="property.description">
                <h3 class="text-sm font-semibold text-[#35483c]">Tentang properti</h3>
                <p class="mt-1.5 whitespace-pre-line text-sm leading-6 text-[#647069]">{{ property.description }}</p>
            </div>

            <div>
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-[#35483c]">Unit tersedia</h3>
                    <span class="text-xs text-[#879088]">{{ units.length }} unit</span>
                </div>
                <ul class="space-y-2">
                    <li v-for="unit in units" :key="unit.name" class="flex items-center justify-between gap-4 rounded-xl border border-[#e5eae3] px-4 py-3">
                        <div>
                            <p class="text-sm font-semibold text-[#35483c]">{{ unit.name }}</p>
                            <p class="mt-0.5 text-xs text-[#7a857d]">Maksimal {{ unit.capacity }} orang</p>
                        </div>
                        <p class="shrink-0 text-sm font-semibold text-[#42664e]">{{ formatPrice(unit.monthly_price) }}<span class="text-[10px] font-normal text-[#89928b]"> / bln</span></p>
                    </li>
                </ul>
            </div>

            <div class="rounded-2xl border border-[#e3e9e1] bg-[#f7f9f5] p-4">
                <p class="text-sm font-semibold text-[#35483c]">Tertarik dengan hunian ini?</p>
                <p class="mt-1 text-xs leading-5 text-[#748077]">Ajukan minat untuk unit yang tersedia dan ikuti status permohonanmu.</p>
                <Link :href="`/listings/${listing.slug}/apply`" class="mt-3 inline-flex min-h-10 items-center rounded-lg bg-[#1c3d33] px-4 text-xs font-semibold text-white transition hover:bg-[#285744]">Ajukan sewa</Link>
            </div>
        </section>
    </AuthLayout>
</template>
