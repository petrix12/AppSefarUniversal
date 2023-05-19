<?php

namespace App\Http\Controllers;

use App\Models\Agcliente;
use App\Models\Country;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Stripe;
use App\Exports\StripeExcel;

class StripeController extends Controller
{
	public function stripeverify(){
        return view('stripe.finder');
    }

    public function stripefind(Request $request){
    	Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));
    	$variable = json_decode(json_encode($request->all()),true);

    	$dbsearch = json_decode(json_encode(DB::table('users')->where('email', $variable["info"])->get(["id", "name", "email", "passport", "id_pago"])),true);

		$test = json_decode(json_encode(Stripe\Customer::all(['email' => $variable["info"], 'limit' =>100])),true);

		foreach ($test["data"] as $key => $value) {
			foreach ($dbsearch as $key1 => $value1) {
				if ($value["email"]==$value1["email"]){
					$array["datadb"]=$value1;
					$array["data"]=$value;
					$arraysend[]=$array;
				}
			}
		}

		if (isset($arraysend)){
			return $arraysend;
		} else {
			return "none";
		}
		
    }

    public function stripegetidpago(Request $request){
    	Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));
    	$variable = json_decode(json_encode($request->all()),true);

    	$dbsearch = json_decode(json_encode(DB::table('users')->where('email', $variable["info"])->get(["id", "name", "email", "passport", "id_pago"])),true);

		$test = json_decode(json_encode(Stripe\Customer::all(['email' => $variable["info"], 'limit' =>100])),true);

		foreach ($test["data"] as $key => $value) {
			$test2 = json_decode(json_encode(Stripe\Charge::all(['customer' => $value["id"], 'limit' =>100])),true);
			foreach ($dbsearch as $key1 => $value1) {
				foreach ($test2["data"] as $key2 => $value2) {
					if ($value["email"]==$value1["email"]){
						$array["datadb"]=$value1;
						$array["data"]=$value;
						$array["datapago"]=$value2;
						$epoch = $array["datapago"]["created"];
						date_default_timezone_set('Europe/Madrid');
						$array["datespain"] = date('r', $epoch);
						date_default_timezone_set('America/Caracas');
						$array["datevenezuela"] = date('r', $epoch);
						$arraysend[]=$array;
					}
				}
			}
		}

		if (isset($arraysend)){
			return $arraysend;
		} else {
			return "none";
		}
		
    }

    public function stripeupdatedata(Request $request){
    	$variable = json_decode(json_encode($request->all()),true);

    	$affected = DB::table('users')->where('email', $variable["correopago"])->update(['id_pago' => $variable["stripeidpago"]]);

    	return $variable;
    }

    public function listLatestStripeData(){
    	Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));

    	$startOfMonth = strtotime('first day of this month this year midnight');
		$endOfMonth = strtotime('first day of next month this year midnight');

		$mycharges = Stripe\Charge::all([
			'created' => [
		    	'gte' => $startOfMonth,
		    	'lte' => $endOfMonth,
		    ],
		    'limit' => 100
		]);

		$charges = [];

		foreach ($mycharges->data as $charge) {
		    $charges[] = $charge;
		}

		$verify = 1;

		while ($mycharges->has_more) {
			$lastCharge = end($mycharges->data);
			$mycharges =Stripe\Charge::all([
				'created' => [
			    	'gte' => $startOfMonth,
			    	'lte' => $endOfMonth,
			    ],
			    'limit' => 100,
				'starting_after' => $lastCharge->id,
			]);

			foreach ($mycharges->data as $charge) {
			    $charges[] = $charge;
			}
		}

		$balance = Stripe\Balance::retrieve();

		return view('stripe.listarstripe', compact('charges', 'startOfMonth', 'balance'));
    }

    public function exportdatastripeexcel(Request $request){

    	$meses = array(
		    1 => 'january',
		    2 => 'february',
		    3 => 'march',
		    4 => 'april',
		    5 => 'may',
		    6 => 'june',
		    7 => 'july',
		    8 => 'august',
		    9 => 'september',
		    10 => 'october',
		    11 => 'november',
		    12 => 'december'
		);

		$year = $request->yearstripe;

    	if ($request->monthstripe<12){
    		$firstmonth = $meses[$request->monthstripe];
    		$nextmonth = $meses[$request->monthstripe+1];
    		$startOfMonth = strtotime('first day of '.$firstmonth.' '.$year.' midnight');
			$endOfMonth = strtotime('first day of '.$nextmonth.' '.$year.' midnight');
    	} else {
    		$firstmonth = $meses[12];
    		$nextmonth = $meses[1];
    		$startOfMonth = strtotime('first day of '.$firstmonth.' '.$year.' midnight');
			$endOfMonth = strtotime('first day of '.$nextmonth.' '. ($year+1) .' midnight');
    	}

		return Excel::download(new StripeExcel($startOfMonth, $endOfMonth), 'export.xlsx');

    }

    public function getStripeAJAX(Request $request){
    	$meses = array(
		    1 => 'january',
		    2 => 'february',
		    3 => 'march',
		    4 => 'april',
		    5 => 'may',
		    6 => 'june',
		    7 => 'july',
		    8 => 'august',
		    9 => 'september',
		    10 => 'october',
		    11 => 'november',
		    12 => 'december'
		);

		$year = $request->yearstripe;

    	if ($request->monthstripe<12){
    		$firstmonth = $meses[$request->monthstripe];
    		$nextmonth = $meses[$request->monthstripe+1];
    		$startOfMonth = strtotime('first day of '.$firstmonth.' '.$year.' midnight');
			$endOfMonth = strtotime('first day of '.$nextmonth.' '.$year.' midnight');
    	} else {
    		$firstmonth = $meses[12];
    		$nextmonth = $meses[1];
    		$startOfMonth = strtotime('first day of '.$firstmonth.' '.$year.' midnight');
			$endOfMonth = strtotime('first day of '.$nextmonth.' '. ($year+1) .' midnight');
    	}

		Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));

		$mycharges = Stripe\Charge::all([
			'created' => [
		    	'gte' => $startOfMonth,
		    	'lte' => $endOfMonth,
		    ],
		    'limit' => 100
		]);

		$charges = [];

		foreach ($mycharges->data as $charge) {
		    $charges[] = $charge;
		}

		$verify = 1;

		while ($mycharges->has_more) {
			$lastCharge = end($mycharges->data);
			$mycharges =Stripe\Charge::all([
				'created' => [
			    	'gte' => $startOfMonth,
			    	'lte' => $endOfMonth,
			    ],
			    'limit' => 100,
				'starting_after' => $lastCharge->id,
			]);

			foreach ($mycharges->data as $charge) {
			    $charges[] = $charge;
			}
		}

		foreach ($charges as $charge) {
            if ($charge->status == 'succeeded'){
                $data[] = [
                    $charge->id,
                    ($charge->amount / 100),
                    $charge->currency,
                    $charge->receipt_email,
                    date('d/m/Y H:i:s', $charge["created"] - 4 * 60 * 60),
                    date('d/m/Y H:i:s', $charge["created"] + 2 * 60 * 60),
                ];
            }
        }

        return json_encode($data);
    }
}