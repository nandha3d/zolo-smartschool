<?php

namespace App\Providers;

use App\Services\FeaturesService;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;

class CustomBladeDirectivesServiceProvider extends ServiceProvider {
    /**
     * Register services.
     *
     * @return void
     */
    public function register() {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot() {

        $this->callAfterResolving('blade.compiler', function (BladeCompiler $bladeCompiler) {
            $this->registerBladeExtensions($bladeCompiler);
        });

    }

    protected function registerBladeExtensions($bladeCompiler) {

        $bladeCompiler->directive('hasFeature', function ($arguments) {
            return "<?php if(\\App\Services\FeaturesService::hasFeature($arguments)): ?>";
        });
        $bladeCompiler->directive('endHasFeature', function () {
            return '<?php endif; ?>';
        });


        $bladeCompiler->directive('hasNotFeature', function ($arguments) {
            return "<?php if(!\\App\Services\FeaturesService::hasFeature($arguments)): ?>";
        });
        $bladeCompiler->directive('endHasNotFeature', function () {
            return '<?php endif; ?>';
        });

        $bladeCompiler->directive('hasAnyFeature', function ($arguments) {
            return "<?php if(\App\Services\FeaturesService::hasAnyFeature($arguments)): ?>";
        });
        $bladeCompiler->directive('endHasAnyFeature', function () {
            return '<?php endif; ?>';
        });

        $bladeCompiler->directive('hasAllFeatures', function ($arguments) {
            return "<?php if(\App\Services\FeaturesService::hasAllFeature($arguments)): ?>";
        });
        $bladeCompiler->directive('endHasAllFeatures', function () {
            return '<?php endif; ?>';
        });

        /**
         * These echo 'true' or 'false' into the markup.
         *
         * Both used to call FeaturesService directly from the directive callback,
         * which runs at *compile* time, and returned the literal result. Blade caches
         * compiled views, so the first request to compile a view baked that user's
         * feature access into the cached file and every later request - for every
         * school - was served the same answer. It also made `view:cache` fail
         * outright, since there is no authenticated user during CLI compilation.
         *
         * They now emit PHP that is evaluated per render, like the directives above.
         */
        $bladeCompiler->directive('hasFeatureAccess', function (string $arguments) {
            return "<?php echo \App\Services\FeaturesService::hasFeature($arguments) ? 'true' : 'false'; ?>";
        });

        $bladeCompiler->directive('hasAnyFeatureAccess', function ($arguments) {
            return "<?php echo \App\Services\FeaturesService::hasAnyFeature($arguments) ? 'true' : 'false'; ?>";
        });
    }
}
