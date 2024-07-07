<?php
// app/Http/Controllers/SalesPredictionController.php

namespace App\Http\Controllers;

use App\Services\SalesPredictionService;
use Illuminate\Http\Request;

class SalesPredictionController extends Controller
{
    protected $salesPredictionService;

    public function __construct(SalesPredictionService $salesPredictionService)
    {
        $this->salesPredictionService = $salesPredictionService;
    }

    public function predict(Request $request)
    {
        $days = $request->input('days', 30);
        $model = $this->salesPredictionService->trainModel();
        $predictions = $this->salesPredictionService->predictDailySales($model, $days);

        $response = [
            'daily_predictions' => $predictions,
        ];

        return response()->json($response);
    }
}
