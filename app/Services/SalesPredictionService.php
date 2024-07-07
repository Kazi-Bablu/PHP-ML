<?php

namespace App\Services;

use Carbon\Carbon;
use DB;
use Phpml\Dataset\ArrayDataset;
use Phpml\Regression\LeastSquares;
use Phpml\Preprocessing\Normalizer;

class SalesPredictionService
{
    /**
     * Train a sales prediction model based on historical data.
     *
     * @return LeastSquares
     */
    public function trainModel()
    {
        // Fetch historical sales data
        $sales = DB::table('sales')
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(quantity) as quantity'),
                DB::raw('DAYOFWEEK(created_at) as day_of_week'),
                DB::raw('MONTH(created_at) as month'),
                DB::raw('YEAR(created_at) as year')
            )
            ->groupBy('date', 'day_of_week', 'month', 'year')
            ->get();

        $samples = [];
        $targets = [];

        foreach ($sales as $sale) {
            $date = Carbon::parse($sale->date);
            $sample = [
                $date->timestamp,
                $date->dayOfWeek,
                $date->month,
            ];

            $samples[] = $sample;
            $targets[] = $sale->quantity;
        }

        // Normalize features
        $normalizer = new Normalizer();
        $samples = $normalizer->transform($samples);

        // Create dataset
        $dataset = new ArrayDataset($samples, $targets);

        // Train model
        $regression = new LeastSquares();
        $regression->train($dataset->getSamples(), $dataset->getTargets());

        // Evaluate the model (optional)
        //$this->evaluateModel($regression, $samples, $targets);

        return $regression;
    }

    /**
     * Predict daily sales quantities for the next `days` days.
     *
     * @param LeastSquares $model
     * @param int $days
     * @return array
     */
    public function predictDailySales($model, $days)
    {
        $predictions = [];
        $currentDate = Carbon::now();

        for ($i = 0; $i < $days; $i++) {
            $date = $currentDate->copy()->addDays($i)->startOfDay();
            $sample = [$date->timestamp, $date->dayOfWeek, $date->month];
            $predictedQuantity = $model->predict($sample);

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
}
