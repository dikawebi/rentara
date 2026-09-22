<x-app-layout>
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <p class="text-sm font-semibold text-rentara-teal">Ruang kerja aktif: {{ $workspace->name }}</p>
        <div class="mt-2 flex flex-wrap items-end justify-between gap-4"><h1 class="rentara-page-title">Fasilitas</h1><a href="{{ route('app.amenities.create') }}" class="rounded-lg bg-rentara-blue px-4 py-2.5 text-sm font-semibold text-white">Tambah fasilitas</a></div>
        @if (session('status'))<p class="mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800" role="status">{{ session('status') }}</p>@endif
        <section class="rentara-card mt-6 overflow-x-auto"><table class="min-w-full divide-y divide-slate-200 text-left text-sm"><thead><tr class="text-xs uppercase text-slate-500"><th class="px-4 py-3">Nama</th><th class="px-4 py-3">Deskripsi</th><th class="px-4 py-3">Unit</th><th class="px-4 py-3"></th></tr></thead><tbody class="divide-y divide-slate-100">
        @forelse ($amenities as $amenity)<tr><td class="px-4 py-3 font-semibold">{{ $amenity->name }}</td><td class="px-4 py-3">{{ $amenity->description ?? '—' }}</td><td class="px-4 py-3">{{ $amenity->units_count }}</td><td class="px-4 py-3 text-right"><a class="font-semibold text-rentara-blue" href="{{ route('app.amenities.edit', $amenity) }}">Ubah</a><form class="ml-3 inline" method="POST" action="{{ route('app.amenities.destroy', $amenity) }}">@csrf @method('DELETE')<button class="font-semibold text-red-700">Hapus</button></form></td></tr>@empty
        <tr><td colspan="4" class="px-6 py-12 text-center">Belum ada fasilitas</td></tr>@endforelse</tbody></table><div class="px-4 py-4">{{ $amenities->links() }}</div></section>
    </div>
</x-app-layout>
