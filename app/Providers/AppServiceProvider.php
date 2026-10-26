<?php

namespace App\Providers;

use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $limits = function (Request $request, int $perIp, int $perSession): array {
            $session = hash('sha256', $request->session()->getId());
            return [
                Limit::perMinute($perIp)->by('ip:'.($request->ip() ?? 'unknown')),
                Limit::perMinute($perSession)->by('session:'.$session),
            ];
        };
        RateLimiter::for('rtc.join', fn (Request $r) => $limits($r, 30, 12));
        RateLimiter::for('rtc.state', fn (Request $r) => $limits($r, 600, 180));
        RateLimiter::for('rtc.signal', fn (Request $r) => $limits($r, 360, 150));
        RateLimiter::for('rtc.action', fn (Request $r) => $limits($r, 80, 40));
        RateLimiter::for('rtc.report', fn (Request $r) => [
            Limit::perHour(12)->by('ip:'.($r->ip() ?? 'unknown')),
            Limit::perHour(5)->by('session:'.hash('sha256', $r->session()->getId())),
        ]);
    }
}
