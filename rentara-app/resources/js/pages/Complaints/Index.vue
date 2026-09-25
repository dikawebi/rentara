<script setup>
import { Head, Link } from '@inertiajs/vue3';
defineProps({ complaints: Object, audience: String });
</script>
<template>
    <Head title="Keluhan" />
    <main class="mx-auto max-w-4xl p-6"><div class="mb-6 flex justify-between"><h1 class="text-2xl font-semibold">Keluhan saya</h1><Link href="/complaints/create" class="rounded-xl bg-[#47785a] px-4 py-2 text-sm text-white">Buat keluhan</Link></div><div class="space-y-3"><Link v-for="item in complaints.data" :key="item.id" :href="item.show_url" class="block rounded-2xl border p-4"><div class="flex justify-between"><b>{{ item.title }}</b><span>{{ item.status }}</span></div><small>{{ item.unit }} · {{ item.priority }}</small></Link></div></main>
</template>
