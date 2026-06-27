<?php

namespace App\Providers;

use App\Models\Admin;
use App\Models\Building;
use App\Models\Contract;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\MaintenanceRequest;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Region;
use App\Models\Room;
use App\Models\RoomMovement;
use App\Models\Tenant;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        App::setLocale('vi');

        Relation::enforceMorphMap([
            'admin'               => Admin::class,
            'building'            => Building::class,
            'contract'            => Contract::class,
            'expense'             => Expense::class,
            'invoice'             => Invoice::class,
            'maintenance_request' => MaintenanceRequest::class,
            'notification'        => Notification::class,
            'payment'             => Payment::class,
            'region'              => Region::class,
            'room'                => Room::class,
            'room_movement'       => RoomMovement::class,
            'tenant'              => Tenant::class,
        ]);

        RateLimiter::for('chat-send', function (Request $request): Limit {
            $user = $request->user('admin') ?? $request->user('tenant');
            $guard = $request->user('admin') ? 'admin' : 'tenant';

            return Limit::perMinute(60)->by($guard . ':' . ($user?->id ?? $request->ip()));
        });

        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }
    }
}
