<x-app-layout>
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <p class="text-sm font-semibold text-rentara-teal">Ruang kerja aktif: {{ $w->name }}</p>
        <div class="mt-2 flex flex-wrap items-end justify-between gap-4">
            <div><h1 class="rentara-page-title">Kontrak sewa</h1><p class="mt-2 text-slate-600">Kelola kontrak dan status hunian unit.</p></div>
            <a href="{{ route('app.contracts.create') }}" class="rounded-lg bg-rentara-blue px-4 py-2.5 text-sm font-semibold text-white">Tambah kontrak</a>
        </div>
        @if(session('status'))<p class="mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800" role="status">{{ session('status') }}</p>@endif
        <section class="rentara-card mt-6 overflow-x-auto">
            @if($cs->isEmpty())
                <div class="px-6 py-12 text-center"><h2 class="font-display text-lg font-bold text-rentara-navy">Belum ada kontrak</h2><p class="mt-2 text-sm text-slate-600">Buat kontrak pertama untuk mulai mengelola hunian.</p></div>
            @else
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm"><caption class="sr-only">Daftar kontrak sewa</caption><thead><tr class="text-xs font-semibold uppercase tracking-wide text-slate-500"><th scope="col" class="px-4 py-3">Nomor</th><th scope="col" class="px-4 py-3">Unit</th><th scope="col" class="px-4 py-3">Tenant</th><th scope="col" class="px-4 py-3">Periode</th><th scope="col" class="px-4 py-3">Status</th></tr></thead><tbody class="divide-y divide-slate-100">
                @foreach($cs as $contract)<tr><td class="px-4 py-3 font-semibold"><a class="text-rentara-blue hover:underline" href="{{ route('app.contracts.show', $contract) }}">{{ $contract->contract_number }}</a></td><td class="px-4 py-3">{{ $contract->unit?->unit_number ?? '—' }}</td><td class="px-4 py-3">{{ $contract->tenants->pluck('name')->join(', ') ?: 'Belum ditambahkan' }}</td><td class="px-4 py-3">{{ $contract->start_date->format('d/m/Y') }} – {{ $contract->end_date->format('d/m/Y') }}</td><td class="px-4 py-3">{{ ['draft'=>'Draft','pending'=>'Menunggu','active'=>'Aktif','expiring'=>'Akan berakhir','completed'=>'Selesai','terminated'=>'Dihentikan','cancelled'=>'Dibatalkan'][$contract->status->value] ?? $contract->status->value }}</td></tr>@endforeach
                </tbody></table><div class="px-4 py-4">{{ $cs->links() }}</div>
            @endif
        </section>
    </div>
</x-app-layout>
