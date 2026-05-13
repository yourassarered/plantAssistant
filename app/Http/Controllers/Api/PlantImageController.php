<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StorePlantImageRequest;
use App\Http\Requests\Api\UpdatePlantImageRequest;
use App\Http\Resources\PlantImageResource;
use App\Models\Plant;
use App\Models\PlantImage;
use App\Services\ImageStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlantImageController extends Controller
{
    public function index(Request $request, int $plantId)
    {
        $plant = Plant::findOrFail($plantId);
        $this->authorize('view', $plant);

        $images = $plant->images()->paginate(min($request->integer('per_page', 15), 100));

        return PlantImageResource::collection($images);
    }

    public function store(StorePlantImageRequest $request, int $plantId, ImageStorageService $images)
    {
        $plant = Plant::findOrFail($plantId);
        $this->authorize('createForPlant', [PlantImage::class, $plant->user_id]);

        $uploaded = $request->file('image');
        $image = DB::transaction(function () use ($images, $plant, $uploaded) {
            $path = $images->storeCompressed($uploaded, "plants/{$plant->id}", 1600, 82);

            return $plant->images()->create([
                'path' => $path,
                'original_name' => $uploaded->getClientOriginalName(),
                'size' => $uploaded->getSize() ?: 0,
            ]);
        });

        return (new PlantImageResource($image))->response()->setStatusCode(201);
    }

    public function show(Request $request, int $id)
    {
        $image = PlantImage::with('plant')->findOrFail($id);
        $this->authorize('view', $image);

        return new PlantImageResource($image);
    }

    public function update(UpdatePlantImageRequest $request, int $id, ImageStorageService $images)
    {
        $image = PlantImage::with('plant')->findOrFail($id);
        $this->authorize('update', $image);

        $uploaded = $request->file('image');

        DB::transaction(function () use ($images, $image, $uploaded): void {
            $images->delete($image->path);

            $image->update([
                'path' => $images->storeCompressed($uploaded, "plants/{$image->plant_id}", 1600, 82),
                'original_name' => $uploaded->getClientOriginalName(),
                'size' => $uploaded->getSize() ?: 0,
            ]);
        });

        return new PlantImageResource($image->fresh());
    }

    public function destroy(Request $request, int $id, ImageStorageService $images)
    {
        $image = PlantImage::with('plant')->findOrFail($id);
        $this->authorize('delete', $image);

        DB::transaction(function () use ($images, $image): void {
            $images->delete($image->path);
            $image->delete();
        });

        return response()->json(['message' => 'Plant image deleted successfully']);
    }
}
