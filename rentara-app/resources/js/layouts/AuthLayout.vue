<script setup>
import { Link, usePage } from '@inertiajs/vue3';

defineProps({
    eyebrow: {
        type: String,
        default: 'Selamat datang di Rentara',
    },
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        required: true,
    },
});

const page = usePage();
</script>

<template>
    <div class="min-h-screen bg-[#f4f6f2] text-[#1d3027]">
        <header class="mx-auto flex max-w-7xl items-center justify-between px-5 py-5 sm:px-8 lg:px-12">
            <Link href="/" class="flex items-center gap-2.5 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#337b63] focus:ring-offset-4">
                <span class="grid size-9 place-items-center rounded-xl bg-[#193b32] text-white">
                    <svg viewBox="0 0 24 24" fill="none" class="size-5" aria-hidden="true">
                        <path d="M4 11.5 12 5l8 6.5V20h-6v-5h-4v5H4v-8.5Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" />
                    </svg>
                </span>
                <span class="text-xl font-semibold tracking-[-0.06em]">rentara<span class="text-[#4e876e]">.</span></span>
            </Link>
            <div class="flex items-center gap-4">
                <Link v-if="page.props.auth?.user" href="/notifications" class="text-sm font-medium text-[#647069] transition hover:text-[#193b32]">Notifikasi <span v-if="page.props.auth.unread_notifications_count" class="rounded-full bg-[#dcebdc] px-1.5 py-0.5 text-xs">{{ page.props.auth.unread_notifications_count }}</span></Link>
                <Link href="/" class="text-sm font-medium text-[#647069] transition hover:text-[#193b32]">Kembali ke beranda</Link>
            </div>
        </header>

        <main class="mx-auto grid max-w-7xl gap-10 px-5 pb-14 pt-6 sm:px-8 lg:grid-cols-[0.9fr_1.1fr] lg:items-center lg:gap-20 lg:px-12 lg:pb-24 lg:pt-12">
            <section class="hidden max-w-lg lg:block">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#63816a]">{{ eyebrow }}</p>
                <h1 class="mt-4 text-5xl font-medium leading-[1.05] tracking-[-0.07em] text-[#20372b]">{{ title }}</h1>
                <p class="mt-5 text-base leading-7 text-[#69766e]">{{ description }}</p>

                <div class="mt-10 rounded-[1.75rem] bg-[#1c3d33] p-7 text-white shadow-xl shadow-[#1d392e]/10">
                    <div class="flex items-center gap-3">
                        <span class="grid size-10 place-items-center rounded-xl bg-white/10 text-[#d7e5d6]">
                            <svg viewBox="0 0 24 24" fill="none" class="size-5" aria-hidden="true"><path d="M5 12.5 9.3 17 19 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        </span>
                        <span class="text-sm font-semibold">Hunian dan pengelolaan, satu tempat.</span>
                    </div>
                    <p class="mt-4 text-sm leading-6 text-[#cad9cb]">Temukan kos atau kontrakan, ajukan dengan jelas, dan ikuti proses sewanya dengan tenang.</p>
                </div>
            </section>

            <section class="mx-auto w-full max-w-xl">
                <div class="mb-7 lg:hidden">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#63816a]">{{ eyebrow }}</p>
                    <h1 class="mt-3 text-3xl font-medium tracking-[-0.06em] text-[#20372b]">{{ title }}</h1>
                    <p class="mt-2 text-sm leading-6 text-[#69766e]">{{ description }}</p>
                </div>
                <div class="rounded-[1.75rem] border border-[#e7ebe4] bg-white p-6 shadow-[0_24px_70px_-45px_rgba(27,53,39,0.34)] sm:p-9">
                    <slot />
                </div>
            </section>
        </main>
    </div>
</template>
