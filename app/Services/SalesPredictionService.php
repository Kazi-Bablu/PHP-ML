<?php

// app/Services/SalesPredictionService.php

namespace App\Services;

use DB;
use Carbon\Carbon;
use Phpml\Metric\Regression;
use Phpml\Regression\SVR;
use Phpml\SupportVectorMachine\Kernel;
use Exception;
use Illuminate\Support\Facades\Log;

class SalesPredictionService
{
    public function trainModel()
    {
        try {
            ini_set('memory_limit', '-1');
            set_time_limit(0); // Extend time limit for long-running processes

            // Fetch historical sales data in chunks
            $sales = DB::table('accounts')
                ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(id) as quantity'))
                ->groupBy('date')
                ->orderBy('date');

            $samples = [];
            $targets = [];

            $sales->chunk(100, function ($chunk) use (&$samples, &$targets) {
                foreach ($chunk as $sale) {
                    $timestamp = Carbon::parse($sale->date)->timestamp;

                    $samples[] = [$timestamp];
                    $targets[] = $sale->quantity;
                }
            });

            // Normalize the timestamps
            $samples = $this->normalizeData($samples);

            // Use Support Vector Regression (SVR) for better performance
            $regression = new SVR(Kernel::RBF, $cost = 1000, $degree = 3, $gamma = 6);
            $regression->train($samples, $targets);

            // Evaluate the model
            $this->evaluateModel($regression, $samples, $targets);

            return $regression;

        } catch (Exception $e) {
            Log::error('Error in trainModel method: ' . $e->getMessage());
            // You can handle the exception here as per your application's requirement
            // Example: throw $e; // Rethrow the exception if you want to propagate it
            return null; // Return null or handle the error gracefully
        }
    }

    public function evaluateModel($model, $samples, $targets)
    {
        try {
            $predicted = array_map(function ($sample) use ($model) {
                return $model->predict($sample);
            }, $samples);

            $mae = Regression::meanAbsoluteError($targets, $predicted);
            $mse = Regression::meanSquaredError($targets, $predicted);
            $r2 = Regression::r2score($targets, $predicted);

            echo "Mean Absolute Error (MAE): " . $mae . PHP_EOL;
            echo "Mean Squared Error (MSE): " . $mse . PHP_EOL;
            echo "R² Score: " . $r2 . PHP_EOL;

        } catch (Exception $e) {
            Log::error('Error in evaluateModel method: ' . $e->getMessage());
            // Handle or log the error as needed
            // Example: throw $e;
        }
    }

    public function predictDailySales($model, $days)
    {
        try {
            $predictions = [];
            $currentDate = Carbon::now();

            for ($i = 0; $i < $days; $i++) {
                $date = $currentDate->copy()->addDays($i)->startOfDay();
                $timestamp = $date->timestamp;
                $normalizedTimestamp = $this->normalizeData([[$timestamp]])[0];
                $predictedQuantity = $model->predict($normalizedTimestamp);

                $predictions[] = [
                    'date' => $date->toDateString(),
                    'predicted_quantity' => $predictedQuantity,
                    'lower_bound' => null,
                    'upper_bound' => null,
                    'probability' => null,
                ];
            }

            return $predictions;

        } catch (Exception $e) {
            Log::error('Error in predictDailySales method: ' . $e->getMessage());
            // Handle or log the error as needed
            // Example: throw $e;
            return []; // Return an empty array or handle the error gracefully
        }
    }

    private function normalizeData($data)
    {
        try {
            $flattenedData = array_merge(...$data);
            $min = min($flattenedData);
            $max = max($flattenedData);

            // Check for division by zero
            if ($max == $min) {
                return $data; // Return original data if all values are identical
            }

            return array_map(function ($sample) use ($min, $max) {
                return [(($sample[0] - $min) / ($max - $min))];
            }, $data);

        } catch (Exception $e) {
            Log::error('Error in normalizeData method: ' . $e->getMessage());
            // Handle or log the error as needed
            // Example: throw $e;
            return $data; // Return original data or handle the error gracefully
        }
    }

    // Add additional methods as needed for data preprocessing, model tuning, etc.
}
