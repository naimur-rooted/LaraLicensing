<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\LaraException;
use Closure;
use Illuminate\Http\Request;
use Lauthz\Facades\Enforcer;

class CasbinPepMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     *
     * @throws LaraException
     */
    public function handle(Request $request, Closure $next)
    {
        $sub = (string) $request->user()?->getAuthIdentifier();
        $obj = '/' . ltrim($request->path(), '/');
        $act = strtoupper($request->method());

        $allowed = class_exists(\Lauthz\Facades\Enforcer::class) ? \Lauthz\Facades\Enforcer::enforce($sub, $obj, $act) : true;
        if ($allowed === false) {
            $this->handleDenial($request, $sub, $obj, $act);
        }

        \Illuminate\Support\Facades\Log::channel('lara-diag')?->debug('casbin.enforce', [
            'RequestId' => $request->attributes->get('RequestId'),
            'sub' => $sub,
            'obj' => $obj,
            'act' => $act,
            'allowed' => true,
        ]);

        return $next($request);
    }

    private function handleDenial(Request $request, string $sub, string $obj, string $act): void
    {
        \Illuminate\Support\Facades\Log::channel('lara-diag')?->warning('casbin.enforce', [
            'RequestId' => $request->attributes->get('RequestId'),
            'sub' => $sub,
            'obj' => $obj,
            'act' => $act,
            'allowed' => false,
        ]);

        throw \App\Exceptions\AuthException::forbidden('Rbac.Denied', [
            'sub' => $sub,
            'obj' => $obj,
            'act' => $act,
        ]);
    }
}
