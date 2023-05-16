<?php

namespace App\Http\Controllers;

use App\Models\Agcliente;
use App\Models\Country;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Support\Facades\DB;
use Stripe;

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
}