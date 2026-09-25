<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AuthLayout from '../layouts/AuthLayout.vue';

defineProps({
    user: {
        type: Object,
        required: true,
    },
    organizations: {
        type: Array,
        required: true,
    },
});

const page = usePage();
const form = useForm({ name: '' });

function createOrganization() {
    form.post('/organizations', {
        preserveScroll: true,
        onSuccess: () => form.reset('name'),
    });
}

function logout() {
    router.post('/logout');
}
</script>

<template>
    <Head title="Dashboard — Rentara" />
    <AuthLayout eyebrow="Akun terverifikasi" title="Selamat datang di Rentara." description="Akunmu siap digunakan. Berikutnya, siapkan ruang kerja untuk properti dan aktivitas sewamu.">
        <div class="mb-7">
            <p class="text-sm text-[#768078]">Kamu masuk sebagai</p>
            <h2 class="mt-1 text-2xl font-semibold tracking-[-0.05em] text-[#20372b]">{{ user.name }}</h2>
            <p class="mt-1 text-sm text-[#768078]">{{ user.email }}</p>
        </div>

        <Link href="/my-applications" class="mb-6 inline-flex min-h-10 items-center rounded-lg border border-[#dce4dc] px-3.5 text-xs font-semibold text-[#47785a] transition hover:bg-[#f7f9f5]">Pengajuan sewa saya</Link>

        <div v-if="page.props.flash?.status === 'organization-created'" role="status" class="mb-5 rounded-xl bg-[#edf5ed] px-4 py-3 text-sm text-[#315e40]">
            Workspace berhasil dibuat. Kamu terdaftar sebagai owner.
        </div>
        <div v-if="page.props.flash?.status === 'organization-invitation-accepted'" role="status" class="mb-5 rounded-xl bg-[#edf5ed] px-4 py-3 text-sm text-[#315e40]">
            Undangan diterima. Workspace sudah ditambahkan ke akunmu.
        </div>

        <section aria-labelledby="workspaces-heading">
            <div class="mb-4 flex items-center justify-between gap-4">
                <div>
                    <h3 id="workspaces-heading" class="text-sm font-semibold text-[#35483c]">Workspace properti</h3>
                    <p class="mt-1 text-xs text-[#7a857d]">Organisasi yang dapat kamu kelola.</p>
                </div>
                <span class="grid size-9 place-items-center rounded-xl bg-[#edf4eb] text-[#47785a]" aria-hidden="true">⌂</span>
            </div>

            <ul v-if="organizations.length" class="mb-5 space-y-2">
                <li v-for="organization in organizations" :key="organization.id" class="flex items-center justify-between gap-4 rounded-xl border border-[#e5eae3] px-4 py-3">
                    <div class="min-w-0">
                        <span class="block truncate text-sm font-semibold text-[#35483c]">{{ organization.name }}</span>
                        <div class="mt-1 flex flex-wrap gap-x-3">
                            <Link :href="`/organizations/${organization.id}/properties`" class="inline-flex text-xs font-semibold text-[#47785a] hover:text-[#244d39]">Lihat properti</Link>
                            <Link v-if="organization.can_manage_members" :href="`/organizations/${organization.id}/members`" class="inline-flex text-xs font-semibold text-[#47785a] hover:text-[#244d39]">Kelola anggota</Link>
                        </div>
                    </div>
                    <span class="shrink-0 rounded-full bg-[#f0f4ee] px-2.5 py-1 text-[11px] font-medium capitalize text-[#607269]">{{ organization.role }}</span>
                </li>
            </ul>
            <p v-else class="mb-5 rounded-xl border border-dashed border-[#d7e0d6] bg-[#f8faf7] px-4 py-4 text-sm leading-6 text-[#748077]">
                Kamu belum memiliki workspace. Buat workspace untuk mulai menyiapkan kos atau kontrakan.
            </p>

            <form class="space-y-3 rounded-2xl border border-[#e3e9e1] bg-[#f7f9f5] p-4 sm:p-5" @submit.prevent="createOrganization">
                <label for="organization-name" class="block text-sm font-medium text-[#35483c]">Nama organisasi baru</label>
                <input id="organization-name" v-model="form.name" type="text" name="name" required maxlength="150" autocomplete="organization" class="w-full rounded-xl border border-[#dce4dc] bg-white px-4 py-3 text-sm text-[#20372b] outline-none transition placeholder:text-[#a2aaa3] focus:border-[#58836a] focus:ring-4 focus:ring-[#548365]/10" placeholder="Contoh: Kos Mentari Jakarta" />
                <p v-if="form.errors.name" class="text-xs text-rose-700">{{ form.errors.name }}</p>
                <button type="submit" :disabled="form.processing" class="flex min-h-11 w-full items-center justify-center rounded-xl bg-[#1c3d33] px-4 text-sm font-semibold text-white transition hover:bg-[#285744] focus:outline-none focus:ring-2 focus:ring-[#337b63] focus:ring-offset-2 disabled:cursor-wait disabled:opacity-60">
                    {{ form.processing ? 'Membuat workspace…' : 'Buat workspace' }}
                </button>
            </form>
        </section>

        <Link v-if="user.is_platform_admin" href="/admin/listings" class="mt-6 inline-flex min-h-11 items-center rounded-xl border border-[#dce4dc] px-4 text-sm font-semibold text-[#53645a] transition hover:bg-[#f7f8f5] focus:outline-none focus:ring-2 focus:ring-[#337b63]">
            Review listing admin
        </Link>

        <button type="button" class="mt-6 min-h-11 rounded-xl border border-[#dce4dc] px-4 text-sm font-semibold text-[#53645a] transition hover:bg-[#f7f8f5] focus:outline-none focus:ring-2 focus:ring-[#337b63]" @click="logout">Keluar</button>
    </AuthLayout>
</template>
