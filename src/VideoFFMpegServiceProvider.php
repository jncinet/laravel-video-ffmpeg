<?php

namespace Qihucms\MediaProcessing;

use Illuminate\Support\ServiceProvider;

class MediaProcessingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(FFMpeg::class, function () {
            return new FFMpeg();
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
