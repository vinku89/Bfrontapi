<?php

namespace App\Http\Controllers\API;
   
use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;
use App\User;
use Illuminate\Support\Facades\Auth;
use Validator;
use Illuminate\Support\Facades\Mail;
use App\Userinfo;
use App\Country;
use App\Referrals;
use App\ReferralBonus;
use App\ResetPasswordHistory;
use DB;
use App\Balance;
use App\Biding;
use App\CoinListing;
//use PragmaRX\Google2FAQRCode\Google2FA;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use App\KycVerification;
use PragmaRX\Google2FA\Google2FA;
class UserController extends BaseController 
{
	
	// get Countries Names
    public function getCounties(Request $request)
	{ 
		$data = $request->user();
		$user_id = $data['user_id'];
		
		$countryList = Country::orderBy("country_name", "ASC")->get();
		$countries = array();
		if(@count($countryList)>0){
			
				foreach($countryList as $res){
					$countries[] = array(
									"country_id"=>$res['countryid'],
									"country_name"=>$res['country_name'],
									"currencycode"=>$res['currencycode'],
									"currency"=>$res['currency'],
									"country_status"=>$res['country_status'],
									"nationality"=>$res['nationality'],
									);
				}
				return response()->json(["Success"=>true,'status' => 200,'Result' => $countries], 200);
		}else{
			return response()->json(["Success"=>false,'Status' => 422, 'Result' => $countries], 422);
		}
		
	}
	
	// Get Logged user details
	public function userDetails(Request $request)
	{ 
		$data = $request->user();
		$user_id = $data['user_id'];
		$email = $data['email'];
		$brexily_address = $data['brexily_address'];
		$userData = Userinfo::select("*")->where("user_id","=",$user_id)->first();
		$userLoginInfo =  DB::table('login_history')->select('ip' , 'created_date')->where("user_id","=",$user_id)->orderby('created_date' , 'DESC')->first();
		$userkyc=KycVerification::where('user_id',$user_id)->first();
		$userBlances= Balance::select(\DB::raw('sum(dbt_balance.main_balance*coin_listing.coin_price) as mainBalance , sum(dbt_balance.balance*coin_listing.coin_price) as tradingBlance'))->Join('coin_listing','dbt_balance.currency_symbol' , '=' , 'coin_listing.coin_symbol' )->where('dbt_balance.user_id',$user_id)->first();
		
		$sellAmount = Biding::select(\DB::raw('SUM(dbt_biding.bid_qty_available*coin_listing.coin_price) as inOrderBalance'))->Join('coin_listing','dbt_biding.currency_symbol' , '=' , 'coin_listing.coin_symbol' )->where('dbt_biding.user_id',$user_id)->where('dbt_biding.status',2)->where('dbt_biding.bid_type','SELL')->first();

		$buyqry = 'select SUM(db.bid_qty_available*db.bid_price*cl.coin_price) as inOrderBalance from dbt_biding db left join base_currency_pairing bcp on (REPLACE(bcp.trading_pairs, "/", "_")=db.market_symbol) left join coin_listing cl on cl.id=bcp.coin_id where db.status=2 and db.bid_type="BUY" and db.user_id='.$user_id.' limit 1'; 
		$buyAmount = DB::select(\DB::raw($buyqry));

		if($buyAmount && $buyAmount[0]->inOrderBalance){
			$buy_amt = $buyAmount[0]->inOrderBalance;
		}else{
			$buy_amt = 0;
		}
		if($sellAmount && $sellAmount->inOrderBalance){
			$sell_amt = $sellAmount->inOrderBalance;
		}else{
			$sell_amt = 0;
		}
		$userInorderBlance= ($buy_amt+$sell_amt);
		if($userInorderBlance) {

		} else {
			$userInorderBlance = 0;
		}

		$btcUsd=CoinListing::select('coin_price')->where('coin_symbol' , 'btc')->first();
		
		//select ur.role_name, rs.role, (CASE WHEN rs.fee_applicable=1 THEN (select SUM(COALESCE(cs.commission_percent,0)) from commission_settings cs where cs.user_role=rs.role) ELSE 0 END) as cpercent from roles_settings rs inner join user_roles ur on rs.role=ur.rec_id where rs.role=1

		$userRole = DB::table('roles_settings')->select(\DB::raw('user_roles.role_name, roles_settings.role, (CASE WHEN roles_settings.fee_applicable=1 THEN (select SUM(COALESCE(commission_settings.commission_percent,0)) from commission_settings  where commission_settings.user_role=roles_settings.role) ELSE 0 END) as cpercent'))->join('user_roles' , 'user_roles.rec_id' , '=' , 'roles_settings.role' )->join('users' , 'users.role' , '=' , 'roles_settings.role')->where('users.user_id'  ,  $user_id)->first();


		// user_ranking -> monthly cron
		$rank = 0;



		$kyc_status=0;
		if($userkyc!==null){
			if($userkyc['status']=='reject'){
				$kyc_status=0;
			}else if($userkyc['status']=='pending'){
				$kyc_status=0;
			}else{
				$kyc_status=1;
			}
			
		}
		$fs = DB::table('fee_settings')->first();
		if(@count($userData) >0){
				$userInfo = array(
								"user_id"=>$user_id,
								"email"=>$email,
								"first_name"=>$userData->first_name,
								"last_name"=>$userData->last_name,
								"birth_date"=>$userData->birth_date,
								"created_at"=>date('d-m-Y',strtotime($data['created_at'])),
								"gender"=>$userData->gender,
								"nationality"=>$userData->nationality,
								"mobile_number"=>$userData->mobile_number,
								"ref_code"=>$userData->ref_code,
								"applied_ref_code"=>$userData->applied_ref_code,
								"Token2fa_validation"=>$userData->Token2fa_validation,
								"Token2fa_validation_initial"=>$userData->Token2fa_validation_initial,
								"Twofa_status"=>$userData->Twofa_status,
								"login_tfa_status"=>$userData->login_tfa_status,
								"withdraw_tfa_status"=>$userData->withdraw_tfa_status,
								"transferto_user_tfa_status"=>$userData->transferto_user_tfa_status,
								"brexily_address"=>$brexily_address,
								"kyc_status"=>$kyc_status,
								"payment_fee_mode"=>$userData->payment_fee_mode,
								"EVR_maker_fee"=>$fs->evr_maker_fee,
								"EVR_taker_fee"=>$fs->evr_taker_fee,
								"All_bc_maker_fee"=>$fs->all_bc_maker_fee,
								"All_bc_taker_fee"=>$fs->all_bc_taker_fee,
								"lastLoginDate"=>$userLoginInfo->created_date,
								"ip"=>$userLoginInfo->ip,
								"mainBalaces"=>number_format_two_dec($userBlances->mainBalance),
								"tradingBlance"=>number_format_two_dec($userBlances->tradingBlance),
								"inOrderBlance"=>number_format_two_dec($userInorderBlance),
								"totalAssert"=>number_format_two_dec($userBlances->mainBalance+$userBlances->tradingBlance+$userInorderBlance),
								"mainBalacesBtc"=>number_format_eight_dec($userBlances->mainBalance/$btcUsd->coin_price),
								"tradingBlanceBtc"=>number_format_eight_dec($userBlances->tradingBlance/$btcUsd->coin_price),
								"inOrderBlanceBtc"=>number_format_eight_dec($userInorderBlance/$btcUsd->coin_price),
								"totalAssertBtc"=>number_format_eight_dec(($userBlances->mainBalance+$userBlances->tradingBlance+$userInorderBlance)/$btcUsd->coin_price),
								"userRole"=>$userRole->role_name,
								"userCPercent"=>$userRole->cpercent,
								"rank"=>$userData->rank,
								"earn_crypto"=>1
							);
							
				return response()->json(["Success"=>true,'status' => 200,'Result' => $userInfo], 200);
		}else{
			$userInfo = array();
			return response()->json(["Success"=>false,'status' => 422,'Result' => $userInfo], 200);
		}
		
		
		
	}
	
	// update profile Data
	public function updateProfile(Request $request)
	{ 
		$data = $request->user();
		$user_id = $data['user_id'];
		$email = $data['email'];
		
		$validator = Validator::make($request->all(), [
			//'first_name' => 'required',
			//'last_name' => 'required',
			//'birth_date' => 'required',
			//'gender' => 'required',
			'nationality' => 'required',
			'mobile_number' => 'required',
		]);
		if ($validator->fails()) {
			return response()->json(['status' => false, 'Result' => $validator->errors()], 422);
		}else{
			$first_name = request('first_name');
			$last_name = request('last_name');
			//$birth_date = request('birth_date');
			$gender = request('gender');
			$nationality = request('nationality');
			$mobile_number = request('mobile_number');
			
			$userData = Userinfo::select("*")->where("user_id","=",$user_id)->first();
			if(!empty($userData)){
				//if($userData->ref_code != ""){
					
					$updateData = array(
						//"first_name"=>$first_name,
						//"last_name"=>$last_name,
						//"birth_date"=>$birth_date,
						//"gender"=>$gender,
						"nationality"=>$nationality,
						"mobile_number"=>$mobile_number,
						);
					$res = Userinfo::where('user_id',"=",$user_id)->update($updateData);
					return response()->json(["Success"=>true,'status' => 200,'Result' => "User information Updated Successfully"], 200);
				// }else{
					
				// 	//generate Brexily referral code
				// 	$name = substr($first_name,0,2);
					
				// 	$ref_code = strtoupper($name)."BREXILY"."00".$user_id;
				// 	$updateData = array(
				// 		"first_name"=>$first_name,
				// 		"last_name"=>$last_name,
				// 		"birth_date"=>$birth_date,
				// 		"gender"=>$gender,
				// 		"nationality"=>$nationality,
				// 		"mobile_number"=>$mobile_number,
				// 		"ref_code"=>$ref_code,
				// 		);
				// 	$res = Userinfo::where('user_id',"=",$user_id)->update($updateData);
				// 	return response()->json(["Success"=>true,'status' => 200,'Result' => "User information Updated Successfully"], 200);
				// }	
			}else{
				return response()->json(["Success"=>false,'status' => 200,'Result' => "Server is Busy"], 200);
			}
			
			
		}

		
	}
	
	// fetch Referral List API
	public function fetchReferral(Request $request)
	{  
		
		$data = $request->user();
		$user_id = $data['user_id'];
		$email = $data['email'];
		
		$validator = Validator::make($request->all(), [
			'ancestor_id' => 'required',
			'distance' => 'required',
		]);
		if ($validator->fails()) {
			return response()->json(['status' => false, 'Result' => $validator->errors()], 200);
		}else{
			$ancestor_id = request('ancestor_id');
			$distance = request('distance');
			
			$data = DB::table('users as u')
					   ->join('referrals as rf', 'rf.descendant_id', '=', 'u.user_id')
					   ->join('userinfo as uf', 'uf.user_id', '=', 'u.user_id')
					   ->select(DB::raw("CONCAT(uf.first_name,' ', uf.last_name) as name"),'u.email', 'rf.distance', 'rf.ancestor_id', 'rf.descendant_id')
					   ->where("rf.ancestor_id","=",$ancestor_id)
					   ->where("rf.distance","=",$distance)
					   ->get();
					   
			if(@count($data) > 0){
				$referral_list = array();
				foreach($data as $res){
					$referral_list[] = $res;	
				}
				return response()->json(["Success"=>true,'status' => 200,'Result' => $referral_list], 200);
			}else{
				$referral_list = array();
				return response()->json(["Success"=>false,'status' => 200,'Result' => $referral_list], 200);
			}
			
		}

		
	}
	
	// my Referral List API
	public function myReferral(Request $request)
	{  
		
		$data = $request->user();
		$user_id = $data['user_id'];
		
		$email = $data['email'];
		
		//$Total_Earning = ReferralBonus::select(DB::raw('SUM(evr) as evr'))->where("user_id","=",$user_id)->toSql();
		//print_r($Total_Earning);exit;
		
		$Total_Earning = ReferralBonus::where("user_id","=",$user_id)->sum('evr');
		$TotalEarning = ($Total_Earning)?$Total_Earning:0;
		
		$total_distance = Referrals::select("*")->where("ancestor_id","=",$user_id)->where("distance","!=",0)->count();
		$ref = array("TotalEarning"=>$TotalEarning,"total_distance"=>$total_distance);
		
		$res = array();
		if($total_distance > 0){
			
			for($i=1;$i<=10;$i++){
				$level_count = Referrals::select("*")->where("ancestor_id","=",$user_id)->where("distance","=",$i)->count();
				$res[] = array(
							"level"=>"Level ".$i,
							"distance"=>$level_count
							);
				/*if($level_count==0){
					break;	
				}*/
			}
			
		}
		
		$tot_result = array('ref'=>$ref,'Result' => $res);
		return response()->json(["Success"=>true,'status' => 200,'Result'=>$tot_result], 200);
	}
	
	// add-2FA-authentication API
	public function addTwoFAAuthentication(Request $request)
	{  
		
		$data = $request->user();
		$user_id = $data['user_id'];
		$email = $data['email'];
		
		$userData = Userinfo::select("*")->where("user_id","=",$user_id)->first();
		if(!empty($userData)){
			
			if($userData->google_2fa_key != ""){
				$res = array("msg"=>"Your Key Already Exist","google_secreate_key"=>$userData->google_2fa_key);
				return response()->json(["Success"=>true,'status' => 200,'Result'=>$res ], 200);
			}else{
				$google2fa = new Google2FA();
				$google2fa_url = "";
				//$google2fa->setAllowInsecureCallToGoogleApis(true);
				$secretkey=$google2fa->generateSecretKey();
				
				$google2fa = (new \PragmaRX\Google2FA\Google2FA());
				$qrCodeUrl = $google2fa->getQRCodeUrl(
						"Brexily",
						$email,
						$secretkey
					);
				
				$google2fa_url = self::custom_generate_qrcode_url($qrCodeUrl,$secretkey,$user_id);
				
				
				$res = array("msg"=>"Your key generated successfully","google_secreate_key"=>$secretkey,"google2fa_qrcode_url"=>$google2fa_url);
				
				return response()->json(["Success"=>true,'status' => 200,'Result'=>$res ], 200);
			}
		}else{
			$res = array("msg"=>"E-mail does not exist");
			return response()->json(["Success"=>false,'status' => 422,'Result'=>$res ], 200);
		}

		
	}

	public static function custom_generate_qrcode_url($qrCodeUrl,$secretkey,$user_id){

		$writer = new Writer(
		    new ImageRenderer(
		        new RendererStyle(400),
		        new ImagickImageBackEnd()
		    )
		);

		$qrcode_image = base64_encode($writer->writeString($qrCodeUrl));
		
		$qrc_img = 'data:image/png;base64,'.$qrcode_image;

		Userinfo::where('user_id',$user_id)->update(["google_2fa_key"=>$secretkey,"google2fa_qrcode_url"=>$qrc_img]);

		return $qrc_img;

	}
	
	// google-qrcode-render API
	public function googleQrcodeRender(Request $request)
	{  
		
		$data = $request->user();
		$user_id = $data['user_id'];
		$email = $data['email'];
		
		$userData = Userinfo::select("*")->where("user_id","=",$user_id)->first();
		if(!empty($userData)){
			
			$res = array("msg"=>"Your key generated successfully","google_secreate_key"=>$userData->google_2fa_key,"google2fa_qrcode_url"=>$userData->google2fa_qrcode_url);
				
			return response()->json(["Success"=>true,'status' => 200,'Result'=>$res ], 200);
		}else{
			$res = array("msg"=>"E-mail does not exist");
			return response()->json(["Success"=>false,'status' => 422,'Result'=>$res ], 200);
		}

		
	}
	// googleVerifyToken
	public function twofaSettings(Request $request)
	{  

		$data = $request->user();
		$user_id = $data['user_id'];
		$email = $data['email'];
		$validator = Validator::make($request->all(), [
			'type' => 'required',
			'status' => 'required',
		]);
		if ($validator->fails()) {
			return response()->json(['status' => false, 'Result' => $validator->errors()], 400);
		}else{
			$type = request('type');
			$status = request('status');
			if($type == 'login'){
				$data = array("login_tfa_status"=>$status);	
				Userinfo::where('user_id',$user_id)->update($data);
			}else if($type == 'withdraw'){
				$data = array("withdraw_tfa_status"=>$status);	
				Userinfo::where('user_id',$user_id)->update($data);
			}else if($type == 'transfertouser'){
				$data = array("transferto_user_tfa_status"=>$status);	
				Userinfo::where('user_id',$user_id)->update($data);
			}
			
			$resArr = array("msg"=>"Successfuly Updated");
			return response()->json(["Success"=>true,'status' => 200,'Result'=>$resArr ], 200);
		}
	}
	
	// googleVerifyToken
	public function googleVerifyToken(Request $request)
	{  
		
		$data = $request->user();
		$user_id = $data['user_id'];
		$email = $data['email'];
		$validator = Validator::make($request->all(), [
			'google_token' => 'required',
			'verification_status_change' => 'required',
		]);
		if ($validator->fails()) {
			return response()->json(['status' => false, 'Result' => $validator->errors()], 400);
		}else{
			$google_token = request('google_token');
			$verification_status_change = request('verification_status_change');
			
			$userData = Userinfo::select("*")->where("user_id","=",$user_id)->first();
			if(!empty($userData)){
				$Token2fa_validation_initial = $userData->Token2fa_validation_initial;
				
				if($verification_status_change == "A" && $Token2fa_validation_initial == "D"){
					
					$google2fa = new Google2FA();
					$window = 0;
					$res=$google2fa->verifyKey($userData['google_2fa_key'], $google_token,$window);
					if($res){
						$data = array("Twofa_status"=>"A","Token2fa_validation"=>"A","Token2fa_validation_initial"=>"A","login_tfa_status"=>"A","withdraw_tfa_status"=>"A","transferto_user_tfa_status"=>"A");	
						Userinfo::where('user_id',$userData['user_id'])->update($data);	
						if($userData['applied_ref_code'] != ""){
							$descendant_id = $userData->user_id;
							$refResult = Userinfo::select("*")->where("ref_code","=",$userData['applied_ref_code'])->groupBy('user_id')->get();
							if(@count($refResult)>0){
								/*$ancestor_id = $refResult[0]->user_id;
								$insertObj = array(
									"ancestor_id"=> $ancestor_id,
									"descendant_id"=> $descendant_id
								);
								
								$resrefNodes = DB::table('referrals_nodes')->insert($insertObj);*/
								$resArr = array("msg"=>"Successfuly Verified & Activated");
								return response()->json(["Success"=>true,'status' => 200,'Result'=>$resArr ], 200);
							}else{
								/*$insertObj = array(
									"ancestor_id"=>$userData->user_id,
									"descendant_id"=>$userData->user_id
								);
								
								$resrefNodes = DB::table('referrals_nodes')->insert($insertObj);*/
								$resArr = array("msg"=>"Successfuly Verified & Activated");
								return response()->json(["Success"=>true,'status' => 200,'Result'=>$resArr ], 200);
							}
							
						}else{
						
							/*$insertObj = array(
									"ancestor_id"=>$userData->user_id,
									"descendant_id"=>$userData->user_id
								);
								
							$resrefNodes = DB::table('referrals_nodes')->insert($insertObj);*/
							$resArr = array("msg"=>"Successfuly Verified & Activated");
							return response()->json(["Success"=>true,'status' => 200,'Result'=>$resArr ], 200);
						}
						
							
					}else{
						$resArr = array("msg"=>"Verification Failed");
						return response()->json(["Success"=>false,'status' => 401,'Result'=>$resArr ], 401);
					}
					
				}else{
					if($verification_status_change == "A" && $Token2fa_validation_initial == "A"){
						$google2fa = new Google2FA();
						$window = 0;
						$res=$google2fa->verifyKey($userData['google_2fa_key'], $google_token,$window);
						if($res){
							$data = array("Twofa_status"=>"A","Token2fa_validation"=>"A","login_tfa_status"=>"A","withdraw_tfa_status"=>"A","transferto_user_tfa_status"=>"A");	
							Userinfo::where('user_id',$userData['user_id'])->update($data);	
							$resArr = array("msg"=>"Successfuly Verified & Activated");
							return response()->json(["Success"=>true,'status' => 200,'Result'=>$resArr ], 200);
							
						}else{
							$resArr = array("msg"=>"Verification Failed");
							return response()->json(["Success"=>false,'status' => 401,'Result'=>$resArr ], 401);
						}
						
					}else{
						$google2fa = new Google2FA();
						$window = 0;
						$res=$google2fa->verifyKey($userData['google_2fa_key'], $google_token,$window);
						if($res){
							$data = array("Twofa_status"=>"D","login_tfa_status"=>"D","withdraw_tfa_status"=>"D","transferto_user_tfa_status"=>"D");	
							Userinfo::where('user_id',$userData['user_id'])->update($data);	
							$resArr = array("msg"=>"Successfuly Verified & Deactivated");
							return response()->json(["Success"=>true,'status' => 200,'Result'=>$resArr ], 200);
							
						}else{
							$resArr = array("msg"=>"Verification Failed");
							return response()->json(["Success"=>false,'status' => 401,'Result'=>$resArr ], 401);
						}
					}	
				
				}
				
				
					
				return response()->json(["Success"=>true,'status' => 200,'Result'=>$res ], 200);
			}else{
				$res = array("msg"=>"E-mail does not exist");
				return response()->json(["Success"=>false,'status' => 422,'Result'=>$res ], 400);
			}
		
		}
		

		
	}
	
	
	
    
}
