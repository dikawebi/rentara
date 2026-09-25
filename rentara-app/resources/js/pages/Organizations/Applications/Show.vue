<script setup>
import { computed, reactive } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AuthLayout from '../../../layouts/AuthLayout.vue';

const props = defineProps({
    organization: { type: Object, required: true },
    application: { type: Object, required: true },
});

const page = usePage();
const statusLabels = { pending: 'Menunggu review', accepted: 'Diterima', rejected: 'Perlu dokumen ulang' };
const canReviewDocuments = ['submitted', 'under_review', 'info_requested', 'approved'].includes(props.application.status);
const applicationStatusLabels = {
    submitted: 'Dikirim',
    under_review: 'Sedang ditinjau',
    info_requested: 'Perlu informasi',
    rejected: 'Ditolak',
    approved: 'Disetujui',
    verified: 'Terverifikasi',
};
const canDecide = ['submitted', 'under_review', 'info_requested'].includes(props.application.status);
const decisionForm = useForm({ status: 'under_review', notes: '' });
const agreementForm = useForm({ terms_snapshot: props.application.agreement ? JSON.stringify(props.application.agreement.terms_snapshot) : '{}', contract_file: null });
const paymentForm = useForm({ amount: props.application.booking?.payment_amount ?? '', reference: props.application.booking?.payment_reference ?? '', note: '' });
const invoiceForms = reactive(Object.fromEntries((props.application.tenancy?.invoices ?? []).map((invoice) => [invoice.id, useForm({ reference: '', note: '' })])));
const reversalForms = reactive(Object.fromEntries((props.application.tenancy?.invoices ?? []).map((invoice) => [invoice.id, useForm({ reason: '' })])));
const invoiceFilter = reactive({ status: 'all' });
const filteredInvoices = computed(() => (props.application.tenancy?.invoices ?? []).filter((invoice) => invoiceFilter.status === 'all' || invoice.status === invoiceFilter.status));
const reviewForms = reactive(Object.fromEntries(props.application.identity_documents.map((document) => [
    document.id,
    useForm({ review_status: 'accepted', review_notes: '' }),
])));
const missingDocuments = computed(() => props.application.required_documents.filter((required) => !props.application.identity_documents.some((document) => document.document_type === required.value)));

function reviewDocument(document) {
    reviewForms[document.id].patch(`/organizations/${props.organization.id}/applications/${props.application.id}/identity-documents/${document.id}/review`, {
        preserveScroll: true,
        onSuccess: () => reviewForms[document.id].reset('review_notes'),
    });
}

function updateApplicationStatus() {
    decisionForm.patch(`/organizations/${props.organization.id}/applications/${props.application.id}/status`, {
        preserveScroll: true,
        onSuccess: () => decisionForm.reset('notes'),
    });
}

function confirmPayment() {
    paymentForm.post(props.application.booking.confirm_url, { preserveScroll: true });
}

function markInvoicePaid(invoice) {
    invoiceForms[invoice.id].patch(invoice.mark_paid_url, { preserveScroll: true });
}

function reverseInvoice(invoice) {
    reversalForms[invoice.id].patch(invoice.reverse_url, { preserveScroll: true, onSuccess: () => reversalForms[invoice.id].reset('reason') });
}

function formatDate(value) {
    return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeZone: 'Asia/Jakarta' }).format(new Date(value));
}

function formatPrice(amount) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(amount);
}
</script>

<template>
    <Head title="Tinjau pengajuan penyewa — Rentara" />
    <AuthLayout eyebrow="Workspace pemilik" title="Tinjau pengajuan penyewa." description="Dokumen identitas hanya tersedia bagi pemilik/pengelola dengan akses ke organisasi ini.">
        <div class="mb-6">
            <Link :href="`/organizations/${organization.id}/applications`" class="text-xs font-semibold text-[#47785a] hover:text-[#244d39]">← Semua pengajuan</Link>
            <h2 class="mt-3 text-2xl font-semibold tracking-[-0.05em] text-[#20372b]">{{ application.applicant.name }}</h2>
            <p class="mt-1 text-sm text-[#768078]">{{ application.applicant.email }}</p>
        </div>

        <div v-if="page.props.flash?.status === 'identity-document-reviewed'" role="status" class="mb-5 rounded-xl bg-[#edf5ed] px-4 py-3 text-sm text-[#315e40]">
            Hasil review dokumen tersimpan.
        </div>
        <div v-if="page.props.flash?.status === 'application-status-updated'" role="status" class="mb-5 rounded-xl bg-[#edf5ed] px-4 py-3 text-sm text-[#315e40]">
            Status pengajuan diperbarui.
        </div>

        <section class="rounded-2xl border border-[#e3e9e1] bg-[#f7f9f5] p-4">
            <h3 class="text-sm font-semibold text-[#35483c]">Rencana sewa</h3>
            <p class="mt-2 text-sm text-[#58675e]">{{ application.listing_snapshot.property_name }} · {{ application.listing_snapshot.unit_name }}</p>
            <p class="mt-1 text-xs text-[#7a857d]">Masuk {{ formatDate(application.requested_move_in) }} · {{ application.requested_duration_months }} bulan</p>
            <p v-if="application.applicant_note" class="mt-3 whitespace-pre-line text-sm leading-5 text-[#58675e]">{{ application.applicant_note }}</p>
        </section>

        <section class="mt-5 rounded-2xl border border-[#e3e9e1] p-4">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-sm font-semibold text-[#35483c]">Keputusan pengajuan</h3>
                <span class="rounded-full bg-[#f0f4ee] px-2.5 py-1 text-[10px] font-semibold text-[#607269]">{{ applicationStatusLabels[application.status] }}</span>
            </div>
            <p v-if="application.decision_notes" class="mt-3 whitespace-pre-line rounded-lg bg-amber-50 px-3 py-2 text-xs leading-5 text-amber-900">Catatan terakhir: {{ application.decision_notes }}</p>
            <form v-if="canDecide" class="mt-4 space-y-3" @submit.prevent="updateApplicationStatus">
                <div>
                    <label for="application-status" class="mb-1.5 block text-xs font-medium text-[#526157]">Perbarui status</label>
                    <select id="application-status" v-model="decisionForm.status" class="w-full rounded-lg border border-[#dce4dc] bg-white px-3 py-2.5 text-sm outline-none focus:border-[#58836a] focus:ring-4 focus:ring-[#548365]/10">
                        <option value="under_review">Tandai sedang ditinjau</option>
                        <option value="info_requested">Minta informasi tambahan</option>
                        <option value="rejected">Tolak pengajuan</option>
                        <option value="approved">Setujui pengajuan</option>
                    </select>
                </div>
                <div v-if="['info_requested', 'rejected'].includes(decisionForm.status)">
                    <label for="application-notes" class="mb-1.5 block text-xs font-medium text-[#526157]">Catatan untuk pencari</label>
                    <textarea id="application-notes" v-model="decisionForm.notes" rows="3" minlength="10" maxlength="2000" required class="w-full resize-y rounded-lg border border-[#dce4dc] bg-white px-3 py-2.5 text-sm outline-none focus:border-[#58836a] focus:ring-4 focus:ring-[#548365]/10"></textarea>
                </div>
                <p v-if="decisionForm.errors.status" class="text-xs text-rose-700">{{ decisionForm.errors.status }}</p>
                <p v-if="decisionForm.errors.notes" class="text-xs text-rose-700">{{ decisionForm.errors.notes }}</p>
                <button type="submit" :disabled="decisionForm.processing" class="min-h-10 rounded-lg bg-[#1c3d33] px-4 text-xs font-semibold text-white disabled:opacity-60">{{ decisionForm.processing ? 'Menyimpan…' : 'Simpan keputusan' }}</button>
            </form>
            <p v-else class="mt-3 text-xs text-[#748077]">Pengajuan ini sudah berada pada status akhir.</p>
        </section>

        <section class="mt-7" aria-labelledby="identity-heading">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h3 id="identity-heading" class="text-sm font-semibold text-[#35483c]">Dokumen identitas</h3>
                <span class="text-[10px] text-[#89928b]">Retensi mengikuti status pengajuan/masa sewa</span>
            </div>

            <p v-if="missingDocuments.length" class="mb-3 rounded-xl bg-amber-50 px-4 py-3 text-xs leading-5 text-amber-900">
                Belum diunggah: {{ missingDocuments.map((document) => document.label).join(', ') }}.
            </p>

            <ul v-if="application.identity_documents.length" class="space-y-3">
                <li v-for="document in application.identity_documents" :key="document.id" class="rounded-2xl border border-[#e5eae3] p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h4 class="text-sm font-semibold text-[#35483c]">{{ document.document_label }}</h4>
                            <p class="mt-1 text-xs text-[#7a857d]">Diunggah {{ formatDate(document.uploaded_at) }}</p>
                        </div>
                        <span class="rounded-full bg-[#f0f4ee] px-2.5 py-1 text-[10px] font-semibold text-[#607269]">{{ statusLabels[document.review_status] }}</span>
                    </div>
                    <a :href="document.download_url" class="mt-3 inline-flex min-h-9 items-center rounded-lg border border-[#dce4dc] px-3 text-xs font-semibold text-[#456451] hover:bg-[#f7f9f5]">Unduh untuk ditinjau</a>
                    <p v-if="document.review_notes" class="mt-3 rounded-lg bg-rose-50 px-3 py-2 text-xs leading-5 text-rose-800">Catatan: {{ document.review_notes }}</p>

                    <form v-if="canReviewDocuments && document.review_status === 'pending'" class="mt-4 space-y-3 border-t border-[#edf0eb] pt-4" @submit.prevent="reviewDocument(document)">
                        <div>
                            <label :for="`review-status-${document.id}`" class="mb-1.5 block text-xs font-medium text-[#526157]">Hasil review</label>
                            <select :id="`review-status-${document.id}`" v-model="reviewForms[document.id].review_status" class="w-full rounded-lg border border-[#dce4dc] bg-white px-3 py-2.5 text-sm outline-none focus:border-[#58836a] focus:ring-4 focus:ring-[#548365]/10">
                                <option value="accepted">Terima dokumen</option>
                                <option value="rejected">Minta unggah ulang</option>
                            </select>
                        </div>
                        <div v-if="reviewForms[document.id].review_status === 'rejected'">
                            <label :for="`review-notes-${document.id}`" class="mb-1.5 block text-xs font-medium text-[#526157]">Alasan / instruksi perbaikan</label>
                            <textarea :id="`review-notes-${document.id}`" v-model="reviewForms[document.id].review_notes" rows="2" minlength="10" maxlength="1000" required class="w-full resize-y rounded-lg border border-[#dce4dc] bg-white px-3 py-2.5 text-sm outline-none focus:border-[#58836a] focus:ring-4 focus:ring-[#548365]/10"></textarea>
                        </div>
                        <p v-if="reviewForms[document.id].errors.review_status" class="text-xs text-rose-700">{{ reviewForms[document.id].errors.review_status }}</p>
                        <p v-if="reviewForms[document.id].errors.review_notes" class="text-xs text-rose-700">{{ reviewForms[document.id].errors.review_notes }}</p>
                        <button type="submit" :disabled="reviewForms[document.id].processing" class="min-h-10 rounded-lg bg-[#1c3d33] px-4 text-xs font-semibold text-white disabled:opacity-60">{{ reviewForms[document.id].processing ? 'Menyimpan…' : 'Simpan hasil review' }}</button>
                    </form>
                    <p v-else-if="document.reviewer_name" class="mt-3 text-[10px] text-[#89928b]">Ditinjau oleh {{ document.reviewer_name }}</p>
                </li>
            </ul>
            <p v-else class="rounded-2xl border border-dashed border-[#d7e0d6] px-4 py-6 text-center text-sm text-[#748077]">Belum ada dokumen yang diunggah pencari.</p>
        </section>

        <section class="mt-7 rounded-2xl border border-[#e3e9e1] p-4">
            <h3 class="text-sm font-semibold text-[#35483c]">Perjanjian sewa</h3>
            <form class="mt-3 space-y-3" enctype="multipart/form-data" @submit.prevent="agreementForm.post(`/organizations/${organization.id}/applications/${application.id}/agreement`, { forceFormData: true, preserveScroll: true })">
                <textarea v-model="agreementForm.terms_snapshot" rows="4" required class="w-full rounded-lg border border-[#dce4dc] px-3 py-2 text-sm" placeholder="JSON terms, misalnya {&quot;monthly_rent&quot;: 1800000}"></textarea>
                <input type="file" accept="application/pdf" class="block w-full text-xs" @change="agreementForm.contract_file = $event.target.files[0]" />
                <p v-if="agreementForm.errors.terms_snapshot || agreementForm.errors.contract_file" class="text-xs text-rose-700">{{ agreementForm.errors.terms_snapshot || agreementForm.errors.contract_file }}</p>
                <button type="submit" :disabled="agreementForm.processing" class="min-h-9 rounded-lg bg-[#1c3d33] px-3.5 text-xs font-semibold text-white">Simpan terms & kontrak</button>
            </form>
            <div v-if="application.agreement" class="mt-4 text-xs text-[#58675e]">
                Versi {{ application.agreement.version }} · Persetujuan organisasi: {{ application.agreement.organization_approved_at ? 'Sudah' : 'Menunggu' }} · Applicant: {{ application.agreement.applicant_approved_at ? 'Sudah' : 'Menunggu' }}
                <a :href="application.agreement.download_url" class="ml-2 font-semibold text-[#47785a]">Unduh PDF</a>
                <form v-if="application.status === 'approved' && !application.agreement.organization_approved_at" class="mt-3" @submit.prevent="useForm({}).post(`/organizations/${organization.id}/applications/${application.id}/agreement/approve`, { preserveScroll: true })"><button class="min-h-9 rounded-lg bg-[#1c3d33] px-3.5 text-xs font-semibold text-white">Setujui perjanjian</button></form>
            </div>
        </section>

        <section v-if="application.booking" class="mt-7 rounded-2xl border border-[#e3e9e1] p-4">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-sm font-semibold text-[#35483c]">Booking</h3>
                <span class="rounded-full bg-[#f0f4ee] px-2.5 py-1 text-[10px] font-semibold text-[#607269]">{{ application.booking.status === 'completed' ? 'Selesai' : 'Menunggu pembayaran' }}</span>
            </div>
            <p class="mt-2 text-xs text-[#748077]">Pembayaran dilakukan di luar platform dan dicatat manual setelah diterima.</p>
            <p v-if="application.booking.confirmed_at" class="mt-2 text-xs text-[#315e40]">Pembayaran sudah dikonfirmasi.</p>
            <form v-if="application.booking.status === 'verified'" class="mt-4 space-y-3" @submit.prevent="confirmPayment">
                <div class="grid gap-3 sm:grid-cols-2">
                    <input v-model="paymentForm.amount" type="number" min="0" placeholder="Jumlah diterima (opsional)" class="w-full rounded-lg border border-[#dce4dc] bg-white px-3 py-2.5 text-sm" />
                    <input v-model="paymentForm.reference" maxlength="255" placeholder="Referensi (opsional)" class="w-full rounded-lg border border-[#dce4dc] bg-white px-3 py-2.5 text-sm" />
                </div>
                <textarea v-model="paymentForm.note" rows="2" maxlength="5000" placeholder="Catatan pembayaran (opsional)" class="w-full resize-y rounded-lg border border-[#dce4dc] bg-white px-3 py-2.5 text-sm"></textarea>
                <p v-if="paymentForm.errors.amount || paymentForm.errors.reference || paymentForm.errors.note" class="text-xs text-rose-700">{{ paymentForm.errors.amount || paymentForm.errors.reference || paymentForm.errors.note }}</p>
                <button type="submit" :disabled="paymentForm.processing" class="min-h-10 rounded-lg bg-[#1c3d33] px-4 text-xs font-semibold text-white disabled:opacity-60">{{ paymentForm.processing ? 'Menyimpan…' : 'Konfirmasi pembayaran diterima' }}</button>
            </form>
        </section>
         <section v-if="application.tenancy" class="mt-7 rounded-2xl border border-[#cfe2d2] bg-[#f4faf3] p-4">
            <h3 class="text-sm font-semibold text-[#35483c]">Tenancy aktif</h3>
            <p class="mt-2 text-xs text-[#58675e]">{{ application.tenancy.tenant_name }} · {{ application.tenancy.tenant_email }}</p>
             <p class="mt-1 text-xs text-[#748077]">{{ formatDate(application.tenancy.start_date) }} sampai {{ formatDate(application.tenancy.end_date) }} · {{ formatPrice(application.tenancy.monthly_rent) }} / bulan</p>
             <div v-if="application.tenancy.invoices.length" class="mt-4 border-t border-[#d9e9d9] pt-3">
                  <div class="flex items-center justify-between gap-3"><h4 class="text-xs font-semibold text-[#35483c]">Tagihan penyewa</h4><select v-model="invoiceFilter.status" class="rounded-lg border border-[#dce4dc] bg-white px-2 py-1 text-[10px]"><option value="all">Semua</option><option value="unpaid">Belum dibayar</option><option value="paid">Lunas</option></select></div>
                  <ul class="mt-2 space-y-3">
                      <li v-for="invoice in filteredInvoices" :key="invoice.id" class="rounded-lg bg-white px-3 py-3 text-xs">
                         <div class="flex items-center justify-between gap-3"><span>{{ invoice.type === 'rent' ? 'Sewa' : invoice.type === 'deposit' ? 'Deposit' : 'Lainnya' }}</span><strong>{{ formatPrice(invoice.amount) }}</strong></div>
                          <p class="mt-1 text-[#748077]">Jatuh tempo {{ formatDate(invoice.due_date) }} · {{ invoice.status === 'paid' ? 'Lunas' : invoice.status === 'void' ? 'Void' : 'Belum dibayar' }}</p>
                          <a v-if="invoice.payment_evidence_download_url" :href="invoice.payment_evidence_download_url" class="mt-2 inline-flex font-semibold text-[#47785a]">Unduh bukti pembayaran ({{ invoice.payment_evidence_name }})</a>
                         <form v-if="invoice.status === 'unpaid'" class="mt-3 space-y-2" @submit.prevent="markInvoicePaid(invoice)">
                             <input v-model="invoiceForms[invoice.id].reference" maxlength="255" placeholder="Referensi pembayaran manual (opsional)" class="w-full rounded-lg border border-[#dce4dc] px-3 py-2 text-xs" />
                             <textarea v-model="invoiceForms[invoice.id].note" rows="2" maxlength="5000" placeholder="Catatan pembayaran manual (opsional)" class="w-full rounded-lg border border-[#dce4dc] px-3 py-2 text-xs"></textarea>
                              <button type="submit" :disabled="invoiceForms[invoice.id].processing" class="min-h-9 rounded-lg bg-[#1c3d33] px-3.5 text-xs font-semibold text-white disabled:opacity-60">{{ invoiceForms[invoice.id].processing ? 'Menyimpan…' : 'Tandai lunas manual' }}</button>
                          </form>
                          <form v-if="invoice.status === 'paid'" class="mt-3 space-y-2 border-t border-[#edf0eb] pt-3" @submit.prevent="reverseInvoice(invoice)">
                              <textarea v-model="reversalForms[invoice.id].reason" rows="2" minlength="10" maxlength="2000" required placeholder="Alasan koreksi / pembalikan (minimal 10 karakter)" class="w-full resize-y rounded-lg border border-[#dce4dc] px-3 py-2 text-xs"></textarea>
                              <p v-if="reversalForms[invoice.id].errors.reason" class="text-xs text-rose-700">{{ reversalForms[invoice.id].errors.reason }}</p>
                              <button type="submit" :disabled="reversalForms[invoice.id].processing" class="min-h-9 rounded-lg border border-amber-300 px-3.5 text-xs font-semibold text-amber-800 disabled:opacity-60">Koreksi: kembalikan menjadi belum dibayar</button>
                          </form>
                     </li>
                 </ul>
             </div>
         </section>
    </AuthLayout>
</template>
