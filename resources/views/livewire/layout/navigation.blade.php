<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component {
    public function logout(Logout $logout): void
    {
        $logout();
        $this->redirect('/', navigate: true);
    }
}; ?>

@php
    $user = auth()->user();
    $workspace = request()->attributes->get('currentWorkspace');
    $platform = request()->routeIs('admin.*');
@endphp

<div x-data="{
    drawer: false,
    profile: false,
    openDrawer() { this.drawer = true; this.$nextTick(() => this.$refs.drawerClose.focus()); },
    closeDrawer() { this.drawer = false; this.$nextTick(() => this.$refs.mobileTrigger.focus()); },
    openProfile() { this.profile = true; },
    closeProfile(restoreFocus = false) { this.profile = false; if (restoreFocus) this.$nextTick(() => this.$refs.profileTrigger.focus()); },
    trapDrawerFocus(event) {
        const focusable = [...this.$refs.drawer.querySelectorAll('a[href], button:not([disabled]), [tabindex]:not([tabindex=\'-1\'])')].filter((element) => !element.hidden);
        const first = focusable[0]; const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    }
}">
    <a href="#main-content" class="sr-only fixed left-4 top-4 z-50 rounded bg-white px-3 py-2 text-rentara-navy shadow focus:not-sr-only">Lewati navigasi</a>

    <aside class="fixed inset-y-0 left-0 z-40 hidden w-72 flex-col bg-rentara-navy px-4 py-6 lg:flex" aria-label="Navigasi utama">
        <a href="{{ $platform ? route('admin.dashboard') : route('app.dashboard') }}" class="mb-9 inline-flex w-fit" wire:navigate>
            <x-brand-logo variant="full" theme="dark" class="h-10 w-auto" />
        </a>
        <p class="px-3 text-xs font-bold uppercase tracking-widest text-slate-400">{{ $platform ? 'Platform' : 'Operasional' }}</p>
        <nav class="mt-3 space-y-1">
            <a href="{{ $platform ? route('admin.dashboard') : route('app.dashboard') }}" class="rentara-nav-link {{ request()->routeIs($platform ? 'admin.dashboard' : 'app.dashboard') ? 'rentara-nav-link-active' : '' }}" wire:navigate aria-current="{{ request()->routeIs($platform ? 'admin.dashboard' : 'app.dashboard') ? 'page' : 'false' }}">Dashboard</a>
            @if (! $platform && $workspace && $user->can('update', $workspace))
                <a href="{{ route('app.workspace.settings') }}" class="rentara-nav-link {{ request()->routeIs('app.workspace.settings') ? 'rentara-nav-link-active' : '' }}" wire:navigate>Pengaturan ruang kerja</a>
            @endif
            @if (! $platform && $workspace && $user->can('manageMembers', $workspace))
                <a href="{{ route('app.members') }}" class="rentara-nav-link {{ request()->routeIs('app.members') ? 'rentara-nav-link-active' : '' }}" wire:navigate>Anggota</a>
            @endif
        </nav>
        <div class="mt-auto rounded-xl border border-white/10 bg-white/5 p-3 text-xs text-slate-300">{{ config('app.brand.tagline') }}</div>
    </aside>

    <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur lg:pl-72">
        <div class="flex h-16 items-center justify-between px-4 sm:px-6 lg:px-8">
            <button x-ref="mobileTrigger" type="button" class="rounded-lg p-2 text-rentara-navy lg:hidden" @click="openDrawer()" aria-label="Buka navigasi" :aria-expanded="drawer.toString()" aria-controls="mobile-navigation">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
            </button>
            <div class="lg:hidden"><x-brand-logo variant="mark" class="h-9 w-9" /></div>
            <div class="hidden min-w-0 lg:block">@if ($workspace && ! $platform)<p class="truncate text-sm font-semibold text-rentara-navy">{{ $workspace->name }}</p><p class="text-xs text-slate-500">Ruang kerja aktif</p>@else<p class="text-sm text-slate-500">{{ $platform ? 'Administrasi platform' : 'Operasional properti' }}</p>@endif</div>
            <div class="ml-auto flex items-center gap-2">
                <button type="button" disabled class="rounded-lg p-2 text-slate-400" aria-label="Notifikasi belum tersedia" title="Notifikasi tersedia pada rilis mendatang"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-width="2" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 00-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m2 0v1a2 2 0 004 0v-1" /></svg></button>
                @if ($workspace && ! $platform)<button type="button" disabled class="hidden rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-500 sm:block" title="Pergantian ruang kerja tersedia pada rilis mendatang">{{ $workspace->name }} · Ganti nanti</button>@endif
                <div class="relative" @keydown.escape.stop="closeProfile(true)">
                    <button x-ref="profileTrigger" type="button" @click="profile ? closeProfile() : openProfile()" class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-left" :aria-expanded="profile.toString()" aria-controls="profile-navigation" aria-label="Menu profil {{ $user->name }}"><span class="grid h-8 w-8 place-items-center rounded-full bg-rentara-blue text-sm font-bold text-white">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span><span class="hidden max-w-32 truncate text-sm font-semibold text-rentara-navy sm:block">{{ $user->name }}</span></button>
                    <div x-cloak x-show="profile" @click.outside="closeProfile()" id="profile-navigation" class="absolute right-0 mt-2 w-48 rounded-xl border border-slate-200 bg-white p-1 shadow-lg">
                        <ul>
                            <li><a href="{{ route('profile') }}" class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-50" wire:navigate>Profil</a></li>
                            <li><button wire:click="logout" class="w-full rounded-lg px-3 py-2 text-left text-sm text-rentara-danger hover:bg-red-50">Keluar</button></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div x-cloak x-show="drawer" class="relative z-50 lg:hidden" id="mobile-navigation" role="dialog" aria-modal="true" aria-label="Navigasi utama" @keydown.escape.window="drawer && closeDrawer()" @keydown.tab="trapDrawerFocus($event)">
        <div class="fixed inset-0 bg-slate-950/50" @click="closeDrawer()"></div>
        <aside x-ref="drawer" class="fixed inset-y-0 left-0 flex w-72 flex-col bg-rentara-navy px-4 py-6 shadow-xl">
            <div class="mb-8 flex items-center justify-between"><x-brand-logo variant="full" theme="dark" class="h-10 w-auto" /><button x-ref="drawerClose" type="button" @click="closeDrawer()" class="rounded p-2 text-white" aria-label="Tutup navigasi">×</button></div>
            <nav class="space-y-1"><a href="{{ $platform ? route('admin.dashboard') : route('app.dashboard') }}" class="rentara-nav-link rentara-nav-link-active" wire:navigate>Dashboard</a>@if (! $platform && $workspace && $user->can('update', $workspace))<a href="{{ route('app.workspace.settings') }}" class="rentara-nav-link" wire:navigate>Pengaturan ruang kerja</a>@endif @if (! $platform && $workspace && $user->can('manageMembers', $workspace))<a href="{{ route('app.members') }}" class="rentara-nav-link" wire:navigate>Anggota</a>@endif</nav>
        </aside>
    </div>
</div>
