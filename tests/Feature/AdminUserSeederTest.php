<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class AdminUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_seeder_creates_admin_from_config_without_overwriting_existing_account(): void
    {
        config()->set('admin.seed', [
            'name' => 'CMS Admin',
            'email' => 'cms@example.com',
            'password' => 'StrongPassword123',
        ]);

        $this->seed(AdminUserSeeder::class);

        $admin = User::where('email', 'cms@example.com')->firstOrFail();
        $this->assertTrue($admin->is_admin);
        $this->assertTrue(Hash::check('StrongPassword123', $admin->password));

        config()->set('admin.seed.password', 'ChangedPassword123');
        $this->seed(AdminUserSeeder::class);

        $this->assertTrue(Hash::check('StrongPassword123', $admin->fresh()->password));
        $this->assertDatabaseCount('users', 1);
    }

    public function test_admin_seeder_skips_when_credentials_are_not_configured(): void
    {
        config()->set('admin.seed.email', null);
        config()->set('admin.seed.password', null);

        $this->seed(AdminUserSeeder::class);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_admin_seeder_does_not_promote_an_existing_non_admin(): void
    {
        User::factory()->create(['email' => 'cms@example.com', 'is_admin' => false]);
        config()->set('admin.seed', [
            'name' => 'CMS Admin',
            'email' => 'cms@example.com',
            'password' => 'StrongPassword123',
        ]);

        $this->expectException(RuntimeException::class);
        $this->seed(AdminUserSeeder::class);
    }
}
