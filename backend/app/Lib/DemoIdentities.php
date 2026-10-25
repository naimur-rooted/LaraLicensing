<?php

declare(strict_types=1);

namespace App\Lib;

final class DemoIdentities
{
    public const PASSWORD = 'demo123';

    public static function password(): string
    {
        return self::PASSWORD;
    }

    /**
     * @return list<array{Email: string, Role: string, Capabilities: list<string>}>
     */
    public static function all(): array
    {
        return [
            [
                'Email' => 'admin@demo.lara.test',
                'Role' => 'Admin',
                'Capabilities' => ['*'],
            ],
            [
                'Email' => 'reseller@demo.lara.test',
                'Role' => 'Reseller',
                'Capabilities' => ['reseller.*'],
            ],
            [
                'Email' => 'user@licensingportal.local',
                'Role' => 'EndUser',
                'Capabilities' => ['portal.*'],
            ],
        ];
    }
}
