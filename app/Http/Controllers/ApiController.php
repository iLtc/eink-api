<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

use Google_Client;
use Google_Service_Calendar;

class ApiController extends Controller
{
    public function callAction($method, $parameters) {
        $token = $parameters[0]->query('token', '');

        if (!env('APP_DEBUG', false) && $token != env('EINK_TOKEN', '')) {
            return response()->json([
                'status' => 'fail',
                'reason' => 'Miss eInk Token'
            ], 403);
        }

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
            'tasks' => $results
        ]);
    }

    public function calendar(Request $request) {
        $calendars_data = json_decode(Storage::get('calendars.json'), true);
        $events = array();

        $now = now();
        $tomorrow = now()->addDay();

        foreach ($calendars_data as $account => $calendars) {
            $client = new Google_Client();
            $client->setAuthConfig(storage_path('app/client_secret.json'));

            if (Storage::exists('auth/'.$account)) {
                $tokens = json_decode(Storage::get('auth/'.$account), true);

                $client->setAccessToken($tokens);

                if ($client->isAccessTokenExpired()) {
                    $client->fetchAccessTokenWithRefreshToken();

                    if (!$client->isAccessTokenExpired()) {
                        Storage::put('auth/' . $account, json_encode($client->getAccessToken()));
                    }
                }
            }

            if ($client->isAccessTokenExpired()) {
                return response()->json([
                    'status' => 'fail',
                    'reason' => $account . ' needs to be authenticated!',
                    'url' => route('google_auth', ['account' => $account])
                ], 403);
            }

            $service = new Google_Service_Calendar($client);

            foreach ($calendars as $name => $details) {
                $results = $service->events->listEvents($details['id'], array(
                    'orderBy' => 'startTime',
                    'singleEvents' => true,
                    'timeMin' => $now->toIso8601String(),
                    'timeMax' => $tomorrow->toIso8601String()
                ));

                $data = $results->getItems();

                foreach ($data as $event) {
                    array_push($events, array(
                        'calendar' => $name,
                        'important' => $details['important'],
                        'start' => $event['start'],
                        'end' => $event['end'],
                        'summary' => $event['summary']
                    ));
                }
            }
        }

        usort($events, function ($e1, $e2) {
            return $e1['start']['dateTime'] <=> $e2['start']['dateTime'];
        });

        return response()->json([
            'status' => 'success',
            'events' => $events
        ]);
    }

    public function all_in_one(Request $request) {
        $original_data = array(
            'weather' => $this->weather($request),
            'habitica' => $this->habitica($request),
            'omnifocus' => $this->omnifocus($request),
            'calendar' => $this->calendar($request)
        );

        $data = array();

        foreach ($original_data as $name => $temp) {
            $json = json_decode($temp->content(), true);

            if ($temp->getStatusCode() != 200) {
                return response()->json([
                    'status' => 'fail',
                    'reason' => 'Failed to get data from '. $name . ': ' . $json['reason']
                ], $temp->getStatusCode());
            }

            $data[$name] = $json;
        }

        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }
}
