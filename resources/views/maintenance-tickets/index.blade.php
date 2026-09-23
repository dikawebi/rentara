<x-app-layout>
    <x-slot name="header"><div class="flex flex-wrap items-center justify-between gap-3"><h1 class="font-semibold text-xl text-rentara-navy">Tiket pemeliharaan</h1><a href="{{ route('app.maintenance-tickets.create') }}" class="rounded-lg bg-rentara-navy px-4 py-2 text-sm font-semibold text-white">Buat tiket</a></div></x-slot>
    <div class="mx-auto max-w-7xl space-y-4 p-4 sm:p-6">
        @if(session('status')) <div role="status" class="rounded-lg bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div> @endif
        <section class="overflow-hidden rounded-xl bg-white shadow-sm" aria-labelledby="ticket-list-heading">
            <h2 id="ticket-list-heading" class="sr-only">Daftar tiket</h2>
            @if($tickets->count())
                <div class="overflow-x-auto"><table class="w-full min-w-[680px] text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-600"><tr><th scope="col" class="px-4 py-3">Tiket</th><th scope="col" class="px-4 py-3">Properti / unit</th><th scope="col" class="px-4 py-3">Status</th><th scope="col" class="px-4 py-3">Prioritas</th><th scope="col" class="px-4 py-3">Petugas</th></tr></thead><tbody class="divide-y divide-slate-100">
                    @foreach($tickets as $ticket)<tr class="align-top"><td class="px-4 py-4"><a class="font-semibold text-rentara-navy underline" href="{{ route('app.maintenance-tickets.show', $ticket) }}">{{ $ticket->title }}</a><div class="mt-1 text-xs text-slate-500">#{{ $ticket->id }}</div></td><td class="px-4 py-4">{{ $ticket->property?->name ?? '—' }}<div class="text-xs text-slate-500">Unit {{ $ticket->unit?->unit_number ?? '—' }}</div></td><td class="px-4 py-4">{{ ['submitted'=>'Diajukan','reviewed'=>'Ditinjau','assigned'=>'Ditugaskan','in_progress'=>'Sedang dikerjakan','waiting'=>'Menunggu','resolved'=>'Selesai','closed'=>'Ditutup','rejected'=>'Ditolak'][$ticket->status->value] ?? $ticket->status->value }}</td><td class="px-4 py-4">{{ ['low'=>'Rendah','normal'=>'Normal','high'=>'Tinggi','urgent'=>'Mendesak'][$ticket->priority->value] ?? $ticket->priority->value }}</td><td class="px-4 py-4">{{ $ticket->assignee?->name ?? 'Belum ditugaskan' }}</td></tr>@endforeach
                </tbody></table></div>
                <div class="border-t border-slate-100 p-4">{{ $tickets->links() }}</div>
            @else <div class="p-8 text-center"><p class="font-semibold text-slate-800">Belum ada tiket pemeliharaan.</p><p class="mt-1 text-sm text-slate-600">Buat tiket untuk mencatat kebutuhan perbaikan internal.</p></div> @endif
        </section>
    </div>
</x-app-layout>
