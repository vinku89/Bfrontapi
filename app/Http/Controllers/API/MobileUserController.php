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
class MobileUserController extends BaseController 
{
	// Get Logged user details
	public function mobileUserDetails(Request $request)
	{ 
		$data = $request->user();
		$user_id = $data['user_id'];
		$email = $data['email'];
		$brexily_address = $data['brexily_address'];
		$userData = Userinfo::select("*")->where("user_id","=",$user_id)->first();
		$userLoginInfo =  DB::table('login_history')->select('ip' , 'created_date')->where("user_id","=",$user_id)->orderby('created_date' , 'DESC')->first();
		$userkyc=KycVerification::where('user_id',$user_id)->first();
		$userBlances= Balance::select(\DB::raw('sum(dbt_balance.main_balance*coin_listing.coin_price) as mainBalance , sum(dbt_balance.balance*coin_listing.coin_price) as tradingBlance'))->Join('coin_listing','dbt_balance.currency_symbol' , '=' , 'coin_listing.coin_symbol' )->where('dbt_balance.user_id',$user_id)->first();
		

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

		if(@count($userData) >0){
			//echo json_encode($userData);exit;
			$country=Country::where("countryid",$userData->nationality)->first();
			$earn_crypto=0;
			$brexco=0;
			$wallets=0;
			if($country!==null){
				$earn_crypto=1;
				$brexco=1;
				$wallets=1;
			}
			$userInfo = array(
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
							"lastLoginDate"=>$userLoginInfo->created_date,
							"ip"=>$userLoginInfo->ip,
							"mainBalaces"=>number_format_two_dec($userBlances->mainBalance),
							"tradingBlance"=>number_format_two_dec($userBlances->tradingBlance),
							"totalAsset"=>number_format_two_dec($userBlances->mainBalance+$userBlances->tradingBlance),
							"userRole"=>$userRole->role_name,
							"userCPercent"=>$userRole->cpercent,
							"rank"=>$userData->rank,
							"earn_crypto"=>$earn_crypto,
							"brexco"=>$brexco,
							"wallets"=>$wallets
						);
							
				return response()->json(["Success"=>true,'status' => 200,'Result' => $userInfo], 200);
		}else{
			$userInfo = array();
			return response()->json(["Success"=>false,'status' => 422,'Result' => $userInfo], 200);
		}
			
	}


}