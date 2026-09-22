<x-app-layout>
    <div class="mx-auto max-w-2xl px-4 py-8 sm:px-6 lg:px-8">
        <p class="text-sm font-semibold text-rentara-teal">Ruang kerja aktif: {{ $workspace->name }}</p>
        <h1 class="rentara-page-title mt-2">Tambah lantai · {{ $property->name }}</h1>
        <p class="mt-2 text-slate-600">Lengkapi data lantai baru.</p>

        <section class="rentara-card mt-6">
            <form method="POST" action="{{ route('app.properties.floors.store', $property) }}" class="space-y-4">
                @csrf
                @include('floors._form')
                <div class="flex items-center gap-3 pt-2">
                    <button type="submit" class="rounded-lg bg-rentara-blue px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90">Simpan lantai</button>
                    <a href="{{ route('app.properties.floors.index', $property) }}" class="text-sm font-semibold text-slate-600 hover:underline">Batal</a>
                </div>
            </form>
        </section>
    </div>
</x-app-layout>
