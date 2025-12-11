<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class PermissionsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('permissions')->delete();
        
        \DB::table('permissions')->insert(array (
            0 => 
            array (
                'id' => 1,
                'name' => 'view assets',
                'guard_name' => 'web',
                'created_at' => '2025-11-24 08:15:56',
                'updated_at' => '2025-11-24 08:15:56',
            ),
            1 => 
            array (
                'id' => 2,
                'name' => 'manage assets',
                'guard_name' => 'web',
                'created_at' => '2025-11-24 08:15:56',
                'updated_at' => '2025-11-24 08:15:56',
            ),
            2 => 
            array (
                'id' => 3,
                'name' => 'create work order',
                'guard_name' => 'web',
                'created_at' => '2025-11-24 08:15:56',
                'updated_at' => '2025-11-24 08:15:56',
            ),
            3 => 
            array (
                'id' => 4,
                'name' => 'scan items',
                'guard_name' => 'web',
                'created_at' => '2025-11-24 08:15:56',
                'updated_at' => '2025-11-24 08:15:56',
            ),
            4 => 
            array (
                'id' => 5,
                'name' => 'manage users',
                'guard_name' => 'web',
                'created_at' => '2025-11-24 08:15:56',
                'updated_at' => '2025-11-24 08:15:56',
            ),
        ));
        
        
    }
}