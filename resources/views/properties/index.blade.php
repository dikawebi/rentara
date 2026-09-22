<x-app-layout>
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <p class="text-sm font-semibold text-rentara-teal">Ruang kerja aktif: {{ $workspace->name }}</p>
        <div class="mt-2 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="rentara-page-title">Daftar properti</h1>
                <p class="mt-2 text-slate-600">Kelola properti sewa pada ruang kerja {{ $workspace->name }}.</p>
            </div>
            <a href="{{ route('app.properties.create') }}" class="rounded-lg bg-rentara-blue px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90">Tambah properti</a>
        </div>

        @if (session('status'))
            <p class="mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800" role="status">{{ session('status') }}</p>
        @endif

        <section class="rentara-card mt-6 overflow-x-auto">
            @if ($properties->isEmpty())
                <div class="px-6 py-12 text-center">
                    <h2 class="font-display text-lg font-bold text-rentara-navy">Belum ada properti</h2>
                    <p class="mt-2 text-sm text-slate-600">Tambahkan properti pertama Anda untuk mulai mengelola unit sewa.</p>
                    <a href="{{ route('app.properties.create') }}" class="mt-4 inline-block rounded-lg bg-rentara-blue px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90">Tambah properti</a>
                </div>
            @else
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead>
                        <tr class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th scope="col" class="px-4 py-3">Nama</th>
                            <th scope="col" class="px-4 py-3">Jenis</th>
                            <th scope="col" class="px-4 py-3">Kota</th>
                            <th scope="col" class="px-4 py-3">Status</th>
                            <th scope="col" class="px-4 py-3"><span class="sr-only">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($properties as $property)
                            <tr>
                                <td class="px-4 py-3 font-semibold text-rentara-navy">
                                    <a href="{{ route('app.properties.show', $property) }}" class="hover:underline">{{ $property->name }}</a>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ $property->property_type->value === 'kost' ? 'Kost' : ($property->property_type->value === 'house' ? 'Rumah' : ($property->property_type->value === 'apartment' ? 'Apartemen' : ucfirst($property->property_type->value))) }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $property->city ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    @if ($property->status->value === 'active')
                                        <span class="rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-800">Aktif</span>
                                    @elseif ($property->status->value === 'inactive')
                                        <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">Nonaktif</span>
                                    @else
                                        <span class="rounded-full bg-slate-200 px-3 py-1 text-xs font-semibold text-slate-600">Diarsipkan</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('app.properties.show', $property) }}" class="font-semibold text-rentara-blue hover:underline">Lihat</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="px-4 py-4">{{ $properties->links() }}</div>
            @endif
        </section>
    </div>
</x-app-layout>
