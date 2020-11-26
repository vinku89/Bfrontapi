<?php

namespace App\Http\Controllers\API;
use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;
use App\User;
use Illuminate\Support\Facades\Auth;
use Validator;
use Illuminate\Support\Facades\Mail;
use App\Userinfo;
use DB;
use App\CoinListing;
use App\BaseCurrency;
use App\BaseCurrencyPairing;
use App\Balance;
use App\BalanceLog;
use App\Biding;
use App\Withdraw;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;
use Log;
use App\Coinhistory;
use PragmaRX\Google2FA\Google2FA;
use App\ShortLinks;
use App\KycVerification;
use App\UserAddresses;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use App\Http\Controllers\CronJobsController;
use App\Library\NodeApiCalls;
use \GuzzleHttp\Client;
use \GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Hash;
use App\ApiSessions;
use App\BrexcoServices;
use App\Country;
use App\BrexcoCoinSettings;
use App\BrexcoTransactions;
use App\BrexcoPackages;

class BrexcoController extends BaseController 
{

	
	public function brexco_service(Request $request){

		$login=config('constants.DVS_API_KEY');
		$password=config('constants.DVS_API_SECRET');
		$url=config('constants.DVS_API_HOST').'services';
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($ch, CURLOPT_USERPWD, "$login:$password");
		$result = curl_exec($ch);
		curl_close($ch);  
		echo($result);
	}
	public static function curl_call_transferTo($api){
		$api_key = config('constants.TRANSFERTO_API_KEY');
		$api_secret = config('constants.TRANSFERTO_API_SECRET');
		$nonce = gettimeofday(true); # nonce has to be unique for each request
		$host = config('constants.TRANSFERTO_API_HOST');

		$hmac = base64_encode(hash_hmac('sha256', $api_key.$nonce, $api_secret, true ));
		//echo "hmac : $hmac".PHP_EOL;

		// set up the curl resource
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "$host/$api");
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		    "X-TransferTo-apikey: $api_key",
		    "X-TransferTo-nonce: $nonce",
		    "X-TransferTo-hmac: $hmac",
		));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		// execute the request
		$output = curl_exec($ch);
		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		
		// close curl resource to free up system resources
		curl_close($ch);
		return $output;
	}
	public static function curl_call_dvs($api){
		$login=config('constants.DVS_API_KEY');
		$password=config('constants.DVS_API_SECRET');
		$url=config('constants.DVS_API_HOST').$api;
		Log::info("brexco api url ".$url);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($ch, CURLOPT_USERPWD, "$login:$password");
		$result = curl_exec($ch);
		curl_close($ch);  
		//echo($result);
		return $result;
	}
	public static function curl_post_call_dvs($api,$post_array){
		$login=config('constants.DVS_API_KEY');
		$password=config('constants.DVS_API_SECRET');
		$url=config('constants.DVS_API_HOST').$api;
		$headers = array(
		    'Content-Type:application/json',
		    'Authorization: Basic '. base64_encode("$login:$password") // <---
		);
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,config('constants.DVS_API_HOST').'async/transactions');
		
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST,'POST');
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_array));
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		/*curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($ch, CURLOPT_USERPWD, "$login:$password");*/
		$result = curl_exec($ch);
		curl_close($ch);  
		//echo($result);
		return $result;
	}
	public function brexcoCountriesList(Request $request){
		$data = $request->user();
		$user_id = $data['user_id'];
		//self::brexcoServicesIntoDb();
		$userData = Userinfo::leftJoin('country as c', 'c.countryid', '=', 'userinfo.nationality')->where("user_id","=",$user_id)->first();
		$usercountry='';
		$country_code="";
		if($userData!==null){
			$usercountry=$userData['dvs_portal_iso_code'];
			$country_code=$userData['currencycode'];
		}
		$countries=array();
		//for($i=1;$i<10;$i++){
			//$res=self::curl_call_dvs("countries?page=".$i."&per_page=100");
			$res=Country::where('dvs_portal_iso_code','!=',"")->orderBy('country_name')->get()->toArray();
			//$countryList=json_decode($res);
			//if(empty($countryList->errors)){
				Log::info("country ".json_encode($res));
				if(!empty($res)){
					foreach ($res as $p) {
						$countries[]=array(
							"name"=>$p['country_name'],
							"iso_code"=>$p['dvs_portal_iso_code']
							);
						
						
					}
				}else{
					//break;
				}
				
			/*}else{
				break;
			}*/
		//}

		if(!empty($countries)){
				
				return response()->json(["Success"=>true,'status' => 200,'Result' => $countries,'user_country'=>$usercountry,"country_code"=>$country_code], 200);
		}else{
			return response()->json(["Success"=>false,'Status' => 422, 'Result' => $countries,'user_country'=>'',"country_code"=>""], 422);
		}
		
	}

	public function getDefaultServices(Request $request){
		$data = $request->user();
		$user_id = $data['user_id'];
		$userData = Userinfo::leftJoin('country as c', 'c.countryid', '=', 'userinfo.nationality')->where("user_id","=",$user_id)->first();
		$usercountry='';
		$service_ids = array();
		if($userData!==null){
			$usercountry=$userData['dvs_portal_iso_code'];
		}
		
		
		$bservices=BrexcoServices::where('status',1)->orderBy('services_order')->get()->toArray();
		if($usercountry!==null){
			$country=Country::where('dvs_portal_iso_code',$usercountry)->first();
			$service_ids=json_decode($country['brexco_services']);
		}
		
		//if($usercountry!=0 && $usercountry!=''){
			//$res=self::curl_call("services?country_id=".$usercountry);
			/*$res=self::curl_call_dvs("services");
			Log::info("service api ".$res);
			$servicesList=json_decode($res);

			if(@count($servicesList)>0){
				$service_ids = array_map(function ($ar) {return $ar->id;}, $servicesList);
			}*/
		//}
		$active_service_ids = array(1,2,3,4,5,6,9);
		$services=array();
		foreach ($bservices as $bs) {
			$is_active=0;
			Log::info('portal id'.$bs['dvs_portal_id']);
			if($usercountry!==null){
				if(in_array($bs['dvs_portal_id'], $service_ids) && in_array($bs['dvs_portal_id'],$active_service_ids)){
					$is_active=1;
				}
			}
			$service_logo="mbl-reload.png";
			if($bs['service_logo']!=""){
				$service_logo=$bs['service_logo'];
			}
			$services[]=array(
				"service_id"=>$bs['id'],
				"service_name"=>$bs['service_name'],
				"service_logo"=>$service_logo,
				"service_description"=>$bs['service_description'],
				"is_active"=>$is_active,
				"portal_service_id"=>$bs['dvs_portal_id']
			);
		}
		return response()->json(["Success"=>true,'status' => 200,'Result' => $services,'services'=>$service_ids], 200);

	}

	public function getCountryServices(Request $request,$country){
		$data = $request->user();
		$user_id = $data['user_id'];
		
		$bservices=BrexcoServices::where('status',1)->orderBy('services_order')->get()->toArray();
		$service_ids = array();
		$countryCode="";
		if($country!=''){
			$country=Country::where('dvs_portal_iso_code',$country)->first();
			$countryCode=$country['currencycode'];
			$service_ids=json_decode($country['brexco_services']);
		}
			/*$res=self::curl_call("services?country_id=".$country);
			Log::info("services ".$res);
			$servicesList=json_decode($res);

			if(@count($servicesList->services)>0){
				$service_ids = array_map(function ($ar) {return $ar->service_id;}, $servicesList->services);
			}*/
			/*$res=self::curl_call_dvs("services");
			Log::info("service api ".$res);
			$servicesList=json_decode($res);

			if(@count($servicesList)>0){
				$service_ids = array_map(function ($ar) {return $ar->id;}, $servicesList);
			}*/

		$active_service_ids = array(1,2,3,4,5,6,9);
		$services=array();
		foreach ($bservices as $bs) {
			$is_active=0;
			if($country!=''){
				if(in_array($bs['dvs_portal_id'], $service_ids) && in_array($bs['dvs_portal_id'],$active_service_ids)){
					$is_active=1;
				}
			}
			$service_logo="mbl-reload.png";
			if($bs['service_logo']!=""){
				$service_logo=$bs['service_logo'];
			}
			$services[]=array(
				"service_id"=>$bs['id'],
				"service_name"=>$bs['service_name'],
				"service_logo"=>$service_logo,
				"service_description"=>$bs['service_description'],
				"is_active"=>$is_active,
				"portal_service_id"=>$bs['dvs_portal_id']
			);
		}
		return response()->json(["Success"=>true,'status' => 200,'Result' => $services,"countryCode"=>$countryCode], 200);
	}

	public function getBrexcoCryptos(Request $request){
		$data = $request->user();
		$user_id = $data['user_id'];
		$brexcoCoinSettings=BrexcoCoinSettings::where('status','Active')->get()->toArray();
		
		$cryptoLists=array();
		/*$cryptoLists[]=array(
			"crypto_symbol"=>"Choose Payment Option",
			"crypto_name"=>"",
			"coin_image"=>"",
			"balance"=>"",
			"usd_balance"=>""
		);*/
		$c=0;
		foreach ($brexcoCoinSettings as $bc) {
			$coinbal=CoinListing::leftJoin('dbt_balance','coin_listing.coin_symbol','=','dbt_balance.currency_symbol')->where('coin_listing.status',1)->where('dbt_balance.user_id',$user_id)->where('coin_listing.coin_symbol',$bc['coin_symbol'])->first();
			if($coinbal!==null){
				if($coinbal['coin_price']>0){
					if($coinbal['main_balance']>0){
						$c=1;
						$usd_value=$coinbal['main_balance']*$coinbal['coin_price'];
						$cryptoLists[]=array(
							"crypto_symbol"=>$coinbal['coin_symbol'],
							"crypto_name"=>$coinbal['coin_name'],
							"coin_image"=>$coinbal['coin_image'],
							"balance"=>number_format_eight_dec($coinbal['main_balance'])." ".$coinbal['coin_symbol'],
							"usd_balance"=>number_format_eight_dec($usd_value)." USD"
						);
					}
				}
				
			}
			
			
		}
		if($c==0){
			$cryptoLists=array();
			$cryptoLists[]=array(
			"crypto_symbol"=>"",
			"crypto_name"=>"",
			"coin_image"=>"",
			"balance"=>"",
			"usd_balance"=>""
		);
		}
		return response()->json(["Success"=>true,'status' => 200,'Result' => $cryptoLists], 200);
	}
	public function getCountryOperators(Request $request,$country,$portal_service_id){
		$data = $request->user();
		$user_id = $data['user_id'];
		$active_service_ids = array(1,2,3,4,5,6,9);
		$operators = array();
		$countryCode="";
		if($country!=''){
			$country=Country::where('dvs_portal_iso_code',$country)->first();
			$isoCode=$country['dvs_portal_iso_code'];
			$products=array();
			/*$products[]=array(
				"operator_text"=>"Choose Operator Option",
				"operator_name"=>"",
				"operator_id"=>""
				
			);*/

			//for($i=1;$i<10;$i++){
				if($portal_service_id == 3 || $portal_service_id == 4){
					$type="FIXED_VALUE_PIN_PURCHASE";
				}else{
					$type="FIXED_VALUE_RECHARGE";
				}
				//$res=self::curl_call_dvs("products?page=".$i."&per_page=100&country_iso_code=".$isoCode."&service_id=".$portal_service_id."&type=".$type);
				//Log::info("products api ".$res);
				//$productsList=json_decode($res);
				$productsList=BrexcoPackages::where('country_iso_code',$isoCode)->where('service',$portal_service_id)->distinct('operator_id')->orderBy('operator_id','ASC')->get();
				
				//if(empty($productsList->errors)){
					foreach ($productsList as $p) {
						if($p['operator_id']==1809 && in_array($portal_service_id,$active_service_ids)){
						}else{
							$products[]=array(
								"operator_id"=>$p['operator_id'],
								"operator_name"=>$p['operator_name'],
								"operator_text"=>""
							);
						}
						
					}
					
				// }else{
				// 	break;
				// }
				
			//}
			$products =self::unique_key($products, 'operator_id');
			
			//$products = array_map("unserialize", array_unique(array_map("serialize", $products)));
			
		}
		/*$services=array();
		foreach ($bservices as $bs) {
			$is_active=0;
			if(in_array($bs['dvs_portal_id'], $service_ids) && $bs['dvs_portal_id']==1){
				$is_active=1;
			}
			$service_logo="mbl-reload.png";
			if($bs['service_logo']!=""){
				$service_logo=$bs['service_logo'];
			}
			$services[]=array(
				"service_id"=>$bs['id'],
				"service_name"=>$bs['service_name'],
				"service_logo"=>$service_logo,
				"service_description"=>$bs['service_description'],
				"is_active"=>$is_active
			);
		}*/
		return response()->json(["Success"=>true,'status' => 200,'Result' => $products], 200);
	}
	public static function unique_key($array,$keyname){

	 $new_array = array();
	 foreach($array as $key=>$value){

	   if(!isset($new_array[$value[$keyname]])){
	     $new_array[$value[$keyname]] = $value;
	   }

	 }
	 $new_array = array_values($new_array);
	 return $new_array;
	}
	public static function getBrexcoProducts(Request $request,$country,$portal_service_id,$operator_id){
		$data = $request->user();
		$user_id = $data['user_id'];

		$active_service_ids = array(1,2,3,4,5,6,9);
		$operators = array();
		$countryCode="";
		if($country!=''){
			$country=Country::where('dvs_portal_iso_code',$country)->first();
			$isoCode=$country['dvs_portal_iso_code'];
			$products=array();
			$products1=array();
			$products2=array();
			//for($i=1;$i<10;$i++){
				if($portal_service_id == 3 || $portal_service_id == 4){
					$type="FIXED_VALUE_PIN_PURCHASE";
				}else{
					$type="FIXED_VALUE_RECHARGE";
				}
				// $res=self::curl_call_dvs("products?page=".$i."&per_page=100&country_iso_code=".$isoCode."&service_id=".$portal_service_id."&operator_id=".$operator_id."&type=".$type);
				// Log::info("products api ".$res);
				// $productsList=json_decode($res);
				$productsList = BrexcoPackages::where(['country_iso_code'=>$isoCode, 'service'=>$portal_service_id, 'operator_id'=>$operator_id])->orderBy('operator_id','ASC')->get();
				//if(empty($productsList->errors)){
					foreach ($productsList as $item) {
						$p=unserialize($item['packages']);
						//Log::info('products response'.$item2);
						if($p->operator->id==1809 && in_array($portal_service_id,$active_service_ids)){
						}else{
                            if($p->destination->unit==""){
								return response()->json(["Success"=>false,'status' => 422,'Result' => array(),'error_text'=>"Currency type is empty"], 422);
							}
							if($p->destination->unit == 'VES') $p->destination->unit = 'VEF';
							$usdamount=self::currencyConvertUsingApilayer($p->destination->unit,"USD",$p->destination->amount);
							if($usdamount>=0){
    							$prodtemp=explode(' ',$p->name);
    							$numberStr=floatVal($prodtemp[0]);
    							if($numberStr==0){
    								$products1[]=array(
    									"product_id"=>$p->id,
    									"product_name"=>strtoupper($p->name),
    									"for_sort"=>$numberStr,
    									"amount"=>$p->destination->amount,
    									"currency_type"=>$p->destination->unit,
                                        "gift_card_name"=>$p->operator->name
    								);
    							}else{
    								$products2[]=array(
    									"product_id"=>$p->id,
    									"product_name"=>strtoupper($p->name),
    									"for_sort"=>$numberStr,
    									"amount"=>$p->destination->amount,
    									"currency_type"=>$p->destination->unit,
                                        "gift_card_name"=>$p->operator->name
    								);
    							}
                            }
							
						}
						
					}
					
				// }else{
				// 	break;
				// }
				
			//}
			
			/*usort($products2, function($a, $b) {
			    return $a['for_sort'] <=> $b['for_sort'];
			});
			usort($products1, function($a, $b) {
			    return $a['product_id'] <=> $b['product_id'];
			});*/
			$products=array_merge($products2,$products1);
			usort($products, function($a, $b) {
			    return $a['amount'] <=> $b['amount'];
			});
		}
		return response()->json(["Success"=>true,'status' => 200,'Result' => $products], 200);
	}
	public static function currencyConvertUsingApilayer($from_currency,$to_currency="USD",$amount){
        $endpoint = 'convert';
        $access_key = 'ecfdc4594634ec8f7ee42c66b672da60';//config("constants.API_LAYER_ACCESSKEY"); //'fe67fff72d0a8f104b1044d8dfea650d';
        Log::info('https://api.currencylayer.com/'.$endpoint.'?access_key='.$access_key.'&from='.$from_currency.'&to='.$to_currency.'&amount='.$amount.'');
        // initialize CURL:
        $ch = curl_init('https://api.currencylayer.com/'.$endpoint.'?access_key='.$access_key.'&from='.$from_currency.'&to='.$to_currency.'&amount='.$amount.'');    
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // get the (still encoded) JSON data:
        $json = curl_exec($ch);
        Log::info("currency layer response ".$json);
        curl_close($ch);

        // Decode JSON response:
        $conversionResult = json_decode($json, true);
        // access the conversion result
        if($conversionResult['success'] == 1){
            $convertToUSD =  $conversionResult['result'];
        }else{
            $convertToUSD ="";
        }
        return $convertToUSD;
    }
    public function getBrexcoConfirmDet(Request $request,$crypto,$amount,$currency_type){
    	$data = $request->user();
		$user_id = $data['user_id'];
		$coinbal=CoinListing::leftJoin('dbt_balance','coin_listing.coin_symbol','=','dbt_balance.currency_symbol')->where('coin_listing.status',1)->where('dbt_balance.user_id',$user_id)->where('coin_listing.coin_symbol',$crypto)->first();
		if($currency_type==""){
			return response()->json(["Success"=>false,'status' => 422,'Result' => array(),'error_text'=>"Currency type is empty"], 422);
		}
		if($currency_type == 'VES') $currency_type = 'VEF';
		$usdamount=self::currencyConvertUsingApilayer($currency_type,"USD",$amount);
		if($usdamount>=0){
			//$usdamount=10;
			$crypto_amount_cal=$usdamount/$coinbal['coin_price'];
			$crypto_amount_format=number_format_eight_dec($crypto_amount_cal);
			$crypto_amount=str_replace(",","", $crypto_amount_format);
			$bcsettings=BrexcoCoinSettings::where('coin_symbol',$crypto)->where('status','Active')->first();
			$b_fee=0;
			$b_fee_format=0;
			$discount=0;
			$discount_format='0';
			$discount_perc=0;
			$b_fee_perc=0;
			if($bcsettings!==null){
				
				$discount_cal=($crypto_amount*$bcsettings['discount'])/100;
				$discount_format=number_format_eight_dec($discount_cal);
				$discount=str_replace(",","", $discount_format);

				$discount_perc=$bcsettings['discount'];
				$b_fee_perc=$bcsettings['fee'];
			}
			$amount_with_discount_cal=$crypto_amount-$discount;
			$b_fee=($amount_with_discount_cal*$b_fee_perc)/100;
			$b_fee_format=number_format_eight_dec($b_fee);
			$amount_with_discount_cal_format=number_format_eight_dec($amount_with_discount_cal);
			$amount_with_fee_cal=$amount_with_discount_cal+$b_fee;
			$amount_with_fee_format=number_format_eight_dec($amount_with_fee_cal);
			$amount_with_fee=str_replace(",","", $amount_with_fee_format);
			
			$amount_with_discount_format=number_format_eight_dec($amount_with_fee);
			//$amount_with_discount=str_replace(",","", $amount_with_discount_format);
			$userinfo=Userinfo::where("user_id",$user_id)->first();
			$res=array(
				"amount"=>number_format($amount,2),
				"crypto_amount"=>$crypto_amount_format,
				"usd_amount"=>number_format($usdamount,2),
				"blockchain_fee"=>$b_fee_format,
				"discount"=>$discount_format,
				"discount_perc"=>$discount_perc,
				"amount_with_fee"=>$amount_with_fee_format,
				"amount_with_discount"=>$amount_with_discount_cal_format,
				"crypto_image"=>$coinbal['coin_image'],
				"is_enabled_twofa"=>$userinfo['login_tfa_status']
			);

			return response()->json(["Success"=>true,'status' => 200,'Result' => $res], 200);
		}else{
			return response()->json(["Success"=>false,'status' => 422,'Result' => ""], 422);
		}
    }
    public function brexco_sel_country_data(Request $request,$country){
		$data = $request->user();
		$user_id = $data['user_id'];

		$bservices=BrexcoServices::where('status',1)->orderBy('services_order')->get()->toArray();
		$service_ids = array();
		$countryCode="";
		if($country!=''){
			$countrydet=Country::where('dvs_portal_iso_code',$country)->first();
			$countryCode=$countrydet['currencycode'];
			$service_ids=json_decode($countrydet['brexco_services']);
		}

		$active_service_ids = array(1,2,3,4,5,6,9);
		$services=array();
		foreach ($bservices as $bs) {
			$is_active=0;
			if($country!=''){
				if(in_array($bs['dvs_portal_id'], $service_ids) && in_array($bs['dvs_portal_id'],$active_service_ids)){
					$is_active=1;
				}
			}
			$service_logo="mbl-reload.png";
			if($bs['service_logo']!=""){
				$service_logo=$bs['service_logo'];
			}
			$services[]=array(
				"service_id"=>$bs['id'],
				"service_name"=>$bs['service_name'],
				"service_logo"=>$service_logo,
				"service_description"=>$bs['service_description'],
				"is_active"=>$is_active,
				"portal_service_id"=>$bs['dvs_portal_id']
			);
		}

		if($countrydet!==null){
			return response()->json(["Success"=>true,'status' => 200,'Result' => $countrydet['currencycode'],"services"=>$services,'active_services'=>$active_service_ids], 200);
		}else{
			return response()->json(["Success"=>false,'Status' => 422, 'Result' => ""], 422);
		}
		
	}
	public function brexcoTransaction(Request $request){
		$data = $request->user();
		$user_id = $data['user_id'];
		$validator = Validator::make($request->all(), [
                'country' => 'required',
                'service' => 'required',
                'crypto' => 'required',
                'operator' => 'required',
                'account_number' => 'required',
                'product' => 'required'
                ]);
		if ($validator->fails()) {
            return response()->json(['status'=>'Failure','Result'=>$validator->errors()], 400);            
        }
        $country=request('country');
		$service=request('service');
		$crypto=request('crypto');
		$country_code=request('country_code');
		$operator=request('operator');
		$account_number=request('account_number');
		$mobile_number=request('mobile_number');
		$product=request('product');
		$two_fa=request('two_fa');
		$transaction_note=request('transaction_note');
		if(empty($transaction_note)){
			$transaction_note="";
		}
		$userinfo=Userinfo::where("user_id",$user_id)->first();
		if($userinfo['login_tfa_status']=="A"){
			$google2fa = new Google2FA();
	        $window = 0;
	        $res=$google2fa->verifyKey($userinfo['google_2fa_key'], $two_fa,$window);
	        if(!$res){
	        	return response()->json(['status'=>'Failure','Result'=>'Please enter correct verification code','response_code'=>1], 400);
	            
	        }
		}
		$pres=self::curl_call_dvs("products/".$product);
		Log::info("products api ".$pres);
		$productsList=json_decode($pres);
		$amount=0;
		$currency_type='';
		$opertor_name='';
		if(empty($productsList->errors)){
			if(!empty($productsList)){
				$amount=$productsList->destination->amount;
				$currency_type=$productsList->destination->unit;
				$opertor_name=$productsList->operator->name;
			}
			
		}
		$coinbal=CoinListing::leftJoin('dbt_balance','coin_listing.coin_symbol','=','dbt_balance.currency_symbol')->where('coin_listing.status',1)->where('dbt_balance.user_id',$user_id)->where('coin_listing.coin_symbol',$crypto)->first();
		if($currency_type == 'VES') $currency_type = 'VEF';
		$usdamount=self::currencyConvertUsingApilayer($currency_type,"USD",$amount);
		//$usdamount=10;
		if($usdamount<0){
			return response()->json(['status'=>'Failure','Result'=>'Invalid Request'], 400);
		}
		$crypto_amount_cal=$usdamount/$coinbal->coin_price;
		$crypto_amount_format=number_format_eight_dec($crypto_amount_cal);
		$crypto_amount=str_replace(",","", $crypto_amount_format);
		$bcsettings=BrexcoCoinSettings::where('coin_symbol',$crypto)->where('status','Active')->first();
		$b_fee=0;
		$discount=0;
		$discount_format=0;
		$discount_cal="";
		$b_fee_perc=0;
		if($bcsettings!==null){
				
			$discount_cal=($crypto_amount*$bcsettings['discount'])/100;
			$discount_format=number_format_eight_dec($discount_cal);
			$discount=str_replace(",","", $discount_format);

			$discount_perc=$bcsettings['discount'];
			$b_fee_perc=$bcsettings['fee'];
		}
		$amount_with_discount_cal=$crypto_amount-$discount;
		$b_fee=($amount_with_discount_cal*$b_fee_perc)/100;
		$b_fee_format=number_format_eight_dec($b_fee);
		$amount_with_discount_cal_format=number_format_eight_dec($amount_with_discount_cal);
		$amount_with_fee_cal=$amount_with_discount_cal+$b_fee;
		$amount_with_fee_format=number_format_eight_dec($amount_with_fee_cal);
		$amount_with_fee=str_replace(",","", $amount_with_fee_format);
		
		$amount_with_discount_format=number_format_eight_dec($amount_with_fee);
		$coinbal=CoinListing::leftJoin('dbt_balance','coin_listing.coin_symbol','=','dbt_balance.currency_symbol')->where('coin_listing.status',1)->where('dbt_balance.user_id',$user_id)->where('coin_listing.coin_symbol',$crypto)->first();
		if($coinbal===null){
			return response()->json(['status'=>'Failure','Result'=>'Please change another crypto payment option','response_code'=>2], 400);
		}else{
			if($coinbal->main_balance<=0){
				return response()->json(['status'=>'Failure','Result'=>'Insufficient crypto balance','response_code'=>3], 400);
			}
			if($coinbal->main_balance<$amount_with_fee){
				return response()->json(['status'=>'Failure','Result'=>'Insufficient crypto balance','response_code'=>3], 400);
			}
		}
		
		
		$mobile_number=$country_code.$mobile_number;
		$account_number=$account_number;
		
		$external_id=rand(100,10000).time();
		$post_array=array(
			"external_id"=>$external_id,
			"product_id"=>$product,
			"auto_confirm"=>true,
			"credit_party_identifier"=>array(
					"mobile_number"=>$mobile_number,
					"account_number"=>$account_number
				)
		);
		Log::info("transaction api request ".json_encode($post_array));
		$res=self::curl_post_call_dvs("async/transactions",$post_array);
		Log::info("transaction api ".$res);
		Log::info("data:".$mobile_number.'account number-'.$account_number);
		$transres=json_decode($res);
		$status='';
		$status_text='';
		$transaction_id='';
		$error_codes="";
		$error_Stutus = array('REJECTED','DECLINED');
		if(empty($transres->errors) && !in_array($transres->status->message,$error_Stutus)){
			$status=$transres->status->message;
			$status_text=$transres->status->message;
			$transaction_id=$transres->id;
			
		}else{
			$status='FAILED';
			$status_text=$transres->errors[0]->message;
			$error_codes=$transres->errors[0]->code;
		}
		
		$bservices=BrexcoServices::where('dvs_portal_id',$service)->where('status',1)->first();
		$service_name = $bservices['service_name'];
		$barray=array(
			'external_id'=>$external_id,
			'payment_id'=>$transaction_id,
			'product_id'=>$product,
			'user_id'=>$user_id,
			'service_id'=>$bservices['id'],
			'country'=>$country,
			'crypto_symbol'=>$crypto,
			'amount'=>number_format($amount,2),
			'currency_type'=>$currency_type,
			'usd_amount'=>number_format($usdamount,2),
			'discount'=>$bcsettings['discount']."%",
			'fee'=>number_format_eight_dec($b_fee),
			'crypto_amount'=>$crypto_amount_format,
			'status'=>$status,
			'status_text'=>$status_text,
			'transaction_note'=>$transaction_note,
			'account_number'=>$account_number,
			'mobile_number'=>$mobile_number,
			'total_amount'=>$amount_with_fee_format


		);
		$base_id=BrexcoTransactions::insertGetId($barray);
		if(empty($transres->errors) && !in_array($transres->status->message,$error_Stutus){
			$updatebalance = array(
	            'main_balance' => $coinbal->main_balance-$amount_with_fee,
	        );
	        $bres = Balance::where('user_id',$user_id)->where('currency_symbol',$crypto )->update($updatebalance);
	        //main wallet ledger table
       		$description = "Brexco transactions ".$amount_with_fee." ".$crypto." on wallet Main Account";
       		$transaction_id="BX".rand(100,10000).time();
       		$insertArr = array(
					'transaction_id' => $transaction_id,
					'user_id' => $user_id, 
					'sender_id' => $user_id, 
					'receiver_id' => 0, 
					'transaction_date' => date("Y-m-d H:i:s"), 
					'currency_symbol' => $crypto, 
					'type' => 'DEBIT', 
					'amount' => $amount_with_fee, 
					'balance' => $coinbal->main_balance-$amount_with_fee, 
					'transaction_type' => "Brexco transactions", 
					'description' => $description,
					'base_id' => $base_id
				);
			$res = DB::table('main_wallet_ledger')->insert($insertArr);

			$email=$data['email'];
			//Log::info("userinfo ".json_encode($userinfo));
			// if($service == 1 || $service == 4) {
			// 	$account_number = $account_number;
			// 	$mobile_number = $mobile_number;
			// }else{
			// 	$mobile_number = $mobile_number;
			// 	$account_number = $account_number;
			// }
	   		$edata['useremail'] = array( 'first_name' => $userinfo['first_name'], 'last_name' => $userinfo['last_name'], 'email' => $email,'crypto_amount'=>$crypto_amount_format,"crypto_type"=>strtoupper($crypto),"amount"=>number_format($amount,2),"currency_type"=>$currency_type,"payment_id"=>$transaction_id,"payment_date"=>date('M d, Y h:i:s A'),"account_number"=>$account_number,"mobile_number"=>$mobile_number,"service"=>$service,"fee"=>number_format_eight_dec($b_fee),"amount_with_fee_format"=>$amount_with_fee_format,"discount"=>$bcsettings['discount']."%","discount_format"=>$discount_format,"amount_with_discount_format"=>$amount_with_discount_cal_format,'opertor_name'=>$opertor_name,"country_code"=>$country_code);

			Mail::send(['html'=>'email_templates.successful-transfer'], $edata, function($message) use ($userinfo,$email) {
				$message->to($email, $userinfo['first_name']." ".$userinfo['last_name'])->subject('Brexco Transaction');
					$message->from('support@brexily.com ','Brexily');
				});
		}
		
		if($status!='FAILED'){
			return response()->json(["Success"=>true,'status' => 200,'Result' => "Transaction has been successfully completed","snakbar_date"=>date('d-m-Y h:i:s A'),"snakbar_text"=>"You have successfully sent ".$amount_with_discount_format." ".strtoupper($crypto)." / ".number_format($amount,2)." ".$currency_type." for ".ucwords($service_name)], 200);
		}else{
			Log::info("brexco trnsaction response ".$status_text);
			if($error_codes==1000400 || $error_codes==1005003){
				$status_text="Invalid mobile number";
			}
			if($error_codes==1006001){
				$status_text="Something went wrong";
			}
			return response()->json(["Success"=>false,'Status' => 422, 'Result' => $status_text], 422);
		}
		
	}
	public function brexcoTransactionsList(Request $request){
		$data = $request->user();
		$user_id = $data['user_id'];
		$crypto =  request('crypto') ;
		$page =  request('page') ;
		$trans=array();
		if($crypto=='All' && $page==''){
			$trans=BrexcoTransactions::where('user_id',$user_id)->orderBy('created_at','desc')->get()->toArray();
		}
		$brexcoArr=array();
		foreach ($trans as $d) {
			$payment_id='-';
			if($d['payment_id']!=""){
				$payment_id=$d['payment_id'];
			}
			$bservices=BrexcoServices::where('id',$d['service_id'])->first();
			$brexcoArr[]=array(
				"time"=>date('d/m/Y H:i:s', strtotime($d['created_at'])),
				"created_at"=>$d['created_at'],
				"payment_id"=>$payment_id,
				"currency"=>$d['crypto_symbol'],
				"service"=>$bservices['dvs_portal_id'],
				"service_name"=>ucwords($bservices['service_name']),
				"account_number"=>$d['account_number'],
				"mobile_number"=>$d['mobile_number'],
				"amount"=>$d['amount']." ".$d['currency_type'],
				"usd_amount"=>"$".$d['usd_amount'],
				"discount"=>$d['discount'],
				"fee"=>$d['fee'],
				"crypto_amount"=>$d['total_amount'],
				"status"=>$d['status']
			);
		}
		return response()->json(["Success"=>true,'status' => 200,'Result' => $brexcoArr], 200);
	}

}