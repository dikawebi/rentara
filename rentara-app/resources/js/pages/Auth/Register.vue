<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '../../layouts/AuthLayout.vue';

const form = useForm({ name: '', email: '', password: '', password_confirmation: '' });

function submit() {
    form.post('/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head title="Buat akun — Rentara" />
    <AuthLayout eyebrow="Mulai bersama Rentara" title="Satu akun untuk langkah berikutnya." description="Buat akun untuk mencari hunian atau mulai mengelola properti.">
        <div class="mb-7">
            <h2 class="text-2xl font-semibold tracking-[-0.05em] text-[#20372b]">Buat akun</h2>
            <p class="mt-1.5 text-sm text-[#768078]">Sudah punya akun? <Link href="/login" class="font-semibold text-[#367052] hover:text-[#244d39]">Masuk</Link></p>
        </div>

        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <label for="name" class="mb-2 block text-sm font-medium text-[#35483c]">Nama lengkap</label>
                <input id="name" v-model="form.name" type="text" name="name" autocomplete="name" required autofocus class="w-full rounded-xl border border-[#dce4dc] bg-white px-4 py-3 text-sm text-[#20372b] outline-none transition placeholder:text-[#a2aaa3] focus:border-[#58836a] focus:ring-4 focus:ring-[#548365]/10" placeholder="Nama kamu" />
                <p v-if="form.errors.name" class="mt-1.5 text-xs text-rose-700">{{ form.errors.name }}</p>
            </div>

            <div>
                <label for="email" class="mb-2 block text-sm font-medium text-[#35483c]">Alamat email</label>
                <input id="email" v-model="form.email" type="email" name="email" autocomplete="email" required class="w-full rounded-xl border border-[#dce4dc] bg-white px-4 py-3 text-sm text-[#20372b] outline-none transition placeholder:text-[#a2aaa3] focus:border-[#58836a] focus:ring-4 focus:ring-[#548365]/10" placeholder="nama@email.com" />
                <p v-if="form.errors.email" class="mt-1.5 text-xs text-rose-700">{{ form.errors.email }}</p>
            </div>

            <div>
                <label for="password" class="mb-2 block text-sm font-medium text-[#35483c]">Kata sandi</label>
                <input id="password" v-model="form.password" type="password" name="password" autocomplete="new-password" required class="w-full rounded-xl border border-[#dce4dc] bg-white px-4 py-3 text-sm text-[#20372b] outline-none transition placeholder:text-[#a2aaa3] focus:border-[#58836a] focus:ring-4 focus:ring-[#548365]/10" placeholder="Minimal 12 karakter" />
                <p v-if="form.errors.password" class="mt-1.5 text-xs text-rose-700">{{ form.errors.password }}</p>
            </div>

            <div>
                <label for="password_confirmation" class="mb-2 block text-sm font-medium text-[#35483c]">Ulangi kata sandi</label>
                <input id="password_confirmation" v-model="form.password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required class="w-full rounded-xl border border-[#dce4dc] bg-white px-4 py-3 text-sm text-[#20372b] outline-none transition placeholder:text-[#a2aaa3] focus:border-[#58836a] focus:ring-4 focus:ring-[#548365]/10" placeholder="Masukkan ulang kata sandi" />
                <p v-if="form.errors.password_confirmation" class="mt-1.5 text-xs text-rose-700">{{ form.errors.password_confirmation }}</p>
            </div>

            <p class="pt-1 text-xs leading-5 text-[#7b857d]">Dengan mendaftar, kamu setuju menggunakan Rentara sesuai ketentuan layanan dan kebijakan privasi.</p>
            <button type="submit" :disabled="form.processing" class="flex min-h-12 w-full items-center justify-center rounded-xl bg-[#1c3d33] px-5 text-sm font-semibold text-white transition hover:bg-[#285744] focus:outline-none focus:ring-2 focus:ring-[#337b63] focus:ring-offset-2 disabled:cursor-wait disabled:opacity-60">
                {{ form.processing ? 'Membuat akun…' : 'Buat akun' }}
            </button>
        </form>
    </AuthLayout>
</template>
