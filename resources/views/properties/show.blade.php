<x-app-layout>
    <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
        <p class="text-sm font-semibold text-rentara-teal">Ruang kerja aktif: {{ $workspace->name }}</p>
        <div class="mt-2 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="rentara-page-title">{{ $property->name }}</h1>
                <p class="mt-2 text-slate-600">
                    {{ $property->property_type->value === 'kost' ? 'Kost' : ($property->property_type->value === 'house' ? 'Rumah' : ($property->property_type->value === 'apartment' ? 'Apartemen' : ucfirst($property->property_type->value))) }}
                    @if ($property->status->value === 'active')
                        · <span class="rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-800">Aktif</span>
                    @elseif ($property->status->value === 'inactive')
                        · <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">Nonaktif</span>
                    @else
                        · <span class="rounded-full bg-slate-200 px-3 py-1 text-xs font-semibold text-slate-600">Diarsipkan</span>
                    @endif
                </p>
            </div>
            <a href="{{ route('app.properties.index') }}" class="text-sm font-semibold text-rentara-blue hover:underline">Kembali ke daftar</a>
        </div>

        @if (session('status'))
            <p class="mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800" role="status">{{ session('status') }}</p>
        @endif

        <section class="rentara-card mt-6">
            <dl class="divide-y divide-slate-100 text-sm">
                <div class="grid gap-1 py-3 sm:grid-cols-3">
                    <dt class="font-semibold text-slate-500">Alamat</dt>
                    <dd class="text-slate-800 sm:col-span-2">{{ $property->address ?? '—' }}</dd>
                </div>
                <div class="grid gap-1 py-3 sm:grid-cols-3">
                    <dt class="font-semibold text-slate-500">Kota</dt>
                    <dd class="text-slate-800 sm:col-span-2">{{ $property->city ?? '—' }}</dd>
                </div>
                <div class="grid gap-1 py-3 sm:grid-cols-3">
                    <dt class="font-semibold text-slate-500">Provinsi</dt>
                    <dd class="text-slate-800 sm:col-span-2">{{ $property->province ?? '—' }}</dd>
                </div>
                <div class="grid gap-1 py-3 sm:grid-cols-3">
                    <dt class="font-semibold text-slate-500">Kode pos</dt>
                    <dd class="text-slate-800 sm:col-span-2">{{ $property->postal_code ?? '—' }}</dd>
                </div>
                <div class="grid gap-1 py-3 sm:grid-cols-3">
                    <dt class="font-semibold text-slate-500">Koordinat</dt>
                    <dd class="text-slate-800 sm:col-span-2">{{ $property->latitude ?? '—' }}, {{ $property->longitude ?? '—' }}</dd>
                </div>
                <div class="grid gap-1 py-3 sm:grid-cols-3">
                    <dt class="font-semibold text-slate-500">Telepon</dt>
                    <dd class="text-slate-800 sm:col-span-2">{{ $property->phone ?? '—' }}</dd>
                </div>
                <div class="grid gap-1 py-3 sm:grid-cols-3">
                    <dt class="font-semibold text-slate-500">Surel</dt>
                    <dd class="text-slate-800 sm:col-span-2">{{ $property->email ?? '—' }}</dd>
                </div>
                <div class="grid gap-1 py-3 sm:grid-cols-3">
                    <dt class="font-semibold text-slate-500">Dibuat oleh</dt>
                    <dd class="text-slate-800 sm:col-span-2">{{ $property->creator?->name ?? '—' }}</dd>
                </div>
            </dl>
        </section>

        <section class="mt-6 flex flex-wrap items-center gap-3">
            <a href="{{ route('app.properties.edit', $property) }}" class="rounded-lg bg-rentara-blue px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90">Ubah properti</a>
            <form method="POST" action="{{ route('app.properties.destroy', $property) }}" onsubmit="return confirm('Hapus properti {{ $property->name }}? Tindakan ini dapat dipulihkan oleh pemilik ruang kerja.');">
                @csrf
                @method('DELETE')
                <button type="submit" class="rounded-lg bg-red-700 px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90">Hapus properti</button>
            </form>
        </section>

        <nav class="mt-8 flex flex-wrap gap-2" aria-label="Bagian properti">
            <a href="{{ route('app.properties.units.index', $property) }}" class="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-rentara-navy hover:border-rentara-blue">Unit ({{ $property->units_count ?? 0 }})</a>
            <a href="{{ route('app.properties.buildings.index', $property) }}" class="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-rentara-navy hover:border-rentara-blue">Gedung ({{ $property->buildings_count ?? 0 }})</a>
            <a href="{{ route('app.properties.floors.index', $property) }}" class="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-rentara-navy hover:border-rentara-blue">Lantai ({{ $property->floors_count ?? 0 }})</a>
            <a href="{{ route('app.properties.blocks.index', $property) }}" class="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-rentara-navy hover:border-rentara-blue">Blok ({{ $property->blocks_count ?? 0 }})</a>
            <a href="{{ route('app.properties.assignments.index', $property) }}" class="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-rentara-navy hover:border-rentara-blue">Penugasan ({{ $property->assignments_count ?? 0 }})</a>
        </nav>
    </div>
</x-app-layout>
