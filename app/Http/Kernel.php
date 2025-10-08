<?php

namespace App\Http;


use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * The application's route middleware groups.
     *
     * @var array<string, array<int, class-string|string>>
     */
    protected $middlewareGroups = [
        'web' => [
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class, 
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],  

        'api' => [
            \App\Http\Middleware\JsonExceptionMiddleware::class,
            'throttle:api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
    ];  

    
    /**
     * The application's route middleware aliases.
     *
     * These middleware may be assigned to groups or used individually.
     *
     * @var array<string, class-string|string>
     */
    
    protected $middlewareAliases = [
        'resolve.tenant' => \App\Http\Middleware\ResolveTenant::class,
        'kyc' => \App\Http\Middleware\CheckKycStatus::class,
    ];

}