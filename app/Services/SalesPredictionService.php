<?php

// app/Services/SalesPredictionService.php

namespace App\Services;

use Carbon\Carbon;
use DB;
use Phpml\Metric\Regression;
use Phpml\Regression\LeastSquares;

class SalesPredictionService
{
    public function trainModel()
    {
        // Fetch historical sales data
        $sales = DB::table('sales')
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(quantity) as quantity'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->chunk(1000); // Chunking data to handle large datasets efficiently

        $samples = [];
        $targets = [];

        foreach ($sales as $chunk) {
            foreach ($chunk as $sale) {
                $samples[] = [Carbon::parse($sale->date)->timestamp];
                $targets[] = $sale->quantity;
            }
        }

        $regression = new LeastSquares();
        $regression->train($samples, $targets);

        // Evaluate the model
        $this->evaluateModel($regression, $samples, $targets);

        return $regression;
    }

    public function evaluateModel($model, $samples, $targets)
    {
        $predicted = [];
        foreach ($samples as $sample) {
            $predicted[] = $model->predict($sample);
        }

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
            $predictedQuantity = $model->predict([$timestamp]);

            $predictions[] = [
                'date' => $date->toDateString(),
                'predicted_quantity' => $predictedQuantity,
                'lower_bound' => null, // Optional: you can remove these if you are not calculating them
                'upper_bound' => null, // Optional: you can remove these if you are not calculating them
                'probability' => null, // Optional: you can remove this if you are not calculating it
            ];
        }

        return $predictions;
    }
}
