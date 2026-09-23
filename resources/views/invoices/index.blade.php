<x-app-layout>
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <p class="text-sm font-semibold text-rentara-teal">Ruang kerja aktif: {{ $w->name }}</p>
        <div class="mt-2 flex flex-wrap items-end justify-between gap-4">
            <div><h1 class="rentara-page-title">Tagihan manual</h1><p class="mt-2 text-slate-600">Catat invoice dan status pelunasan secara manual.</p></div>
            @can('create', [App\Models\Invoice::class, $w])
                <a href="{{ route('app.invoices.create') }}" class="rounded-lg bg-rentara-blue px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90">Buat invoice</a>
            @endcan
        </div>
        @if(session('status'))<p class="mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800" role="status">{{ session('status') }}</p>@endif
        <section class="rentara-card mt-6 overflow-x-auto">
            @if($invoices->isEmpty())
                <div class="px-6 py-12 text-center"><h2 class="font-display text-lg font-bold text-rentara-navy">Belum ada invoice</h2><p class="mt-2 text-sm text-slate-600">Buat invoice manual pertama untuk mulai mencatat tagihan.</p></div>
            @else
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm"><caption class="sr-only">Daftar invoice manual</caption><thead><tr class="text-xs font-semibold uppercase tracking-wide text-slate-500"><th scope="col" class="px-4 py-3">Invoice</th><th scope="col" class="px-4 py-3">Tenant</th><th scope="col" class="px-4 py-3">Unit / properti</th><th scope="col" class="px-4 py-3">Periode</th><th scope="col" class="px-4 py-3">Jatuh tempo</th><th scope="col" class="px-4 py-3">Jumlah</th><th scope="col" class="px-4 py-3">Status</th><th scope="col" class="px-4 py-3"><span class="sr-only">Aksi</span></th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($invoices as $invoice)
                            @php($status = $invoice->is_overdue ? 'Terlambat' : ($invoice->status->value === 'paid' ? 'Lunas' : 'Belum dibayar'))
                            <tr><td class="px-4 py-3 font-semibold text-rentara-navy"><a class="text-rentara-blue hover:underline" href="{{ route('app.invoices.show', $invoice) }}">{{ $invoice->invoice_number }}</a><span class="block text-xs font-normal text-slate-500">{{ $invoice->contract?->contract_number ?? '—' }}</span></td><td class="px-4 py-3">{{ $invoice->tenant?->name ?? '—' }}</td><td class="px-4 py-3 text-slate-600">{{ $invoice->contract?->unit?->unit_number ?? '—' }}<span class="block text-xs">{{ $invoice->property?->name ?? '—' }}</span></td><td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ $invoice->period_start->format('d/m/Y') }} – {{ $invoice->period_end->format('d/m/Y') }}</td><td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ $invoice->due_date->format('d/m/Y') }}</td><td class="whitespace-nowrap px-4 py-3 font-semibold">{{ $invoice->currency }} {{ number_format($invoice->amount, 0, ',', '.') }}</td><td class="px-4 py-3"><span class="rounded-full px-3 py-1 text-xs font-semibold {{ $status === 'Lunas' ? 'bg-green-100 text-green-800' : ($status === 'Terlambat' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800') }}">{{ $status }}</span></td><td class="px-4 py-3 text-right"><a class="font-semibold text-rentara-blue hover:underline" href="{{ route('app.invoices.show', $invoice) }}">Lihat</a></td></tr>
                        @endforeach
                    </tbody>
                </table><div class="px-4 py-4">{{ $invoices->links() }}</div>
            @endif
        </section>
    </div>
</x-app-layout>
