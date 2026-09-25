<script setup>
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AuthLayout from '../../layouts/AuthLayout.vue';

const page = usePage();
const form = useForm({});

function resend() {
    form.post('/email/verification-notification', { preserveScroll: true });
}

function logout() {
    router.post('/logout');
}
</script>

<template>
    <Head title="Verifikasi email — Rentara" />
    <AuthLayout eyebrow="Satu langkah lagi" title="Verifikasi alamat emailmu." description="Kami perlu memastikan email ini benar-benar milikmu sebelum kamu menggunakan fitur Rentara.">
        <div class="mb-6 grid size-12 place-items-center rounded-2xl bg-[#edf4eb] text-[#47785a]">
            <svg viewBox="0 0 24 24" fill="none" class="size-6" aria-hidden="true"><path d="M4 7.5 12 13l8-5.5M5 5h14a1 1 0 0 1 1 1v12H4V6a1 1 0 0 1 1-1Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" /></svg>
        </div>
        <h2 class="text-2xl font-semibold tracking-[-0.05em] text-[#20372b]">Periksa emailmu</h2>
        <p class="mt-3 text-sm leading-6 text-[#6c786f]">Tautan verifikasi sudah dikirim ke <strong class="font-semibold text-[#35483c]">{{ page.props.auth?.user?.email }}</strong>. Klik tautan tersebut untuk mengaktifkan akun.</p>

        <div v-if="page.props.flash?.status === 'verification-link-sent'" role="status" class="mt-5 rounded-xl bg-[#edf5ed] px-4 py-3 text-sm text-[#315e40]">
            Tautan verifikasi baru telah dikirim.
        </div>

        <div class="mt-7 space-y-3">
            <button type="button" :disabled="form.processing" class="flex min-h-12 w-full items-center justify-center rounded-xl bg-[#1c3d33] px-5 text-sm font-semibold text-white transition hover:bg-[#285744] focus:outline-none focus:ring-2 focus:ring-[#337b63] focus:ring-offset-2 disabled:cursor-wait disabled:opacity-60" @click="resend">
                {{ form.processing ? 'Mengirim…' : 'Kirim ulang tautan verifikasi' }}
            </button>
            <button type="button" class="w-full py-2 text-sm font-semibold text-[#6e7a71] transition hover:text-[#314d3d]" @click="logout">Keluar dari akun</button>
        </div>
    </AuthLayout>
</template>
