<x-app-layout>
    <div class="mx-auto max-w-2xl px-4 py-8 sm:px-6 lg:px-8">
        <p class="text-sm font-semibold text-rentara-teal">Ruang kerja aktif: {{ $workspace->name }}</p>
        <h1 class="rentara-page-title mt-2">Ubah properti</h1>
        <p class="mt-2 text-slate-600">Perbarui data {{ $property->name }}.</p>

        <section class="rentara-card mt-6">
            <form method="POST" action="{{ route('app.properties.update', $property) }}" class="space-y-4">
                @csrf
                @method('PUT')
                @include('properties._form')
                <div class="flex items-center gap-3 pt-2">
                    <button type="submit" class="rounded-lg bg-rentara-blue px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90">Simpan perubahan</button>
                    <a href="{{ route('app.properties.show', $property) }}" class="text-sm font-semibold text-slate-600 hover:underline">Batal</a>
                </div>
            </form>
        </section>
    </div>
</x-app-layout>
