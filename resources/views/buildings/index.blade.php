<x-app-layout>
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <p class="text-sm font-semibold text-rentara-teal">Ruang kerja aktif: {{ $workspace->name }}</p>
        <div class="mt-2 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="rentara-page-title">Gedung · {{ $property->name }}</h1>
                <p class="mt-2 text-slate-600">Kelola gedung pada properti {{ $property->name }}.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('app.properties.show', $property) }}" class="text-sm font-semibold text-slate-600 hover:underline">Kembali ke properti</a>
                <a href="{{ route('app.properties.buildings.create', $property) }}" class="rounded-lg bg-rentara-blue px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90">Tambah gedung</a>
            </div>
        </div>

        @if (session('status'))
            <p class="mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800" role="status">{{ session('status') }}</p>
        @endif

        <section class="rentara-card mt-6 overflow-x-auto">
            @if ($buildings->isEmpty())
                <div class="px-6 py-12 text-center">
                    <h2 class="font-display text-lg font-bold text-rentara-navy">Belum ada gedung</h2>
                    <p class="mt-2 text-sm text-slate-600">Tambahkan gedung pertama untuk properti ini.</p>
                    <a href="{{ route('app.properties.buildings.create', $property) }}" class="mt-4 inline-block rounded-lg bg-rentara-blue px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90">Tambah gedung</a>
                </div>
            @else
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead>
                        <tr class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th scope="col" class="px-4 py-3">Nama</th>
                            <th scope="col" class="px-4 py-3">Urutan</th>
                            <th scope="col" class="px-4 py-3">Catatan</th>
                            <th scope="col" class="px-4 py-3"><span class="sr-only">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($buildings as $building)
                            <tr>
                                <td class="px-4 py-3 font-semibold text-rentara-navy">{{ $building->name }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $building->sort_order }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $building->notes ?? '—' }}</td>
                                <td class="px-4 py-3 text-right">
                                    <span class="inline-flex items-center gap-3">
                                        <a href="{{ route('app.properties.buildings.edit', [$property, $building]) }}" class="font-semibold text-rentara-blue hover:underline">Ubah</a>
                                        <form method="POST" action="{{ route('app.properties.buildings.destroy', [$property, $building]) }}" class="inline" onsubmit="return confirm('Hapus gedung {{ $building->name }}? Tindakan ini dapat dipulihkan.');">
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
                <div class="px-4 py-4">{{ $buildings->links() }}</div>
            @endif
        </section>
    </div>
</x-app-layout>
