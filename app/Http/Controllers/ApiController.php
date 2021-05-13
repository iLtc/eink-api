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

    public function habitica(Request $request) {
        $response = Http::withHeaders([
            'x-api-user' => env('HABITICA_USERID'),
            'x-api-key' => env('HABITICA_TOKEN')
        ])->get('https://habitica.com/api/v3/user');

        if ($response->failed()) {
            return response()->json([
                'status' => 'fail',
                'reason' => 'HTTP Request Returns '.$response->status()
            ], $response->status());
        }

        $tasks_order = $response['data']['tasksOrder'];

        $response = Http::withHeaders([
            'x-api-user' => env('HABITICA_USERID'),
            'x-api-key' => env('HABITICA_TOKEN')
        ])->get('https://habitica.com/api/v3/tasks/user');

        if ($response->failed()) {
            return response()->json([
                'status' => 'fail',
                'reason' => 'HTTP Request Returns '.$response->status()
            ], $response->status());
        }

        $old_data = $response['data'];

        $data = array();

        foreach ($old_data as &$item) {
            if ($item['type'] != 'daily')
                continue;

            $data[$item['id']] = array(
                'type' => $item['type'],
                'isDue' => $item['isDue'],
                'completed' => $item['completed'],
                'text' => $item['text']
            );
        }

        $sorted_data = array();

        foreach ($tasks_order['dailys'] as &$id) {
            array_push($sorted_data, $data[$id]);
        }

        return response()->json([
            'status' => 'success',
            'data' => $sorted_data
        ]);
    }

    public function omnifocus(Request $request) {
        $now = new \DateTime('now', new \DateTimeZone('America/New_York'));

        $end_of_today = new \DateTime('now', new \DateTimeZone('America/New_York'));
        $end_of_today->setTime(23, 59, 59);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.env('TASKS_SERVER_TOKEN')
        ])->get('https://tasks.iltc.app/api/tasks?start='. $now->format(\DateTime::ISO8601) .'&end='. $end_of_today->format(\DateTime::ISO8601));

        if ($response->failed()) {
            return response()->json([
                'status' => 'fail',
                'reason' => 'HTTP Request Returns '.$response->status()
            ], $response->status());
        }

        $results = array();

        foreach ($response->json() as &$task) {
            if ($task['active'] == 0 || $task['completed'] == 1 || $task['taskStatus'] == 'Completed')
                continue;

            if ($task['dueDate'] == null)
                $task['dueDate'] = '';

            $task['dueDate'] = str_replace('.000000Z', '', $task['dueDate']);

            array_push($results, $task);
        }

        return response()->json([
            'status' => 'success',
            'data' => $results
        ]);
    }
}
