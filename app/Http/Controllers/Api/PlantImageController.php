<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlantImageResource;
use App\Models\Plant;
use App\Models\PlantImage;
use App\Services\ImageStorageService;
use Illuminate\Http\Request;

class PlantImageController extends Controller
{
    public function index(Request $request, int $plantId)
    {
        $plant = $this->plantVisibleToUser($request, $plantId);

        $images = $plant->images()
            ->paginate(min($request->integer('per_page', 15), 100));

        return PlantImageResource::collection($images);
    }

    public function store(Request $request, int $plantId, ImageStorageService $images)
    {
        $plant = $this->ownedPlant($request, $plantId);

        $validated = $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:8192',
        ]);

        $path = $images->storeCompressed($validated['image'], "plants/{$plant->id}", 1600, 82);

        $image = $plant->images()->create([
            'path' => $path,
            'original_name' => $validated['image']->getClientOriginalName(),
            'size' => $validated['image']->getSize() ?: 0,
        ]);

        return (new PlantImageResource($image))->response()->setStatusCode(201);
    }

    public function show(Request $request, int $id)
    {
        $image = PlantImage::with('plant')->findOrFail($id);
        $this->assertPlantVisible($request, $image->plant);

        return new PlantImageResource($image);
    }

    public function update(Request $request, int $id, ImageStorageService $images)
    {
        $image = PlantImage::with('plant')->findOrFail($id);
        $this->assertPlantOwned($request, $image->plant);

        $validated = $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:8192',
        ]);

        $images->delete($image->path);
        $image->update([
            'path' => $images->storeCompressed($validated['image'], "plants/{$image->plant_id}", 1600, 82),
            'original_name' => $validated['image']->getClientOriginalName(),
            'size' => $validated['image']->getSize() ?: 0,
        ]);

        return new PlantImageResource($image);
    }

    public function destroy(Request $request, int $id, ImageStorageService $images)
    {
        $image = PlantImage::with('plant')->findOrFail($id);

        if (! $request->user()->isAdmin()) {
            $this->assertPlantOwned($request, $image->plant);
        }

        $images->delete($image->path);
        $image->delete();

        return response()->json(['message' => 'Plant image deleted successfully']);
    }

    private function ownedPlant(Request $request, int $plantId): Plant
    {
        return Plant::where('user_id', $request->user()->id)->findOrFail($plantId);
    }

    private function plantVisibleToUser(Request $request, int $plantId): Plant
    {
        $plant = Plant::findOrFail($plantId);
        $this->assertPlantVisible($request, $plant);

        return $plant;
    }

    private function assertPlantVisible(Request $request, Plant $plant): void
    {
        if ($plant->user_id !== $request->user()->id && ! $plant->is_public && ! $request->user()->isAdmin()) {
            abort(403, 'Unauthorized');
        }
    }

    private function assertPlantOwned(Request $request, Plant $plant): void
    {
        if ($plant->user_id !== $request->user()->id) {
            abort(403, 'Plant not found or does not belong to you');
        }
    }
}
