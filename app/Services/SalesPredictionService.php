<?php

// app/Services/SalesPredictionService.php

namespace App\Services;

use DB;
use Carbon\Carbon;
use Phpml\Metric\Regression;
use Phpml\Regression\SVR;
use Phpml\SupportVectorMachine\Kernel;

class SalesPredictionService
{
    public function trainModel()
    {
        ini_set('memory_limit', '-1');
        set_time_limit(0); // Extend time limit for long-running processes

        // Fetch historical sales data in chunks
        $sales = DB::table('sales')
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(quantity) as quantity'))
            ->groupBy('date')
            ->orderBy('date');

        $samples = [];
        $targets = [];


        $sales->chunk(1000, function ($chunk) use (&$samples, &$targets) {
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
    }

    public function evaluateModel($model, $samples, $targets)
    {
        $predicted = array_map(function ($sample) use ($model) {
            return $model->predict($sample);
        }, $samples);

        $mae = Regression::meanAbsoluteError($targets, $predicted);
        $mse = Regression::meanSquaredError($targets, $predicted);
        $r2 = Regression::r2score($targets, $predicted);

        echo "Mean Absolute Error (MAE): " . $mae . PHP_EOL;
        echo "Mean Squared Error (MSE): " . $mse . PHP_EOL;
        echo "R² Score: " . $r2 . PHP_EOL;
    }

    public function predictDailySales($model, $days)
    {
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
    }

    private function normalizeData($data)
    {
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
    }
}
