<?php

namespace App\Http\Controllers;

use App\Models\Block;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BlockController extends Controller
{
    private function findProperty(Request $request, int $propertyId): Property
    {
        $workspaceId = $request->attributes->get('currentWorkspace')->id;

        return Property::where('workspace_id', $workspaceId)->findOrFail($propertyId);
    }

    private function find(Property $property, int $id, bool $withTrashed = false): Block
    {
        return Block::where('workspace_id', $property->workspace_id)
            ->where('property_id', $property->id)
            ->when($withTrashed, fn ($query) => $query->withTrashed())
            ->findOrFail($id);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(Property $property, ?int $ignoreId = null): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('blocks', 'name')
                    ->where(fn ($query) => $query->where('property_id', $property->id))
                    ->ignore($ignoreId),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:16777215'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'name.unique' => 'Nama blok sudah digunakan pada properti ini (termasuk data yang telah dihapus).',
        ];
    }

    public function index(Request $request, int $property)
    {
        $property = $this->findProperty($request, $property);
        $this->authorize('viewAny', [Block::class, $property]);

        $blocks = Block::where('property_id', $property->id)
            ->orderBy('sort_order')->orderBy('name')
            ->paginate(15)->withQueryString();

        return response()->view('blocks.index', [
            'workspace' => $request->attributes->get('currentWorkspace'),
            'property' => $property,
            'blocks' => $blocks,
        ]);
    }

    public function create(Request $request, int $property)
    {
        $property = $this->findProperty($request, $property);
        $this->authorize('create', [Block::class, $property]);

        return response()->view('blocks.create', [
            'workspace' => $request->attributes->get('currentWorkspace'),
            'property' => $property,
        ]);
    }

    public function store(Request $request, int $property)
    {
        $property = $this->findProperty($request, $property);
        $this->authorize('create', [Block::class, $property]);

        $data = $request->validate($this->rules($property), $this->messages());
        $data['workspace_id'] = $property->workspace_id;
        $data['property_id'] = $property->id;
        $data['sort_order'] ??= 0;

        $block = Block::create($data);

        return redirect()->route('app.properties.blocks.index', $property)->with('status', 'Blok berhasil ditambahkan.');
    }

    public function edit(Request $request, int $property, int $block)
    {
        $property = $this->findProperty($request, $property);
        $block = $this->find($property, $block);
        $this->authorize('update', $block);

        return response()->view('blocks.edit', [
            'workspace' => $request->attributes->get('currentWorkspace'),
            'property' => $property,
            'block' => $block,
        ]);
    }

    public function update(Request $request, int $property, int $block)
    {
        $property = $this->findProperty($request, $property);
        $block = $this->find($property, $block);
        $this->authorize('update', $block);

        $data = $request->validate($this->rules($property, $block->id), $this->messages());

        $block->update($data);

        return redirect()->route('app.properties.blocks.index', $property)->with('status', 'Perubahan blok berhasil disimpan.');
    }

    public function destroy(Request $request, int $property, int $block)
    {
        $property = $this->findProperty($request, $property);
        $block = $this->find($property, $block);
        $this->authorize('delete', $block);

        $block->delete();

        return redirect()->route('app.properties.blocks.index', $property)->with('status', 'Blok berhasil dihapus.');
    }

    public function restore(Request $request, int $property, int $block)
    {
        $property = $this->findProperty($request, $property);
        $block = $this->find($property, $block, true);
        $this->authorize('restore', $block);

        if ($block->trashed()) {
            $conflict = Block::where('property_id', $property->id)
                ->where('name', $block->name)
                ->whereNull('deleted_at')
                ->exists();

            abort_if($conflict, 422, 'Blok "'.$block->name.'" tidak dapat dipulihkan karena nama tersebut sudah digunakan oleh data aktif.');

            $block->restore();
        }

        return redirect()->route('app.properties.blocks.index', $property)->with('status', 'Blok berhasil dipulihkan.');
    }
}
