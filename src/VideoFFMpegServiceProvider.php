<?php

namespace Qihucms\VideoFFMpeg;

use Illuminate\Support\ServiceProvider;

class VideoFFMpegServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('videoFFMpeg', function () {
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
