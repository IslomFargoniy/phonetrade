<?php

use Illuminate\Support\Facades\Schedule;

/*
 * Serverda doimiy worker jarayoni yo'q. Navbat har daqiqada cron orqali
 * ishga tushadi, ishlar tugagach to'xtaydi.
 */
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping(2);
