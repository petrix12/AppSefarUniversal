<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Justijndepover\Teamleader\Teamleader;
use Justijndepover\Teamleader\Exceptions\ApiException as TeamleaderException;

class TeamLeaderController extends Controller
{
    public function checkteamleader()
    {
        $teamleader = new Teamleader(env('TEAMLEADER_CLIENT_ID'), env('TEAMLEADER_CLIENT_SECRET'), env('TEAMLEADER_REDIRECT_URI'), 'FtvPC1SE2h3LVPEJZIsrfaVWTwwn7T0R');

        header("Location: {$teamleader->redirectForAuthorizationUrl()}");
        exit;
    }

    public function tlprocess()
    {
        $teamleader = new Teamleader(env('TEAMLEADER_CLIENT_ID'), env('TEAMLEADER_CLIENT_SECRET'), env('TEAMLEADER_REDIRECT_URI'), 'FtvPC1SE2h3LVPEJZIsrfaVWTwwn7T0R');

        if (isset($_GET['error'])) {
            return "hubo un error al conectarse a TL";
        }

        if (isset($_GET['state']) && $_GET['state'] != $teamleader->getState()) {
            return "hubo un error revisando el State";
        }

        $teamleader->setAuthorizationCode($_GET['code']);
        $teamleader->connect();

        // store these values:
        $tokens["accessToken"] = $teamleader->getAccessToken();
        $tokens["refreshToken"] = $teamleader->getRefreshToken();
        $tokens["expiresAt"] = $teamleader->getTokenExpiresAt();

        Storage::disk('local')->put('loginTeamLeader.json', json_encode($tokens));

        return redirect()->route('queryTL');
    }

    public function queryTL()
    {
        $time=0.75;
        if (!Storage::disk('local')->exists('loginTeamLeader.json')) {
            return redirect()->route('checkteamleader');
        }

        $myarray = json_decode(Storage::disk('local')->get('loginTeamLeader.json'), true);

        if ($myarray["expiresAt"] < time()) {
            return redirect()->route('checkteamleader');
        }

        $teamleader = new Teamleader(env('TEAMLEADER_CLIENT_ID'), env('TEAMLEADER_CLIENT_SECRET'), env('TEAMLEADER_REDIRECT_URI'), 'FtvPC1SE2h3LVPEJZIsrfaVWTwwn7T0R');
        $teamleader->setAccessToken($myarray["accessToken"]);
        $teamleader->setRefreshToken($myarray["refreshToken"]);
        $teamleader->setTokenExpiresAt($myarray["expiresAt"]);

        
        if (Storage::disk('local')->exists('Fields-TeamLeaderData.json')) {
            $alldata = json_decode(Storage::disk('local')->get('Fields-TeamLeaderData.json'), true);
        } else {
            $alldata = [];

            $i = 1+0;
            $j = 0;

            while ($j == 0) {
                $forfetch = '{
                  "page": {
                    "size": 100,
                    "number": ' . $i . '
                  }
                }';
                $fetched = $teamleader->get('customFieldDefinitions.list', json_decode($forfetch, true));
                $alldata = array_merge($alldata, $fetched['data']);

                if(sizeof($fetched['data'])!=100){
                    $j = 1;
                } else {
                    $i = $i + 1;
                }
                
                sleep($time);
            }
        }

        $tokens["accessToken"] = $teamleader->getAccessToken();
        $tokens["refreshToken"] = $teamleader->getRefreshToken();
        $tokens["expiresAt"] = $teamleader->getTokenExpiresAt();

        Storage::disk('local')->put('Fields-TeamLeaderData.json', json_encode($alldata));

        $i = 0;

        /*

        foreach ($alldata as $key => $contact) {
            if (!array_key_exists('extended_data', $alldata[$key])) {
                $variables = '{
                    "id": "'.$contact["id"].'"
                }';

                $fetched = $teamleader->get('customFieldDefinitions.info', json_decode($variables, true));
                $alldata[$key]["extended_data"] = $fetched["data"];
                
                sleep($time);

                $i = $i + 1;

                if ($i==500){
                    Storage::disk('local')->put('Fields-TeamLeaderData.json', json_encode($alldata));
                    $i = 0;
                }
            }            
        }



        // you should always store your tokens at the end of a call
        $tokens["accessToken"] = $teamleader->getAccessToken();
        $tokens["refreshToken"] = $teamleader->getRefreshToken();
        $tokens["expiresAt"] = $teamleader->getTokenExpiresAt();

        */

        Storage::disk('local')->put('loginTeamLeader.json', json_encode($tokens));

        Storage::disk('local')->put('Fields-TeamLeaderData.json', json_encode($alldata));
    }
}