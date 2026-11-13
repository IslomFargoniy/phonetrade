<?php

use Illuminate\Support\Facades\Schedule;

/*
 * Serverda doimiy worker jarayoni yo'q. Navbat har daqiqada cron orqali
 * ishga tushadi, ishlar tugagach to'xtaydi.
 */
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping(2);

/*
 * Markaziy bank kursi ertalab e'lon qilinadi. Bir marta ololmasa, keyingi
 * urinishlar to'ldiradi; kursi bor do'konlarga tegilmaydi.
 */
Schedule::command('rates:fetch')->dailyAt('07:00');
Schedule::command('rates:fetch')->dailyAt('09:00');
Schedule::command('rates:fetch')->dailyAt('12:00');
