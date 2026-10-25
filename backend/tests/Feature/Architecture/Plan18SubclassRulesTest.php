<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * Step 149: Plan18SubclassRulesTest
 */
test('all exception subclasses are final')
    ->expect([
        \App\Exceptions\AuthException::class,
        \App\Exceptions\DomainConflictException::class,
        \App\Exceptions\InternalException::class,
        \App\Exceptions\NotFoundException::class,
        \App\Exceptions\RateLimitException::class,
        \App\Exceptions\ValidationException::class,
    ])
    ->classes()
    ->toExtend('App\Exceptions\LaraException')
    ->toBeFinal();

test('LaraException itself is not final')
    ->expect('App\Exceptions\LaraException')
    ->not->toBeFinal();

test('Controllers do not instantiate LaraException directly')
    ->expect('App\Exceptions\LaraException')
    ->not->toBeUsedIn('App\Http\Controllers');
