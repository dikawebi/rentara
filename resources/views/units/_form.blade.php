<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label for="unit_number" class="block text-sm font-semibold text-rentara-navy">Nomor unit</label>
        <input id="unit_number" name="unit_number" type="text" value="{{ old('unit_number', $unit->unit_number ?? '') }}" required maxlength="50" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
        @error('unit_number')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="name" class="block text-sm font-semibold text-rentara-navy">Nama unit</label>
        <input id="name" name="name" type="text" value="{{ old('name', $unit->name ?? '') }}" maxlength="255" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
        @error('name')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
</div>
<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label for="building_id" class="block text-sm font-semibold text-rentara-navy">Gedung</label>
        <select id="building_id" name="building_id" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
            <option value="">— Tanpa gedung —</option>
            @foreach ($buildings as $building)
                <option value="{{ $building->id }}" @selected((string) old('building_id', $unit->building_id ?? '') === (string) $building->id)>{{ $building->name }}</option>
            @endforeach
        </select>
        @error('building_id')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="floor_id" class="block text-sm font-semibold text-rentara-navy">Lantai</label>
        <select id="floor_id" name="floor_id" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
            <option value="">— Tanpa lantai —</option>
            @foreach ($floors as $floor)
                <option value="{{ $floor->id }}" @selected((string) old('floor_id', $unit->floor_id ?? '') === (string) $floor->id)>{{ $floor->name }}</option>
            @endforeach
        </select>
        @error('floor_id')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
</div>
<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label for="block_id" class="block text-sm font-semibold text-rentara-navy">Blok</label>
        <select id="block_id" name="block_id" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
            <option value="">— Tanpa blok —</option>
            @foreach ($blocks as $block)
                <option value="{{ $block->id }}" @selected((string) old('block_id', $unit->block_id ?? '') === (string) $block->id)>{{ $block->name }}</option>
            @endforeach
        </select>
        @error('block_id')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="unit_type_id" class="block text-sm font-semibold text-rentara-navy">Tipe unit</label>
        <select id="unit_type_id" name="unit_type_id" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
            <option value="">— Tanpa tipe —</option>
            @foreach ($unitTypes as $unitType)
                <option value="{{ $unitType->id }}" @selected((string) old('unit_type_id', $unit->unit_type_id ?? '') === (string) $unitType->id)>{{ $unitType->name }}</option>
            @endforeach
        </select>
        @error('unit_type_id')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
</div>
<div class="grid gap-4 sm:grid-cols-3">
    <div>
        <label for="area" class="block text-sm font-semibold text-rentara-navy">Luas (m²)</label>
        <input id="area" name="area" type="text" inputmode="decimal" value="{{ old('area', $unit->area ?? '') }}" placeholder="Contoh: 24.50" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
        @error('area')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="capacity" class="block text-sm font-semibold text-rentara-navy">Kapasitas</label>
        <input id="capacity" name="capacity" type="number" min="1" max="100" value="{{ old('capacity', $unit->capacity ?? 1) }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
        @error('capacity')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="status" class="block text-sm font-semibold text-rentara-navy">Status</label>
        <select id="status" name="status" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
            @foreach (['available' => 'Tersedia', 'occupied' => 'Terisi', 'maintenance' => 'Perawatan'] as $value => $label)
                <option value="{{ $value }}" @selected(old('status', ($unit->status ?? null)?->value ?? 'available') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('status')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
</div>
<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label for="rental_price" class="block text-sm font-semibold text-rentara-navy">Harga sewa (Rp)</label>
        <input id="rental_price" name="rental_price" type="number" min="0" value="{{ old('rental_price', $unit->rental_price ?? '') }}" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
        @error('rental_price')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="rental_period" class="block text-sm font-semibold text-rentara-navy">Periode sewa</label>
        <select id="rental_period" name="rental_period" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
            @foreach (['daily' => 'Harian', 'monthly' => 'Bulanan', 'yearly' => 'Tahunan'] as $value => $label)
                <option value="{{ $value }}" @selected(old('rental_period', ($unit->rental_period ?? null)?->value ?? 'monthly') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('rental_period')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
</div>
