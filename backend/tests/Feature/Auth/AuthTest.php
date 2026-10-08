<?php

use App\Enum\Role;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    // Request dari domain frontend dianggap stateful oleh Sanctum (memakai session)
    config(['sanctum.stateful' => ['localhost:5173']]);
    $this->withHeader('Origin', 'http://localhost:5173');
});

function validRegistration(array $overrides = []): array
{
    return array_merge([
        'name' => 'Budi Santoso',
        'email' => 'budi@example.test',
        'phone' => '081234567890',
        'password' => 'rahasia-banget-123',
        'password_confirmation' => 'rahasia-banget-123',
        'crm_consent' => true,
    ], $overrides);
}

// Registrasi

it('mendaftarkan klien baru dan langsung login', function () {
    $this->postJson('/api/v1/auth/register', validRegistration())
        ->assertCreated()
        ->assertJsonPath('data.role', 'client')
        ->assertJsonPath('data.crm_consent', true);

    $user = User::where('email', 'budi@example.test')->firstOrFail();

    expect($user->role)->toBe(Role::Client)
        ->and($user->crm_consent_at)->not->toBeNull()
        ->and($user->phone)->toBe('6281234567890');

    $this->assertAuthenticatedAs($user);
});

it('menyimpan persetujuan CRM kosong kalau klien menolak', function () {
    $this->postJson('/api/v1/auth/register', validRegistration(['crm_consent' => false]))
        ->assertCreated()
        ->assertJsonPath('data.crm_consent', false);

    expect(User::firstWhere('email', 'budi@example.test')->crm_consent_at)->toBeNull();
});

it('mengabaikan role yang dikirim saat pendaftaran', function () {
    $this->postJson('/api/v1/auth/register', validRegistration(['role' => 'manager']))
        ->assertCreated();

    expect(User::firstWhere('email', 'budi@example.test')->role)->toBe(Role::Client);
});

it('menolak pendaftaran tanpa pilihan persetujuan CRM', function () {
    $this->postJson('/api/v1/auth/register', validRegistration(['crm_consent' => null]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('crm_consent');
});

it('menolak email yang sudah terdaftar', function () {
    User::factory()->create(['email' => 'budi@example.test']);

    $this->postJson('/api/v1/auth/register', validRegistration())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

// Login dan logout

it('login dengan kredensial yang benar', function () {
    $user = User::factory()->create(['password' => 'rahasia-123']);

    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'rahasia-123'])
        ->assertOk()
        ->assertJsonPath('data.email', $user->email);

    $this->assertAuthenticatedAs($user);
});

it('menolak kata sandi yang salah', function () {
    $user = User::factory()->create(['password' => 'rahasia-123']);

    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'salah-total'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    $this->assertGuest();
});

it('menolak login akun yang dinonaktifkan', function () {
    $user = User::factory()->inactive()->create(['password' => 'rahasia-123']);

    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'rahasia-123'])
        ->assertUnprocessable();

    $this->assertGuest();
});

it('logout mengakhiri sesi', function () {
    $user = User::factory()->create(['password' => 'rahasia-123']);

    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'rahasia-123'])
        ->assertOk();

    $this->postJson('/api/v1/auth/logout')->assertNoContent();

    // Setelah route auth:sanctum dipanggil, guard bawaan berpindah ke 'sanctum'
    // dan menyimpan user dari request itu selama proses test yang sama.
    // Sesi login disimpan di guard 'web', jadi periksa guard itu.
    $this->assertGuest('web');
});

// Data pengguna saat ini

it('me menolak pengguna yang belum login', function () {
    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
});

it('me mengembalikan data pengguna yang sedang login', function () {
    Sanctum::actingAs(User::factory()->operator()->create());

    $this->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.role', 'operator');
});

it('me menolak akun yang dinonaktifkan', function () {
    Sanctum::actingAs(User::factory()->inactive()->create());

    $this->getJson('/api/v1/auth/me')->assertForbidden();
});

// Pembatasan peran

it('membatasi route berdasarkan peran', function () {
    Route::middleware(['auth:sanctum', 'active', 'role:manager'])
        ->get('/api/v1/_test/manager-only', fn () => response()->json(['ok' => true]));

    Sanctum::actingAs(User::factory()->create());
    $this->getJson('/api/v1/_test/manager-only')->assertForbidden();

    Sanctum::actingAs(User::factory()->operator()->create());
    $this->getJson('/api/v1/_test/manager-only')->assertForbidden();

    Sanctum::actingAs(User::factory()->manager()->create());
    $this->getJson('/api/v1/_test/manager-only')->assertOk();
});

it('mengizinkan beberapa peran sekaligus', function () {
    Route::middleware(['auth:sanctum', 'active', 'role:manager,operator'])
        ->get('/api/v1/_test/staff-only', fn () => response()->json(['ok' => true]));

    Sanctum::actingAs(User::factory()->create());
    $this->getJson('/api/v1/_test/staff-only')->assertForbidden();

    Sanctum::actingAs(User::factory()->operator()->create());
    $this->getJson('/api/v1/_test/staff-only')->assertOk();
});