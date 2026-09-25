<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AuthLayout from '../../layouts/AuthLayout.vue';

const page = usePage();
const form = useForm({ email: '', password: '', remember: false });

function submit() {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Masuk — Rentara" />
    <AuthLayout eyebrow="Akun Rentara" title="Senang melihatmu kembali." description="Masuk untuk melanjutkan pencarian hunian atau mengelola properti.">
        <div class="mb-7">
            <h2 class="text-2xl font-semibold tracking-[-0.05em] text-[#20372b]">Masuk ke akun</h2>
            <p class="mt-1.5 text-sm text-[#768078]">Belum punya akun? <Link href="/register" class="font-semibold text-[#367052] hover:text-[#244d39]">Daftar</Link></p>
        </div>

        <div v-if="page.props.flash?.status" role="status" class="mb-5 rounded-xl bg-[#edf5ed] px-4 py-3 text-sm text-[#315e40]">
            {{ page.props.flash.status === 'passwords.reset' ? 'Kata sandi berhasil diperbarui. Silakan masuk.' : page.props.flash.status }}
        </div>

        <form class="space-y-5" @submit.prevent="submit">
            <div>
                <label for="email" class="mb-2 block text-sm font-medium text-[#35483c]">Alamat email</label>
                <input id="email" v-model="form.email" type="email" name="email" autocomplete="email" required autofocus class="w-full rounded-xl border border-[#dce4dc] bg-white px-4 py-3 text-sm text-[#20372b] outline-none transition placeholder:text-[#a2aaa3] focus:border-[#58836a] focus:ring-4 focus:ring-[#548365]/10" placeholder="nama@email.com" />
                <p v-if="form.errors.email" class="mt-1.5 text-xs text-rose-700">{{ form.errors.email }}</p>
            </div>

            <div>
                <div class="mb-2 flex items-center justify-between gap-3">
                    <label for="password" class="text-sm font-medium text-[#35483c]">Kata sandi</label>
                    <Link href="/forgot-password" class="text-xs font-semibold text-[#47785a] hover:text-[#244d39]">Lupa kata sandi?</Link>
                </div>
                <input id="password" v-model="form.password" type="password" name="password" autocomplete="current-password" required class="w-full rounded-xl border border-[#dce4dc] bg-white px-4 py-3 text-sm text-[#20372b] outline-none transition placeholder:text-[#a2aaa3] focus:border-[#58836a] focus:ring-4 focus:ring-[#548365]/10" placeholder="Masukkan kata sandi" />
                <p v-if="form.errors.password" class="mt-1.5 text-xs text-rose-700">{{ form.errors.password }}</p>
            </div>

            <label class="flex cursor-pointer items-center gap-2.5 text-sm text-[#647069]">
                <input v-model="form.remember" type="checkbox" name="remember" class="size-4 rounded border-[#cbd5cb] text-[#326447] focus:ring-[#548365]" />
                Ingat saya
            </label>

            <button type="submit" :disabled="form.processing" class="flex min-h-12 w-full items-center justify-center rounded-xl bg-[#1c3d33] px-5 text-sm font-semibold text-white transition hover:bg-[#285744] focus:outline-none focus:ring-2 focus:ring-[#337b63] focus:ring-offset-2 disabled:cursor-wait disabled:opacity-60">
                {{ form.processing ? 'Memproses…' : 'Masuk' }}
            </button>
        </form>
    </AuthLayout>
</template>
