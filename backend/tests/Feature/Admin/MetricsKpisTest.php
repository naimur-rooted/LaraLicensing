<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Support\SeedHelpers;
use App\Lib\DemoIdentities;

uses(RefreshDatabase::class, SeedHelpers::class);

test('GET /Api/Admin/Metrics returns 200 with non-zero KPIs under default seed', function () {
    $this->seedWithProfile('default');
    
    $identity = DemoIdentities::all()[0]; // SuperAdmin
    $loginRes = $this->postJson('/Api/Auth/Login', [
        'Email' => $identity['Email'],
        'Password' => DemoIdentities::password(),
    ]);
    $token = $loginRes->json()['Results']['AccessToken'];
    
    $res = $this->withToken($token)->getJson('/Api/Admin/Metrics');
    $res->assertStatus(200);
    
    $json = $res->json();
    $row = $json['Results'][0] ?? $json['Results'];
    $this->assertGreaterThanOrEqual(8, $row['Resellers']['TotalCount'] ?? $row['ResellersActive']);
    $this->assertGreaterThanOrEqual(120, $row['Licenses']['TotalCount'] ?? $row['LicensesTotal']);
    $this->assertGreaterThanOrEqual(24, $row['QuotaRequests']['TotalCount'] ?? $row['QuotaRequestsPending']);
});

test('under empty seed, every tile returns 0 without erroring', function () {
    $this->seedWithProfile('empty');
    
    $identity = DemoIdentities::all()[0];
    $loginRes = $this->postJson('/Api/Auth/Login', [
        'Email' => $identity['Email'],
        'Password' => DemoIdentities::password(),
    ]);
    $token = $loginRes->json()['Results']['AccessToken'];
    
    $res = $this->withToken($token)->getJson('/Api/Admin/Metrics');
    $res->assertStatus(200);
    
    $json = $res->json();
    $row = $json['Results'][0] ?? $json['Results'];
    $this->assertSame(0, $row['Resellers']['TotalCount'] ?? $row['ResellersActive']);
    $this->assertSame(0, $row['Licenses']['TotalCount'] ?? $row['LicensesTotal']);
    $this->assertSame(0, $row['QuotaRequests']['TotalCount'] ?? $row['QuotaRequestsPending']);
});

test('reseller role gets AuthzRoleDenied', function () {
    $this->seedWithProfile('default');
    
    $identity = collect(DemoIdentities::all())->firstWhere('Role', 'Reseller');
    $loginRes = $this->postJson('/Api/Auth/Login', [
        'Email' => $identity['Email'],
        'Password' => DemoIdentities::password(),
    ]);
    $token = $loginRes->json()['Results']['AccessToken'];
    
    $res = $this->withToken($token)->getJson('/Api/Admin/Metrics');
    
    $res->assertStatus(403);
    $json = $res->json();
    $this->assertContains($json['Attributes']['Error']['ErrorCode'], ['AuthzRoleDenied', 'AuthForbidden']);
});
