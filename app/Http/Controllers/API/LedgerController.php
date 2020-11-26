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
use App\SelfTransactionsHistory;
use App\InternalTransactionHistory;
use App\KycVerification;
use App\AddressBook;
use App\UserAddresses;
use App\Http\Controllers\API\DepositController;
use App\Library\NodeApiCalls;
class LedgerController extends BaseController 
{
	public static function existingMainWalletTransactions(){
		Log::info("existingMainWalletTransactions: start ");
		 $userscount = User::select('user_id')
        ->where('is_user_blocked' , '=' ,0)
        ->where('status' , '=' , 'A')
        //->where('user_id' , '=' , '118')
        ->get();
        $count = count($userscount);
        Log::info("existingMainWalletTransactions ".$count);
        
        $batchSize = 100;
        for($start=0; $start <= $count; $start+=$batchSize) {
        	 $user_res = User::select('user_id')
	        ->where('is_user_blocked' , '=' ,0)
	        ->where('status' , '=' , 'A')
	        //->where('user_id' , '=' , '118')
	        ->orderBy('user_id','ASC')
	        ->skip($start)->take($batchSize)->get();

	        foreach ($user_res as $key => $value) {
	        	$login_user_id = $value->user_id;
	        	Log::info("existingMainWalletTransactions user_id".$login_user_id);

	        	$userData = Userinfo::select("*")->where("user_id","=",$login_user_id)->first();
	        if($userData){
				if($userData->main_wallet_ledger_update == 0){
					$arr = array(
			            'main_wallet_ledger_update' => 1
			        );
			        Userinfo::where('user_id',$login_user_id)->update($arr);

				$res = DB::select("SELECT id AS base_id,'empty' AS reward_id, user_id,user_id AS received_user_id,user_id AS sender_user_id,crypto_symbol,internal_value as amount, 'CREDIT' AS type,'Deposit' AS transaction_type, created_at AS transaction_date,transaction_from  FROM `deposit_transaction` WHERE `user_id` LIKE '$login_user_id' AND status=1
					UNION ALL 
					SELECT rec_id AS base_id,'empty' AS reward_id, user_id,user_id AS received_user_id,'0' AS sender_user_id,bonus_wallet as crypto_symbol,bonus as amount,'CREDIT' AS type,'Deposit Bonus' AS transaction_type, created_at AS transaction_date,'empty' AS transaction_from  FROM `deposit_bonus_transactions` WHERE `user_id` LIKE '$login_user_id' AND type='OUT' 
					UNION ALL 
					SELECT id AS base_id,'empty' AS reward_id, user_id,'0' AS received_user_id,user_id AS sender_user_id,currency_symbol as crypto_symbol,amount,'DEBIT' AS type,'Withdraw' AS transaction_type,created_at AS transaction_date,'empty' AS transaction_from  FROM `dbt_withdraw` WHERE `user_id` LIKE '$login_user_id'  
					UNION ALL 
					SELECT id AS base_id,'empty' AS reward_id, user_id,received_user_id,user_id AS sender_user_id,wallet_symbol as crypto_symbol,amount,'DEBIT' AS type,'Transfer Internally' AS transaction_type, created_date AS transaction_date,'empty' AS transaction_from  FROM `internal_transaction_history` WHERE `user_id` LIKE '$login_user_id' OR received_user_id='$login_user_id'
					UNION ALL 
					SELECT id AS base_id,'empty' AS reward_id, user_id,user_id AS received_user_id,user_id AS sender_user_id,wallet_symbol as crypto_symbol,amount,transaction_type AS type,'Self transactions' AS transaction_type,created_at AS transaction_date,'empty' AS transaction_from  FROM `self_transactions_history` WHERE `user_id` = '$login_user_id' 
					UNION ALL 
					SELECT rec_id AS base_id,'empty' AS reward_id, user_id,user_id AS received_user_id,'0' AS sender_user_id,'USDT' as crypto_symbol,commission_amount as amount,'CREDIT' AS type,'Commission Payout' AS transaction_type,payout_date AS transaction_date,'empty' AS transaction_from  FROM `commission_payout` WHERE `user_id` = '$login_user_id' 
					UNION ALL 
					SELECT id AS base_id,'empty' AS reward_id, user_id,user_id AS received_user_id,subscribed_by_user_id AS sender_user_id,package_crypto_type as crypto_symbol,package_amount_crypto as amount,'DEBIT' AS type,'Earncrypto transactions' AS transaction_type,created_at AS transaction_date,'empty' AS transaction_from  FROM `earncrypto_transaction` WHERE `user_id` LIKE '$login_user_id' OR subscribed_by_user_id = '$login_user_id'
					UNION ALL 
					SELECT id AS base_id,'empty' AS reward_id, upliner_user_id as user_id,upliner_user_id AS received_user_id,'0' AS sender_user_id,payout_crypto_type as crypto_symbol,payout_amount_crypto as amount,'CREDIT' AS type,'Earncrypto Payout' AS transaction_type,created_at AS transaction_date,'empty' AS transaction_from  FROM `earncrypto_daily_payout` WHERE `upliner_user_id` = '$login_user_id' 
					UNION ALL 
					SELECT id AS base_id,'empty' AS reward_id, user_id,'0' AS received_user_id,user_id AS sender_user_id,crypto_symbol ,crypto_amount ,'DEBIT' AS type,'Brexco transactions' AS transaction_type,created_at AS transaction_date,'empty' AS transaction_from  FROM `brexco_transactions` WHERE `user_id` = '$login_user_id' 
					UNION ALL 
					SELECT rec_id AS base_id,reward_id, user_id,user_id AS received_user_id,'0' AS sender_user_id,currency_symbol as crypto_symbol,used as amount,'CREDIT' AS type,'Reward Incentives' AS transaction_type,created_at AS transaction_date,'empty' AS transaction_from  FROM `reward_incentives` WHERE `user_id` = '$login_user_id' 
					UNION ALL 
					SELECT id AS base_id,'empty' AS reward_id, user_id,user_id AS received_user_id,'0' AS sender_user_id,currency_symbol as crypto_symbol,credit_crypto as amount,'CREDIT' AS type,'Withdraw Reject' AS transaction_type,created_date AS transaction_date,'empty' AS transaction_from FROM `withdraw_transaction_history` WHERE `user_id` LIKE '$login_user_id' AND credit_crypto != 0 
					order by transaction_date DESC");
					
					Log::info("existingMainWalletTransactions result_user_id".$login_user_id);
					
					$bal_arr = array();
					foreach ($res as $key => $value) {
						$base_id = $value->base_id;
						$user_id = $value->user_id;
						$sender_user_id = $value->sender_user_id;
						$receiver_user_id = $value->received_user_id;
						$crypto_symbol = $value->crypto_symbol;
						$amount = $value->amount;
						$type = $value->type;
						$transaction_type = $value->transaction_type;
						$transaction_date = $value->transaction_date;
						
						if($transaction_type == "Deposit"){
							if($value->transaction_from == 'admin'){
								$description = "Deposit of ".$amount." ".$crypto_symbol." to Main Account from admin";
							}else{
								$description = "Deposit of ".$amount." ".$crypto_symbol." to Main Account from others";
							}	
							$transaction_id="DP".rand(100,10000).time();			
						}
						else if($transaction_type == "Deposit Bonus"){
							$description = "Deposit Bonus of ".$amount." ".$crypto_symbol." to Main Account";
							$transaction_id="DPB".rand(100,10000).time();
						}
						else if($transaction_type == "Withdraw"){
							$description = "Withdraw ".$amount." ".$crypto_symbol." from Main Account";
							$transaction_id="WR".rand(100,10000).time();
						}
						else if($transaction_type == "Transfer Internally"){
							if($value->received_user_id == $login_user_id){
								$userData = Userinfo::select("*")->where("user_id","=",$value->user_id)->first();
								$username = ($userData->first_name == "" ? $userData->email : $userData->first_name." ".$userData->last_name);
								$description = "Transfer Internally ".$amount." ".$crypto_symbol." from ".$username." on wallet Main Account";
								$type = 'CREDIT';
								$user_id = $value->received_user_id;
							}else if($value->user_id == $login_user_id){
								$userData = Userinfo::select("*")->where("user_id","=",$value->received_user_id)->first();
								$username = ($userData->first_name == "" ? $userData->email : $userData->first_name." ".$userData->last_name);
								$description = "Transfer Internally ".$amount." ".$crypto_symbol." to ".$username." on wallet Main Account";
								$type = 'DEBIT';
								$user_id = $value->user_id;
							}
							$transaction_id="TI".rand(100,10000).time();
						}
						else if($transaction_type == "Self transactions"){
							if($type == 'MAIN_TO_TRADING'){
								$type = 'DEBIT';
								$description = "Self transactions ".$amount." ".$crypto_symbol." to Trade Account";
								$transaction_type = 'MAIN_TO_TRADING';
							}else{
								$type = 'CREDIT';
								$description = "Self transactions ".$amount." ".$crypto_symbol." from Trade Account";
								$transaction_type = 'TRADING_TO_MAIN';
							}
							$transaction_id="SLF".rand(100,10000).time();
						}
						else if($transaction_type == "Commission Payout"){
							$transaction_type = "Commission Payout On Trading Fee";
							$description = "Commission Payout ".$amount." ".$crypto_symbol." On Trading Fee to Main Account";
							$transaction_id="CP".rand(100,10000).time();
						}
						else if($transaction_type == "Earncrypto transactions"){
							if($value->sender_user_id == $login_user_id){
								$userData = Userinfo::select("*")->where("user_id","=",$value->user_id)->first();
								$username = ($userData->first_name == "" ? $userData->email : $userData->first_name." ".$userData->last_name);
								$description = "Earncrypto package purchased with ".$amount." ".$crypto_symbol." for ".$username." on wallet Main Account";
								$user_id = $value->user_id;
								$sender_user_id = $value->sender_user_id;
								$receiver_user_id = $value->user_id;
							}else{
								$description = "Earncrypto package purchased with ".$amount." ".$crypto_symbol." on wallet Main Account";
								$user_id = $value->user_id;
								$sender_user_id = $value->user_id;
								$receiver_user_id = $value->user_id;
							}	
							$transaction_id="ECR".rand(100,10000).time();				
						}
						else if($transaction_type == "Earncrypto Payout"){
							$description = "Earncrypto Payout ".$amount." ".$crypto_symbol." on wallet Main Account";
							$transaction_id="ECP".rand(100,10000).time();
						}
						else if($transaction_type == "Brexco transactions"){
							$description = "Brexco transactions ".$amount." ".$crypto_symbol." on wallet Main Account";
							$transaction_id="BX".rand(100,10000).time();
						}
						else if($transaction_type == "Reward Incentives"){
							if($value->reward_id == 1){
								$description = "Reward Incentives (Airdrops) ".$amount." ".$crypto_symbol." transfer to Main Account";
							}else if($value->reward_id == 2){
								$description = "Reward Incentives (Welcome Bonus) ".$amount." ".$crypto_symbol." transfer to Main Account";
							}else if($value->reward_id == 3){
								$description = "Reward Incentives (Deposit Bonus) ".$amount." ".$crypto_symbol." transfer to Main Account";
							}
							
							$transaction_id="RI".rand(100,10000).time();
						}
						else if($transaction_type == "Withdraw Reject"){
							$description = "Withdraw Reject ".$amount." ".$crypto_symbol." on wallet Main Account";
							$transaction_id="WRR".rand(100,10000).time();
						}

						$balanceFrom = Balance::where('user_id', $user_id)->where('currency_symbol', $crypto_symbol)->first();

						if(!empty($bal_arr[$crypto_symbol])){
							if($bal_arr[$crypto_symbol.'_prevtype'] == 'CREDIT'){
								$bal_arr[$crypto_symbol] = floatval($bal_arr[$crypto_symbol]) - floatval($bal_arr[$crypto_symbol.'_prevamt']);
								if($bal_arr[$crypto_symbol] < 0){
									$bal_arr[$crypto_symbol] = 0;
								}
							}else{
								$bal_arr[$crypto_symbol] = floatval($bal_arr[$crypto_symbol]) + floatval($bal_arr[$crypto_symbol.'_prevamt']);
							}

						}else{
							if($balanceFrom){
								$bal_arr[$crypto_symbol] = $balanceFrom->main_balance;
							}else{
								$bal_arr[$crypto_symbol] = 0;
							}
							
						}

						$bal_arr[$crypto_symbol.'_prevamt'] = $amount;
						$bal_arr[$crypto_symbol.'_prevtype'] = $type;
						
						try{
							if($amount > 0){
								$bal = self::checkBalance($user_id,$crypto_symbol,$type,$amount);
								$insertArr = array(
									'transaction_id' => $transaction_id, 
									'user_id' => $user_id, 
									'sender_id' => $sender_user_id, 
									'receiver_id' => $receiver_user_id, 
									'transaction_date' => $transaction_date, 
									'currency_symbol' => $crypto_symbol, 
									'type' => $type, 
									'amount' => $amount, 
									'balance' => $bal_arr[$crypto_symbol], 
									'transaction_type' => $transaction_type, 
									'description' => $description,
									'base_id' => $base_id,
									'created_at' => $transaction_date
								);
								$res = DB::table('main_wallet_ledger')->insert($insertArr);
							}					
						}catch (\Exception $e) {
							Log::info("existingMainWalletTransactions ".json_encode($e));
						}				
					}
				}
			}
	        }

	    }
	    Log::info("existingMainWalletTransactions: end ");

	}
	public static function checkBalance($user_id,$crypto_symbol,$type,$amount){

		$balanceFrom = Balance::where('user_id', $user_id)->where('currency_symbol', $crypto_symbol)->first();
		
		if($type == 'CREDIT'){
			$balance = $amount+$balanceFrom->main_balance;
		}else{
			$balance = $amount-$balanceFrom->main_balance;
		}
		return $balance;

	}

}