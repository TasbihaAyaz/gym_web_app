<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Browser check-in alerts (web toast + Web Push)
    |--------------------------------------------------------------------------
    |
    | When false, the in-browser check-in slide and push notifications are
    | disabled. Desktop / Windows app polling still works normally.
    |
    */
    'browser_alerts' => (bool) env('CHECKIN_BROWSER_ALERTS', true),

];
