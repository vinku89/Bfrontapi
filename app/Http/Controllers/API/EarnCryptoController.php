<?php
namespace App\Http\Controllers\API;
use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;

use App\User;
//use Illuminate\Support\Facades\Auth;
//use Validator;
use Illuminate\Support\Facades\Mail;
//use App\Userinfo;
use DB;
//use Amqp;
use Log;
use App\CoinListing;
use App\Balance;

class EarnCryptoController extends BaseController
{
  
  // Runs daily and sends earnings to user
  public static function dailyPayoutsCron()
  {
  	Log::info('start: dailyPayoutsCron');
  	try {


  		// Change status to withdraw
  		DB::table('earncrypto_transaction')->whereRaw('CURDATE() > locking_period_date')->whereRaw('status = 1')->update(array("status"=>'2'));

  		$activeTrades = DB::table('earncrypto_transaction')->whereRaw('earncrypto_transaction.status in (1,2)')->get()->toArray();

	     $earncryptoEnableOffer = DB::table('earncrypto_constant')->select('value','formDate' , 'toDate','lockingPeriodMonths' , 'percentages')->where('name', '=', 'EARNCRYPTO_ENABLE_PERCENTAGE_BTW_DATES')->first();
		 

  		for($i=0; $i< count($activeTrades); $i++) {
  			$trade = $activeTrades[$i];

  			// calculate daily payout usd amount
  			$tradeId = $trade->id;
			$time=strtotime(date("Y/m/d"));
			$tradeMonth=date("m",$time);

			$tradeYear=date("Y",$time);

  			//$date = $trade->join_date;

  			// get days in year and month
  			// $tradeYear = date_format($date,"Y");
  			// $tradeMonth = date_format($date,"m");
  			$days = cal_days_in_month(CAL_GREGORIAN, $tradeMonth, $tradeYear);
  			if(empty($days)) {
  				$days = 1 ;
  			}

  			$lockPeriod = DB::table('earncrypto_lockperiod')->where('id', '=', $trade->locking_period_id)->first();

			$activeUserblock = DB::table('users')->where('user_id','=', $trade->user_id)->where('status', '=', 'A')->where('is_user_blocked' ,'=' , "0")->first();

			if(empty($activeUserblock)) {
  				continue;
  			}

  			$inDateRange = false;

  			$joinDate = $trade->join_date;

  			//chaeck after desc 31st 2020 condtion  

  		    // if(strtotime(date("Y/m/d")) > strtotime('2020-12-31') && $trade->locking_period_months >= 6)
  		    // {
  		    //  $inDateRange = true;
  		    // } 
            

  			// check joindate between 17th Nov to 31st Dec and percentages are level0-5%,level1-2%,level2-0.7%,level3-0.3%

			if( $earncryptoEnableOffer->value == 'Y')
			{
				if(strtotime($earncryptoEnableOffer->formDate) <= strtotime($joinDate) &&  strtotime($earncryptoEnableOffer->toDate) >= strtotime($joinDate) && $trade->locking_period_months >= $earncryptoEnableOffer->lockingPeriodMonths)
				{
				 $percentagesArr =	explode(",",$earncryptoEnableOffer->percentages);

				$inDateRange = true;

			    }

			}


  			$upliners = DB::table('referrals')->whereRaw('descendant_id = ?', [$trade->user_id])->whereRaw(' distance in (0,1,2,3) ')->get()->toArray();

  			$payoutCrypto = DB::table('earncrypto_constant')->select('value')->where('name','=','EARNCRYPTO_PAYOUT')->first();
  			$crypto = CoinListing::select('coin_symbol','coin_name','coin_price')->where('coin_symbol' ,  $payoutCrypto->value)->first();

  			foreach ($upliners as $key => $value) {

  				$tradePackageUsd = $trade->package_amount_usd;
	  			$tradePayoutCrypto = $trade->package_amount_usd/$crypto->coin_price;
	  			$payoutUserId = $value->ancestor_id;

	  			$activeUser = DB::table('users')->where('user_id','=', $payoutUserId)->where('status', '=', 'A')->where('is_user_blocked' ,'=' , 0)->first();
	  			// Skipping Deactive user
	  			if(empty($activeUser)) {
	  				continue;
	  			}
				$levelPercent = 0;
  				if($value->distance == 0) {
  					if($inDateRange) {
  						$levelPercent = $percentagesArr[0];
  					} else {
  						$levelPercent = $lockPeriod->level0_percent;
  					}
  					
  				} else if($value->distance == 1) {
  					if($inDateRange) {
  						$levelPercent = $percentagesArr[1];
  					} else {
  						$levelPercent = $lockPeriod->upliner_level1_percent;
  					}
  				}  else if($value->distance == 2) {
  					if($inDateRange) {
  						$levelPercent = $percentagesArr[2];
  					} else {
  						$levelPercent = $lockPeriod->upliner_level2_percent;
  					}
  				}  else if($value->distance == 3) {
  					if($inDateRange) {
  						$levelPercent = $percentagesArr[3];
  					} else {
  						$levelPercent = $lockPeriod->upliner_level3_percent;
  					}
  				}

	  		    $payoutMonthlyUsd = $tradePackageUsd*$levelPercent/100;
	  		    $payoutMonthlyCrypto = $tradePayoutCrypto*$levelPercent/100;

	  			// Daily payout calculation per day
	  			$dailyPayoutUsd = $payoutMonthlyUsd/$days;
	  			$dailyPayoutCrypto = $payoutMonthlyCrypto/$days;

	  			echo 'EarnCryptoDaily: percent '.$levelPercent.' Monthly'. $payoutMonthlyUsd.' Daily '.$dailyPayoutUsd;


	  			// Insert to earnings and update mainbalance
	  			$arr = array('earncrypto_transaction_id'=>$trade->id, 
	  						'payout_amount_usd'=> $dailyPayoutUsd,
	  						'payout_amount_crypto'=> $dailyPayoutCrypto,
	  					    'payout_crypto_type'=>$trade->payout_crypto_type,
	  					    'upliner_user_id'=> $payoutUserId,
	  					    'upliner_level'=> $value->distance
	  					     );

	  			DB::table('earncrypto_daily_payout')->insert($arr);

	  			Balance::where('user_id','=', $payoutUserId)->where('currency_symbol', '=', $trade->payout_crypto_type)->increment('main_balance', $dailyPayoutCrypto);

	  			if($value->distance == 0) {
	  				$updatedPayoutUsd = $trade->payout_amount_usd+$dailyPayoutUsd;
	  				$updatedPayoutCrypto = $trade->payout_amount_crypto+$dailyPayoutCrypto;
	  				$updatedTradeArr = array("payout_amount_usd"=>$updatedPayoutUsd, "payout_amount_crypto"=>$updatedPayoutCrypto);


	  				DB::table('earncrypto_transaction')->where('id', '=', $tradeId)->update($updatedTradeArr);
	  				Log::info('Added earncrypto_transaction : TradeId '.$tradeId);
	  			}
	  			Log::info('Added Payout Main Balance '.$dailyPayoutCrypto.' Crypto to User: '.$payoutUserId);
  			}
  		}
  	}
  	catch(Exception $e) {
  		Log::error('Problem while sending payouts');
  	}
  	Log::info('end: dailyPayoutsCron');
  }

  public function getEarnCryptoHistory(Request $request)
  {
  	try
  	{
		$data = $request->user();
		$user_id = $data['user_id'];
		
		 $status  = Request('status_id');
		 $package_id = Request('package_id');
		 $lockingPerod_id = Request('lockingPerod_id');
		 $crypto = Request('crypto');
        
		 $totalRec = DB::table('earncrypto_transaction')->select(DB::raw('count(*) as totalRec'))->where('user_id' , $user_id)->first();

        //\DB::enableQueryLog();
		$query =  DB::table('earncrypto_transaction as e')->select(DB::raw('	DATE_FORMAT(join_date , "%d-%m-%Y") as joinDate , package_amount_usd ,payout_crypto_type, payout_amount_usd , (package_amount_crypto) as tradingInCripto , locking_period_months , package_crypto_type ,  DATE_FORMAT(locking_period_date, "%d-%m-%Y") as lockingPeriodDate , withdraw_id ,  DATE_FORMAT(withdraw_date , "%d-%m-%Y") as withdrawdate , e.status, payout_amount_crypto , penalty_percent, penalty_amount_crypto, coin_image '))->join('coin_listing as c','coin_symbol', '=', 'package_crypto_type')->where('user_id' , $user_id );

       if (!empty($status) && ($status != ''))
       {
		$query  = $query->where('e.status' , $status);

       }
       
       if (!empty($package_id))
       {
		$query = $query->where('package_id' , $package_id );

       }
       
       if (!empty($lockingPerod_id))
       {
		$query = $query->where('locking_period_months' , $lockingPerod_id );

       }
       
       if (!empty($crypto))
       {
		$query = $query->where('package_crypto_type' , $crypto );

       } else {
       		$crypto = 'BTC';
       }

		$result['earnCryptoHistory']  = $query->orderby('join_date' , 'desc')->get();

		$totalActiveTrading = DB::table('earncrypto_transaction')->selectRaw('SUM(COALESCE(package_amount_usd,0)) as pkgAmt, SUM(COALESCE(payout_amount_usd,0)) as payoutAmt')->where('user_id','=', $user_id)->whereRaw('status in (1,2)')->first();

		$totalPayouts = DB::table('earncrypto_daily_payout')->selectRaw('SUM(COALESCE(payout_amount_usd,0)) as payoutAmt')->where('upliner_user_id','=', $user_id)->first();

		$result['totalActiveTrading'] = $totalActiveTrading->pkgAmt;
		$result['totalEarnProfit'] = $totalPayouts->payoutAmt;
		$result['totalRecords'] = $totalRec->totalRec;
		//$query = \DB::getQueryLog();

        //print_r(end($query));

		$result['walletBlance'] = DB::table('dbt_balance')->where('user_id',$user_id)->where('currency_symbol' , $crypto)->get();

		$result['cryptoList'] = CoinListing::select('coin_symbol','coin_name','coin_price', 'coin_image')->where('earnCrypto_status' , 1)->get();

		$result['earnCrypto_package_list'] = DB::table('earncrypto_packagelist')->select('package_amount','id')->where('status' , 1)->orderby('package_amount' , 'ASC')->get();


		$result['earncrypto_lockperiod'] = DB::table('earncrypto_lockperiod')->select('id','months')->groupby('months')->orderby('months' , 'asc')->get();

		$result['earncrypto_status'] = DB::table('earncrypto_status')->select('status','id')->get();
    	

    	$result['earncrypto_enable_before_tenure'] = DB::table('earncrypto_constant')->select('value')->where('name', '=', 'EARNCRYPTO_ENABLE_BEFORE_TENURE')->first();
    	return response()->json(["Success"=>true,'status' => 200,'Result' => $result], 200);

    }
    catch(Exception $e)
    {
     return response()->json(["Success"=>false,'status' => 400,'Result' => array()], 400);
    }
  }

  public function validateEmail(Request $request)
  {
  		$data = $request->user();
		$user_id = $data['user_id'];

  	try{
  		$subscribeFriendEmail = Request('subscribe_friend_email');
  		if(empty($subscribeFriendEmail)) {
  			return response()->json(["Success"=>true,'status' => 400,'Result' => 'Email is not valid'], 200);	
  		}
  		
  		$emailValidater = DB::table('users')->select('email')->whereRaw('LOWER(email) = LOWER("'.$subscribeFriendEmail.'")')->where('status', '=', 'A')->where('user_id','!=', $user_id)->first();



  		$selfEmail  = DB::table('users')->select('email')->whereRaw('LOWER(email) = LOWER("'.$subscribeFriendEmail.'")')->where('status', '=', 'A')->where('user_id','=', $user_id)->first();


  		if(strtolower(@$emailValidater->email) == strtolower($subscribeFriendEmail))
  		{
  		return response()->json(["Success"=>true,'status' => 200,'Result' => 'Email is valid'], 200);
  		}
  		else if(strtolower(@$selfEmail->email) == strtolower($subscribeFriendEmail))
  		{
  		 return response()->json(["Success"=>true,'status' => 200,'Result' => 'Cannot purchase to yourself'], 200);
  		}
  		else
  		{
  		return response()->json(["Success"=>true,'status' => 400,'Result' => 'Email is not valid'], 200);	
  		}
    }
  	
  	catch(Exception $e)
  	{
  		 return response()->json(["Success"=>false,'status' => 400,'Result' => array()], 400);
  	}


  }
  public function earnjoinPackage(Request $request)
  {
  	try {
		$data = $request->user();
		$user_id = $data['user_id'];
		DB::beginTransaction();
		$userInfo =		DB::table('users')->select('users.email as email' , 'userinfo.first_name as firstName' , 'userinfo.last_name as lastName')->join('userinfo' , 'userinfo.user_id' , '=' , 'users.user_id')->where('users.user_id' ,$user_id )->first();
  


		$cryptoType = Request('cryptoType');
		$lockingPeriodId = Request('lockingPerod_id');
		$packageId = Request('package_id');
		$subscribeFriendEmail = Request('subscribe_friend_email');

		// validations

		$packages  = DB::table('earncrypto_packagelist')->select('package_amount')->whereraw('id = ? and status' , [$packageId , 1])->first(); 

		$lockingperiod   = DB::table('earncrypto_lockperiod')->select('*')->whereraw('id = ?' , [$lockingPeriodId])->first();

		$crypto = CoinListing::select('coin_symbol','coin_name','coin_price')->where('coin_symbol' ,  $cryptoType)->where('earnCrypto_status' , 1)->first();

		if(!empty($subscribeFriendEmail)) {
			
			$friendUserData = DB::table('users')->select('users.user_id','userinfo.first_name as refFristName' , 'userinfo.last_name as refLastName')->whereRaw('users.email = ?', [$subscribeFriendEmail])->join('userinfo' ,'userinfo.user_id' , '=' , 'users.user_id')->where('users.status', '=', 'A')->first();
			if(empty($friendUserData)) {
				DB::commit();
				return response()->json(["Success"=>false,'status' => 400,'Result' => 'Subscribe Email Not Valid'], 400);
			}
		}

		if(empty($packages)) {
			DB::commit();
			return response()->json(["Success"=>false,'status' => 400,'Result' => 'Subscribe Package Not Valid'], 400);
		}

		if(empty($lockingperiod)) {
			DB::commit();
			return response()->json(["Success"=>false,'status' => 400,'Result' => 'Subscribe Locking Period Not Valid'], 400);
		}

		if(empty($crypto)) {
			DB::commit();
			return response()->json(["Success"=>false,'status' => 400,'Result' => 'Subscribe Crypto Not Valid'], 400);
		}

		$lockingPeriodEnddate = date('Y-m-d H:m:s', strtotime('+'.$lockingperiod->months.'months'));

		$str = 'ABCDEFGHIJkLMNOPQRSTWUVXYZ';
		$shuffled = substr( str_shuffle($str) , 0 , 3);

		$withdraw_id = "EARN".$shuffled.time();
	
		$payoutCrypto = DB::table('earncrypto_constant')->select('value')->where('name','=','EARNCRYPTO_PAYOUT')->first();
		$packageAmountCrypto = ($packages->package_amount/$crypto->coin_price);

		$userBalance = DB::table('dbt_balance')->select('main_balance')->where('user_id', '=', $user_id)->where('currency_symbol', '=', $crypto->coin_symbol)->sharedLock()->first();

		if($userBalance->main_balance >= $packageAmountCrypto ) {
			$isBalanceUpdated = Balance::where('user_id', $user_id)->whereRaw('currency_symbol = ?', [$crypto->coin_symbol])->decrement('main_balance', $packageAmountCrypto);
		} else {
			Log::error('earnjoinPackage: Insufficient Main balance UserId: '.$user_id);
			DB::commit();
			return response()->json(["Success"=>false,'status' => 400,'Result' => 'Insufficient Main Balance'], 400);
		}

		$arr =  array('locking_period_id'=>$lockingPeriodId,
				'package_id'=>$packageId,
				'user_id'=>$user_id ,
				'package_amount_usd'=>$packages->package_amount,
				'package_amount_crypto'=>$packageAmountCrypto,
				'payout_monthly_percent'=>$lockingperiod->level0_percent,
				'penalty_percent'=>$lockingperiod->penalty_percent,
				'locking_period_months'=>$lockingperiod->months,
				'package_crypto_type'=>$crypto->coin_symbol ,
				'payout_crypto_type'=>$payoutCrypto->value,
				'withdraw_id'=>$withdraw_id ,
				'locking_period_date'=>$lockingPeriodEnddate ,
				'status'=>1);

		if(!empty(@$friendUserData)) {
			$arr['user_id'] = $friendUserData->user_id;
			$arr['subscribed_by_user_id'] = $user_id;
		}
		if($isBalanceUpdated) {
					
			$insertData =  DB::table('earncrypto_transaction')->insertGetId($arr);
			
			try{

				 $userTotalAmt = DB::table('dbt_balance')->select('main_balance')->where('user_id' , $user_id)->where('currency_symbol' , $crypto->coin_symbol)->first(); 
    	       
					$description = "Earncrypto join Package ".$packageAmountCrypto." ". $crypto->coin_symbol." on wallet Main Account";
					$transaction_id="ECP".rand(100,10000).time();
					$insertArr = array(
						'transaction_id' => $transaction_id, 
						'user_id' => $user_id, 
						'sender_id' => $user_id, 
						'receiver_id' => 0 , 
						'transaction_date' => date("Y-m-d H:i:s"), 
						'currency_symbol' => $crypto->coin_symbol, 
						'type' => "DEBIT", 
						'amount' => $packageAmountCrypto, 
						'balance' => $userTotalAmt->main_balance, 
						'transaction_type' =>"Join Package", 
						'description' => $description,
						'base_id' => $insertData,
						'created_at' => date("Y-m-d H:i:s")
					);
					DB::commit();
					$res = DB::table('main_wallet_ledger')->insert($insertArr);
				}catch (Exception $e) {
					DB::commit();
					Log::info("existingMainWalletTransactions ".json_encode($e));
				}

			if($insertData)
			{
				if(!empty(@$friendUserData)) {
					$data = array('first_name' => $userInfo->firstName , 'last_name' =>$userInfo->lastName ,  'withdraw_id' => $withdraw_id  ,  'package_amount_usd' => $packages->package_amount , 'package_amount_crypto' => $packageAmountCrypto , 'package_crypto_type'=>$crypto->coin_symbol,'refrealEamil'=>$subscribeFriendEmail , 'refFristNmae'=>$friendUserData->refFristName , 'refLastName'=>$friendUserData->refLastName );

				$emailid = array('toemail' => $userInfo->email);
				//$userinfo->email
				Mail::send(['html'=>'email_templates.earn-crypto-subscribe-myfirnd-successfully'], $data, function($message) use ($emailid) {
				$message->to($emailid['toemail'], 'Earn Crypto Successful Subscription')->subject
				('Earn Crypto Successful Subscription');
				$message->from('support@brexily.com','Brexily');
				});
				}
				else{

				$data = array('first_name' => $userInfo->firstName , 'last_name' =>$userInfo->lastName ,  'withdraw_id' => $withdraw_id ,  'package_amount_usd' => $packages->package_amount , 'package_amount_crypto' => $packageAmountCrypto , 'package_crypto_type'=>$crypto->coin_symbol );

				$emailid = array('toemail' => $userInfo->email);
				//$userinfo->email
				Mail::send(['html'=>'email_templates.earn-crypto-successful-subscribe'], $data, function($message) use ($emailid) {
				$message->to($emailid['toemail'], 'Earn Crypto Successful Subscription')->subject
				('Earn Crypto Successful Subscription');
				$message->from('support@brexily.com','Brexily');
				});
				}
		}
			Log::info('earnjoinPackage: Package subscribed successfully UserId: '.$user_id);
		} else {
			Log::error('earnjoinPackage: Balance not updated properly UserId: '.$user_id);
			DB::commit();
			return response()->json(["Success"=>false,'status' => 400,'Result' => 'Something went wrong. Please contact Admin'], 400);
		}
		
	} catch(Exception $e) {
		Log::error('earnjoinPackage: Error found '.$e);
		return response()->json(["Success"=>false,'status' => 400,'Result' => 'Something went wrong. Please contact Admin'], 400);
	}

	return response()->json(["Success"=>true,'status' => 200,'Result' => 'Package Subscribed Successfully'], 200);


  }
  public function earnWithdrawalAmount(Request $request)
  {
	$data = $request->user();
	$user_id = $data['user_id'];
 
	$userInfo =		DB::table('users')->select('users.email as email' , 'userinfo.first_name as firstName' , 'userinfo.last_name as lastName')->join('userinfo' , 'userinfo.user_id' , '=' , 'users.user_id')->where('users.user_id' ,$user_id )->first();
  
	 $transactionId =Request('earnTransaction_id') ? Request('earnTransaction_id'): 0;

	$getEarnCryptoHistory = DB::table('earncrypto_transaction')->select('id','status' , 'package_amount_usd', 'package_amount_crypto', 'package_crypto_type', 'locking_period_id','payout_crypto_type' , 'payout_amount_crypto' ,'payout_amount_usd')->where('withdraw_id', '=', $transactionId)->where('user_id' , $user_id)->first();

	// check the status of the record
	if(!(@$getEarnCryptoHistory->status && (@$getEarnCryptoHistory->status == 1 ||  @$getEarnCryptoHistory->status == 2))) {
		return response()->json(["Success"=>false ,'Result' => 'Request has been executed already'], 400);
	} 
	else if(empty($getEarnCryptoHistory))
	{
	  return response()->json(["Success"=>false ,'Result' => 'Invalid Request'], 400);
	}

	$withdrawalDate = date('Y-m-d H:i:s');
	$updateData = [];
	$totalAmountCrypto = 0;

	Log::info('earnWithdrawalAmount: '.$user_id.' - Trans ID: '.$transactionId);

	if($getEarnCryptoHistory->status == 1)
	{

		$earncrypto_enable_before_tenure = DB::table('earncrypto_constant')->select('value')->where('name', '=', 'EARNCRYPTO_ENABLE_BEFORE_TENURE')->first();

		if($earncrypto_enable_before_tenure->value == 'N') {
			return response()->json(["Success"=>false ,'Result' => 'Something went wrong. Please try again'], 400);
		}


		// Terminate when active
		// Add penalty
		$penalty = DB::table('earncrypto_lockperiod')->select('penalty_percent')->where('id', '=', $getEarnCryptoHistory->locking_period_id)->first();
		$penaltyAmountCrypto = ($getEarnCryptoHistory->package_amount_crypto*$penalty->penalty_percent/100);

		$totalAmountCrypto = $getEarnCryptoHistory->package_amount_crypto - $penaltyAmountCrypto;

		$updateData = [
		'status'=> 3, 'withdraw_date'=> $withdrawalDate, 'penalty_amount_crypto'=>$penaltyAmountCrypto
		];


		$data = array('first_name' => $userInfo->firstName , 'last_name' =>$userInfo->lastName , 'withdraw_date' => $withdrawalDate , 'withdraw_id' => $transactionId , 'crypto' => $getEarnCryptoHistory->package_crypto_type , 'payout_crypto_type' 
			=> $getEarnCryptoHistory->payout_crypto_type , 'payout_amount_crypto' => $getEarnCryptoHistory->payout_amount_crypto , 'payout_amount_usd' => $getEarnCryptoHistory->payout_amount_usd , 'package_amount_usd' => $getEarnCryptoHistory->package_amount_usd , 'package_amount_crypto' => $getEarnCryptoHistory->package_amount_crypto , 'package_crypto_type' => $getEarnCryptoHistory->package_crypto_type, 'penalty_amount_crypto'=>$penaltyAmountCrypto);
	

		$emailid = array('toemail' => $userInfo->email);
		//$userinfo->email
		Mail::send(['html'=>'email_templates.earnWithdrawalSuccess'], $data, function($message) use ($emailid) {
						$message->to($emailid['toemail'], 'Earn Crypto Successful Withdrawal')->subject
						('Earn Crypto Successful Withdrawal');
						$message->from('support@brexily.com','Brexily');
					});
		//earnCryptoTemanateWithdrawal
	}
	else if($getEarnCryptoHistory->status == 2) {
		// Withdraw action
		$totalAmountCrypto = $getEarnCryptoHistory->package_amount_crypto;
		$updateData = [
		'status'=> 3, 'withdraw_date'=> $withdrawalDate
		];

	
		$data = array('first_name' => $userInfo->firstName , 'last_name' =>$userInfo->lastName , 'withdraw_date' =>date('Y-m-d') , 'withdraw_id' => $transactionId , 'crypto' => $getEarnCryptoHistory->package_crypto_type , 'payout_crypto_type' 
			=> $getEarnCryptoHistory->payout_crypto_type , 'payout_amount_crypto' => $getEarnCryptoHistory->payout_amount_crypto , 'payout_amount_usd' => $getEarnCryptoHistory->payout_amount_usd , 'package_amount_usd' => $getEarnCryptoHistory->package_amount_usd , 'package_amount_crypto' => $getEarnCryptoHistory->package_amount_crypto , 'package_crypto_type' => $getEarnCryptoHistory->package_crypto_type);
	

		$emailid = array('toemail' => $userInfo->email);
		//$userinfo->email
		Mail::send(['html'=>'email_templates.earnWithdrawalSuccess'], $data, function($message) use ($emailid) {
						$message->to($emailid['toemail'], 'Earn Crypto Successful Withdrawal')->subject
						('Earn Crypto Successful Withdrawal');
						$message->from('support@brexily.com','Brexily');
					});
					
	}


	$updateBal = DB::table('dbt_balance')->where('user_id', $user_id)->where('currency_symbol',  $getEarnCryptoHistory->package_crypto_type)->increment('main_balance', $totalAmountCrypto);

	DB::table('earncrypto_transaction')->where('withdraw_id', '=', $transactionId)->where('user_id', $user_id)->update($updateData);
	if($updateBal && ($totalAmountCrypto > 0))
	{
				try{

				$userTotalAmt = DB::table('dbt_balance')->select('main_balance')->where('user_id' , $user_id)->where('currency_symbol' , $getEarnCryptoHistory->package_crypto_type)->first(); 

				$description = "Earncrypto Withdrawal Package ".$totalAmountCrypto." ". $getEarnCryptoHistory->package_crypto_type." on wallet Main Account";
				$transaction_id="ECP".rand(100,10000).time();
				$insertArr = array(
				'transaction_id' => $transaction_id, 
				'user_id' => $user_id, 
				'sender_id' => 0, 
				'receiver_id' => $user_id , 
				'transaction_date' => date("Y-m-d H:i:s"), 
				'currency_symbol' => $getEarnCryptoHistory->package_crypto_type , 
				'type' => "CREDIT", 
				'amount' => $totalAmountCrypto, 
				'balance' => $userTotalAmt->main_balance, 
				'transaction_type' =>"Withdrawal", 
				'description' => $description,
				'base_id' => $getEarnCryptoHistory->id,
				'created_at' => date("Y-m-d H:i:s")
				);
				$res = DB::table('main_wallet_ledger')->insert($insertArr);
				}catch (Exception $e) {
				Log::info("existingMainWalletTransactions ".json_encode($e));
				}
			}
	return response()->json(["Success"=>true ,'Result' => 'Request has been processed successfully'], 200);
  }


public function earnCryptoEarningsMonthly(Request $request)
{
	try {
			$userData = $request->user();

			$data = array();
	
			$user_id = $userData['user_id'];
			$year = Request('year');

			//\DB::enableQueryLog();
			$query = DB::table('earncrypto_daily_payout')->select(DB::raw('SUM(COALESCE(earncrypto_daily_payout.payout_amount_usd,0)) as payoutAmountUsd, SUM(COALESCE(earncrypto_daily_payout.payout_amount_crypto,0)) as payoutAmountCrypto, earncrypto_daily_payout.payout_crypto_type, DATE_FORMAT(earncrypto_daily_payout.created_at, "%m") as payoutDay'))->join('users', 'users.user_id', '=', 'earncrypto_daily_payout.upliner_user_id');

			$currentDate = getdate();

			$year = empty($year) ? $currentDate['year'] : $year;

			$query = $query->whereRaw('earncrypto_daily_payout.upliner_user_id = ?', [$user_id]);

			$totalAmountData = DB::table('earncrypto_transaction')->select(DB::raw('SUM(COALESCE(package_amount_usd,0)) as totalPackageAmountUsd'))->whereRaw('status in (1,2)')->where('user_id', '=', $user_id)->first();
			$totalPayoutData = DB::table('earncrypto_daily_payout')->select(DB::raw('SUM(COALESCE(payout_amount_usd,0)) as totalPayoutAmountUsd'))->where('upliner_user_id', '=', $user_id)->first();
			
			$data['totalAmountData'] = $totalAmountData ? $totalAmountData->totalPackageAmountUsd : 0;
			$data['totalPayoutData'] = $totalPayoutData ? $totalPayoutData->totalPayoutAmountUsd : 0;

			$query = $query->whereRaw('DATE_FORMAT(earncrypto_daily_payout.created_at, "%Y") = ?', [$year])->groupByRaw('payoutDay, earncrypto_daily_payout.payout_crypto_type');
			
			$data['result'] = $query->orderByRaw(' payoutDay ASC ')->get()->toArray();
			$data['year'] = $year;

			$years = array();

			$currentYear= (int)date("Y");
			for($i=2020; $i<=$currentYear; $i++) {
				array_push($years, $i);
			}
			$data['years'] = $years;
			
			//$query = \DB::getQueryLog();
			//print_r(end($query));

		} catch(Exception $e) {
			Log::error('Problem in payoutsAjax'.$e);
			return response()->json(['status' => 'Fail', 'Reason' =>'Problem while sending payout details'], 500);
		}

		return response()->json(['status' => 'Success', 'Result' =>$data], 200);
}

  public function earnCryptoEarningsDialy(Request $request)
  {

  try {
		$userData = $request->user();

		$data = array();
		$user_id = $userData['user_id'];

		$month = Request('month');
		$year = Request('year');


		//\DB::enableQueryLog();
		$query = DB::table('earncrypto_daily_payout')->select(DB::raw('SUM(COALESCE(earncrypto_daily_payout.payout_amount_usd,0)) as payoutAmountUsd, SUM(COALESCE(earncrypto_daily_payout.payout_amount_crypto,0)) as payoutAmountCrypto, earncrypto_daily_payout.payout_crypto_type, DATE_FORMAT(earncrypto_daily_payout.created_at, "%d") as payoutDay'))->join('users', 'users.user_id', '=', 'earncrypto_daily_payout.upliner_user_id');

		$currentDate = getdate();

		$year = empty($year) ? $currentDate['year'] : $year;
		$month = empty($month) ? $currentDate['mon']: $month;

		$totalPayoutData = DB::table('earncrypto_daily_payout')->select(DB::raw('SUM(COALESCE(payout_amount_usd,0)) as totalPayoutAmountUsd, SUM(COALESCE(payout_amount_crypto,0)) as totalPayoutAmountCrypto'))->where('upliner_user_id', '=', $user_id)->first();
		
		$data['totalPayoutData'] = $totalPayoutData ? $totalPayoutData->totalPayoutAmountUsd : 0;

		$data['totalPayoutCryptoData'] = $totalPayoutData ? $totalPayoutData->totalPayoutAmountCrypto : 0;


		$yearMonth = $year.'-'.$month;

		$query = $query->whereRaw('earncrypto_daily_payout.upliner_user_id = ?', [$user_id]);

		$query = $query->whereRaw('DATE_FORMAT(earncrypto_daily_payout.created_at, "%Y-%m") = ?', [$yearMonth])->groupByRaw('payoutDay, earncrypto_daily_payout.payout_crypto_type');
		
		$data['result'] = $query->orderByRaw(' payoutDay ASC ')->get()->toArray();
		$dataMap = array();
		foreach ($data['result'] as $key => $value) {
			$dataMap[$value->payoutDay] = $value;
		}
		$data['year'] = $year;
		$data['month'] = $month;

		$start_date = "01-".$month."-".$year;
		$start_time = strtotime($start_date);

		$end_time = strtotime("+1 month", $start_time);

		for($i=$start_time; $i<$end_time; $i+=86400)
		{
		   $list[] = date('Y-m-d-D', $i);
		}

		$data['calList'] = $list;

		$months = array();
		$years = array();
		for($i = 1 ; $i <= 12; $i++)
		{
			array_push($months,  date("F",strtotime(date("Y")."-".$i."-01")));
		}

		$currentYear= (int)date("Y");
		for($i=2020; $i<=$currentYear; $i++) {
			array_push($years, $i);
		}
		$data['months'] = $months;
		$data['years'] = $years;
			
			//$query = \DB::getQueryLog();
			//print_r(end($query));

		} catch(Exception $e) {
			Log::error('Problem in payoutsAjax'.$e);
			return response()->json(['status' => 'Fail', 'Reason' =>'Problem while sending payout details'], 500);
		}

		return response()->json(['status' => 'Success', 'Result' =>$data], 200);
  }
  public function earnGetJoinPackagesList(Request $request)
  {

  	try
  	{
	$data = $request->user();
	 $selectedLockingPeriod = Request('lockingPerod_id');
	 $crypto = Request('crypto');
	//exit();
	$user_id = $data['user_id'];
	$result['earnCrypto_package_list'] = DB::table('earncrypto_packagelist')->select(DB::raw('(earncrypto_packagelist.package_amount ) as package_amt ,earncrypto_packagelist.id , (earncrypto_packagelist.package_amount/coin_listing.coin_price) as cryptoAmt , coin_listing.coin_symbol , coin_listing.coin_image' ))->leftjoin('coin_listing' , 'earncrypto_packagelist.id' , '!=' , 'coin_listing.coin_image' )->where('coin_listing.coin_symbol' , $crypto)->where('earncrypto_packagelist.status' , 1)->orderby('earncrypto_packagelist.package_amount' , 'ASC')->get();
	$result['cryptoList'] = CoinListing::select('coin_symbol','coin_name','coin_price' , 'coin_image')->where('earnCrypto_status' , 1)->get();
	if( strtolower($crypto) == 'evr')
	{
	$result['earncrypto_lockperiod'] = DB::table('earncrypto_lockperiod')->select('*')->where('crypto_type' , '=' , 'EVR')->get();
	}
	else
	{
	$result['earncrypto_lockperiod'] = DB::table('earncrypto_lockperiod')->select('*')->where('crypto_type' , '<>' , 'EVR')->get();		
	}
	$time=strtotime(date("Y/m/d"));
    $earncryptoEnableOffer = DB::table('earncrypto_constant')->select('value','formDate' , 'toDate','lockingPeriodMonths' , 'percentages')->where('name', '=', 'EARNCRYPTO_ENABLE_PERCENTAGE_BTW_DATES')->first();
		 



	    if( $earncryptoEnableOffer->value == 'Y')
		{
	       
			if(strtotime($earncryptoEnableOffer->formDate) <= $time &&  strtotime($earncryptoEnableOffer->toDate) >= $time)
			{
		    $percentagesArr =	explode(",",$earncryptoEnableOffer->percentages);
			$result['earnMonthCheck'] = $earncryptoEnableOffer->lockingPeriodMonths;  
			$result['earnPercentCheck'] = $percentagesArr[0];

		    }

	    }




	$result['walletBlance'] = DB::table('dbt_balance')->where('user_id',$user_id)->where('currency_symbol' , $crypto)->get();
	// earncrypto_lockperiod
	return response()->json(["Success"=>true,'status' => 200,'Result' => $result], 200);
    }
    catch(Exception $e)
    {
    return response()->json(["Success"=>false ,'status' => 800,'Result' => $result], 400);
    	
    }
  
  }

  public function earnCryptoRefreals(Request $request)


  {
  	try
  	{
  	$data = $request->user();
	$user_id = $data['user_id'];



	 $recrds =	DB::table('earncrypto_transaction')->select(DB::raw('DATE_FORMAT(earncrypto_transaction.join_date , "%d-%m-%Y") as join_date ,  users.email ,earncrypto_daily_payout.upliner_level , coin_listing.coin_image as crypto , earncrypto_transaction.package_amount_usd ,  earncrypto_transaction.package_amount_usd as commission ,  sum(earncrypto_daily_payout.payout_amount_usd) as payout_amt ,
		earncrypto_transaction.package_amount_crypto , earncrypto_transaction.locking_period_months ,   CONCAT( TRUNCATE((DATEDIFF(earncrypto_transaction.locking_period_date , CURDATE())/30) , 0) , " months " , (DATEDIFF(earncrypto_transaction.locking_period_date , CURDATE())%30) , " days" ) as remainingDays , DATE_FORMAT(earncrypto_transaction.locking_period_date , "%d-%m-%Y") as locking_period_date , earncrypto_lockperiod.upliner_level1_percent ,earncrypto_lockperiod.upliner_level2_percent ,earncrypto_lockperiod.upliner_level3_percent ' ))->leftjoin('earncrypto_daily_payout' , 'earncrypto_transaction.id' , '=' ,
		'earncrypto_daily_payout.earncrypto_transaction_id')->join('users' , 'users.user_id' , '=' , 'earncrypto_transaction.user_id' )->leftjoin('coin_listing' , 'coin_listing.coin_symbol' , '=' , 'earncrypto_transaction.package_crypto_type' )->leftjoin('earncrypto_lockperiod' , 'earncrypto_lockperiod.id' , '=' , 'earncrypto_transaction.locking_period_id' )->where('earncrypto_daily_payout.upliner_user_id' , $user_id)->whereRaw('earncrypto_daily_payout.upliner_level > 0')->groupby('earncrypto_daily_payout.earncrypto_transaction_id')->get()->toArray();


   foreach ($recrds as $key => $value) {


           if(strtotime('17-11-2020') <= strtotime($value->join_date) &&  strtotime('31-12-2020') >= strtotime($value->join_date) && $value->locking_period_months >= 6)
  		    {
 
  			
				if ($value->upliner_level == 1) {
				 $recrds[$key]->commission = ($value->commission * 2)/100;
				 // $recrds[$key]->commission = ($value->commission * 2)/100;


				}
				else if ($value->upliner_level == 2) {
				$recrds[$key]->commission = ($value->commission * 0.7)/100;
				}
				else if ($value->upliner_level == 3) {
				$recrds[$key]->commission = ($value->commission * 0.3)/100;
				}



  		    }
  		    else 
  		    {

				if ($value->upliner_level == 1) {
				$recrds[$key]->commission = ($value->commission * $value->upliner_level1_percent)/100;
				}
				else if ($value->upliner_level == 2) {
				$recrds[$key]->commission = ($value->commission * $value->upliner_level2_percent)/100;
				}
				else if ($value->upliner_level == 3) {
				$recrds[$key]->commission = ($value->commission * $value->upliner_level3_percent)/100;
				}
  		    }
  		   

   

   }
	$result['earnCryptoRefreals_records'] =  $recrds;


	 return response()->json(["Success"=>true,'status' => 200,'Result' => $result], 200);
	}
	catch(Exception $e)
	{
	 return response()->json(["Success"=>false ,'status' => 800,'Result' => $result], 400);
	}




  }

}