<x-app-layout>
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <p class="text-sm font-semibold text-rentara-teal">Ruang kerja aktif: {{ $workspace->name }}</p>
        <div class="mt-2 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="rentara-page-title">Tipe unit</h1>
                <p class="mt-2 text-slate-600">Kelola tipe unit pada ruang kerja {{ $workspace->name }}.</p>
            </div>
            <a href="{{ route('app.unit-types.create') }}" class="rounded-lg bg-rentara-blue px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90">Tambah tipe unit</a>
        </div>

        @if (session('status'))
            <p class="mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800" role="status">{{ session('status') }}</p>
        @endif

        <section class="rentara-card mt-6 overflow-x-auto">
            @if ($unitTypes->isEmpty())
                <div class="px-6 py-12 text-center">
                    <h2 class="font-display text-lg font-bold text-rentara-navy">Belum ada tipe unit</h2>
                    <p class="mt-2 text-sm text-slate-600">Tambahkan tipe unit pertama untuk mengelompokkan unit sewa.</p>
                    <a href="{{ route('app.unit-types.create') }}" class="mt-4 inline-block rounded-lg bg-rentara-blue px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90">Tambah tipe unit</a>
                </div>
            @else
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead>
                        <tr class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th scope="col" class="px-4 py-3">Nama</th>
                            <th scope="col" class="px-4 py-3">Deskripsi</th>
                            <th scope="col" class="px-4 py-3">Kapasitas bawaan</th>
                            <th scope="col" class="px-4 py-3"><span class="sr-only">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($unitTypes as $unitType)
                            <tr>
                                <td class="px-4 py-3 font-semibold text-rentara-navy">{{ $unitType->name }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $unitType->description ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $unitType->default_capacity ?? '—' }}</td>
                                <td class="px-4 py-3 text-right">
                                    <span class="inline-flex items-center gap-3">
                                        <a href="{{ route('app.unit-types.edit', $unitType) }}" class="font-semibold text-rentara-blue hover:underline">Ubah</a>
                                        <form method="POST" action="{{ route('app.unit-types.destroy', $unitType) }}" class="inline" onsubmit="return confirm('Hapus tipe unit {{ $unitType->name }}? Tindakan ini dapat dipulihkan.');">
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
                <div class="px-4 py-4">{{ $unitTypes->links() }}</div>
            @endif
        </section>
    </div>
</x-app-layout>
