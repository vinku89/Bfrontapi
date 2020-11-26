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
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use App\KycVerification;
use PragmaRX\Google2FA\Google2FA;
class MobileWalletController extends BaseController 
{
	public function allWalletsList(Request $request)
	{ 
		$data = $request->user();
		$user_id = $data['user_id'];
		
		$coinList = CoinListing::where("status","!=",0)->orderBy("coin_symbol", "ASC")->get()->toArray();
		$basecurrencyList = array();
		$stablecoinList = array();
		if(@count($coinList)>0){

			return response()->json(["Success"=>true,'status' => 200,'Result' => $coinList,"coin_image_path"=>"https://staging-admin.brexily.com/coin_listing_images/"], 200);
		}else{
			return response()->json(["Success"=>false,'Status' => 422, 'Result' => array(),"coin_image_path"=>"https://staging-admin.brexily.com/coin_listing_images/"], 200);
		}
		
	}
	public function activeWalletsList(Request $request)
	{ 
		$data = $request->user();
		$user_id = $data['user_id'];
		
		$coinList = CoinListing::where("status",1)->orderBy("coin_symbol", "ASC")->get()->toArray();
		$basecurrencyList = array();
		$stablecoinList = array();
		if(@count($coinList)>0){
			foreach ($coinList as $c) {
				
			}
			return response()->json(["Success"=>true,'status' => 200,'Result' => $coinList], 200);
		}else{
			return response()->json(["Success"=>false,'Status' => 422, 'Result' => array()], 200);
		}
		
	}
	
}