<x-app-layout>
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <p class="text-sm font-semibold text-rentara-teal">Ruang kerja aktif: {{ $workspace->name }}</p>
        <div class="mt-2 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="rentara-page-title">Penugasan · {{ $property->name }}</h1>
                <p class="mt-2 text-slate-600">Kelola manajer dan staf yang ditugaskan pada properti {{ $property->name }}.</p>
            </div>
            <a href="{{ route('app.properties.show', $property) }}" class="text-sm font-semibold text-slate-600 hover:underline">Kembali ke properti</a>
        </div>

        @if (session('status'))
            <p class="mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800" role="status">{{ session('status') }}</p>
        @endif

        @can('create', [\App\Models\PropertyAssignment::class, $property])
            <section class="rentara-card mt-6">
                <h2 class="font-display text-base font-bold text-rentara-navy">Tambah penugasan</h2>
                <form method="POST" action="{{ route('app.properties.assignments.store', $property) }}" class="mt-3 flex flex-wrap items-end gap-3">
                    @csrf
                    <div class="min-w-64 flex-1">
                        <label for="user_id" class="block text-sm font-semibold text-rentara-navy">Manajer / staf</label>
                        <select id="user_id" name="user_id" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                            <option value="">— Pilih pengguna —</option>
                            @foreach ($candidates as $candidate)
                                <option value="{{ $candidate->id }}">{{ $candidate->name }} ({{ $candidate->email }})</option>
                            @endforeach
                        </select>
                        @error('user_id')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" class="rounded-lg bg-rentara-blue px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90">Tugaskan</button>
                </form>
                @if ($candidates->isEmpty())
                    <p class="mt-3 text-sm text-slate-600">Tidak ada manajer atau staf aktif yang dapat ditugaskan.</p>
                @endif
            </section>
        @endcan

        <section class="rentara-card mt-6 overflow-x-auto">
            @if ($assignments->isEmpty())
                <div class="px-6 py-12 text-center">
                    <h2 class="font-display text-lg font-bold text-rentara-navy">Belum ada penugasan</h2>
                    <p class="mt-2 text-sm text-slate-600">Tugaskan manajer atau staf agar mereka dapat mengelola properti ini.</p>
                </div>
            @else
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead>
                        <tr class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th scope="col" class="px-4 py-3">Nama</th>
                            <th scope="col" class="px-4 py-3">Surel</th>
                            <th scope="col" class="px-4 py-3">Ditugaskan oleh</th>
                            <th scope="col" class="px-4 py-3"><span class="sr-only">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($assignments as $assignment)
                            <tr>
                                <td class="px-4 py-3 font-semibold text-rentara-navy">{{ $assignment->user?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $assignment->user?->email ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $assignment->creator?->name ?? 'Sistem' }}</td>
                                <td class="px-4 py-3 text-right">
                                    @can('delete', $assignment)
                                        <form method="POST" action="{{ route('app.properties.assignments.destroy', [$property, $assignment]) }}" class="inline" onsubmit="return confirm(@js('Hapus penugasan '.($assignment->user?->name ?? '—').' dari properti ini?'));">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="font-semibold text-red-700 hover:underline">Hapus</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="px-4 py-4">{{ $assignments->links() }}</div>
            @endif
        </section>
    </div>
</x-app-layout>
