<x-app-layout>
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <p class="text-sm font-semibold text-rentara-teal">Ruang kerja aktif: {{ $workspace->name }}</p>
        <div class="mt-2 flex flex-wrap items-end justify-between gap-4"><div><h1 class="rentara-page-title">Ringkasan properti Anda</h1><p class="mt-2 text-slate-600">Pantau operasional {{ $workspace->name }} dari satu tempat.</p></div><button disabled class="rounded-lg bg-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-500" title="Manajemen properti tersedia pada Rilis 1">Tambah properti · Rilis 1</button></div>
        <section class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan operasional">
            @foreach (['Properti' => 'Properti', 'Unit' => 'Unit', 'Penyewa' => 'Penyewa', 'Tagihan tertunda' => 'Tagihan tertunda'] as $label => $description)
                <article class="rentara-card"><p class="text-sm font-semibold text-slate-500">{{ $label }}</p><p class="mt-4 font-display text-3xl font-extrabold text-rentara-navy">—</p><p class="mt-2 text-xs text-slate-500">{{ $description }} akan tersedia pada Rilis 1.</p></article>
            @endforeach
        </section>
        <section class="rentara-card mt-6"><h2 class="font-display text-lg font-bold text-rentara-navy">Belum ada data operasional</h2><p class="mt-2 text-sm text-slate-600">Properti, unit, penyewa, dan tagihan akan muncul di sini saat fitur tersebut dirilis.</p></section>
    </div>
</x-app-layout>
