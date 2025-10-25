<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\App;

class SetLocale
{
    public function handle($request, Closure $next)
    {
        // خذ اللغة من الـ header، أو استخدم الافتراضية
        $locale = $request->header('Accept-Language', config('app.locale'));

        // تأكد أن اللغة موجودة ضمن اللغات المدعومة
        if (in_array($locale, ['en', 'ar'])) {
            App::setLocale($locale);
        } else {
            App::setLocale(config('app.locale'));
        }

        return $next($request);
    }
}
