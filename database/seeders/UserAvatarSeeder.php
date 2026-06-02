<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use SplFileInfo;

class UserAvatarSeeder extends Seeder
{
    private const SOURCE_DIRECTORY = 'images/placeholders/avatars';

    private const STORAGE_DIRECTORY = 'seeders/avatars';

    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    private const PLACEHOLDER_FILENAME = 'avatar-placeholder.png';

    public function run(): void
    {
        $users = User::orderBy('id')->get();
        if ($users->isEmpty()) {
            return;
        }

        $avatars = $this->syncSourceAvatarsToPublicDisk();
        if ($avatars === []) {
            return;
        }

        foreach ($users as $user) {
            $user->forceFill([
                'avatar_path' => $avatars[array_rand($avatars)]['path'],
            ])->save();
        }
    }

    /**
     * @return array<int, array{path: string, original_name: string, size: int}>
     */
    private function syncSourceAvatarsToPublicDisk(): array
    {
        $sourcePath = public_path(self::SOURCE_DIRECTORY);
        if (! File::isDirectory($sourcePath)) {
            return [];
        }

        $storagePath = Storage::disk('public')->path(self::STORAGE_DIRECTORY);
        File::ensureDirectoryExists($storagePath);

        return collect(File::files($sourcePath))
            ->filter(fn (SplFileInfo $file) => in_array(strtolower($file->getExtension()), self::ALLOWED_EXTENSIONS, true))
            ->reject(fn (SplFileInfo $file) => $file->getFilename() === self::PLACEHOLDER_FILENAME)
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
