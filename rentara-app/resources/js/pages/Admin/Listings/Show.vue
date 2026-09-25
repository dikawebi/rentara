<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '../../../layouts/AuthLayout.vue';

const props = defineProps({
    listing: { type: Object, required: true },
    organization: { type: Object, required: true },
    property: { type: Object, required: true },
    units: { type: Array, required: true },
    photos: { type: Array, required: true },
});

const rejectForm = useForm({ review_notes: '' });
const approveForm = useForm({});
const typeLabels = { kos: 'Kos', kontrakan: 'Kontrakan' };
const statusLabels = { available: 'Tersedia', unavailable: 'Tidak tersedia' };
const hasAvailableUnit = computed(() => props.units.some((unit) => unit.status === 'available'));
const submittedAt = props.listing.submitted_at
    ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeZone: 'Asia/Jakarta' }).format(new Date(props.listing.submitted_at))
    : '—';

function approve() {
    if (window.confirm(`Setujui dan terbitkan listing ${props.property.name}?`)) {
        approveForm.post(`/admin/listings/${props.listing.slug}/approve`, { preserveScroll: true });
    }
}

function reject() {
    rejectForm.post(`/admin/listings/${props.listing.slug}/reject`, { preserveScroll: true });
}

function formatPrice(amount) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(amount);
}
</script>

<template>
    <Head :title="`Review ${property.name} — Rentara Admin`" />
    <AuthLayout eyebrow="Moderasi listing" :title="property.name" description="Tinjau alamat, unit, harga, fasilitas dan foto sebelum listing diterbitkan.">
        <div class="mb-6 flex items-start justify-between gap-4">
            <div class="min-w-0">
                <Link href="/admin/listings" class="text-xs font-semibold text-[#47785a] hover:text-[#244d39]">← Kembali ke antrean</Link>
                <p class="mt-3 text-xs text-[#7a857d]">{{ organization.name }} · diajukan {{ submittedAt }}</p>
            </div>
            <span class="shrink-0 rounded-full bg-amber-50 px-3 py-1.5 text-[11px] font-semibold text-amber-800">Menunggu review</span>
        </div>

        <section class="space-y-5">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-lg font-semibold text-[#35483c]">{{ property.name }}</h2>
                    <span class="rounded-full bg-[#f0f4ee] px-2.5 py-1 text-[10px] font-semibold text-[#607269]">{{ typeLabels[property.property_type] }}</span>
                </div>
                <p class="mt-2 text-sm leading-6 text-[#58675e]">{{ property.full_address }}</p>
                <p class="mt-1 text-xs text-[#7a857d]">{{ property.district ? `${property.district}, ` : '' }}{{ property.city }}</p>
                <p v-if="property.description" class="mt-3 whitespace-pre-line text-sm leading-6 text-[#6c786f]">{{ property.description }}</p>
            </div>

            <div class="border-t border-[#e8ece6] pt-4">
                <h3 class="mb-3 text-sm font-semibold text-[#35483c]">Unit dan harga</h3>
                <ul class="space-y-2">
                    <li v-for="unit in units" :key="unit.id" class="flex items-center justify-between gap-3 rounded-xl border border-[#e5eae3] px-3.5 py-3">
                        <div>
                            <p class="text-sm font-semibold text-[#35483c]">{{ unit.name }}</p>
                            <p class="mt-0.5 text-xs text-[#7a857d]">Kapasitas {{ unit.capacity }} orang · {{ statusLabels[unit.status] }}</p>
                        </div>
                        <p class="shrink-0 text-sm font-semibold text-[#42664e]">{{ formatPrice(unit.monthly_price) }}<span class="text-[10px] font-normal text-[#89928b]"> / bln</span></p>
                    </li>
                </ul>
            </div>

            <div class="border-t border-[#e8ece6] pt-4">
                <h3 class="mb-3 text-sm font-semibold text-[#35483c]">Foto ({{ photos.length }})</h3>
                <div v-if="photos.length" class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <img v-for="photo in photos" :key="photo.id" :src="photo.thumbnail_url" :alt="photo.alt_text" class="aspect-[4/3] w-full rounded-xl border border-[#e5eae3] object-cover" />
                </div>
                <p v-else class="rounded-xl bg-rose-50 px-4 py-3 text-xs text-rose-800">Belum ada foto. Listing tidak dapat disetujui tanpa foto.</p>
            </div>

            <div class="border-t border-[#e8ece6] pt-5">
                <form class="space-y-3 rounded-2xl border border-rose-100 bg-rose-50/50 p-4" @submit.prevent="reject">
                    <div>
                        <label for="review-notes" class="block text-sm font-semibold text-[#5f3a3a]">Tolak dengan catatan</label>
                        <p class="mt-1 text-xs leading-5 text-[#886c6c]">Berikan alasan yang dapat ditindaklanjuti pemilik (minimal 10 karakter).</p>
                    </div>
                    <textarea id="review-notes" v-model="rejectForm.review_notes" rows="3" minlength="10" maxlength="1000" required class="w-full resize-y rounded-xl border border-rose-200 bg-white px-3.5 py-3 text-sm outline-none focus:border-rose-400 focus:ring-4 focus:ring-rose-200/50"></textarea>
                    <p v-if="rejectForm.errors.review_notes" class="text-xs text-rose-800">{{ rejectForm.errors.review_notes }}</p>
                    <div v-if="rejectForm.errors.listing" class="text-xs text-rose-800">{{ rejectForm.errors.listing }}</div>
                    <button type="submit" :disabled="rejectForm.processing" class="min-h-10 rounded-xl border border-rose-200 bg-white px-4 text-xs font-semibold text-rose-800 transition hover:bg-rose-50 disabled:opacity-50">{{ rejectForm.processing ? 'Mengirim…' : 'Tolak listing' }}</button>
                </form>

                <div v-if="listing.rejection_reason" class="mt-3 rounded-xl bg-rose-50 px-4 py-3 text-xs leading-5 text-rose-800">
                    Catatan penolakan terakhir: {{ listing.rejection_reason }}
                </div>
                <p v-if="approveForm.errors.listing" class="mt-3 text-xs text-rose-800">{{ approveForm.errors.listing }}</p>

                <button type="button" :disabled="!hasAvailableUnit || !photos.length || approveForm.processing" class="mt-3 flex min-h-11 w-full items-center justify-center rounded-xl bg-[#1c3d33] px-4 text-sm font-semibold text-white transition hover:bg-[#285744] focus:outline-none focus:ring-2 focus:ring-[#337b63] focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50" @click="approve">
                    {{ approveForm.processing ? 'Memproses…' : 'Setujui dan terbitkan' }}
                </button>
                <p v-if="!hasAvailableUnit" class="mt-2 text-center text-xs text-rose-700">Tambahkan unit berstatus tersedia sebelum menyetujui.</p>
            </div>
        </section>
    </AuthLayout>
</template>
