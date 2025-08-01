<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'name' => 'Administrador',
            'email' => 'admin@agenda.com',
            'password' => Hash::make('123456'),
            'rol' => 'admin',
            'activo' => true,
        ]);

        User::create([
            'name' => 'Recepcionista',
            'email' => 'recepcionista@agenda.com',
            'password' => Hash::make('123456'),
            'rol' => 'recepcionista',
            'activo' => true,
        ]);
    }
}
