<x-app-layout>
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <p class="text-sm font-semibold text-rentara-teal">Ruang kerja aktif: {{ $workspace->name }}</p>
        <div class="mt-2 flex flex-wrap items-end justify-between gap-4"><div><h1 class="rentara-page-title">Ringkasan properti Anda</h1><p class="mt-2 text-slate-600">Pantau operasional {{ $workspace->name }} dari satu tempat.</p></div><a href="{{ route('app.properties.index') }}" class="rounded-lg bg-rentara-blue px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90">Kelola properti</a></div>
        <section class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan operasional">
            @foreach (['Properti' => 'Properti', 'Unit' => 'Unit', 'Penyewa' => 'Penyewa', 'Tagihan tertunda' => 'Tagihan tertunda'] as $label => $description)
                <article class="rentara-card"><p class="text-sm font-semibold text-slate-500">{{ $label }}</p><p class="mt-4 font-display text-3xl font-extrabold text-rentara-navy">—</p><p class="mt-2 text-xs text-slate-500">{{ in_array($label, ['Properti', 'Unit'], true) ? $description.' dapat dikelola sekarang.' : $description.' akan tersedia pada rilis berikutnya.' }}</p></article>
            @endforeach
        </section>
        <section class="rentara-card mt-6"><h2 class="font-display text-lg font-bold text-rentara-navy">Belum ada data operasional</h2><p class="mt-2 text-sm text-slate-600">Properti dan unit dapat dikelola sekarang. Fitur penyewa dan tagihan akan tersedia pada rilis berikutnya.</p></section>
    </div>
</x-app-layout>
