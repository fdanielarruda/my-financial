<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class PeopleSeeder extends Seeder
{
    /**
     * Seed the default household people for every user that has none yet.
     */
    public function run(): void
    {
        $defaults = [
            'Eu' => '#2563eb',
            'Irmã' => '#db2777',
            'Pai' => '#059669',
            'Mãe' => '#d97706',
        ];

        User::all()->each(function (User $user) use ($defaults) {
            if ($user->people()->exists()) {
                return;
            }

            foreach ($defaults as $name => $color) {
                $user->people()->create(['name' => $name, 'color' => $color]);
            }
        });
    }
}
