<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

use Google_Client;

class AuthController extends Controller
{
    public function google_auth(Request $request) {
        $account = $request->query('account', '');

        if ($account == '')
            return response()->json([
                'status' => 'fail',
                'data' => 'Miss account name'
            ], 404);

        $client = new Google_Client();
        $client->setAuthConfig(storage_path('app/client_secret.json'));

        if (Storage::exists('auth/'.$account)) {
            $tokens = json_decode(Storage::get('auth/'.$account), true);

            $client->setAccessToken($tokens);

            if (!$client->isAccessTokenExpired()) {
                return response()->json([
                    'status' => 'success',
                    'data' => 'No need to update credentials for '. $account .'!'
                ]);
            }
        }

        $state = Str::random(32);

        $request->session()->put('state', $state);
        $request->session()->put('account', $account);


        $client->addScope(env('GOOGLE_AUTH_SCOPE'));
        $client->setRedirectUri(route('google_callback'));
        $client->setAccessType('offline');
        $client->setPrompt("consent");
        $client->setIncludeGrantedScopes(true);
        $client->setState($state);

        $auth_url = $client->createAuthUrl();

        return redirect($auth_url);
    }

    public function google_callback(Request $request) {
        $client = new Google_Client();
        $client->setAuthConfig(storage_path('app/client_secret.json'));
        $client->setRedirectUri(route('google_callback'));

        $state = $request->session()->get('state');
        $account = $request->session()->get('account');

        $request->session()->flush();

        if ($state != $request->query('state'))
            return response()->json([
                'status' => 'fail',
                'data' => 'State mismatch'
            ], 403);

        $client->fetchAccessTokenWithAuthCode($request->query('code'));

        $tokens = $client->getAccessToken();

        Storage::put('auth/'.$account, json_encode($tokens));

        return response()->json([
            'status' => 'success',
            'data' => 'Credentials for '. $account .' have been saved!'
        ]);
    }
}
