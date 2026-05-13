<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageStorageService
{
    public function storeCompressed(UploadedFile $file, string $directory, int $maxWidth = 1600, int $quality = 82): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $extension = in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true) ? $extension : 'jpg';
        $path = trim($directory, '/').'/'.Str::uuid().'.'.$extension;

        $bytes = $this->compress($file, $extension, $maxWidth, $quality);
        Storage::disk('public')->put($path, $bytes);

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }

    private function compress(UploadedFile $file, string $extension, int $maxWidth, int $quality): string
    {
        $original = file_get_contents($file->getRealPath());

        if (! extension_loaded('gd') || $original === false) {
            return $original ?: '';
        }

        $source = @imagecreatefromstring($original);
        if (! $source) {
            return $original;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $targetWidth = min($width, $maxWidth);
        $targetHeight = (int) round($height * ($targetWidth / $width));

        $image = imagecreatetruecolor($targetWidth, $targetHeight);

        // PNG/WebP can contain transparency, so preserve alpha when possible.
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagecopyresampled($image, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        match ($extension) {
            'png' => imagepng($image, null, 7),
            'webp' => imagewebp($image, null, $quality),
            default => imagejpeg($image, null, $quality),
        };
        $compressed = ob_get_clean();

        imagedestroy($source);
        imagedestroy($image);

        return $compressed ?: $original;
    }
}
