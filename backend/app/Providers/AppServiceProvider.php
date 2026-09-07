<?php

namespace App\Providers;

use App\Actions\Pdf\ShapeArabicText;
use ArPHP\I18N\Arabic;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Building the Arabic tables is not free, and a single receipt runs
        // several fields through it.
        $this->app->singleton(Arabic::class);
        $this->app->singleton(ShapeArabicText::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Marks the values in a PDF view that may hold Arabic, so DomPDF —
        // which cannot shape it on its own — receives text it can draw.
        Blade::directive('arabic', function (string $expression) {
            return "<?php echo e(app(\App\Actions\Pdf\ShapeArabicText::class)->handle($expression)); ?>";
        });
    }
}
