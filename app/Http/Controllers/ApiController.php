<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ApiController extends Controller
{
    public function callAction($method, $parameters) {
        return parent::callAction($method, $parameters);
    }

    public function weather(Request $request) {
        $url = 'https://api.openweathermap.org/data/2.5/onecall?lat='.env('WEATHER_LAT').'&lon='.env('WEATHER_LON').'&units=imperial&appid='.env('OPENWEATHERMAP_APPID');
        $response = Http::get($url);

        if ($response->ok()) {
            return response()->json([
                'status' => 'success',
                'current' => $response['current'],
                'today' => $response['daily'][0],
                'tomorrow' => $response['daily'][1]
            ]);
        } else {
            return response()->json([
                'status' => 'fail',
                'reason' => 'HTTP Request Returns '.$response->status()
            ], $response->status());
        }
    }
}
