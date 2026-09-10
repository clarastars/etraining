<?php

namespace App\Providers;

use App\Listeners\UpdateUsersTimezoneSafely;
use App\Notifications\MsegatChannel;
use App\Support\ChatAppMode;
use Illuminate\Notifications\Notification;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use JamesMills\LaravelTimezone\Listeners\Auth\UpdateUsersTimezone;
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
        $this->app->bind(UpdateUsersTimezone::class, UpdateUsersTimezoneSafely::class);

        Inertia::share([
            'user' => function () {
                $user = optional(auth())->user();
                if ($user) {
                    $user->loadMissing('roles');
                    // Ensure roles are serialized with id
                    if ($user->relationLoaded('roles')) {
                        $user->roles->each(function ($role) {
                            $role->makeVisible(['id']);
                        });
                    }
                    // Middleware may load trainee/instructor; don't serialize their appends globally.
                    $user->unsetRelation('trainee');
                    $user->unsetRelation('instructor');
                    // Opt-in only for shared auth user (not every User serialization / N+1)
                    $user->append('inbox_messages_count');
                }
                return $user;
            },
            'locale' => function () {
                return app()->getLocale();
            },
            // vue-i18n already loads words from vue-i18n-locales.generated.js —
            // do not re-read lang/*.json on every Inertia request.
            'flash' => function () {
                return [
                    'success' => session('success'),
                    'warning' => session('warning'),
                    'error' => session('error'),
                ];
            },
            'ziggy' => function () {
                return \Tightenco\Ziggy\RoutePayload::compile(app('router'));
            },
            'chatAppMode' => function () {
                return ChatAppMode::isActive(request());
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
