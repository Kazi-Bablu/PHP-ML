<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class SalesTableSeeder extends Seeder
{
    public function run()
    {
        ini_set('memory_limit', '-1');
        set_time_limit(0); // Extend time limit for long-running processes


        $faker = Faker::create();
        $batchSize = 10000; // Adjust the batch size as needed
        $totalRecords = 10000000;

        for ($i = 0; $i < $totalRecords; $i += $batchSize) {
            $salesData = [];

            for ($j = 0; $j < $batchSize; $j++) {
                $salesData[] = [
                    'product_id' => $faker->numberBetween(1, 1000),
                    'quantity' => $faker->numberBetween(1, 100),
                    'price' => $faker->randomFloat(2, 1, 100000),
                    'created_at' => $faker->dateTimeBetween('-144 months', 'now'),
                    'updated_at' => now(),
                ];
            }

            DB::table('sales')->insert($salesData);
        }
    }
}
