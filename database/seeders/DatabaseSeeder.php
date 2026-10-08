<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([ReferensiSeeder::class, PeranSeeder::class]);

        // Akun awal. Kata sandi acak dicetak sekali; wajib diganti saat login pertama.
        if (! User::where('username', 'admin')->exists()) {
            $password = Str::password(16);
            User::create([
                'username' => 'admin',
                'name' => 'Super Admin',
                'email' => 'admin@sirenda-psn.local',
                'password' => Hash::make($password),
                'wajib_ganti_password' => true,
            ])->assignRole('Super Admin');
            $this->command?->warn("Akun admin dibuat -- username: admin, kata sandi: {$password}");
        }
    }
}
