<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '../../layouts/AuthLayout.vue';

const props = defineProps({
    listing: { type: Object, required: true },
    units: { type: Array, required: true },
    privacyNoticeVersion: { type: String, required: true },
});

const earliestMoveInDate = new Intl.DateTimeFormat('en-CA', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    timeZone: 'Asia/Jakarta',
}).format(new Date());

const form = useForm({
    unit_id: props.units[0]?.id ?? '',
    requested_move_in: '',
    requested_duration_months: 12,
    applicant_note: '',
    privacy_consent: false,
});

function submit() {
    form.post(`/listings/${props.listing.slug}/applications`);
}

function formatPrice(amount) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(amount);
}
</script>

<template>
    <Head :title="`Ajukan sewa — ${listing.name}`" />
    <AuthLayout eyebrow="Pengajuan sewa" title="Kirim pengajuan hunian." :description="listing.name">
        <div class="mb-6 flex items-start justify-between gap-3">
            <div>
                <h2 class="text-2xl font-semibold tracking-[-0.05em] text-[#20372b]">Detail pengajuan</h2>
                <p class="mt-1.5 text-sm leading-5 text-[#768078]">{{ listing.full_address }}</p>
            </div>
            <Link :href="`/listings/${listing.slug}`" class="shrink-0 text-xs font-semibold text-[#47785a] hover:text-[#244d39]">Lihat listing</Link>
        </div>

        <form class="space-y-5" @submit.prevent="submit">
            <div>
                <label for="unit-id" class="mb-2 block text-sm font-medium text-[#35483c]">Pilih unit</label>
                <select id="unit-id" v-model.number="form.unit_id" required class="w-full rounded-xl border border-[#dce4dc] bg-white px-4 py-3 text-sm text-[#20372b] outline-none transition focus:border-[#58836a] focus:ring-4 focus:ring-[#548365]/10">
                    <option v-for="unit in units" :key="unit.id" :value="unit.id">{{ unit.name }} · maks. {{ unit.capacity }} orang · {{ formatPrice(unit.monthly_price) }}/bulan</option>
                </select>
                <p v-if="form.errors.unit_id" class="mt-1.5 text-xs text-rose-700">{{ form.errors.unit_id }}</p>
            </div>

            <div>
                <label for="requested-move-in" class="mb-2 block text-sm font-medium text-[#35483c]">Rencana tanggal masuk</label>
                <input id="requested-move-in" v-model="form.requested_move_in" type="date" name="requested_move_in" :min="earliestMoveInDate" required class="w-full rounded-xl border border-[#dce4dc] bg-white px-4 py-3 text-sm text-[#20372b] outline-none transition focus:border-[#58836a] focus:ring-4 focus:ring-[#548365]/10" />
                <p v-if="form.errors.requested_move_in" class="mt-1.5 text-xs text-rose-700">{{ form.errors.requested_move_in }}</p>
            </div>

            <div>
                <label for="duration" class="mb-2 block text-sm font-medium text-[#35483c]">Perkiraan durasi sewa</label>
                <div class="flex items-center gap-3">
                    <input id="duration" v-model.number="form.requested_duration_months" type="number" min="1" max="60" required class="w-28 rounded-xl border border-[#dce4dc] bg-white px-4 py-3 text-sm text-[#20372b] outline-none transition focus:border-[#58836a] focus:ring-4 focus:ring-[#548365]/10" />
                    <span class="text-sm text-[#768078]">bulan</span>
                </div>
                <p v-if="form.errors.requested_duration_months" class="mt-1.5 text-xs text-rose-700">{{ form.errors.requested_duration_months }}</p>
            </div>

            <div>
                <label for="applicant-note" class="mb-2 block text-sm font-medium text-[#35483c]">Pesan untuk pemilik <span class="font-normal text-[#89928b]">(opsional)</span></label>
                <textarea id="applicant-note" v-model="form.applicant_note" rows="3" maxlength="2000" class="w-full resize-y rounded-xl border border-[#dce4dc] bg-white px-4 py-3 text-sm text-[#20372b] outline-none transition placeholder:text-[#a2aaa3] focus:border-[#58836a] focus:ring-4 focus:ring-[#548365]/10" placeholder="Perkenalkan diri atau tulis pertanyaan singkat."></textarea>
                <p v-if="form.errors.applicant_note" class="mt-1.5 text-xs text-rose-700">{{ form.errors.applicant_note }}</p>
            </div>

            <div>
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-[#e3e9e1] bg-[#f7f9f5] p-3.5">
                    <input v-model="form.privacy_consent" type="checkbox" name="privacy_consent" class="mt-0.5 size-4 shrink-0 rounded border-[#cbd5cb] text-[#326447] focus:ring-[#548365]" />
                    <span class="text-xs leading-5 text-[#59685f]">Saya setuju data akun dan informasi yang saya kirim digunakan untuk memproses pengajuan ini dan dibagikan kepada pemilik/pengelola listing.</span>
                </label>
                <p class="mt-1.5 text-[10px] text-[#89928b]">Versi pemberitahuan privasi: {{ privacyNoticeVersion }}</p>
                <p v-if="form.errors.privacy_consent" class="mt-1.5 text-xs text-rose-700">{{ form.errors.privacy_consent }}</p>
            </div>

            <p v-if="form.errors.listing" class="rounded-lg bg-rose-50 px-3 py-2 text-xs text-rose-800">{{ form.errors.listing }}</p>
            <button type="submit" :disabled="form.processing" class="flex min-h-12 w-full items-center justify-center rounded-xl bg-[#1c3d33] px-5 text-sm font-semibold text-white transition hover:bg-[#285744] focus:outline-none focus:ring-2 focus:ring-[#337b63] focus:ring-offset-2 disabled:cursor-wait disabled:opacity-60">
                {{ form.processing ? 'Mengirim pengajuan…' : 'Kirim pengajuan' }}
            </button>
            <p class="text-center text-[11px] leading-5 text-[#89928b]">Pengajuan aktif lain untuk unit yang sama tidak dapat dibuat sampai statusnya selesai.</p>
        </form>
    </AuthLayout>
</template>
