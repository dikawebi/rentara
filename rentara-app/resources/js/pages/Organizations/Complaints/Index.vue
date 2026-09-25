<script setup>
import { Head, Link } from '@inertiajs/vue3';
defineProps({ organization: Object, complaints: Object });
</script>
<template><Head title="Keluhan organisasi" /><main class="mx-auto max-w-5xl p-6"><h1 class="mb-6 text-2xl font-semibold">Keluhan {{ organization.name }}</h1><div class="space-y-3"><Link v-for="item in complaints.data" :key="item.id" :href="`/organizations/${organization.id}/complaints/${item.id}`" class="block rounded-2xl border p-4"><div class="flex justify-between"><b>{{ item.title }}</b><span>{{ item.status }}</span></div><small>{{ item.tenant }} · {{ item.unit }} · {{ item.priority }}</small></Link></div></main></template>
