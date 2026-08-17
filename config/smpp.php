<?php
return ['currency' => env('SMS_DEFAULT_CURRENCY', 'BDT'), 'jasmin' => ['http_url'=>env('JASMIN_HTTP_URL'), 'username'=>env('JASMIN_USERNAME'), 'password'=>env('JASMIN_PASSWORD'), 'dlr_url'=>env('JASMIN_DLR_URL'), 'connect_timeout'=>env('JASMIN_CONNECT_TIMEOUT', 5), 'timeout'=>env('JASMIN_TIMEOUT', 20)]];
