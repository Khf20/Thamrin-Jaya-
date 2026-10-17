<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('AdminUserSeeder hanya boleh dijalankan di local/testing.');
        }

        $name = config('admin.seed.name');
        $email = config('admin.seed.email');
        $password = config('admin.seed.password');

        if (blank($email) && blank($password)) {
            return;
        }

        if (! is_string($name) || ! is_string($email) || ! is_string($password)) {
            throw new RuntimeException('Isi ADMIN_SEED_NAME, ADMIN_SEED_EMAIL, dan ADMIN_SEED_PASSWORD untuk membuat akun admin.');
        }

        $existingUser = User::where('email', $email)->first();

        if ($existingUser) {
            if (! $existingUser->is_admin) {
                throw new RuntimeException('Email ADMIN_SEED_EMAIL sudah digunakan akun non-admin; seeder tidak akan menaikkan hak aksesnya.');
            }

            return;
        }

        $data = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', Password::min(12)->letters()->numbers()],
        ])->validate();

        $admin = new User($data);
        $admin->is_admin = true;
        $admin->save();
    }
}
