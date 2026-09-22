<div>
    <label for="name" class="block text-sm font-semibold text-rentara-navy">Nama lantai</label>
    <input id="name" name="name" type="text" value="{{ old('name', $floor->name ?? '') }}" required maxlength="255" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
    @error('name')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
</div>
<div>
    <label for="sort_order" class="block text-sm font-semibold text-rentara-navy">Urutan tampil</label>
    <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $floor->sort_order ?? 0) }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
    @error('sort_order')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
</div>
<div>
    <label for="notes" class="block text-sm font-semibold text-rentara-navy">Catatan</label>
    <textarea id="notes" name="notes" rows="2" maxlength="2000" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">{{ old('notes', $floor->notes ?? '') }}</textarea>
    @error('notes')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
</div>
