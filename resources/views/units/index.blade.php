<x-app-layout>
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <p class="text-sm font-semibold text-rentara-teal">Ruang kerja aktif: {{ $workspace->name }}</p>
        <div class="mt-2 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="rentara-page-title">Unit · {{ $property->name }}</h1>
                <p class="mt-2 text-slate-600">Kelola unit sewa pada properti {{ $property->name }}.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('app.properties.show', $property) }}" class="text-sm font-semibold text-slate-600 hover:underline">Kembali ke properti</a>
                <a href="{{ route('app.properties.units.create', $property) }}" class="rounded-lg bg-rentara-blue px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90">Tambah unit</a>
            </div>
        </div>

        @if (session('status'))
            <p class="mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800" role="status">{{ session('status') }}</p>
        @endif

        <section class="rentara-card mt-6 overflow-x-auto">
            @if ($units->isEmpty())
                <div class="px-6 py-12 text-center">
                    <h2 class="font-display text-lg font-bold text-rentara-navy">Belum ada unit</h2>
                    <p class="mt-2 text-sm text-slate-600">Tambahkan unit pertama untuk properti ini.</p>
                    <a href="{{ route('app.properties.units.create', $property) }}" class="mt-4 inline-block rounded-lg bg-rentara-blue px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90">Tambah unit</a>
                </div>
            @else
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead>
                        <tr class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th scope="col" class="px-4 py-3">Nomor</th>
                            <th scope="col" class="px-4 py-3">Nama</th>
                            <th scope="col" class="px-4 py-3">Lokasi</th>
                            <th scope="col" class="px-4 py-3">Harga</th>
                            <th scope="col" class="px-4 py-3">Status</th>
                            <th scope="col" class="px-4 py-3"><span class="sr-only">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($units as $unit)
                            <tr>
                                <td class="px-4 py-3 font-semibold text-rentara-navy">{{ $unit->unit_number }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $unit->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $unit->building?->name ?? $unit->block?->name ?? $unit->floor?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-600">Rp{{ number_format($unit->rental_price, 0, ',', '.') }}/{{ $unit->rental_period->value === 'daily' ? 'hari' : ($unit->rental_period->value === 'yearly' ? 'tahun' : 'bulan') }}</td>
                                <td class="px-4 py-3">
                                    @if ($unit->status->value === 'available')
                                        <span class="rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-800">Tersedia</span>
                                    @elseif ($unit->status->value === 'occupied')
                                        <span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-800">Terisi</span>
                                    @else
                                        <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">Perawatan</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <span class="inline-flex items-center gap-3">
                                        <a href="{{ route('app.properties.units.edit', [$property, $unit]) }}" class="font-semibold text-rentara-blue hover:underline">Ubah</a>
                                        <form method="POST" action="{{ route('app.properties.units.destroy', [$property, $unit]) }}" class="inline" onsubmit="return confirm('Hapus unit {{ $unit->unit_number }}? Tindakan ini dapat dipulihkan.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="font-semibold text-red-700 hover:underline">Hapus</button>
                                        </form>
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="px-4 py-4">{{ $units->links() }}</div>
            @endif
        </section>
    </div>
</x-app-layout>
