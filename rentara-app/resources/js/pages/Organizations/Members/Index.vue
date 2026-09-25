<script setup>
import { computed } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AuthLayout from '../../../layouts/AuthLayout.vue';

const props = defineProps({
    organization: { type: Object, required: true },
    members: { type: Array, required: true },
    invitations: { type: Array, required: true },
    inviteRoles: { type: Array, required: true },
});

const page = usePage();
const form = useForm({ email: '', role: props.inviteRoles[0] ?? 'staff' });
const roleLabels = { owner: 'Owner', manager: 'Manager', staff: 'Staf' };

const flashMessage = computed(() => ({
    'organization-invitation-sent': 'Undangan dikirim melalui email.',
    'organization-invitation-revoked': 'Undangan berhasil dibatalkan.',
    'organization-member-removed': 'Akses anggota telah dicabut.',
}[page.props.flash?.status] ?? null));

function sendInvitation() {
    form.post(`/organizations/${props.organization.id}/invitations`, {
        preserveScroll: true,
        onSuccess: () => form.reset('email'),
    });
}

function removeMember(member) {
    if (window.confirm(`Cabut akses ${member.name} dari workspace ini?`)) {
        router.delete(`/organizations/${props.organization.id}/members/${member.id}`, { preserveScroll: true });
    }
}

function revokeInvitation(invitation) {
    if (window.confirm(`Batalkan undangan untuk ${invitation.email}?`)) {
        router.delete(`/organizations/${props.organization.id}/invitations/${invitation.id}`, { preserveScroll: true });
    }
}

function formatDate(value) {
    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'Asia/Jakarta',
    }).format(new Date(value));
}
</script>

<template>
    <Head :title="`Anggota ${organization.name} — Rentara`" />
    <AuthLayout eyebrow="Workspace organisasi" :title="organization.name" description="Atur anggota yang dapat membantu mengelola properti dan permintaan penyewa.">
        <div class="mb-6 flex items-center justify-between gap-3">
            <div>
                <h2 class="text-2xl font-semibold tracking-[-0.05em] text-[#20372b]">Anggota workspace</h2>
                <p class="mt-1.5 text-sm text-[#768078]">Undang pemilik/pengelola lain dengan peran yang sesuai.</p>
            </div>
            <Link href="/dashboard" class="shrink-0 text-xs font-semibold text-[#47785a] hover:text-[#244d39]">Dashboard</Link>
        </div>

        <div v-if="flashMessage" role="status" class="mb-5 rounded-xl bg-[#edf5ed] px-4 py-3 text-sm text-[#315e40]">
            {{ flashMessage }}
        </div>

        <form class="space-y-4 rounded-2xl border border-[#e3e9e1] bg-[#f7f9f5] p-4 sm:p-5" @submit.prevent="sendInvitation">
            <h3 class="text-sm font-semibold text-[#35483c]">Undang anggota</h3>
            <div>
                <label for="invite-email" class="mb-2 block text-sm font-medium text-[#35483c]">Alamat email</label>
                <input id="invite-email" v-model="form.email" type="email" name="email" autocomplete="email" required class="w-full rounded-xl border border-[#dce4dc] bg-white px-4 py-3 text-sm text-[#20372b] outline-none transition placeholder:text-[#a2aaa3] focus:border-[#58836a] focus:ring-4 focus:ring-[#548365]/10" placeholder="anggota@email.com" />
                <p v-if="form.errors.email" class="mt-1.5 text-xs text-rose-700">{{ form.errors.email }}</p>
            </div>
            <div>
                <label for="invite-role" class="mb-2 block text-sm font-medium text-[#35483c]">Peran</label>
                <select id="invite-role" v-model="form.role" name="role" class="w-full rounded-xl border border-[#dce4dc] bg-white px-4 py-3 text-sm text-[#20372b] outline-none transition focus:border-[#58836a] focus:ring-4 focus:ring-[#548365]/10">
                    <option v-for="role in inviteRoles" :key="role" :value="role">{{ roleLabels[role] }}</option>
                </select>
                <p v-if="form.errors.role" class="mt-1.5 text-xs text-rose-700">{{ form.errors.role }}</p>
            </div>
            <button type="submit" :disabled="form.processing" class="flex min-h-11 w-full items-center justify-center rounded-xl bg-[#1c3d33] px-4 text-sm font-semibold text-white transition hover:bg-[#285744] focus:outline-none focus:ring-2 focus:ring-[#337b63] focus:ring-offset-2 disabled:cursor-wait disabled:opacity-60">
                {{ form.processing ? 'Mengirim undangan…' : 'Kirim undangan email' }}
            </button>
        </form>

        <section class="mt-8" aria-labelledby="members-heading">
            <div class="mb-3 flex items-center justify-between">
                <h3 id="members-heading" class="text-sm font-semibold text-[#35483c]">Anggota aktif</h3>
                <span class="text-xs text-[#879088]">{{ members.length }}</span>
            </div>
            <ul class="divide-y divide-[#edf0eb] rounded-2xl border border-[#e7ebe5] px-4">
                <li v-for="member in members" :key="member.id" class="flex items-center justify-between gap-3 py-3.5">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-[#35483c]">{{ member.name }}</p>
                        <p class="mt-0.5 truncate text-xs text-[#7a857d]">{{ member.email }}</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <span class="rounded-full bg-[#f0f4ee] px-2.5 py-1 text-[11px] font-medium text-[#607269]">{{ roleLabels[member.role] }}</span>
                        <button v-if="member.can_remove" type="button" class="rounded-lg px-2 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-50 focus:outline-none focus:ring-2 focus:ring-rose-400" @click="removeMember(member)">Cabut</button>
                    </div>
                </li>
            </ul>
        </section>

        <section class="mt-7" aria-labelledby="invitations-heading">
            <div class="mb-3 flex items-center justify-between">
                <h3 id="invitations-heading" class="text-sm font-semibold text-[#35483c]">Undangan menunggu</h3>
                <span class="text-xs text-[#879088]">{{ invitations.length }}</span>
            </div>
            <p v-if="!invitations.length" class="rounded-2xl border border-dashed border-[#d7e0d6] px-4 py-4 text-sm text-[#748077]">Belum ada undangan aktif.</p>
            <ul v-else class="divide-y divide-[#edf0eb] rounded-2xl border border-[#e7ebe5] px-4">
                <li v-for="invitation in invitations" :key="invitation.id" class="flex items-center justify-between gap-3 py-3.5">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-[#35483c]">{{ invitation.email }}</p>
                        <p class="mt-0.5 text-xs text-[#7a857d]">{{ roleLabels[invitation.role] }} · Kedaluwarsa {{ formatDate(invitation.expires_at) }}</p>
                    </div>
                    <button v-if="invitation.can_revoke" type="button" class="shrink-0 rounded-lg px-2 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-50 focus:outline-none focus:ring-2 focus:ring-rose-400" @click="revokeInvitation(invitation)">Batalkan</button>
                </li>
            </ul>
        </section>
    </AuthLayout>
</template>
