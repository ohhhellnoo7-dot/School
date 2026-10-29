<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ParentSeeder extends Seeder
{
    public function run(): void
    {
        for ($i = 1; $i <= 50; $i++) {
            CambodianSchoolData::account('PAR'.CambodianSchoolData::key($i), 'parent', [
                'name' => CambodianSchoolData::SURNAMES[($i - 1) % 10].' '.['Sokun', 'Srey Mom', 'Bunna', 'Srey Leak', 'Sambath'][intdiv($i - 1, 10)],
                'gender' => $i % 2 ? 'male' : 'female', 'phone' => '015'.(300000 + $i),
                'address' => CambodianSchoolData::address($i),
            ]);
        }
    }
}
