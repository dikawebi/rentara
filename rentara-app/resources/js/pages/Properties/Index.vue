<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AuthLayout from '../../layouts/AuthLayout.vue';

const props = defineProps({
    organization: { type: Object, required: true },
    properties: { type: Array, required: true },
    canManage: { type: Boolean, required: true },
    identityDocumentTypes: { type: Array, required: true },
});

const page = usePage();
const form = useForm({
    name: '',
    property_type: 'kos',
    description: '',
    full_address: '',
    district: '',
    city: 'Jakarta',
    identity_document_requirements: ['ktp'],
});
const policyForm = useForm({ booking_expiry_days: props.organization.booking_expiry_days ?? 7 });

const typeLabels = { kos: 'Kos', kontrakan: 'Kontrakan' };
const flashMessage = computed(() => ({
    'property-created': 'Properti berhasil ditambahkan. Tambahkan unit untuk melengkapi datanya.',
    'property-archived': 'Properti dipindahkan dari workspace aktif.',
    'booking-expiry-policy-updated': 'Default masa berlaku booking diperbarui.',
}[page.props.flash?.status] ?? null));

function createProperty() {
    form.post(`/organizations/${props.organization.id}/properties`, {
        preserveScroll: true,
        onSuccess: () => form.reset('name', 'property_type', 'description', 'full_address', 'district'),
    });
}

function updatePolicy() {
    policyForm.patch(`/organizations/${props.organization.id}/booking-policy`, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Properti ${organization.name} — Rentara`" />
    <AuthLayout eyebrow="Workspace properti" :title="organization.name" description="Kelola detail properti dan unit dalam satu workspace.">
        <div class="mb-6 flex items-start justify-between gap-4">
            <div>
                <h2 class="text-2xl font-semibold tracking-[-0.05em] text-[#20372b]">Properti</h2>
                <p class="mt-1.5 text-sm text-[#768078]">{{ properties.length }} properti di workspace ini</p>
            </div>
            <Link href="/dashboard" class="shrink-0 text-xs font-semibold text-[#47785a] hover:text-[#244d39]">Dashboard</Link>
        </div>

        <div v-if="flashMessage" role="status" class="mb-5 rounded-xl bg-[#edf5ed] px-4 py-3 text-sm text-[#315e40]">
            {{ flashMessage }}
        </div>

        <form v-if="canManage" class="mb-7 rounded-2xl border border-[#e3e9e1] bg-[#f7f9f5] p-4" @submit.prevent="updatePolicy">
            <h3 class="text-sm font-semibold text-[#35483c]">Masa berlaku booking</h3>
            <p class="mt-1 text-xs leading-5 text-[#7a857d]">Default waktu pembayaran setelah booking terverifikasi. Pengaturan properti dapat menggantikannya.</p>
            <div class="mt-3 flex flex-wrap items-end gap-3">
                <div>
                    <label for="booking-expiry-days" class="mb-1.5 block text-xs font-medium text-[#526157]">Hari</label>
                    <input id="booking-expiry-days" v-model="policyForm.booking_expiry_days" type="number" min="1" max="30" required class="w-28 rounded-lg border border-[#dce4dc] bg-white px-3 py-2.5 text-sm" />
                </div>
                <button type="submit" :disabled="policyForm.processing" class="min-h-10 rounded-lg bg-[#1c3d33] px-4 text-xs font-semibold text-white disabled:opacity-60">{{ policyForm.processing ? 'Menyimpan…' : 'Simpan default' }}</button>
            </div>
            <p v-if="policyForm.errors.booking_expiry_days" class="mt-1.5 text-xs text-rose-700">{{ policyForm.errors.booking_expiry_days }}</p>
        </form>

        <div v-if="properties.length" class="mb-7 space-y-3">
            <Link v-for="property in properties" :key="property.id" :href="`/organizations/${organization.id}/properties/${property.id}`" class="group block rounded-2xl border border-[#e3e9e1] p-4 transition hover:border-[#b9cdbb] hover:bg-[#fbfcfa] focus:outline-none focus:ring-2 focus:ring-[#337b63]">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="truncate text-sm font-semibold text-[#35483c]">{{ property.name }}</h3>
                            <span class="rounded-full bg-[#f0f4ee] px-2.5 py-1 text-[10px] font-semibold text-[#607269]">{{ typeLabels[property.property_type] }}</span>
                        </div>
                        <p class="mt-1.5 line-clamp-2 text-xs leading-5 text-[#7a857d]">{{ property.full_address }}</p>
                        <p class="mt-2 text-xs font-medium text-[#738078]">{{ property.units_count }} unit · {{ property.district ? `${property.district}, ` : '' }}{{ property.city }}</p>
                    </div>
                    <span class="mt-1 shrink-0 text-sm text-[#66816c] transition group-hover:translate-x-0.5" aria-hidden="true">→</span>
                </div>
            </Link>
        </div>
        <p v-else class="mb-7 rounded-2xl border border-dashed border-[#d7e0d6] bg-[#f8faf7] px-4 py-4 text-sm leading-6 text-[#748077]">
            Belum ada properti di workspace ini.
        </p>

        <form v-if="canManage" class="space-y-4 rounded-2xl border border-[#e3e9e1] bg-[#f7f9f5] p-4 sm:p-5" @submit.prevent="createProperty">
            <div>
                <h3 class="text-sm font-semibold text-[#35483c]">Tambah properti</h3>
                <p class="mt-1 text-xs leading-5 text-[#7a857d]">Data ini hanya ada di workspace sampai listing diajukan dan disetujui admin.</p>
            </div>

            <div>
                <label for="property-name" class="mb-2 block text-sm font-medium text-[#35483c]">Nama properti</label>
                <input id="property-name" v-model="form.name" type="text" name="name" required maxlength="150" class="w-full rounded-xl border border-[#dce4dc] bg-white px-4 py-3 text-sm text-[#20372b] outline-none transition focus:border-[#58836a] focus:ring-4 focus:ring-[#548365]/10" placeholder="Contoh: Kos Mentari Tebet" />
                <p v-if="form.errors.name" class="mt-1.5 text-xs text-rose-700">{{ form.errors.name }}</p>
            </div>

            <div>
                <label for="property-type" class="mb-2 block text-sm font-medium text-[#35483c]">Jenis properti</label>
                <select id="property-type" v-model="form.property_type" name="property_type" class="w-full rounded-xl border border-[#dce4dc] bg-white px-4 py-3 text-sm text-[#20372b] outline-none transition focus:border-[#58836a] focus:ring-4 focus:ring-[#548365]/10">
                    <option value="kos">Kos</option>
                    <option value="kontrakan">Kontrakan</option>
                </select>
                <p v-if="form.errors.property_type" class="mt-1.5 text-xs text-rose-700">{{ form.errors.property_type }}</p>
            </div>

            <div>
                <label for="property-address" class="mb-2 block text-sm font-medium text-[#35483c]">Alamat lengkap</label>
                <textarea id="property-address" v-model="form.full_address" name="full_address" rows="3" required maxlength="1000" class="w-full resize-y rounded-xl border border-[#dce4dc] bg-white px-4 py-3 text-sm text-[#20372b] outline-none transition placeholder:text-[#a2aaa3] focus:border-[#58836a] focus:ring-4 focus:ring-[#548365]/10" placeholder="Jalan, nomor, kelurahan, kecamatan"></textarea>
                <p v-if="form.errors.full_address" class="mt-1.5 text-xs text-rose-700">{{ form.errors.full_address }}</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="property-district" class="mb-2 block text-sm font-medium text-[#35483c]">Kecamatan/area <span class="font-normal text-[#89928b]">(opsional)</span></label>
                    <input id="property-district" v-model="form.district" type="text" name="district" maxlength="150" class="w-full rounded-xl border border-[#dce4dc] bg-white px-4 py-3 text-sm text-[#20372b] outline-none transition focus:border-[#58836a] focus:ring-4 focus:ring-[#548365]/10" placeholder="Tebet" />
                    <p v-if="form.errors.district" class="mt-1.5 text-xs text-rose-700">{{ form.errors.district }}</p>
                </div>
                <div>
                    <label for="property-city" class="mb-2 block text-sm font-medium text-[#35483c]">Kota</label>
                    <input id="property-city" v-model="form.city" type="text" name="city" required maxlength="100" class="w-full rounded-xl border border-[#dce4dc] bg-white px-4 py-3 text-sm text-[#20372b] outline-none transition focus:border-[#58836a] focus:ring-4 focus:ring-[#548365]/10" />
                    <p v-if="form.errors.city" class="mt-1.5 text-xs text-rose-700">{{ form.errors.city }}</p>
                </div>
            </div>

            <div>
                <label for="property-description" class="mb-2 block text-sm font-medium text-[#35483c]">Deskripsi <span class="font-normal text-[#89928b]">(opsional)</span></label>
                <textarea id="property-description" v-model="form.description" name="description" rows="3" maxlength="5000" class="w-full resize-y rounded-xl border border-[#dce4dc] bg-white px-4 py-3 text-sm text-[#20372b] outline-none transition focus:border-[#58836a] focus:ring-4 focus:ring-[#548365]/10"></textarea>
                <p v-if="form.errors.description" class="mt-1.5 text-xs text-rose-700">{{ form.errors.description }}</p>
            </div>

            <fieldset class="rounded-xl border border-[#e3e9e1] bg-white p-3.5">
                <legend class="px-1 text-xs font-semibold text-[#35483c]">Dokumen identitas yang diminta</legend>
                <p class="mb-2 text-[11px] leading-5 text-[#7a857d]">Pencari perlu mengunggah dokumen terpilih saat mengajukan sewa.</p>
                <label v-for="documentType in identityDocumentTypes" :key="documentType.value" class="flex cursor-pointer items-center gap-2 py-1.5 text-xs text-[#59685f]">
                    <input v-model="form.identity_document_requirements" type="checkbox" :value="documentType.value" class="size-4 rounded border-[#cbd5cb] text-[#326447] focus:ring-[#548365]" />
                    {{ documentType.label }}
                </label>
                <p v-if="form.errors.identity_document_requirements || form.errors['identity_document_requirements.0']" class="mt-1 text-xs text-rose-700">{{ form.errors.identity_document_requirements || form.errors['identity_document_requirements.0'] }}</p>
            </fieldset>

            <button type="submit" :disabled="form.processing" class="flex min-h-11 w-full items-center justify-center rounded-xl bg-[#1c3d33] px-4 text-sm font-semibold text-white transition hover:bg-[#285744] focus:outline-none focus:ring-2 focus:ring-[#337b63] focus:ring-offset-2 disabled:cursor-wait disabled:opacity-60">
                {{ form.processing ? 'Menyimpan…' : 'Simpan properti' }}
            </button>
        </form>
    </AuthLayout>
</template>
