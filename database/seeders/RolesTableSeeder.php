<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class RolesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('roles')->delete();
        
        \DB::table('roles')->insert(array (
            0 => 
            array (
                'id' => 1,
                'name' => 'admin',
                'guard_name' => 'web',
                'created_at' => '2025-11-24 08:15:56',
                'updated_at' => '2025-11-24 08:15:56',
            ),
            1 => 
            array (
                'id' => 2,
                'name' => 'senior_staff',
                'guard_name' => 'web',
                'created_at' => '2025-11-24 08:15:56',
                'updated_at' => '2025-11-24 08:15:56',
            ),
            2 => 
            array (
                'id' => 3,
                'name' => 'general_staff',
                'guard_name' => 'web',
                'created_at' => '2025-11-24 08:15:56',
                'updated_at' => '2025-11-24 08:15:56',
            ),
        ));
        
        
    }
}