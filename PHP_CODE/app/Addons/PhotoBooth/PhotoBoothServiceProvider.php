<?php

namespace App\Addons\PhotoBooth;

use Illuminate\Support\ServiceProvider;

/**
 * ID Card Photo Booth.
 *
 * Captures student photographs from a webcam and writes them to the student's
 * existing profile image, so the stock ID card generator can then print them.
 *
 * Deliberately self-contained: routes, views and migrations all live under this
 * directory and are loaded from here. The only edits outside it are registering
 * this provider and adding one sidebar entry — no existing controller, model,
 * migration or service is modified.
 */
class PhotoBoothServiceProvider extends ServiceProvider
{
    public const FEATURE = 'ID Card Photo Booth';

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/resources/views', 'photobooth');
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

        // Fallback wording for the strings this addon introduces. Laravel appends the
        // application's own lang path last when merging JSON translations, so a school
        // that adds these keys to its uploaded language file still wins.
        $this->loadJsonTranslationsFrom(__DIR__ . '/lang');
    }
}
