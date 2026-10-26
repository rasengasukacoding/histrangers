<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Privacy minimization: delete signaling promptly and old reports on schedule.
Schedule::call(function (): void {
    DB::table('rtc_signals')->where('created_at', '<', now()->subMinutes(15))->delete();
    DB::table('rtc_exclusions')->where('expires_at', '<', now())->delete();
    DB::table('rtc_peers')->where('status', 'idle')->where('last_seen_at', '<', now()->subDay())->delete();
    DB::table('rtc_reports')->where('created_at', '<', now()->subDays((int) config('rtc.report_retention_days', 90)))->delete();
})->hourly()->name('rtc-prune');
