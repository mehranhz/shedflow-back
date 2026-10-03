<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
         $users = User::factory(10)->create();
         Organization::factory(10)->recycle($users)->create()->each(function (Organization $organization) use ($users) {
             $organization->users()->attach(
                 $users->random(rand(1,10))->pluck('id')->toArray()
                 ,["type"=>"member","status"=>"pending"]
             );
         });

//        User::factory()->create([
//            'name' => 'Test User',
//            'email' => 'test@example.com',
//        ]);
    }
}
