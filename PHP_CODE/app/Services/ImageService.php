<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\AutoEncoder;
use Intervention\Image\ImageManager;

class ImageService {

    /**
     * Intervention Image v3 is framework agnostic and ships no facade, so the manager
     * is built here and shared by every caller instead of being resolved statically.
     */
    public static function manager(): ImageManager {
        return new ImageManager(new Driver());
    }

    /**
     * Re-encode an image at the given quality, keeping its original format.
     * Accepts anything Intervention can read: a path, an UploadedFile, or binary data.
     */
    public static function encode($source, int $quality = 60): string {
        return (string) self::manager()
            ->read($source)
            ->encode(new AutoEncoder(quality: $quality));
    }

    public static function compression($requestImage, $folder) {
        $file_name = uniqid('', true) . time() . '.' . $requestImage->getClientOriginalExtension();
        Storage::disk('public')->put($folder . '/' . $file_name, self::encode($requestImage, 60));
        return $folder . '/' . $file_name;
    }

    public static function delete($image) {
        if (Storage::disk('public')->exists($image)) {
            return Storage::disk('public')->delete($image);
        }
        //Image does not exist in server so feel free to upload new image
        return true;
    }

}
