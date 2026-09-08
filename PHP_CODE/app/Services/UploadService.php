<?php

namespace App\Services;

use Auth;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class UploadService {

    /**
     * Extensions that are safe to serve from the public disk.
     *
     * SVG is deliberately absent: it is an XML document that can carry script, and the
     * public disk is served from the application origin, so an uploaded SVG would be
     * stored XSS against everyone who views it.
     */
    private const ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'xml',
        'mp3', 'mp4', 'webm', 'ogg',
        'zip',
    ];

    /** Raster formats we re-encode, which also strips any embedded payload. */
    private const RECODED_EXTENSIONS = ['jpg', 'jpeg', 'png'];

    /**
     * Resolve the extension from the file's actual contents rather than from the
     * client-supplied name. A request can claim any original filename it likes, and
     * this disk is web-reachable, so trusting that name would let a caller choose the
     * stored extension.
     */
    private static function safeExtension($requestFile): string {
        $extension = strtolower((string) $requestFile->extension());

        if ($extension === '' || !in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new RuntimeException('Unsupported file type for upload.');
        }

        return $extension;
    }

    public static function upload($requestFile, $folder) {
        if (Auth::user() && Auth::user()->school_id) {
            $folder = Auth::user()->school_id.'/'.$folder;
        } else {
            $folder = 'super-admin/'.$folder;
        }

        $extension = self::safeExtension($requestFile);
        $file_name = uniqid('', true) . time() . '.' . $extension;

        if (in_array($extension, self::RECODED_EXTENSIONS, true)) {
            // Re-encode rather than store verbatim: this compresses and, as a side
            // effect, discards anything hidden in the original container.
            Storage::disk('public')->put(
                $folder . '/' . $file_name,
                ImageService::encode($requestFile, 60)
            );
        } else {
            $requestFile->storeAs($folder, $file_name, 'public');
        }

        return $folder . '/' . $file_name;
    }

    /**
     * @param $image = rawOriginalPath
     * @return bool
     */
    public static function delete($image) {
        if ($image && Storage::disk('public')->exists($image)) {
            return Storage::disk('public')->delete($image);
        }


        //Image does not exist in server so feel free to upload new image
        return true;
    }

}
