<x-app-layout>
    <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
        <p class="text-sm font-semibold text-rentara-teal">Ruang kerja aktif: {{ $workspace->name }}</p><h1 class="rentara-page-title mt-2">Tambah kontrak</h1><p class="mt-2 text-slate-600">Isi data dasar kontrak. Tenant dapat ditambahkan setelah kontrak dibuat.</p>
        <section class="rentara-card mt-6"><form method="POST" action="{{ route('app.contracts.store') }}" class="space-y-5">@csrf
            @include('contracts._form', ['contract' => null, 'workspace' => $workspace])
            <div class="flex items-center gap-3"><button type="submit" class="rounded-lg bg-rentara-blue px-4 py-2.5 text-sm font-semibold text-white">Simpan kontrak</button><a href="{{ route('app.contracts.index') }}" class="text-sm font-semibold text-slate-600 hover:underline">Batal</a></div>
        </form></section>
    </div>
</x-app-layout>
