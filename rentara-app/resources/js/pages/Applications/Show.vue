<script setup>
import { computed, reactive } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '../../layouts/AuthLayout.vue';

const props = defineProps({ application: { type: Object, required: true } });
const documentForms = reactive(Object.fromEntries(props.application.required_documents.map((document) => [
    document.value,
    useForm({ document_type: document.value, document: null }),
])));
const selectedFiles = reactive({});
const fileInputs = {};

const statusLabels = {
    submitted: 'Dikirim',
    under_review: 'Sedang ditinjau',
    info_requested: 'Perlu informasi',
    rejected: 'Ditolak',
    approved: 'Disetujui',
    verified: 'Terverifikasi',
    expired: 'Kedaluwarsa',
};
const documentStatusLabels = { pending: 'Menunggu review', accepted: 'Diterima', rejected: 'Perlu unggah ulang' };
const invoiceFilter = reactive({ status: 'all' });
const invoiceForms = reactive(Object.fromEntries((props.application.tenancy?.invoices ?? []).map((invoice) => [invoice.id, useForm({ evidence: null })])));
const filteredInvoices = computed(() => (props.application.tenancy?.invoices ?? []).filter((invoice) => invoiceFilter.status === 'all' || invoice.status === invoiceFilter.status));

function formatDate(value) {
    return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeZone: 'Asia/Jakarta' }).format(new Date(value));
}

function formatPrice(amount) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(amount);
}

function documentFor(type) {
    return props.application.identity_documents.find((document) => document.document_type === type) ?? null;
}

function updateSelectedFile(type, event) {
    const file = event.target.files?.[0] ?? null;
    selectedFiles[type] = file;
    documentForms[type].document = file;
}

function setFileInput(type, element) {
    fileInputs[type] = element;
}

function uploadDocument(type) {
    const form = documentForms[type];

    form.post(`/my-applications/${props.application.id}/identity-documents`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset('document');
            selectedFiles[type] = null;
            if (fileInputs[type]) {
                fileInputs[type].value = '';
            }
        },
    });
}

function uploadEvidence(invoice, event) {
    invoiceForms[invoice.id].evidence = event.target.files?.[0] ?? null;
    if (invoiceForms[invoice.id].evidence) {
        invoiceForms[invoice.id].post(invoice.payment_evidence_upload_url, { forceFormData: true, preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Detail pengajuan — Rentara" />
    <AuthLayout eyebrow="Riwayat pengajuan" title="Detail pengajuan sewamu." description="Informasi pengajuan ini hanya dapat dilihat dari akun yang mengirimnya.">
        <div class="mb-6 flex items-start justify-between gap-3">
            <div>
                <Link href="/my-applications" class="text-xs font-semibold text-[#47785a] hover:text-[#244d39]">← Pengajuan saya</Link>
                <h2 class="mt-3 text-2xl font-semibold tracking-[-0.05em] text-[#20372b]">{{ application.listing_snapshot.property_name }}</h2>
                <p class="mt-1.5 text-xs text-[#768078]">{{ application.listing_snapshot.unit_name }} · {{ formatDate(application.created_at) }}</p>
            </div>
            <span class="shrink-0 rounded-full bg-[#f0f4ee] px-2.5 py-1 text-[10px] font-semibold text-[#607269]">{{ statusLabels[application.status] }}</span>
        </div>

        <div class="space-y-4 rounded-2xl border border-[#e3e9e1] bg-[#f7f9f5] p-4">
            <div>
                <p class="text-[11px] text-[#879088]">Alamat listing saat diajukan</p>
                <p class="mt-1 text-sm leading-5 text-[#35483c]">{{ application.listing_snapshot.full_address }}</p>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-xl border border-[#e4e9e2] bg-white px-3 py-3">
                    <p class="text-[11px] text-[#818b83]">Tanggal masuk</p>
                    <p class="mt-1 text-sm font-semibold text-[#35483c]">{{ formatDate(application.requested_move_in) }}</p>
                </div>
                <div class="rounded-xl border border-[#e4e9e2] bg-white px-3 py-3">
                    <p class="text-[11px] text-[#818b83]">Durasi</p>
                    <p class="mt-1 text-sm font-semibold text-[#35483c]">{{ application.requested_duration_months }} bulan</p>
                </div>
            </div>
            <div class="rounded-xl border border-[#e4e9e2] bg-white px-3 py-3">
                <p class="text-[11px] text-[#818b83]">Harga unit saat diajukan</p>
                <p class="mt-1 text-sm font-semibold text-[#42664e]">{{ formatPrice(application.listing_snapshot.monthly_price) }} / bulan</p>
            </div>
            <div v-if="application.applicant_note">
                <p class="text-[11px] text-[#879088]">Pesanmu kepada pemilik</p>
                <p class="mt-1 whitespace-pre-line text-sm leading-5 text-[#35483c]">{{ application.applicant_note }}</p>
            </div>
            <p class="text-[10px] text-[#89928b]">Persetujuan privasi versi {{ application.privacy_notice_version }}</p>
        </div>

        <div v-if="application.decision_notes" class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3">
            <p class="text-[11px] font-semibold text-amber-900">Pesan dari pemilik/pengelola</p>
            <p class="mt-1 whitespace-pre-line text-sm leading-5 text-amber-900">{{ application.decision_notes }}</p>
        </div>

        <section v-if="application.agreement" class="mt-7 rounded-2xl border border-[#e3e9e1] p-4">
            <h3 class="text-sm font-semibold text-[#35483c]">Perjanjian sewa · versi {{ application.agreement.version }}</h3>
            <dl class="mt-3 space-y-2 text-xs text-[#58675e]"><div v-for="(value, key) in application.agreement.terms_snapshot" :key="key" class="flex justify-between gap-3"><dt>{{ key }}</dt><dd class="font-medium text-right">{{ value }}</dd></div></dl>
            <p class="mt-3 text-xs text-[#748077]">Persetujuan pemilik: {{ application.agreement.organization_approved_at ? 'Sudah' : 'Menunggu' }} · Persetujuanmu: {{ application.agreement.applicant_approved_at ? 'Sudah' : 'Menunggu' }}</p>
            <a :href="application.agreement.download_url" class="mt-3 inline-flex text-xs font-semibold text-[#47785a] hover:underline">Unduh kontrak PDF</a>
            <form v-if="application.status === 'approved' && !application.agreement.applicant_approved_at" class="mt-4" @submit.prevent="useForm({}).post(`/my-applications/${application.id}/agreement/approve`, { preserveScroll: true })"><button class="min-h-9 rounded-lg bg-[#1c3d33] px-3.5 text-xs font-semibold text-white">Setujui perjanjian</button></form>
        </section>

        <section v-if="application.booking" class="mt-4 rounded-2xl border border-[#e3e9e1] bg-[#f7f9f5] p-4">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-sm font-semibold text-[#35483c]">Booking</h3>
                <span class="rounded-full bg-[#f0f4ee] px-2.5 py-1 text-[10px] font-semibold text-[#607269]">{{ application.booking.status === 'completed' ? 'Selesai' : 'Menunggu pembayaran' }}</span>
            </div>
            <p class="mt-2 text-xs text-[#748077]">{{ formatDate(application.booking.start_date) }} sampai {{ formatDate(application.booking.end_date) }}.</p>
            <p class="mt-1 text-xs text-[#748077]">Pembayaran dilakukan di luar platform dan dikonfirmasi oleh pemilik/pengelola.</p>
            <p v-if="application.booking.confirmed_at" class="mt-2 text-xs text-[#315e40]">Pembayaran sudah dikonfirmasi.</p>
        </section>
         <section v-if="application.tenancy" class="mt-4 rounded-2xl border border-[#cfe2d2] bg-[#f4faf3] p-4">
            <h3 class="text-sm font-semibold text-[#35483c]">Tenancy kamu</h3>
            <p class="mt-2 text-xs text-[#748077]">{{ formatDate(application.tenancy.start_date) }} sampai {{ formatDate(application.tenancy.end_date) }}.</p>
             <p class="mt-1 text-xs text-[#315e40]">{{ formatPrice(application.tenancy.monthly_rent) }} / bulan · Status aktif</p>
              <div v-if="application.tenancy.invoices.length" class="mt-4 border-t border-[#d9e9d9] pt-3">
                  <div class="flex items-center justify-between gap-3"><h4 class="text-xs font-semibold text-[#35483c]">Tagihan</h4><select v-model="invoiceFilter.status" class="rounded-lg border border-[#dce4dc] bg-white px-2 py-1 text-[10px]"><option value="all">Semua</option><option value="unpaid">Belum dibayar</option><option value="paid">Lunas</option></select></div>
                  <ul class="mt-2 space-y-2">
                      <li v-for="invoice in filteredInvoices" :key="invoice.id" class="rounded-lg bg-white px-3 py-3 text-xs">
                          <div class="flex items-center justify-between gap-3"><span>{{ invoice.type === 'rent' ? 'Sewa' : invoice.type === 'deposit' ? 'Deposit' : 'Lainnya' }} · jatuh tempo {{ formatDate(invoice.due_date) }}</span><span class="shrink-0 font-semibold" :class="invoice.status === 'paid' ? 'text-[#315e40]' : 'text-amber-700'">{{ formatPrice(invoice.amount) }} · {{ invoice.status === 'paid' ? 'Lunas' : invoice.status === 'void' ? 'Void' : 'Belum dibayar' }}</span></div>
                          <p v-if="invoice.payment_evidence_name" class="mt-2 text-[#315e40]">Bukti pembayaran: {{ invoice.payment_evidence_name }}</p>
                          <form v-if="invoice.status === 'unpaid' && !invoice.payment_evidence_name" class="mt-3" @submit.prevent>
                              <input type="file" accept="image/jpeg,image/png,application/pdf" required class="block w-full text-[10px]" @change="uploadEvidence(invoice, $event)" />
                              <p class="mt-1 text-[10px] text-[#89928b]">JPEG, PNG atau PDF · maksimal 5 MB. Pembayaran dilakukan di luar platform.</p>
                              <p v-if="invoiceForms[invoice.id].errors.evidence" class="mt-1 text-xs text-rose-700">{{ invoiceForms[invoice.id].errors.evidence }}</p>
                          </form>
                      </li>
                  </ul>
              </div>
         </section>

        <section class="mt-7" aria-labelledby="identity-documents-heading">
            <div class="mb-3">
                <h3 id="identity-documents-heading" class="text-sm font-semibold text-[#35483c]">Dokumen identitas</h3>
                <p class="mt-1 text-xs leading-5 text-[#7a857d]">Dokumen disimpan privat dan hanya dapat diakses pemilik/pengelola organisasi listing.</p>
            </div>

            <p v-if="!application.required_documents.length" class="rounded-xl border border-[#e3e9e1] bg-[#f7f9f5] px-4 py-3 text-xs text-[#748077]">Pemilik tidak meminta dokumen identitas untuk pengajuan ini.</p>

            <ul v-else class="space-y-3">
                <li v-for="required in application.required_documents" :key="required.value" class="rounded-2xl border border-[#e5eae3] p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h4 class="text-sm font-semibold text-[#35483c]">{{ required.label }}</h4>
                        <span v-if="documentFor(required.value)" class="rounded-full bg-[#f0f4ee] px-2.5 py-1 text-[10px] font-semibold text-[#607269]">{{ documentStatusLabels[documentFor(required.value).review_status] }}</span>
                        <span v-else class="text-[10px] text-[#89928b]">Belum diunggah</span>
                    </div>

                    <div v-if="documentFor(required.value)" class="mt-2">
                        <p v-if="documentFor(required.value).review_notes" class="rounded-lg bg-rose-50 px-3 py-2 text-xs leading-5 text-rose-800">{{ documentFor(required.value).review_notes }}</p>
                        <a :href="documentFor(required.value).download_url" class="mt-2 inline-flex text-xs font-semibold text-[#47785a] hover:underline">Unduh dokumen saya</a>
                    </div>

                    <form v-if="!['rejected', 'expired'].includes(application.status) && (!documentFor(required.value) || documentFor(required.value).review_status === 'rejected')" class="mt-3 space-y-2" @submit.prevent="uploadDocument(required.value)">
                        <input :ref="(element) => setFileInput(required.value, element)" type="file" accept="image/jpeg,image/png,application/pdf" required class="block w-full text-xs text-[#647069] file:mr-3 file:rounded-lg file:border-0 file:bg-[#f0f4ee] file:px-3 file:py-2 file:text-xs file:font-semibold file:text-[#42664e]" @change="updateSelectedFile(required.value, $event)" />
                        <p class="text-[10px] leading-4 text-[#89928b]">JPEG, PNG atau PDF · maksimal 5 MB. Foto akan diorientasi dan disimpan tanpa metadata EXIF.</p>
                        <p v-if="documentForms[required.value].errors.document" class="text-xs text-rose-700">{{ documentForms[required.value].errors.document }}</p>
                        <p v-if="documentForms[required.value].errors.document_type" class="text-xs text-rose-700">{{ documentForms[required.value].errors.document_type }}</p>
                        <button type="submit" :disabled="documentForms[required.value].processing || !selectedFiles[required.value]" class="min-h-9 rounded-lg bg-[#1c3d33] px-3.5 text-xs font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50">
                            {{ documentForms[required.value].processing ? 'Mengunggah…' : documentFor(required.value) ? 'Unggah pengganti' : 'Unggah dokumen' }}
                        </button>
                    </form>
                </li>
            </ul>
        </section>

        <Link :href="`/listings/${application.listing_snapshot.listing_slug}`" class="mt-5 inline-flex text-xs font-semibold text-[#47785a] hover:text-[#244d39]">Kembali ke listing</Link>
    </AuthLayout>
</template>
