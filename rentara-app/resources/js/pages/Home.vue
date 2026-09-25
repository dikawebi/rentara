<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';

const language = ref('id');

const copy = computed(() => language.value === 'id'
    ? {
        navSearch: 'Cari hunian',
        navOwners: 'Untuk pemilik',
        signIn: 'Masuk',
        eyebrow: 'Temukan hunian di Jakarta',
        headline: 'Ruang baru untuk cerita berikutnya.',
        description: 'Cari kos atau kontrakan yang cocok, lalu jalani proses sewa dengan informasi yang jelas dari awal.',
        explore: 'Jelajahi kawasan',
        ownerCta: 'Saya pemilik properti',
        cardTitle: 'Cari dengan lebih tenang',
        cardDescription: 'Informasi hunian dan proses sewa tersusun rapi dalam satu tempat.',
        stepOne: 'Temukan pilihan',
        stepOneDescription: 'Jelajahi kos dan kontrakan di area Jakarta.',
        stepTwo: 'Ajukan dengan jelas',
        stepTwoDescription: 'Kirim minat dan ikuti status pengajuanmu.',
        stepThree: 'Sepakati bersama',
        stepThreeDescription: 'Tinjau syarat sewa sebelum booking dikonfirmasi.',
        sectionEyebrow: 'Mulai dari kawasan',
        sectionTitle: 'Cari area yang pas untuk harimu.',
        sectionDescription: 'Pilih kawasan Jakarta untuk mulai menjelajahi pilihan hunian.',
        south: 'Jakarta Selatan',
        central: 'Jakarta Pusat',
        west: 'Jakarta Barat',
        east: 'Jakarta Timur',
        north: 'Jakarta Utara',
        areaAction: 'Lihat kawasan',
        ownerTitle: 'Kelola properti tanpa kehilangan fokus.',
        ownerDescription: 'Satu ruang kerja untuk unit, pengajuan penyewa, tagihan, dan keluhan properti.',
        ownerButton: 'Kelola properti bersama Rentara',
        ownerNote: 'Dirancang untuk pemilik kos dan kontrakan di Indonesia.',
        footerTagline: 'Hunian lebih mudah ditemukan. Properti lebih mudah dikelola.',
        footerMarket: 'Marketplace hunian Indonesia',
    }
    : {
        navSearch: 'Find a home',
        navOwners: 'For property owners',
        signIn: 'Sign in',
        eyebrow: 'Find a place in Jakarta',
        headline: 'A new space for your next chapter.',
        description: 'Find a kost or rental that fits, with clear information throughout the rental process.',
        explore: 'Explore neighborhoods',
        ownerCta: 'I own a property',
        cardTitle: 'Find your next place with confidence',
        cardDescription: 'Home details and rental steps, organized in one place.',
        stepOne: 'Explore your options',
        stepOneDescription: 'Browse kosts and rentals across Jakarta.',
        stepTwo: 'Apply with clarity',
        stepTwoDescription: 'Send an application and follow its progress.',
        stepThree: 'Agree together',
        stepThreeDescription: 'Review rental terms before your booking is confirmed.',
        sectionEyebrow: 'Start with a neighborhood',
        sectionTitle: 'Find an area that fits your day.',
        sectionDescription: 'Choose a Jakarta neighborhood to start exploring homes.',
        south: 'South Jakarta',
        central: 'Central Jakarta',
        west: 'West Jakarta',
        east: 'East Jakarta',
        north: 'North Jakarta',
        areaAction: 'Explore area',
        ownerTitle: 'Manage your property and stay focused.',
        ownerDescription: 'One workspace for units, tenant applications, invoices, and property requests.',
        ownerButton: 'Manage properties with Rentara',
        ownerNote: 'Made for kost and rental owners in Indonesia.',
        footerTagline: 'Homes are easier to find. Properties are easier to manage.',
        footerMarket: 'Indonesia home marketplace',
    });

const areas = computed(() => [
    { name: copy.value.south, detail: 'Blok M · Kemang · Tebet', href: '/listings?q=Jakarta%20Selatan', tone: 'from-[#dce8df] to-[#eff1e7]', icon: '✳' },
    { name: copy.value.central, detail: 'Menteng · Cikini · Senen', href: '/listings?q=Jakarta%20Pusat', tone: 'from-[#e9e0d2] to-[#f4eee5]', icon: '⌂' },
    { name: copy.value.west, detail: 'Kebon Jeruk · Palmerah', href: '/listings?q=Jakarta%20Barat', tone: 'from-[#e3e5d8] to-[#eff0e5]', icon: '◒' },
    { name: copy.value.east, detail: 'Rawamangun · Jatinegara', href: '/listings?q=Jakarta%20Timur', tone: 'from-[#e7ddd7] to-[#f2e9e2]', icon: '⌁' },
    { name: copy.value.north, detail: 'Kelapa Gading · Sunter', href: '/listings?q=Jakarta%20Utara', tone: 'from-[#dce5e9] to-[#edf1ed]', icon: '◌' },
]);

function toggleLanguage() {
    language.value = language.value === 'id' ? 'en' : 'id';
}
</script>

<template>
    <Head :title="`Rentara — ${copy.eyebrow}`">
        <meta name="description" :content="copy.description">
    </Head>

    <div class="min-h-screen overflow-hidden bg-[#f7f8f5] text-[#17211f]">
        <header class="relative z-10 mx-auto flex max-w-7xl items-center justify-between px-5 py-5 sm:px-8 lg:px-12">
            <a href="#top" class="flex items-center gap-2.5 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#337b63] focus:ring-offset-4" aria-label="Rentara home">
                <span class="grid size-9 place-items-center rounded-xl bg-[#193b32] text-white">
                    <svg viewBox="0 0 24 24" fill="none" class="size-5" aria-hidden="true">
                        <path d="M4 11.5 12 5l8 6.5V20h-6v-5h-4v5H4v-8.5Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" />
                        <path d="M8.5 9.4h.01M15.5 9.4h.01" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" />
                    </svg>
                </span>
                <span class="text-[1.25rem] font-semibold tracking-[-0.06em]">rentara<span class="text-[#4e876e]">.</span></span>
            </a>

            <nav class="hidden items-center gap-8 md:flex" aria-label="Main navigation">
                <a href="/listings" class="text-sm font-medium text-[#58645e] transition hover:text-[#193b32]">{{ copy.navSearch }}</a>
                <a href="#pemilik" class="text-sm font-medium text-[#58645e] transition hover:text-[#193b32]">{{ copy.navOwners }}</a>
            </nav>

            <div class="flex items-center gap-2 sm:gap-4">
                <button
                    type="button"
                    class="rounded-lg px-2 py-2 text-xs font-semibold tracking-wide text-[#53615a] transition hover:bg-[#e9eee8] focus:outline-none focus:ring-2 focus:ring-[#337b63]"
                    :aria-label="language === 'id' ? 'Switch language to English' : 'Ganti bahasa ke Bahasa Indonesia'"
                    @click="toggleLanguage"
                >
                    {{ language === 'id' ? 'ID' : 'EN' }} <span class="px-1 text-[#a7afa8]">/</span> {{ language === 'id' ? 'EN' : 'ID' }}
                </button>
                <a href="/login" class="hidden rounded-full border border-[#dce2db] px-4 py-2.5 text-sm font-semibold text-[#263b32] transition hover:border-[#9db4a5] sm:inline-flex">
                    {{ copy.signIn }}
                </a>
                <a href="/register" class="inline-flex rounded-full bg-[#193b32] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#245443] focus:outline-none focus:ring-2 focus:ring-[#337b63] focus:ring-offset-2">
                    {{ copy.ownerCta }}
                </a>
            </div>
        </header>

        <main id="top">
            <section class="relative mx-auto grid max-w-7xl items-center gap-12 px-5 pb-20 pt-12 sm:px-8 sm:pb-24 sm:pt-16 lg:grid-cols-[1.02fr_0.98fr] lg:gap-16 lg:px-12 lg:pb-28 lg:pt-20">
                <div class="relative z-10 max-w-2xl">
                    <p class="mb-6 inline-flex items-center gap-2 rounded-full border border-[#dce4dc] bg-white/80 px-3.5 py-2 text-xs font-semibold tracking-[0.08em] text-[#46715b] shadow-sm shadow-[#21372a]/[0.03]">
                        <span class="size-1.5 rounded-full bg-[#75a683]"></span>
                        {{ copy.eyebrow }}
                    </p>
                    <h1 class="max-w-[12ch] text-[3.35rem] font-medium leading-[1.03] tracking-[-0.075em] text-[#1c3028] sm:text-6xl lg:text-[4.55rem]">
                        {{ copy.headline }}
                    </h1>
                    <p class="mt-6 max-w-xl text-base leading-7 text-[#647069] sm:text-lg sm:leading-8">
                        {{ copy.description }}
                    </p>

                    <div class="mt-9 flex flex-col gap-3 sm:flex-row">
                        <a href="/listings" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-full bg-[#193b32] px-6 text-sm font-semibold text-white shadow-[0_8px_20px_-10px_rgba(25,59,50,0.65)] transition hover:-translate-y-0.5 hover:bg-[#245443] focus:outline-none focus:ring-2 focus:ring-[#337b63] focus:ring-offset-2">
                            {{ copy.explore }}
                            <svg viewBox="0 0 20 20" fill="none" class="size-4" aria-hidden="true"><path d="M4 10h12m-5-5 5 5-5 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        </a>
                        <a href="/register" class="inline-flex min-h-12 items-center justify-center rounded-full border border-[#d8e0d8] bg-white/70 px-6 text-sm font-semibold text-[#354b40] transition hover:border-[#9db4a5] hover:bg-white focus:outline-none focus:ring-2 focus:ring-[#337b63] focus:ring-offset-2">
                            {{ copy.ownerCta }}
                        </a>
                    </div>

                    <div class="mt-10 flex items-center gap-3 text-sm text-[#69756e]">
                        <div class="flex -space-x-2" aria-hidden="true">
                            <span class="grid size-8 place-items-center rounded-full border-2 border-[#f7f8f5] bg-[#d9e5d9] text-[10px] font-semibold text-[#385747]">JKT</span>
                            <span class="grid size-8 place-items-center rounded-full border-2 border-[#f7f8f5] bg-[#e9dfd3] text-xs text-[#785c43]">⌂</span>
                            <span class="grid size-8 place-items-center rounded-full border-2 border-[#f7f8f5] bg-[#dfe6e3] text-xs text-[#49665a]">✳</span>
                        </div>
                        <span>{{ language === 'id' ? 'Dibangun untuk kebutuhan hunian Indonesia' : 'Built around the way Indonesia finds a home' }}</span>
                    </div>
                </div>

                <div class="relative mx-auto w-full max-w-[34rem] lg:ml-auto">
                    <div class="absolute -right-10 -top-12 size-48 rounded-full bg-[#e4ebdd] blur-3xl sm:size-64"></div>
                    <div class="absolute -bottom-10 -left-12 size-48 rounded-full bg-[#e5e2d5] blur-3xl sm:size-60"></div>
                    <div class="relative overflow-hidden rounded-[2rem] border border-white/80 bg-[#e7ece4] p-4 shadow-[0_30px_80px_-42px_rgba(34,57,44,0.35)] sm:p-6">
                        <div class="relative min-h-[19rem] overflow-hidden rounded-[1.5rem] bg-gradient-to-br from-[#b8cbb8] via-[#d7dfd2] to-[#ebe6d9] sm:min-h-[22rem]">
                            <div class="absolute inset-0 opacity-40" aria-hidden="true" style="background-image: radial-gradient(#ffffff 1px, transparent 1px); background-size: 18px 18px;"></div>
                            <div class="absolute -right-10 top-8 h-48 w-48 rounded-full border border-white/50 sm:h-64 sm:w-64"></div>
                            <div class="absolute -right-2 top-16 h-36 w-36 rounded-full border border-white/50 sm:top-20 sm:h-48 sm:w-48"></div>
                            <div class="absolute bottom-0 left-0 right-0 h-32 bg-gradient-to-t from-[#637b67]/25 to-transparent"></div>

                            <div class="absolute bottom-0 left-1/2 flex w-[78%] -translate-x-1/2 items-end justify-center gap-2 sm:gap-3" aria-hidden="true">
                                <div class="h-32 w-[27%] rounded-t-[1.2rem] bg-[#f5f2e9] shadow-xl shadow-[#354b3c]/10 sm:h-40">
                                    <div class="grid h-full grid-cols-2 gap-2 p-3 pt-6"><i class="rounded-t-full bg-[#b8c9b4]"></i><i class="rounded-t-full bg-[#c5d0bd]"></i><i class="rounded-t-full bg-[#c5d0bd]"></i><i class="rounded-t-full bg-[#aabda8]"></i></div>
                                </div>
                                <div class="h-44 w-[34%] rounded-t-[1.35rem] bg-[#f9f7ef] shadow-xl shadow-[#354b3c]/10 sm:h-56">
                                    <div class="grid h-full grid-cols-2 gap-2.5 p-3.5 pt-7"><i class="rounded-t-full bg-[#b6c7b2]"></i><i class="rounded-t-full bg-[#d2d8c9]"></i><i class="rounded-t-full bg-[#c8d2c1]"></i><i class="rounded-t-full bg-[#a7bba6]"></i><i class="rounded-t-full bg-[#c5d0bd]"></i><i class="rounded-t-full bg-[#b8c9b4]"></i></div>
                                </div>
                                <div class="h-28 w-[24%] rounded-t-[1.2rem] bg-[#e9e7dc] shadow-xl shadow-[#354b3c]/10 sm:h-36">
                                    <div class="grid h-full grid-cols-2 gap-2 p-3 pt-5"><i class="rounded-t-full bg-[#bacbb5]"></i><i class="rounded-t-full bg-[#cbd3c3]"></i><i class="rounded-t-full bg-[#cbd3c3]"></i><i class="rounded-t-full bg-[#b0c2ad]"></i></div>
                                </div>
                            </div>

                            <div class="absolute left-4 top-4 rounded-2xl border border-white/70 bg-white/85 px-4 py-3 shadow-lg shadow-[#405144]/10 backdrop-blur sm:left-6 sm:top-6">
                                <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-[#6b7d6e]">Rentara · Jakarta</p>
                                <p class="mt-1 text-sm font-semibold text-[#263d31]">{{ language === 'id' ? 'Temukan tempatmu' : 'Find your place' }}</p>
                            </div>

                            <div class="absolute bottom-4 left-4 right-4 rounded-2xl border border-white/70 bg-white/90 p-4 shadow-xl shadow-[#405144]/10 backdrop-blur sm:bottom-6 sm:left-6 sm:right-6 sm:p-5">
                                <div class="flex items-center justify-between gap-4">
                                    <div>
                                        <p class="text-xs font-medium text-[#768078]">{{ copy.cardTitle }}</p>
                                        <p class="mt-1 max-w-xs text-sm font-medium leading-5 text-[#263d31]">{{ copy.cardDescription }}</p>
                                    </div>
                                    <span class="grid size-10 shrink-0 place-items-center rounded-full bg-[#e9f0e8] text-[#3c7256]">
                                        <svg viewBox="0 0 24 24" fill="none" class="size-5" aria-hidden="true"><path d="M5 12.5 9.3 17 19 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-3 divide-x divide-[#dce3da] px-1 pt-5 text-center">
                            <div class="px-2"><p class="text-xs font-semibold text-[#31483b]">01</p><p class="mt-1 text-[10px] leading-4 text-[#748077] sm:text-xs">{{ copy.stepOne }}</p></div>
                            <div class="px-2"><p class="text-xs font-semibold text-[#31483b]">02</p><p class="mt-1 text-[10px] leading-4 text-[#748077] sm:text-xs">{{ copy.stepTwo }}</p></div>
                            <div class="px-2"><p class="text-xs font-semibold text-[#31483b]">03</p><p class="mt-1 text-[10px] leading-4 text-[#748077] sm:text-xs">{{ copy.stepThree }}</p></div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="kawasan" class="scroll-mt-8 border-t border-[#e8ebe5] bg-white/70">
                <div class="mx-auto max-w-7xl px-5 py-20 sm:px-8 sm:py-24 lg:px-12">
                    <div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#66816c]">{{ copy.sectionEyebrow }}</p>
                            <h2 class="mt-3 max-w-xl text-3xl font-medium tracking-[-0.055em] text-[#1e342a] sm:text-4xl">{{ copy.sectionTitle }}</h2>
                        </div>
                        <p class="max-w-sm text-sm leading-6 text-[#737e76]">{{ copy.sectionDescription }}</p>
                    </div>

                    <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                        <a v-for="(area, index) in areas" :key="area.name" :href="area.href" class="group relative min-h-48 overflow-hidden rounded-3xl border border-[#edf0e9] bg-gradient-to-br p-5 transition duration-300 hover:-translate-y-1 hover:shadow-lg hover:shadow-[#20382b]/[0.06] focus:outline-none focus:ring-2 focus:ring-[#337b63]" :class="area.tone">
                            <span class="absolute -right-5 -top-8 text-[8rem] font-light leading-none text-[#4f6b55]/[0.08] transition duration-300 group-hover:scale-110" aria-hidden="true">{{ area.icon }}</span>
                            <span class="relative grid size-10 place-items-center rounded-2xl border border-white/70 bg-white/60 text-[#56755c] shadow-sm" aria-hidden="true">
                                <svg v-if="index === 0" viewBox="0 0 24 24" fill="none" class="size-5"><path d="M12 21s6-5.4 6-11a6 6 0 1 0-12 0c0 5.6 6 11 6 11Z" stroke="currentColor" stroke-width="1.5"/><circle cx="12" cy="10" r="2" stroke="currentColor" stroke-width="1.5"/></svg>
                                <svg v-else viewBox="0 0 24 24" fill="none" class="size-5"><path d="M4 20V9l8-5 8 5v11h-5v-6H9v6H4Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
                            </span>
                            <div class="absolute bottom-5 left-5 right-5">
                                <h3 class="text-base font-semibold tracking-[-0.02em] text-[#294235]">{{ area.name }}</h3>
                                <p class="mt-1 text-xs text-[#68796d]">{{ area.detail }}</p>
                                <span class="mt-4 inline-flex items-center gap-1 text-xs font-semibold text-[#42664e]">{{ copy.areaAction }} <span class="transition group-hover:translate-x-1" aria-hidden="true">→</span></span>
                            </div>
                        </a>
                    </div>
                </div>
            </section>

            <section id="pemilik" class="scroll-mt-8 px-5 py-16 sm:px-8 sm:py-20 lg:px-12">
                <div class="mx-auto flex max-w-7xl flex-col overflow-hidden rounded-[2rem] bg-[#1c3d33] text-white md:flex-row md:items-center md:justify-between">
                    <div class="max-w-2xl px-7 py-10 sm:px-12 sm:py-14">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#a9c6aa]">{{ copy.navOwners }}</p>
                        <h2 class="mt-4 max-w-xl text-3xl font-medium leading-tight tracking-[-0.055em] sm:text-4xl">{{ copy.ownerTitle }}</h2>
                        <p class="mt-4 max-w-lg text-sm leading-6 text-[#d0ddd1] sm:text-base">{{ copy.ownerDescription }}</p>
                        <a href="/register" class="mt-7 inline-flex min-h-12 items-center gap-2 rounded-full bg-[#f0f3eb] px-5 text-sm font-semibold text-[#203e32] transition hover:bg-white focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-[#1c3d33]">
                            {{ copy.ownerButton }}
                            <svg viewBox="0 0 20 20" fill="none" class="size-4" aria-hidden="true"><path d="M4 10h12m-5-5 5 5-5 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        </a>
                        <p class="mt-4 text-xs text-[#b5cbbb]">{{ copy.ownerNote }}</p>
                    </div>
                    <div class="relative hidden h-full min-h-72 w-[34%] items-center justify-center overflow-hidden md:flex" aria-hidden="true">
                        <div class="absolute size-72 rounded-full border border-white/10"></div>
                        <div class="absolute size-52 rounded-full border border-white/10"></div>
                        <div class="relative grid size-36 place-items-center rounded-[2rem] border border-white/20 bg-white/[0.08] shadow-2xl shadow-black/10 backdrop-blur">
                            <svg viewBox="0 0 64 64" fill="none" class="size-20 text-[#d5e4d3]"><path d="M10 29 32 11l22 18v25H37V38H27v16H10V29Z" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round"/><path d="M21 28h.1M43 28h.1" stroke="currentColor" stroke-width="4" stroke-linecap="round"/></svg>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-[#e5e9e2] bg-[#f7f8f5]">
            <div class="mx-auto flex max-w-7xl flex-col gap-4 px-5 py-7 sm:flex-row sm:items-center sm:justify-between sm:px-8 lg:px-12">
                <a href="#top" class="flex items-center gap-2 text-sm font-semibold tracking-[-0.04em] text-[#294235]">rentara<span class="text-[#4e876e]">.</span></a>
                <p class="text-xs text-[#758078]">{{ copy.footerTagline }}</p>
                <p class="text-xs text-[#89928b]">© {{ new Date().getFullYear() }} Rentara · {{ copy.footerMarket }}</p>
            </div>
        </footer>
    </div>
</template>
