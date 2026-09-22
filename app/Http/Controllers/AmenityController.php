<?php

namespace App\Http\Controllers;

use App\Models\Amenity;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class AmenityController extends Controller
{
    private function find(Request $request, int $id, bool $withTrashed = false): Amenity
    {
        return Amenity::where('workspace_id', $request->attributes->get('currentWorkspace')->id)
            ->when($withTrashed, fn ($query) => $query->withTrashed())->findOrFail($id);
    }

    public function index(Request $request)
    {
        $workspace = $request->attributes->get('currentWorkspace');
        $this->authorize('viewAny', [Amenity::class, $workspace]);
        return view('amenities.index', ['workspace' => $workspace, 'amenities' => $workspace->amenities()->withCount('units')->orderBy('name')->paginate(15)->withQueryString()]);
    }

    public function create(Request $request)
    {
        $workspace = $request->attributes->get('currentWorkspace');
        $this->authorize('create', [Amenity::class, $workspace]);
        return view('amenities.create', compact('workspace'));
    }

    public function store(Request $request)
    {
        $workspace = $request->attributes->get('currentWorkspace');
        $this->authorize('create', [Amenity::class, $workspace]);
        $data = $request->validate(['name' => ['required', 'string', 'max:255', Rule::unique('amenities')->where(fn ($q) => $q->where('workspace_id', $workspace->id))], 'description' => ['nullable', 'string', 'max:2000']]);
        $workspace->amenities()->create($data + ['created_by' => $request->user()->id]);
        return redirect()->route('app.amenities.index')->with('status', 'Fasilitas berhasil ditambahkan.');
    }

    public function edit(Request $request, int $amenity)
    {
        $amenity = $this->find($request, $amenity);
        $this->authorize('update', $amenity);
        return view('amenities.edit', ['workspace' => $request->attributes->get('currentWorkspace'), 'amenity' => $amenity]);
    }

    public function update(Request $request, int $amenity)
    {
        $amenity = $this->find($request, $amenity);
        $this->authorize('update', $amenity);
        $data = $request->validate(['name' => ['required', 'string', 'max:255', Rule::unique('amenities')->where(fn ($q) => $q->where('workspace_id', $amenity->workspace_id))->ignore($amenity->id)], 'description' => ['nullable', 'string', 'max:2000']]);
        $amenity->update($data);
        return redirect()->route('app.amenities.index')->with('status', 'Fasilitas berhasil diperbarui.');
    }

    public function destroy(Request $request, int $amenity)
    {
        $amenity = $this->find($request, $amenity);
        $this->authorize('delete', $amenity);
        $amenity->delete();
        return redirect()->route('app.amenities.index')->with('status', 'Fasilitas berhasil dihapus.');
    }

    public function restore(Request $request, int $amenity)
    {
        $amenity = $this->find($request, $amenity, true);
        $this->authorize('restore', $amenity);
        if ($amenity->trashed()) {
            try {
                DB::transaction(function () use ($amenity) {
                    abort_if(Amenity::where('workspace_id', $amenity->workspace_id)->where('name', $amenity->name)->lockForUpdate()->exists(), 422, 'Nama fasilitas sudah digunakan oleh data aktif.');
                    $amenity->restore();
                });
            } catch (QueryException $e) {
                if (in_array($e->getCode(), ['23000', '23505'], true)) {
                    abort(422, 'Nama fasilitas sudah digunakan oleh data aktif.');
                }
                throw $e;
            }
        }
        return redirect()->route('app.amenities.index')->with('status', 'Fasilitas berhasil dipulihkan.');
    }
}
