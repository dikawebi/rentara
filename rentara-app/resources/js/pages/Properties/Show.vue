<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AuthLayout from '../../layouts/AuthLayout.vue';

const props = defineProps({
    organization: { type: Object, required: true },
    property: { type: Object, required: true },
    units: { type: Array, required: true },
    photos: { type: Array, required: true },
    identityDocumentTypes: { type: Array, required: true },
    canManage: { type: Boolean, required: true },
    canDelete: { type: Boolean, required: true },
});

const page = usePage();
const activeUnitId = ref(null);
const typeLabels = { kos: 'Kos', kontrakan: 'Kontrakan' };
const statusLabels = { available: 'Tersedia', unavailable: 'Tidak tersedia' };
const flashMessage = computed(() => ({
    'property-updated': 'Detail properti berhasil diperbarui.',
    'unit-created': 'Unit berhasil ditambahkan.',
    'unit-updated': 'Detail unit berhasil diperbarui.',
    'unit-deleted': 'Unit berhasil dihapus.',
    'property-photos-uploaded': 'Foto properti berhasil diunggah.',
    'property-photos-reordered': 'Urutan foto berhasil disimpan.',
    'property-photo-deleted': 'Foto berhasil dihapus.',
}[page.props.flash?.status] ?? null));

const propertyForm = useForm({
    name: props.property.name,
    property_type: props.property.property_type,
    description: props.property.description ?? '',
    full_address: props.property.full_address,
    district: props.property.district ?? '',
    city: props.property.city,
    identity_document_requirements: [...props.property.identity_document_requirements],
});

const createUnitForm = useForm({ name: '', capacity: 1, monthly_price: '', status: 'available' });
const editUnitForm = useForm({ name: '', capacity: 1, monthly_price: 1, status: 'available' });
const uploadForm = useForm({ photos: [] });
const fileInput = ref(null);
const uploadError = computed(() => uploadForm.errors.photos
    ?? Object.entries(uploadForm.errors).find(([field]) => field.startsWith('photos.'))?.[1]
    ?? null);

function updateProperty() {
    propertyForm.patch(`/organizations/${props.organization.id}/properties/${props.property.id}`, { preserveScroll: true });
}

function selectPhotos(event) {
    uploadForm.photos = Array.from(event.target.files ?? []);
}

function uploadPhotos() {
    uploadForm.post(`/organizations/${props.organization.id}/properties/${props.property.id}/photos`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            uploadForm.reset('photos');
            if (fileInput.value) {
                fileInput.value.value = '';
            }
        },
    });
}

function movePhoto(index, direction) {
    const photoIds = props.photos.map((photo) => photo.id);
    const targetIndex = index + direction;

    if (targetIndex < 0 || targetIndex >= photoIds.length) {
        return;
    }

    [photoIds[index], photoIds[targetIndex]] = [photoIds[targetIndex], photoIds[index]];
    router.patch(`/organizations/${props.organization.id}/properties/${props.property.id}/photos/order`, {
        photo_ids: photoIds,
    }, { preserveScroll: true });
}

function deletePhoto(photo) {
    if (window.confirm('Hapus foto ini dari properti?')) {
        router.delete(`/organizations/${props.organization.id}/properties/${props.property.id}/photos/${photo.id}`, { preserveScroll: true });
    }
}

function createUnit() {
    createUnitForm.post(`/organizations/${props.organization.id}/properties/${props.property.id}/units`, {
        preserveScroll: true,
        onSuccess: () => createUnitForm.reset('name', 'capacity', 'monthly_price'),
    });
}

function beginUnitEdit(unit) {
    activeUnitId.value = unit.id;
    editUnitForm.name = unit.name;
    editUnitForm.capacity = unit.capacity;
    editUnitForm.monthly_price = unit.monthly_price;
    editUnitForm.status = unit.status;
    editUnitForm.clearErrors();
}

function updateUnit(unit) {
    editUnitForm.patch(`/organizations/${props.organization.id}/properties/${props.property.id}/units/${unit.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            activeUnitId.value = null;
            editUnitForm.reset();
        },
    });
}

function deleteUnit(unit) {
    if (window.confirm(`Hapus ${unit.name} dari properti ini?`)) {
        router.delete(`/organizations/${props.organization.id}/properties/${props.property.id}/units/${unit.id}`, { preserveScroll: true });
    }
}

function archiveProperty() {
    if (window.confirm(`Arsipkan ${props.property.name}? Properti tidak akan tampil lagi di workspace aktif.`)) {
        router.delete(`/organizations/${props.organization.id}/properties/${props.property.id}`);
    }
}

function formatPrice(amount) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(amount);
}
</script>

<template>
    <Head :title="`${property.name} — Rentara`" />
    <AuthLayout eyebrow="Workspace properti" :title="property.name" description="Informasi ini hanya tersedia bagi anggota organisasi yang memiliki akses ke workspace.">
        <div class="mb-6 flex items-start justify-between gap-4">
            <div class="min-w-0">
                <Link :href="`/organizations/${organization.id}/properties`" class="text-xs font-semibold text-[#47785a] hover:text-[#244d39]">← {{ organization.name }}</Link>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <h2 class="text-2xl font-semibold tracking-[-0.05em] text-[#20372b]">{{ property.name }}</h2>
                    <span class="rounded-full bg-[#f0f4ee] px-2.5 py-1 text-[10px] font-semibold text-[#607269]">{{ typeLabels[property.property_type] }}</span>
                </div>
                <p class="mt-2 text-sm leading-6 text-[#768078]">{{ property.full_address }}</p>
                <p class="mt-1 text-xs text-[#879088]">{{ property.district ? `${property.district}, ` : '' }}{{ property.city }}</p>
            </div>
            <div class="flex shrink-0 flex-col items-end gap-2">
                <Link v-if="canManage" :href="`/organizations/${organization.id}/properties/${property.id}/listing`" class="rounded-lg px-2 py-1.5 text-xs font-semibold text-[#47785a] transition hover:bg-[#f0f4ee]">Kelola listing</Link>
                <Link v-if="canManage" :href="`/organizations/${organization.id}/applications`" class="rounded-lg px-2 py-1.5 text-xs font-semibold text-[#47785a] transition hover:bg-[#f0f4ee]">Tinjau pengajuan</Link>
                <button v-if="canDelete" type="button" class="rounded-lg px-2 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-50 focus:outline-none focus:ring-2 focus:ring-rose-400" @click="archiveProperty">Arsipkan</button>
            </div>
        </div>

        <div v-if="flashMessage" role="status" class="mb-5 rounded-xl bg-[#edf5ed] px-4 py-3 text-sm text-[#315e40]">
            {{ flashMessage }}
        </div>

        <section class="mb-8" aria-labelledby="photos-heading">
            <div class="mb-3 flex items-center justify-between">
                <div>
                    <h3 id="photos-heading" class="text-sm font-semibold text-[#35483c]">Foto properti</h3>
                    <p class="mt-1 text-xs text-[#7a857d]">Foto tersimpan privat dan belum terlihat di marketplace.</p>
                </div>
                <span class="text-xs text-[#879088]">{{ photos.length }}/20</span>
            </div>

            <div v-if="photos.length" class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
                <figure v-for="(photo, index) in photos" :key="photo.id" class="group relative overflow-hidden rounded-2xl border border-[#e5eae3] bg-[#f1f4ef]">
                    <img :src="photo.thumbnail_url" :alt="photo.alt_text" loading="lazy" class="aspect-[4/3] w-full object-cover" />
                    <figcaption class="flex items-center justify-between gap-1 px-2.5 py-2">
                        <span class="truncate text-[10px] font-medium text-[#69766e]">Foto {{ index + 1 }}</span>
                        <div v-if="canManage" class="flex shrink-0 gap-0.5">
                            <button type="button" :disabled="index === 0" :aria-label="`Pindahkan foto ${index + 1} ke kiri`" class="grid size-7 place-items-center rounded-md text-xs font-semibold text-[#526157] hover:bg-white disabled:cursor-not-allowed disabled:opacity-30" @click="movePhoto(index, -1)">←</button>
                            <button type="button" :disabled="index === photos.length - 1" :aria-label="`Pindahkan foto ${index + 1} ke kanan`" class="grid size-7 place-items-center rounded-md text-xs font-semibold text-[#526157] hover:bg-white disabled:cursor-not-allowed disabled:opacity-30" @click="movePhoto(index, 1)">→</button>
                            <button type="button" :aria-label="`Hapus foto ${index + 1}`" class="grid size-7 place-items-center rounded-md text-xs font-semibold text-rose-700 hover:bg-rose-50" @click="deletePhoto(photo)">×</button>
                        </div>
                    </figcaption>
                </figure>
            </div>
            <p v-else class="mb-4 rounded-xl border border-dashed border-[#d7e0d6] px-4 py-3 text-xs text-[#748077]">Belum ada foto yang diunggah.</p>

            <form v-if="canManage" class="space-y-3 rounded-2xl border border-[#e3e9e1] bg-[#f7f9f5] p-4" @submit.prevent="uploadPhotos">
                <div>
                    <label for="property-photos" class="mb-1.5 block text-xs font-medium text-[#526157]">Pilih hingga 5 foto (JPEG, PNG, WebP; maksimal 5 MB per foto)</label>
                    <input id="property-photos" ref="fileInput" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple class="block w-full text-xs text-[#647069] file:mr-3 file:rounded-lg file:border-0 file:bg-white file:px-3 file:py-2 file:text-xs file:font-semibold file:text-[#42664e] hover:file:bg-[#edf4eb]" @change="selectPhotos" />
                    <p v-if="uploadError" class="mt-1.5 text-xs text-rose-700">{{ uploadError }}</p>
                    <p v-else-if="uploadForm.photos.length" class="mt-1.5 text-xs text-[#758078]">{{ uploadForm.photos.length }} foto dipilih</p>
                </div>
                <button type="submit" :disabled="uploadForm.processing || !uploadForm.photos.length || photos.length >= 20" class="flex min-h-10 w-full items-center justify-center rounded-lg bg-[#1c3d33] px-4 text-xs font-semibold text-white transition hover:bg-[#285744] disabled:cursor-not-allowed disabled:opacity-50">
                    {{ uploadForm.processing ? 'Memproses dan mengunggah…' : 'Unggah foto' }}
                </button>
            </form>
        </section>

        <section aria-labelledby="units-heading">
            <div class="mb-3 flex items-center justify-between">
                <div>
                    <h3 id="units-heading" class="text-sm font-semibold text-[#35483c]">Unit</h3>
                    <p class="mt-1 text-xs text-[#7a857d]">{{ units.length }} unit di properti ini</p>
                </div>
            </div>

            <p v-if="!units.length" class="mb-4 rounded-2xl border border-dashed border-[#d7e0d6] bg-[#f8faf7] px-4 py-4 text-sm leading-6 text-[#748077]">
                Belum ada unit. Tambahkan kamar atau unit kontrakan untuk melengkapi data properti.
            </p>

            <ul v-else class="mb-5 space-y-3">
                <li v-for="unit in units" :key="unit.id" class="rounded-2xl border border-[#e5eae3] p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h4 class="text-sm font-semibold text-[#35483c]">{{ unit.name }}</h4>
                            <p class="mt-1 text-xs text-[#7a857d]">Kapasitas {{ unit.capacity }} orang · {{ statusLabels[unit.status] }}</p>
                            <p class="mt-2 text-sm font-semibold text-[#42664e]">{{ formatPrice(unit.monthly_price) }} <span class="text-xs font-normal text-[#89928b]">/ bulan</span></p>
                        </div>
                        <div v-if="canManage" class="flex shrink-0 gap-1">
                            <button type="button" class="rounded-lg px-2 py-1.5 text-xs font-semibold text-[#47785a] transition hover:bg-[#f0f4ee] focus:outline-none focus:ring-2 focus:ring-[#337b63]" @click="beginUnitEdit(unit)">Edit</button>
                            <button type="button" class="rounded-lg px-2 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-50 focus:outline-none focus:ring-2 focus:ring-rose-400" @click="deleteUnit(unit)">Hapus</button>
                        </div>
                    </div>

                    <form v-if="canManage && activeUnitId === unit.id" class="mt-4 grid gap-3 border-t border-[#edf0eb] pt-4 sm:grid-cols-2" @submit.prevent="updateUnit(unit)">
                        <div>
                            <label :for="`unit-name-${unit.id}`" class="mb-1.5 block text-xs font-medium text-[#526157]">Nama unit</label>
                            <input :id="`unit-name-${unit.id}`" v-model="editUnitForm.name" type="text" required maxlength="100" class="w-full rounded-lg border border-[#dce4dc] bg-white px-3 py-2.5 text-sm outline-none focus:border-[#58836a] focus:ring-4 focus:ring-[#548365]/10" />
                            <p v-if="editUnitForm.errors.name" class="mt-1 text-xs text-rose-700">{{ editUnitForm.errors.name }}</p>
                        </div>
                        <div>
                            <label :for="`unit-capacity-${unit.id}`" class="mb-1.5 block text-xs font-medium text-[#526157]">Kapasitas orang</label>
                            <input :id="`unit-capacity-${unit.id}`" v-model.number="editUnitForm.capacity" type="number" min="1" max="30" required class="w-full rounded-lg border border-[#dce4dc] bg-white px-3 py-2.5 text-sm outline-none focus:border-[#58836a] focus:ring-4 focus:ring-[#548365]/10" />
                            <p v-if="editUnitForm.errors.capacity" class="mt-1 text-xs text-rose-700">{{ editUnitForm.errors.capacity }}</p>
                        </div>
                        <div>
                            <label :for="`unit-price-${unit.id}`" class="mb-1.5 block text-xs font-medium text-[#526157]">Harga sewa per bulan (IDR)</label>
                            <input :id="`unit-price-${unit.id}`" v-model.number="editUnitForm.monthly_price" type="number" min="1" max="1000000000" required class="w-full rounded-lg border border-[#dce4dc] bg-white px-3 py-2.5 text-sm outline-none focus:border-[#58836a] focus:ring-4 focus:ring-[#548365]/10" />
                            <p v-if="editUnitForm.errors.monthly_price" class="mt-1 text-xs text-rose-700">{{ editUnitForm.errors.monthly_price }}</p>
                        </div>
                        <div>
                            <label :for="`unit-status-${unit.id}`" class="mb-1.5 block text-xs font-medium text-[#526157]">Status ketersediaan</label>
                            <select :id="`unit-status-${unit.id}`" v-model="editUnitForm.status" class="w-full rounded-lg border border-[#dce4dc] bg-white px-3 py-2.5 text-sm outline-none focus:border-[#58836a] focus:ring-4 focus:ring-[#548365]/10">
                                <option value="available">Tersedia</option>
                                <option value="unavailable">Tidak tersedia</option>
                            </select>
                            <p v-if="editUnitForm.errors.status" class="mt-1 text-xs text-rose-700">{{ editUnitForm.errors.status }}</p>
                        </div>
                        <div class="flex gap-2 sm:col-span-2">
                            <button type="submit" :disabled="editUnitForm.processing" class="min-h-10 rounded-lg bg-[#1c3d33] px-4 text-xs font-semibold text-white disabled:opacity-60">Simpan perubahan</button>
                            <button type="button" class="min-h-10 rounded-lg border border-[#dce4dc] px-4 text-xs font-semibold text-[#607269]" @click="activeUnitId = null">Batal</button>
                        </div>
                    </form>
                </li>
            </ul>

            <form v-if="canManage" class="space-y-3 rounded-2xl border border-[#e3e9e1] bg-[#f7f9f5] p-4 sm:p-5" @submit.prevent="createUnit">
                <h4 class="text-sm font-semibold text-[#35483c]">Tambah unit</h4>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label for="new-unit-name" class="mb-1.5 block text-xs font-medium text-[#526157]">Nama unit</label>
                        <input id="new-unit-name" v-model="createUnitForm.name" type="text" required maxlength="100" class="w-full rounded-lg border border-[#dce4dc] bg-white px-3 py-2.5 text-sm outline-none focus:border-[#58836a] focus:ring-4 focus:ring-[#548365]/10" placeholder="Contoh: Kamar A1" />
                        <p v-if="createUnitForm.errors.name" class="mt-1 text-xs text-rose-700">{{ createUnitForm.errors.name }}</p>
                    </div>
                    <div>
                        <label for="new-unit-capacity" class="mb-1.5 block text-xs font-medium text-[#526157]">Kapasitas orang</label>
                        <input id="new-unit-capacity" v-model.number="createUnitForm.capacity" type="number" min="1" max="30" required class="w-full rounded-lg border border-[#dce4dc] bg-white px-3 py-2.5 text-sm outline-none focus:border-[#58836a] focus:ring-4 focus:ring-[#548365]/10" />
                        <p v-if="createUnitForm.errors.capacity" class="mt-1 text-xs text-rose-700">{{ createUnitForm.errors.capacity }}</p>
                    </div>
                </div>
                <fieldset class="rounded-xl border border-[#e3e9e1] bg-[#f7f9f5] p-3.5">
                    <legend class="px-1 text-xs font-semibold text-[#35483c]">Dokumen identitas yang diminta</legend>
                    <p class="mb-2 text-[11px] leading-5 text-[#7a857d]">Pengaturan ini berlaku untuk pengajuan sewa baru.</p>
                    <label v-for="documentType in identityDocumentTypes" :key="documentType.value" class="flex cursor-pointer items-center gap-2 py-1.5 text-xs text-[#59685f]">
                        <input v-model="propertyForm.identity_document_requirements" type="checkbox" :value="documentType.value" class="size-4 rounded border-[#cbd5cb] text-[#326447] focus:ring-[#548365]" />
                        {{ documentType.label }}
                    </label>
                    <p v-if="propertyForm.errors.identity_document_requirements || propertyForm.errors['identity_document_requirements.0']" class="mt-1 text-xs text-rose-700">{{ propertyForm.errors.identity_document_requirements || propertyForm.errors['identity_document_requirements.0'] }}</p>
                </fieldset>
                <div>
                    <label for="new-unit-price" class="mb-1.5 block text-xs font-medium text-[#526157]">Harga sewa per bulan (IDR)</label>
                    <input id="new-unit-price" v-model.number="createUnitForm.monthly_price" type="number" min="1" max="1000000000" required class="w-full rounded-lg border border-[#dce4dc] bg-white px-3 py-2.5 text-sm outline-none focus:border-[#58836a] focus:ring-4 focus:ring-[#548365]/10" placeholder="1500000" />
                    <p v-if="createUnitForm.errors.monthly_price" class="mt-1 text-xs text-rose-700">{{ createUnitForm.errors.monthly_price }}</p>
                </div>
                <button type="submit" :disabled="createUnitForm.processing" class="flex min-h-10 w-full items-center justify-center rounded-lg bg-[#1c3d33] px-4 text-xs font-semibold text-white transition hover:bg-[#285744] disabled:cursor-wait disabled:opacity-60">
                    {{ createUnitForm.processing ? 'Menambahkan…' : 'Tambah unit' }}
                </button>
            </form>
        </section>

        <section v-if="canManage" class="mt-8 border-t border-[#e8ece6] pt-6" aria-labelledby="property-details-heading">
            <h3 id="property-details-heading" class="mb-4 text-sm font-semibold text-[#35483c]">Detail properti</h3>
            <form class="space-y-3" @submit.prevent="updateProperty">
                <div>
                    <label for="edit-property-name" class="mb-1.5 block text-xs font-medium text-[#526157]">Nama properti</label>
                    <input id="edit-property-name" v-model="propertyForm.name" type="text" required maxlength="150" class="w-full rounded-lg border border-[#dce4dc] bg-white px-3 py-2.5 text-sm outline-none focus:border-[#58836a] focus:ring-4 focus:ring-[#548365]/10" />
                    <p v-if="propertyForm.errors.name" class="mt-1 text-xs text-rose-700">{{ propertyForm.errors.name }}</p>
                </div>
                <div>
                    <label for="edit-property-type" class="mb-1.5 block text-xs font-medium text-[#526157]">Jenis</label>
                    <select id="edit-property-type" v-model="propertyForm.property_type" class="w-full rounded-lg border border-[#dce4dc] bg-white px-3 py-2.5 text-sm outline-none focus:border-[#58836a] focus:ring-4 focus:ring-[#548365]/10">
                        <option value="kos">Kos</option>
                        <option value="kontrakan">Kontrakan</option>
                    </select>
                </div>
                <div>
                    <label for="edit-property-address" class="mb-1.5 block text-xs font-medium text-[#526157]">Alamat lengkap</label>
                    <textarea id="edit-property-address" v-model="propertyForm.full_address" rows="3" required maxlength="1000" class="w-full resize-y rounded-lg border border-[#dce4dc] bg-white px-3 py-2.5 text-sm outline-none focus:border-[#58836a] focus:ring-4 focus:ring-[#548365]/10"></textarea>
                    <p v-if="propertyForm.errors.full_address" class="mt-1 text-xs text-rose-700">{{ propertyForm.errors.full_address }}</p>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label for="edit-property-district" class="mb-1.5 block text-xs font-medium text-[#526157]">Kecamatan/area</label>
                        <input id="edit-property-district" v-model="propertyForm.district" type="text" maxlength="150" class="w-full rounded-lg border border-[#dce4dc] bg-white px-3 py-2.5 text-sm outline-none focus:border-[#58836a] focus:ring-4 focus:ring-[#548365]/10" />
                    </div>
                    <div>
                        <label for="edit-property-city" class="mb-1.5 block text-xs font-medium text-[#526157]">Kota</label>
                        <input id="edit-property-city" v-model="propertyForm.city" type="text" required maxlength="100" class="w-full rounded-lg border border-[#dce4dc] bg-white px-3 py-2.5 text-sm outline-none focus:border-[#58836a] focus:ring-4 focus:ring-[#548365]/10" />
                    </div>
                </div>
                <div>
                    <label for="edit-property-description" class="mb-1.5 block text-xs font-medium text-[#526157]">Deskripsi</label>
                    <textarea id="edit-property-description" v-model="propertyForm.description" rows="3" maxlength="5000" class="w-full resize-y rounded-lg border border-[#dce4dc] bg-white px-3 py-2.5 text-sm outline-none focus:border-[#58836a] focus:ring-4 focus:ring-[#548365]/10"></textarea>
                </div>
                <button type="submit" :disabled="propertyForm.processing" class="min-h-10 rounded-lg border border-[#dce4dc] px-4 text-xs font-semibold text-[#456451] transition hover:bg-[#f7f9f5] disabled:opacity-60">Simpan detail properti</button>
            </form>
        </section>
    </AuthLayout>
</template>
