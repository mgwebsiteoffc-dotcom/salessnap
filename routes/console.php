<?php
use Illuminate\Support\Facades\Schedule;
Schedule::command('promotions:dispatch-due')->everyMinute()->withoutOverlapping()->onOneServer();

Schedule::command('promotions:purge-retained-data')->dailyAt('03:00')->withoutOverlapping()->onOneServer();
