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
class DepositController extends BaseController 
{

	
	public function wallet_address_check(Request $request){
		$data = $request->user();
		$user_id = $data['user_id'];
		$validator = Validator::make($request->all(), [
                'wallet_symbol' => 'required',
                ]);
		if ($validator->fails()) {
            return response()->json(['status'=>'Failure','Result'=>$validator->errors()], 200);            
        }
        $wallet_symbol=request('wallet_symbol');
        $coin_info=CoinListing::where("status",1)->whereRaw('UPPER(coin_symbol) = ?', [strtoupper($wallet_symbol)])->first();
        if($coin_info!==null){
   //      	$userkyc=KycVerification::where('user_id',$user_id)->first();
			// if($userkyc!==null){
			// 	if($userkyc['status']=='reject'){
			// 		return response()->json(['status'=>'Failure','Result'=>"Your KYC is rejected"], 401);
			// 	}else if($userkyc['status']=='pending'){
			// 		return response()->json(['status'=>'Failure','Result'=>"Your KYC is pending for approval."], 401);
			// 	}
				
			// }else{
			// 	return response()->json(['status'=>'Failure','Result'=>"You are not submitted the KYC."], 401);
			// }
			$useraddress=UserAddresses::where('user_id',$user_id)->where('wallet_symbol',$wallet_symbol)->where('status',1)->first();
			if($useraddress!=null){
				
				$qrcode=self::address_qrcode_generate($useraddress['wallet_address'],$user_id,$wallet_symbol);
				$address_data=array(
					"wallet_address"=>$useraddress['wallet_address'],
					"qrcode"=>$qrcode
				);
				return response()->json(['status'=>'Success','Result'=>$address_data], 200);
			}else{

				$authRes=NodeApiCalls::auth($user_id);
				if($authRes==config('constants.NODE_TOKEN_EXPIRED')){
					return response()->json(['status'=>'Success','Result'=>"Node token expired","is_expired"=>1], 400);
				}
				$authtoken=$authRes->data->token;
				

				$walletRes=NodeApiCalls::wallet_creation($user_id,$authtoken,$wallet_symbol,$coin_info->is_erc20);
				if($authRes==config('constants.NODE_TOKEN_EXPIRED')){
					return response()->json(['status'=>'Success','Result'=>"Node token expired","is_expired"=>1], 400);
				}
				Log::info("wallet creation ".json_encode($walletRes));
				if($walletRes->success){
					$wallet_address=$walletRes->data->address;
					$idata=array(
							"user_id"=>$user_id,
							"wallet_symbol"=>$wallet_symbol,
							"wallet_address"=>$wallet_address,
							"status"=>1
						);
					UserAddresses::updateOrCreate(["user_id" => $user_id,"wallet_symbol"=>$wallet_symbol], $idata);
					//if($walletRes->data->isExist==false){
						
						
						//UserAddresses::insert($idata);
					/*}else{
						Log::info("wallet creation for ".$wallet_symbol." ".json_encode($walletRes));
					}	*/		
					$qrcode=self::address_qrcode_generate($wallet_address,$user_id,$wallet_symbol);
					$address_data=array(
						"wallet_address"=>$wallet_address,
						"qrcode"=>$qrcode
					);
					return response()->json(['status'=>'Success','Result'=>$address_data], 200);
				}else{
					return response()->json(['status'=>'Failure','Result'=>"Address creation failed."], 400);
				}
				
			}
		}
	}
	public static function create_api_session_at_withdraw($user_id){
		ApiSessions::where("user_id",$user_id)->update(['is_expired'=>1]);
		$session_key=Hash::make($user_id.time());				   			
		ApiSessions::insert(array(
				"user_id"=>$user_id,
				"session_key"=>$session_key,
				"expire_date"=>date("Y-m-d H:i:s",strtotime("+5 minutes")),
				"is_expired"=>0
			)
		);
		return $session_key;
    }
	public function create_api_session(Request $request){
		$data = $request->user();
		$user_id = $data['user_id'];
		ApiSessions::where("user_id",$user_id)->update(['is_expired'=>1]);
		$session_key=Hash::make($user_id.time());				   			
		ApiSessions::insert(array(
				"user_id"=>$user_id,
				"session_key"=>$session_key,
				"expire_date"=>date("Y-m-d H:i:s",strtotime("+5 minutes")),
				"is_expired"=>0
			)
		);
		return response()->json(['status'=>'Success','Result'=> $session_key], 200);
    }
	public static function address_qrcode_generate($address,$user_id,$wallet_symbol){

		$writer = new Writer(
		    new ImageRenderer(
		        new RendererStyle(400),
		        new ImagickImageBackEnd()
		    )
		);

		$qrcode_image = base64_encode($writer->writeString($address));
		
		$qrc_img = 'data:image/png;base64,'.$qrcode_image;

		UserAddresses::where('user_id',$user_id)->where('wallet_symbol',$wallet_symbol)->where('wallet_address',$address)->update(["qrcode"=>$qrc_img]);

		return $qrc_img;

	}
}