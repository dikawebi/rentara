<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AuthLayout from '../../../layouts/AuthLayout.vue';

const props = defineProps({
    organization: { type: Object, required: true },
    role: { type: String, required: true },
    expiresAt: { type: String, required: true },
    token: { type: String, required: true },
});

const form = useForm({});
const roleLabel = props.role === 'manager' ? 'manager' : 'staf';
const formattedExpiry = new Intl.DateTimeFormat('id-ID', {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone: 'Asia/Jakarta',
}).format(new Date(props.expiresAt));

function acceptInvitation() {
    form.post(`/organization-invitations/${props.token}/accept`);
}
</script>

<template>
    <Head title="Terima undangan — Rentara" />
    <AuthLayout eyebrow="Undangan workspace" title="Kamu diundang bergabung." description="Tinjau detail workspace sebelum menerima undangan.">
        <div class="mb-7 grid size-12 place-items-center rounded-2xl bg-[#edf4eb] text-[#47785a]" aria-hidden="true">⌂</div>
        <h2 class="text-2xl font-semibold tracking-[-0.05em] text-[#20372b]">{{ organization.name }}</h2>
        <p class="mt-3 text-sm leading-6 text-[#6c786f]">Kamu akan bergabung sebagai <strong class="font-semibold text-[#35483c]">{{ roleLabel }}</strong>. Undangan berlaku sampai {{ formattedExpiry }} WIB.</p>
        <p class="mt-4 rounded-xl bg-[#f7f9f5] px-4 py-3 text-xs leading-5 text-[#748077]">Undangan hanya dapat diterima oleh akun dengan alamat email yang menerima undangan ini.</p>

        <form class="mt-6" @submit.prevent="acceptInvitation">
            <button type="submit" :disabled="form.processing" class="flex min-h-12 w-full items-center justify-center rounded-xl bg-[#1c3d33] px-5 text-sm font-semibold text-white transition hover:bg-[#285744] focus:outline-none focus:ring-2 focus:ring-[#337b63] focus:ring-offset-2 disabled:cursor-wait disabled:opacity-60">
                {{ form.processing ? 'Menerima undangan…' : 'Terima undangan' }}
            </button>
        </form>
    </AuthLayout>
</template>
