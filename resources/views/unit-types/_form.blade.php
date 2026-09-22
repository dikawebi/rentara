<div>
    <label for="name" class="block text-sm font-semibold text-rentara-navy">Nama tipe unit</label>
    <input id="name" name="name" type="text" value="{{ old('name', $unitType->name ?? '') }}" required maxlength="255" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
    @error('name')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
</div>
<div>
    <label for="description" class="block text-sm font-semibold text-rentara-navy">Deskripsi</label>
    <textarea id="description" name="description" rows="2" maxlength="2000" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">{{ old('description', $unitType->description ?? '') }}</textarea>
    @error('description')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
</div>
<div>
    <label for="default_capacity" class="block text-sm font-semibold text-rentara-navy">Kapasitas bawaan</label>
    <input id="default_capacity" name="default_capacity" type="number" min="1" max="100" value="{{ old('default_capacity', $unitType->default_capacity ?? '') }}" placeholder="Contoh: 2" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
    @error('default_capacity')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
</div>
