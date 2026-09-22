<div>
    <label for="name" class="block text-sm font-semibold text-rentara-navy">Nama properti</label>
    <input id="name" name="name" type="text" value="{{ old('name', $property->name ?? '') }}" required maxlength="255" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
    @error('name')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
</div>
<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label for="property_type" class="block text-sm font-semibold text-rentara-navy">Jenis properti</label>
        <select id="property_type" name="property_type" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
            @foreach (['kost' => 'Kost', 'house' => 'Rumah', 'apartment' => 'Apartemen', 'kios' => 'Kios', 'ruko' => 'Ruko'] as $value => $label)
                <option value="{{ $value }}" @selected(old('property_type', ($property->property_type ?? null)?->value) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('property_type')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="status" class="block text-sm font-semibold text-rentara-navy">Status</label>
        <select id="status" name="status" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
            @foreach (['active' => 'Aktif', 'inactive' => 'Nonaktif', 'archived' => 'Diarsipkan'] as $value => $label)
                <option value="{{ $value }}" @selected(old('status', ($property->status ?? null)?->value ?? 'active') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('status')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
</div>
<div>
    <label for="address" class="block text-sm font-semibold text-rentara-navy">Alamat</label>
    <textarea id="address" name="address" rows="2" maxlength="2000" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">{{ old('address', $property->address ?? '') }}</textarea>
    @error('address')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
</div>
<div class="grid gap-4 sm:grid-cols-3">
    <div>
        <label for="city" class="block text-sm font-semibold text-rentara-navy">Kota</label>
        <input id="city" name="city" type="text" value="{{ old('city', $property->city ?? '') }}" maxlength="100" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
        @error('city')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="province" class="block text-sm font-semibold text-rentara-navy">Provinsi</label>
        <input id="province" name="province" type="text" value="{{ old('province', $property->province ?? '') }}" maxlength="100" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
        @error('province')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="postal_code" class="block text-sm font-semibold text-rentara-navy">Kode pos</label>
        <input id="postal_code" name="postal_code" type="text" value="{{ old('postal_code', $property->postal_code ?? '') }}" maxlength="20" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
        @error('postal_code')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
</div>
<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label for="latitude" class="block text-sm font-semibold text-rentara-navy">Lintang (latitude)</label>
        <input id="latitude" name="latitude" type="text" inputmode="decimal" value="{{ old('latitude', $property->latitude ?? '') }}" placeholder="-6.2000000" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
        @error('latitude')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="longitude" class="block text-sm font-semibold text-rentara-navy">Bujur (longitude)</label>
        <input id="longitude" name="longitude" type="text" inputmode="decimal" value="{{ old('longitude', $property->longitude ?? '') }}" placeholder="106.8166667" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
        @error('longitude')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
</div>
<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label for="phone" class="block text-sm font-semibold text-rentara-navy">Telepon</label>
        <input id="phone" name="phone" type="text" value="{{ old('phone', $property->phone ?? '') }}" maxlength="30" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
        @error('phone')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="email" class="block text-sm font-semibold text-rentara-navy">Surel</label>
        <input id="email" name="email" type="email" value="{{ old('email', $property->email ?? '') }}" maxlength="255" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
        @error('email')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
</div>
