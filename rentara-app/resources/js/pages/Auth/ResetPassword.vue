<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '../../layouts/AuthLayout.vue';

const props = defineProps({
    token: { type: String, required: true },
    email: { type: String, default: '' },
});

const form = useForm({ token: props.token, email: props.email, password: '', password_confirmation: '' });

function submit() {
    form.post('/reset-password', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head title="Buat kata sandi baru — Rentara" />
    <AuthLayout eyebrow="Keamanan akun" title="Pilih kata sandi baru." description="Gunakan kata sandi yang kuat dan belum pernah dipakai di akun lain.">
        <div class="mb-7">
            <h2 class="text-2xl font-semibold tracking-[-0.05em] text-[#20372b]">Kata sandi baru</h2>
            <p class="mt-1.5 text-sm text-[#768078]">Minimal 12 karakter.</p>
        </div>

        <form class="space-y-5" @submit.prevent="submit">
            <div>
                <label for="email" class="mb-2 block text-sm font-medium text-[#35483c]">Alamat email</label>
                <input id="email" v-model="form.email" type="email" name="email" autocomplete="email" required readonly class="w-full rounded-xl border border-[#e5e9e3] bg-[#f7f8f5] px-4 py-3 text-sm text-[#657168] outline-none" />
                <p v-if="form.errors.email" class="mt-1.5 text-xs text-rose-700">{{ form.errors.email }}</p>
            </div>
            <div>
                <label for="password" class="mb-2 block text-sm font-medium text-[#35483c]">Kata sandi baru</label>
                <input id="password" v-model="form.password" type="password" name="password" autocomplete="new-password" required autofocus class="w-full rounded-xl border border-[#dce4dc] bg-white px-4 py-3 text-sm text-[#20372b] outline-none transition focus:border-[#58836a] focus:ring-4 focus:ring-[#548365]/10" />
                <p v-if="form.errors.password" class="mt-1.5 text-xs text-rose-700">{{ form.errors.password }}</p>
            </div>
            <div>
                <label for="password_confirmation" class="mb-2 block text-sm font-medium text-[#35483c]">Ulangi kata sandi</label>
                <input id="password_confirmation" v-model="form.password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required class="w-full rounded-xl border border-[#dce4dc] bg-white px-4 py-3 text-sm text-[#20372b] outline-none transition focus:border-[#58836a] focus:ring-4 focus:ring-[#548365]/10" />
                <p v-if="form.errors.password_confirmation" class="mt-1.5 text-xs text-rose-700">{{ form.errors.password_confirmation }}</p>
            </div>
            <button type="submit" :disabled="form.processing" class="flex min-h-12 w-full items-center justify-center rounded-xl bg-[#1c3d33] px-5 text-sm font-semibold text-white transition hover:bg-[#285744] focus:outline-none focus:ring-2 focus:ring-[#337b63] focus:ring-offset-2 disabled:cursor-wait disabled:opacity-60">
                {{ form.processing ? 'Menyimpan…' : 'Simpan kata sandi baru' }}
            </button>
            <p class="text-center text-sm text-[#768078]"><Link href="/login" class="font-semibold text-[#367052] hover:text-[#244d39]">Kembali ke masuk</Link></p>
        </form>
    </AuthLayout>
</template>
