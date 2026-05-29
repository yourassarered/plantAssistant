<?php

namespace Database\Seeders;

use App\Models\Plant;
use App\Models\PlantImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use SplFileInfo;

class PlantImageSeeder extends Seeder
{
    private const SOURCE_DIRECTORY = 'images/placeholders/plants';
    private const STORAGE_DIRECTORY = 'seeders/plants';
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];
    private const MIN_IMAGES_PER_PLANT = 1;
    private const MAX_IMAGES_PER_PLANT = 2;

    public function run(): void
    {
        $plants = Plant::orderBy('id')->get();
        if ($plants->isEmpty()) {
            return;
        }

        $images = $this->syncSourceImagesToPublicDisk();
        if (count($images) < 2) {
            return;
        }

        foreach ($plants as $plant) {
            $selectedImages = collect($images)
                ->shuffle()
                ->take(random_int(self::MIN_IMAGES_PER_PLANT, min(self::MAX_IMAGES_PER_PLANT, count($images))))
                ->values();

            $selectedPaths = $selectedImages->pluck('path')->all();

            PlantImage::query()
                ->where('plant_id', $plant->id)
                ->where('path', 'like', self::STORAGE_DIRECTORY.'/%')
                ->whereNotIn('path', $selectedPaths)
                ->delete();

            foreach ($selectedImages as $image) {
                PlantImage::updateOrCreate(
                    [
                        'plant_id' => $plant->id,
                        'path' => $image['path'],
                    ],
                    [
                        'original_name' => $image['original_name'],
                        'size' => $image['size'],
                    ]
                );
            }
        }
    }

    /**
     * @return array<int, array{path: string, original_name: string, size: int}>
     */
    private function syncSourceImagesToPublicDisk(): array
    {
        $sourcePath = public_path(self::SOURCE_DIRECTORY);
        if (! File::isDirectory($sourcePath)) {
            return [];
        }

        $storagePath = Storage::disk('public')->path(self::STORAGE_DIRECTORY);
        File::ensureDirectoryExists($storagePath);

        return collect(File::files($sourcePath))
            ->filter(fn (SplFileInfo $file) => in_array(strtolower($file->getExtension()), self::ALLOWED_EXTENSIONS, true))
            ->reject(fn (SplFileInfo $file) => $file->getFilename() === 'plant-placeholder.png')
            ->sortBy(fn (SplFileInfo $file) => $file->getFilename())
            ->values()
            ->map(function (SplFileInfo $file) use ($storagePath): array {
                $destinationFile = $storagePath.DIRECTORY_SEPARATOR.$file->getFilename();

                if (! File::exists($destinationFile) || sha1_file($file->getPathname()) !== sha1_file($destinationFile)) {
                    File::copy($file->getPathname(), $destinationFile);
                }

                return [
                    'path' => self::STORAGE_DIRECTORY.'/'.$file->getFilename(),
                    'original_name' => $file->getFilename(),
                    'size' => (int) File::size($destinationFile),
                ];
            })
            ->all();
    }
}
