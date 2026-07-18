<?php

namespace Database\Seeders;

use App\Models\Institution;
use Illuminate\Database\Seeder;

class InstitutionSeeder extends Seeder
{
    /**
     * Seed the built-in (system-wide) institutions, visible to every user.
     */
    public function run(): void
    {
        $institutions = [
            'Nubank' => '#8a05be',
            'Bradesco' => '#cc092f',
            'Inter' => '#ff7a00',
            'PicPay' => '#21c25e',
            'Agibank' => '#ff6900',
        ];

        foreach ($institutions as $name => $color) {
            Institution::withoutGlobalScopes()->firstOrCreate(
                ['name' => $name, 'user_id' => null],
                ['color' => $color]
            );
        }
    }
}
