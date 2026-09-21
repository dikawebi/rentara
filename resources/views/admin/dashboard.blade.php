<x-app-layout>
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <p class="text-sm font-semibold text-rentara-teal">Platform {{ config('app.brand.name') }}</p><h1 class="rentara-page-title mt-2">Administrasi platform</h1><p class="mt-2 text-slate-600">Ringkasan layanan dan ruang kerja di platform.</p>
        <section class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan platform">@foreach (['Pengguna', 'Ruang kerja', 'Properti', 'Menunggu peninjauan'] as $label)<article class="rentara-card"><p class="text-sm font-semibold text-slate-500">{{ $label }}</p><p class="mt-4 font-display text-3xl font-extrabold text-rentara-navy">—</p><p class="mt-2 text-xs text-slate-500">Data tersedia pada rilis mendatang.</p></article>@endforeach</section>
        <section class="rentara-card mt-6"><h2 class="font-display text-lg font-bold text-rentara-navy">Belum ada tindakan untuk ditinjau</h2><p class="mt-2 text-sm text-slate-600">Alur peninjauan platform akan tersedia pada rilis berikutnya.</p></section>
    </div>
</x-app-layout>
