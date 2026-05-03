<?php

namespace App\Providers;

use App\Notifications\MsegatChannel;
use Illuminate\Notifications\Notification;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        Inertia::share([
            'user' => function () {
                $user = optional(auth())->user();
                if ($user) {
                    $user->load('roles');
                    // Ensure roles are serialized with id
                    if ($user->relationLoaded('roles')) {
                        $user->roles->each(function ($role) {
                            $role->makeVisible(['id']);
                        });
                    }
                }
                return $user;
            },
            'locale' => function () {
                return app()->getLocale();
            },
            'language' => function () {
                return translations(
                    resource_path('lang/'. app()->getLocale() .'.json')
                );
            },
            'flash' => function () {
                return [
                    'success' => session('success'),
                    'warning' => session('warning'),
                    'error' => session('error'),
                ];
            },
        ]);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if (env('APP_USE_HTTPS')) {
            \Illuminate\Pagination\AbstractPaginator::currentPathResolver(function () {
                /** @var \Illuminate\Routing\UrlGenerator $url */
                $url = app('url');
                return $url->current();
            });
            URL::forceScheme('https');
        }
        
        // Force Laravel to use APP_URL for all generated URLs (prevents port issues)
        if (config('app.url')) {
            URL::forceRootUrl(config('app.url'));
        }
        
        // إعداد Media Library للعمل مع المنفذ الصحيح في البيئة المحلية
        if (config('app.env') === 'local') {
            \Spatie\MediaLibrary\MediaCollections\Models\Media::creating(function ($media) {
                // تأكد من أن الروابط تحتوي على المنفذ الصحيح
                if (request()->getPort() && request()->getPort() !== 80) {
                    $media->setCustomProperty('port', request()->getPort());
                }
            });
        }
    }
}
