<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
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
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Biding;
use App\FeeDeductions;
use App\Referrals;
use App\ReferralBonus;
use App\KycVerification;

class CommissionController extends Controller {

        public static function commissionPayout(){

                $yesterday = Carbon::yesterday()->format('Y-m-d');//commission pay for
                $res = DB::table('cron_report')->where('cron_date',$yesterday)->where('cron_name','Trade Commission')->first();
                if($res){
                        Log::info("Trade Commission cron already executed for ".$yesterday);
                }else{
                        $insertObj = array(
                                                "cron_name"=>'Trade Commission',
                                                "cron_date"=>$yesterday,
                                                "started_date"=>date('Y-m-d H:i:s'),
                                        );

                        $cron_id = DB::table('cron_report')->insertGetId($insertObj); // save cron executed for

                        Log::info("startComm: CommissionController ");
                        $fd = FeeDeductions::select('user_id')->where('status',1)->whereRaw('DATE(order_completed_at) = "'.$yesterday.'"')->groupBy('user_id')->get();//yesterday date records
                        
                        $up_arr = array();
                        foreach ($fd as $key => $value) {
                           $user_id = $value->user_id;
                           
                           $upliners = Referrals::select('ancestor_id')->where('descendant_id',$user_id)->where('distance','<=',9)->get();
                           foreach ($upliners as $key => $val) {
                               array_push($up_arr, $val->ancestor_id);
                           }                    
                        }
                        
                        $pay_user_id = array_unique($up_arr);
                        $count = count($pay_user_id);
                        foreach ($pay_user_id as $key => $value) {
                            $user_id = $value;
                            $userData = DB::select("select role from users where user_id='".$user_id."' AND status='A' and is_user_blocked=0");
                            if($userData){
                               $role = $userData[0]->role; 
                               $bal_res = DB::select("select COALESCE ((select MIN(sh.balance_snapshot) from dbt_balance_short_history sh where db.user_id=sh.user_id and DATE(sh.transaction_date)='".$yesterday."'), db.main_balance) as balance from dbt_balance db WHERE db.user_id='".$user_id."' AND db.currency_symbol='EVR' LIMIT 1");
                               if($bal_res){
                                    $balance = $bal_res[0]->balance;
                                    Log::info("Balance: CommissionController user_id ".$user_id." role ".$role." bal ".$balance);
                                    $cm_set = DB::table('commission_settings')->where('user_role',$role)->where('status',1)->get();
                                    foreach ($cm_set as $key => $sett) {
                                            if($balance >= $sett->level_entitlements){
                                                    self::payoutByLevels($user_id, $sett,$yesterday);
                                            }
                                    }

                               }
                                
                            }
                            
                        }

                        $updata = array(
                                            "end_date"=>date('Y-m-d H:i:s'),
                                    );
                        DB::table('cron_report')->where('rec_id', $cron_id)->update($updata); // save cron end time
                        Log::info("end: CommissionController ");
                }

        }

        public static function payoutByLevels($user_id, $cm_set,$yesterday){

                        //$yesterday = Carbon::yesterday()->format('Y-m-d');
                        $level = $cm_set->level;
                        $commission_percent = $cm_set->commission_percent;
                        $level_entitlements = $cm_set->level_entitlements;
                        $downliners = Referrals::select('*')->where('ancestor_id',$user_id)->where('distance',$level)->get();

                        if($downliners){
                                foreach ($downliners as $key => $value) {
                                        $descendant_id = $value->descendant_id;//downliner user id
                                        $fd = FeeDeductions::select('currency',DB::raw('SUM(fee_in_usd) as fee_in_usd'))->where('user_id',$descendant_id)->where('status',1)->whereRaw('DATE(order_completed_at) = "'.$yesterday.'"')->get();//yesterday date records

                                        $fee_in_usd = $fd[0]->fee_in_usd;
                                        if($fee_in_usd > 0){
                                                $comm_amt = $fee_in_usd*$commission_percent/100;
                                                $usd_price = CoinListing::select('coin_price')->where('coin_symbol','USDT')->first();
                        $comm_in_usd = $comm_amt*$usd_price->coin_price;

                                                $payout_date  = date('Y-m-d H:i:s');
                                                $insertObj = array(
                                                                                        "user_id"=>$user_id,
                                                                                        "from_user_id"=>$descendant_id,
                                                                                        "commission_amount"=>$comm_in_usd,
                                                                                        "commission_perc"=>$commission_percent,
                                                                                        "payout_date"=>$payout_date,
                                                                                        "commission_for"=>$yesterday,
                                                                                        "level"=>$level
                                                                                );

                                                $res = DB::table('commission_payout')->insert($insertObj);
                                                Balance::where('user_id', $user_id)->where('currency_symbol','USDT')->increment('main_balance', $comm_in_usd);
                                                //echo $user_id." -- ".$comm_in_usd." paid <br/>";
                                                Log::info("user: payoutByLevels user_id ".$user_id." comm_in_usd ".$comm_in_usd);
                                        }
                                }
                        }
        }

        //removed balance short history talbe
        public static function clearBalanceShortHistory(){

                $dt = Carbon::now()->subDays(3);
                $res = DB::table('dbt_balance_short_history')->where('transaction_date', '<', $dt)->delete();

        }

        //Airdrops or bonus transfer to Rewards_incentives table for before september

        public static function transferRewards(){
                Log::info("NewTransferRewards1: transferRewards ");
                // $userscount = ReferralBonus::select('referral_bonus.user_id')->whereRaw('DATE(created_at) < "2020-10-01"')
                // ->where('user_id' , '>' ,10450)->groupBy('referral_bonus.user_id')->get();

        $userscount = ReferralBonus::select('referral_bonus.user_id')
        ->leftjoin('users as u', function($join){
                    $join->on('u.user_id','=','referral_bonus.user_id');
                })
        ->where('u.is_user_blocked' , '=' ,0)
        ->where('referral_bonus.user_id' , '>' ,156082)
        ->where('referral_bonus.user_id' , '<=' ,189873)
        ->whereRaw('DATE(referral_bonus.created_at) < "2020-10-01"')
                ->groupBy('referral_bonus.user_id')->get();

                // $userscount = ReferralBonus::select('referral_bonus.user_id',DB::raw('SUM(referral_bonus.evr) as evr'),'b.main_balance')
                // ->leftJoin('dbt_balance as b', 'b.user_id', '=', 'referral_bonus.user_id')->where('b.currency_symbol', '=', 'EVR')->groupBy('referral_bonus.user_id')->get();
                $count = count($userscount);
                Log::info("NewTransferRewards2: transferRewards ".$count);

                $batchSize = 200;
                for($start=0; $start <= $count; $start+=$batchSize) {
                //      $res = ReferralBonus::select('referral_bonus.user_id',DB::raw('SUM(referral_bonus.evr) as evr'),'b.main_balance')
                // ->leftjoin('dbt_balance as b', function($join){
  //                   $join->on('b.user_id','=','referral_bonus.user_id')
  //                       ->where('b.currency_symbol' , '=' , 'EVR');
  //               })
                // ->groupBy('referral_bonus.user_id')->skip($start)->take($batchSize)->get();
                        $res = ReferralBonus::select('referral_bonus.user_id',DB::raw('SUM(referral_bonus.evr) as evr'))
        ->leftjoin('users as u', function($join){
                    $join->on('u.user_id','=','referral_bonus.user_id');
                })
        ->where('u.is_user_blocked' , '=' ,0)
        ->where('referral_bonus.user_id' , '>' ,156082)
        ->where('referral_bonus.user_id' , '<=' ,189873)
        ->whereRaw('DATE(referral_bonus.created_at) < "2020-10-01"')
                ->groupBy('referral_bonus.user_id')->skip($start)->take($batchSize)->get();

                //      $res = ReferralBonus::select('referral_bonus.user_id',DB::raw('SUM(referral_bonus.evr) as evr'))
                //      ->whereRaw('DATE(created_at) < "2020-10-01"')
                // ->where('user_id' , '>' ,10450)
                // ->groupBy('referral_bonus.user_id')->skip($start)->take($batchSize)->get();

                foreach ($res as $key => $value) {
                        $user_id = $value->user_id;
                        $rewards_in_evr = $value->evr;
                        Log::info("NewTransferRewards3: transferRewards user_id ".$value->user_id);
                        $bal_res = Balance::where('user_id', $user_id)->where('currency_symbol','EVR')->first();
                        //echo $value->user_id." user ".$value->evr." Bonus ".$value->main_balance." main_balance <br/>";

                        $main_balance = $bal_res->main_balance; //EVR main wallet balance
                        if($main_balance >=  $rewards_in_evr){
                                $sufficient_evr = 1;
                                $used = 0;
                        }else{
                                $sufficient_evr = 0;
                                $used = $rewards_in_evr-$main_balance;
                        }

                        $balance = $rewards_in_evr-$used;
                        $insertObj = array(
                                                                "user_id"=>$user_id,
                                                                "reward_id"=>1,
                                                                "currency_symbol"=>'EVR',
                                                                "total_qty"=>$rewards_in_evr,
                                                                "used"=>$used,
                                                                "balance"=>$balance,
                                                                "sufficient_evr"=>$sufficient_evr,
                                                        );

                        $cron_id = DB::table('reward_incentives')->insertGetId($insertObj);

                        $insertObj1 = array(
                                                                "user_id"=>$user_id,
                                                                "reward_id"=>1,
                                                                "currency_symbol"=>'EVR',
                                                                "total_qty"=>$rewards_in_evr,
                                                                "used"=>$used,
                                                                "balance"=>$balance,
                                                                "sufficient_evr"=>$sufficient_evr,
                                                                "job_name"=>'Airdrops_Before_Sept1',
                                                        );

                        $cron_id = DB::table('reward_incentives_audit')->insertGetId($insertObj1);

                        Balance::where('user_id', $user_id)->where('currency_symbol','EVR')->decrement('main_balance', $balance);

                        //If KYC completed transfer rewards to main wallet account
                        if($sufficient_evr == 1){

                                $downliners = ReferralBonus::select('*')->where('user_id',$user_id)->get();
                                if($downliners){
                                        foreach ($downliners as $key => $value) {
                                                $descendant_id = $value->from_id;//downliner user id

                                                $bonus = $value->evr;
                                                $userkyc=KycVerification::where('user_id',$descendant_id)->first();
                                                if($userkyc!==null){
                                                        if($userkyc['status'] == 'approve'){
                                                                //echo $descendant_id." from user_id ".$bonus." bonus kyc approve <br/>";

                                                                //balance check condition
                                                                $res = DB::table('reward_incentives')->select('*')->where('user_id',$user_id)->where('reward_id',1)->first();
                                                                if($res){
                                                                        if($res->balance !=0 && $res->balance >= $bonus){
                                                                                Log::info("users: transferRewards user_id ".$user_id." bonus ".$bonus);
                                                                        $query = DB::table('reward_incentives')
                                                                                  ->where('user_id',$user_id)->where('reward_id',1);
                                                                                $query->increment('used',$bonus);
                                                                                $query->decrement('balance',$bonus);

                                                                                Balance::where('user_id', $user_id)->where('currency_symbol','EVR')->increment('main_balance', $bonus);
                                                                        }
                                                                }
                                                        }
                                                }
                                        }
                                }
                        }
                }
                Log::info("Airdrops_Before_Sept1 Batch Size ".$start);
                }
                Log::info("end: transferRewards ");
        }

        public static function transferRewardsTwo(){
                Log::info("transferRewardsTwo: transferRewards ");
                // $userscount = ReferralBonus::select('referral_bonus.user_id')->whereRaw('DATE(created_at) < "2020-10-01"')
                // ->where('user_id' , '>' ,10450)->groupBy('referral_bonus.user_id')->get();

        $userscount = ReferralBonus::select('referral_bonus.user_id')
        ->leftjoin('users as u', function($join){
                    $join->on('u.user_id','=','referral_bonus.user_id');
                })
        ->where('u.is_user_blocked' , '=' ,0)
        ->where('referral_bonus.user_id' , '>' ,189873)
        ->where('referral_bonus.user_id' , '<=' ,223665)
        ->whereRaw('DATE(referral_bonus.created_at) < "2020-10-01"')
                ->groupBy('referral_bonus.user_id')->get();

                // $userscount = ReferralBonus::select('referral_bonus.user_id',DB::raw('SUM(referral_bonus.evr) as evr'),'b.main_balance')
                // ->leftJoin('dbt_balance as b', 'b.user_id', '=', 'referral_bonus.user_id')->where('b.currency_symbol', '=', 'EVR')->groupBy('referral_bonus.user_id')->get();
                $count = count($userscount);
                Log::info("transferRewardsTwo2: transferRewards ".$count);

                $batchSize = 200;
                for($start=0; $start <= $count; $start+=$batchSize) {
                //      $res = ReferralBonus::select('referral_bonus.user_id',DB::raw('SUM(referral_bonus.evr) as evr'),'b.main_balance')
                // ->leftjoin('dbt_balance as b', function($join){
  //                   $join->on('b.user_id','=','referral_bonus.user_id')
  //                       ->where('b.currency_symbol' , '=' , 'EVR');
  //               })
                // ->groupBy('referral_bonus.user_id')->skip($start)->take($batchSize)->get();
                        $res = ReferralBonus::select('referral_bonus.user_id',DB::raw('SUM(referral_bonus.evr) as evr'))
        ->leftjoin('users as u', function($join){
                    $join->on('u.user_id','=','referral_bonus.user_id');
                })
        ->where('u.is_user_blocked' , '=' ,0)
        ->where('referral_bonus.user_id' , '>' ,189873)
        ->where('referral_bonus.user_id' , '<=' ,223665)
        ->whereRaw('DATE(referral_bonus.created_at) < "2020-10-01"')
                ->groupBy('referral_bonus.user_id')->skip($start)->take($batchSize)->get();

                //      $res = ReferralBonus::select('referral_bonus.user_id',DB::raw('SUM(referral_bonus.evr) as evr'))
                //      ->whereRaw('DATE(created_at) < "2020-10-01"')
                // ->where('user_id' , '>' ,10450)
                // ->groupBy('referral_bonus.user_id')->skip($start)->take($batchSize)->get();

                foreach ($res as $key => $value) {
                        $user_id = $value->user_id;
                        $rewards_in_evr = $value->evr;
                        Log::info("transferRewardsTwo3: transferRewards user_id ".$value->user_id);
                        $bal_res = Balance::where('user_id', $user_id)->where('currency_symbol','EVR')->first();
                        //echo $value->user_id." user ".$value->evr." Bonus ".$value->main_balance." main_balance <br/>";

                        $main_balance = $bal_res->main_balance; //EVR main wallet balance
                        if($main_balance >=  $rewards_in_evr){
                                $sufficient_evr = 1;
                                $used = 0;
                        }else{
                                $sufficient_evr = 0;
                                $used = $rewards_in_evr-$main_balance;
                        }

                        $balance = $rewards_in_evr-$used;
                        $insertObj = array(
                                                                "user_id"=>$user_id,
                                                                "reward_id"=>1,
                                                                "currency_symbol"=>'EVR',
                                                                "total_qty"=>$rewards_in_evr,
                                                                "used"=>$used,
                                                                "balance"=>$balance,
                                                                "sufficient_evr"=>$sufficient_evr,
                                                        );

                        $cron_id = DB::table('reward_incentives')->insertGetId($insertObj);

                        $insertObj1 = array(
                                                                "user_id"=>$user_id,
                                                                "reward_id"=>1,
                                                                "currency_symbol"=>'EVR',
                                                                "total_qty"=>$rewards_in_evr,
                                                                "used"=>$used,
                                                                "balance"=>$balance,
                                                                "sufficient_evr"=>$sufficient_evr,
                                                                "job_name"=>'Airdrops_Before_Sept1',
                                                        );

                        $cron_id = DB::table('reward_incentives_audit')->insertGetId($insertObj1);

                        Balance::where('user_id', $user_id)->where('currency_symbol','EVR')->decrement('main_balance', $balance);

                        //If KYC completed transfer rewards to main wallet account
                        if($sufficient_evr == 1){

                                $downliners = ReferralBonus::select('*')->where('user_id',$user_id)->get();
                                if($downliners){
                                        foreach ($downliners as $key => $value) {
                                                $descendant_id = $value->from_id;//downliner user id

                                                $bonus = $value->evr;
                                                $userkyc=KycVerification::where('user_id',$descendant_id)->first();
                                                if($userkyc!==null){
                                                        if($userkyc['status'] == 'approve'){
                                                                //echo $descendant_id." from user_id ".$bonus." bonus kyc approve <br/>";

                                                                //balance check condition
                                                                $res = DB::table('reward_incentives')->select('*')->where('user_id',$user_id)->where('reward_id',1)->first();
                                                                if($res){
                                                                        if($res->balance !=0 && $res->balance >= $bonus){
                                                                                Log::info("users: transferRewards user_id ".$user_id." bonus ".$bonus);
                                                                        $query = DB::table('reward_incentives')
                                                                                  ->where('user_id',$user_id)->where('reward_id',1);
                                                                                $query->increment('used',$bonus);
                                                                                $query->decrement('balance',$bonus);

                                                                                Balance::where('user_id', $user_id)->where('currency_symbol','EVR')->increment('main_balance', $bonus);
                                                                        }
                                                                }
                                                        }
                                                }
                                        }
                                }
                        }
                }
                Log::info("Airdrops_Before_Sept1 Batch Size ".$start);
                }
                Log::info("end: transferRewards ");
        }

        public static function transferRewardsThree(){
                Log::info("transferRewardsThree1: transferRewards ");
                // $userscount = ReferralBonus::select('referral_bonus.user_id')->whereRaw('DATE(created_at) < "2020-10-01"')
                // ->where('user_id' , '>' ,10450)->groupBy('referral_bonus.user_id')->get();

        $userscount = ReferralBonus::select('referral_bonus.user_id')
        ->leftjoin('users as u', function($join){
                    $join->on('u.user_id','=','referral_bonus.user_id');
                })
        ->where('u.is_user_blocked' , '=' ,0)
        ->where('referral_bonus.user_id' , '>' ,223665)
        ->where('referral_bonus.user_id' , '<=' ,257456)
        ->whereRaw('DATE(referral_bonus.created_at) < "2020-10-01"')
                ->groupBy('referral_bonus.user_id')->get();

                // $userscount = ReferralBonus::select('referral_bonus.user_id',DB::raw('SUM(referral_bonus.evr) as evr'),'b.main_balance')
                // ->leftJoin('dbt_balance as b', 'b.user_id', '=', 'referral_bonus.user_id')->where('b.currency_symbol', '=', 'EVR')->groupBy('referral_bonus.user_id')->get();
                $count = count($userscount);
                Log::info("transferRewardsThree2: transferRewards ".$count);

                $batchSize = 200;
                for($start=0; $start <= $count; $start+=$batchSize) {
                //      $res = ReferralBonus::select('referral_bonus.user_id',DB::raw('SUM(referral_bonus.evr) as evr'),'b.main_balance')
                // ->leftjoin('dbt_balance as b', function($join){
  //                   $join->on('b.user_id','=','referral_bonus.user_id')
  //                       ->where('b.currency_symbol' , '=' , 'EVR');
  //               })
                // ->groupBy('referral_bonus.user_id')->skip($start)->take($batchSize)->get();
                        $res = ReferralBonus::select('referral_bonus.user_id',DB::raw('SUM(referral_bonus.evr) as evr'))
        ->leftjoin('users as u', function($join){
                    $join->on('u.user_id','=','referral_bonus.user_id');
                })
        ->where('u.is_user_blocked' , '=' ,0)
        ->where('referral_bonus.user_id' , '>' ,223665)
        ->where('referral_bonus.user_id' , '<=' ,257456)
        ->whereRaw('DATE(referral_bonus.created_at) < "2020-10-01"')
                ->groupBy('referral_bonus.user_id')->skip($start)->take($batchSize)->get();

                //      $res = ReferralBonus::select('referral_bonus.user_id',DB::raw('SUM(referral_bonus.evr) as evr'))
                //      ->whereRaw('DATE(created_at) < "2020-10-01"')
                // ->where('user_id' , '>' ,10450)
                // ->groupBy('referral_bonus.user_id')->skip($start)->take($batchSize)->get();

                foreach ($res as $key => $value) {
                        $user_id = $value->user_id;
                        $rewards_in_evr = $value->evr;
                        Log::info("transferRewardsThree3: transferRewards user_id ".$value->user_id);
                        $bal_res = Balance::where('user_id', $user_id)->where('currency_symbol','EVR')->first();
                        //echo $value->user_id." user ".$value->evr." Bonus ".$value->main_balance." main_balance <br/>";

                        $main_balance = $bal_res->main_balance; //EVR main wallet balance
                        if($main_balance >=  $rewards_in_evr){
                                $sufficient_evr = 1;
                                $used = 0;
                        }else{
                                $sufficient_evr = 0;
                                $used = $rewards_in_evr-$main_balance;
                        }

                        $balance = $rewards_in_evr-$used;
                        $insertObj = array(
                                                                "user_id"=>$user_id,
                                                                "reward_id"=>1,
                                                                "currency_symbol"=>'EVR',
                                                                "total_qty"=>$rewards_in_evr,
                                                                "used"=>$used,
                                                                "balance"=>$balance,
                                                                "sufficient_evr"=>$sufficient_evr,
                                                        );

                        $cron_id = DB::table('reward_incentives')->insertGetId($insertObj);

                        $insertObj1 = array(
                                                                "user_id"=>$user_id,
                                                                "reward_id"=>1,
                                                                "currency_symbol"=>'EVR',
                                                                "total_qty"=>$rewards_in_evr,
                                                                "used"=>$used,
                                                                "balance"=>$balance,
                                                                "sufficient_evr"=>$sufficient_evr,
                                                                "job_name"=>'Airdrops_Before_Sept1',
                                                        );

                        $cron_id = DB::table('reward_incentives_audit')->insertGetId($insertObj1);

                        Balance::where('user_id', $user_id)->where('currency_symbol','EVR')->decrement('main_balance', $balance);

                        //If KYC completed transfer rewards to main wallet account
                        if($sufficient_evr == 1){

                                $downliners = ReferralBonus::select('*')->where('user_id',$user_id)->get();
                                if($downliners){
                                        foreach ($downliners as $key => $value) {
                                                $descendant_id = $value->from_id;//downliner user id

                                                $bonus = $value->evr;
                                                $userkyc=KycVerification::where('user_id',$descendant_id)->first();
                                                if($userkyc!==null){
                                                        if($userkyc['status'] == 'approve'){
                                                                //echo $descendant_id." from user_id ".$bonus." bonus kyc approve <br/>";

                                                                //balance check condition
                                                                $res = DB::table('reward_incentives')->select('*')->where('user_id',$user_id)->where('reward_id',1)->first();
                                                                if($res){
                                                                        if($res->balance !=0 && $res->balance >= $bonus){
                                                                                Log::info("users: transferRewards user_id ".$user_id." bonus ".$bonus);
                                                                        $query = DB::table('reward_incentives')
                                                                                  ->where('user_id',$user_id)->where('reward_id',1);
                                                                                $query->increment('used',$bonus);
                                                                                $query->decrement('balance',$bonus);

                                                                                Balance::where('user_id', $user_id)->where('currency_symbol','EVR')->increment('main_balance', $bonus);
                                                                        }
                                                                }
                                                        }
                                                }
                                        }
                                }
                        }
                }
                Log::info("Airdrops_Before_Sept1 Batch Size ".$start);
                }
                Log::info("end: transferRewards ");
        }

        //Airdrops and bonus transfer to Rewards_incentives table for september 1st to september 30th
        // user will get 60EVR + 3EVR (1st level)

        public static function transferAirdrops(){

                $userscount = DB::select("select u.user_id from users u where DATE(u.created_at) BETWEEN '2020-09-01' AND '2020-09-30'");
                        $count = count($userscount);
                        Log::info("start: transferAirdrops ".json_encode($userscount));
                        $batchSize = 5000;
                        for($start=0; $start <= $count; $start+=$batchSize) {

                                $userData = DB::select("select u.user_id from users u where DATE(u.created_at) BETWEEN '2020-09-01' AND '2020-09-30' LIMIT ".$start.",".$batchSize);
                                foreach ($userData as $key => $value) {
                                        $user_id = $value->user_id;
                                        //echo $user_id." userid <br/>";
                                        $res = Referrals::select('referrals.*')->leftjoin('users as u', 'u.user_id', '=', 'referrals.descendant_id')->where('referrals.ancestor_id',$user_id)->where('referrals.distance', 1)->whereRaw('DATE(u.created_at) < "2020-10-01"')->get();//users table join
                                        $ref_count = count($res);
                                        // echo $res."count <br/> ";
                                        // exit();

                                        $rewards_in_evr = 60+$ref_count*3;

                                        $insertObj = array(
                                                                "user_id"=>$user_id,
                                                                "reward_id"=>1,
                                                                "currency_symbol"=>'EVR',
                                                                "total_qty"=>$rewards_in_evr,
                                                                "used"=>0,
                                                                "balance"=>$rewards_in_evr,
                                                                "sufficient_evr"=>1,
                                                        );

                                        $cron_id = DB::table('reward_incentives')->insertGetId($insertObj);

                                        $insertObj1 = array(
                                                                                "user_id"=>$user_id,
                                                                                "reward_id"=>1,
                                                                                "currency_symbol"=>'EVR',
                                                                                "total_qty"=>$rewards_in_evr,
                                                                                "used"=>0,
                                                                                "balance"=>$rewards_in_evr,
                                                                                "sufficient_evr"=>1,
                                                                                "job_name"=>'Airdrops_Sept1_To_Sept30',
                                                                        );
                                        $cron_id = DB::table('reward_incentives_audit')->insertGetId($insertObj1);

                                        $downliners = Referrals::select('referrals.*')->leftjoin('users as u', 'u.user_id', '=', 'referrals.descendant_id')->where('referrals.ancestor_id',$user_id)->whereIn('referrals.distance', array(0,1))->whereRaw('DATE(u.created_at) < "2020-10-01"')->get();

                                        if($downliners){
                                                foreach ($downliners as $key => $value) {
                                                        $descendant_id = $value->descendant_id;//downliner user id
                                                        if($value->distance == 0){
                                                                $bonus = 60;
                                                        }else{
                                                                $bonus = 3;
                                                        }
                                                        $obj = array(
                                                                                "user_id"=>$user_id,
                                                                                "from_id"=>$descendant_id,
                                                                                "evr"=>$bonus,
                                                                                "usd"=>0
                                                                        );
                                                        DB::table('referral_bonus')->insert($obj);

                                                        $userkyc=KycVerification::where('user_id',$descendant_id)->first();
                                                        if($userkyc!==null){
                                                                if($userkyc['status'] == 'approve'){
                                                                        //echo $descendant_id." from user_id ".$bonus." bonus kyc approve <br/>";
                                                                        Log::info("users: transferAirdrops user_id ".$user_id." bonus ".$bonus);
                                                                $query = DB::table('reward_incentives')
                                                                          ->where('user_id',$user_id)->where('reward_id',1);
                                                                        $query->increment('used',floatval($bonus));
                                                                        $query->decrement('balance',floatval($bonus));

                                                                        $balance = Balance::where('user_id', $user_id)->where('currency_symbol', 'EVR')->first();
                                                                        if($balance===null){
                                                                                $b=array(
                                                                                        "user_id"=>$user_id,
                                                                                        "currency_symbol"=>'EVR',
                                                                                        "main_balance"=>$bonus
                                                                                );
                                                                                Balance::insert($b);
                                                                        }else{
                                                                                Balance::where('user_id', $user_id)->where('currency_symbol','EVR')->increment('main_balance', $bonus);
                                                                        }

                                                                }
                                                        }

                                                }
                                        }

                                }
                        }
                        Log::info("end: transferAirdrops ");
        }

//Rewards_incentive transfer to Main account, if user completed KYC
        public static function incentivesToMainAccountTransfer(){
                Log::info("startingg: incentivesToMainAccountTransfer ");
                $yesterday = Carbon::yesterday()->format('Y-m-d');

                $res = DB::table('cron_report')->where('cron_date',$yesterday)->where('cron_name','Transfer To Main Account')->first();
                if($res){
                        Log::info("Transfer To Main Account cron already executed for ".$yesterday);
                }else{
                        $insertObj = array(
                                                                "cron_name"=>'Transfer To Main Account',
                                                                "cron_date"=>$yesterday,
                                                                "started_date"=>date('Y-m-d H:i:s'),
                                                        );

                        $cron_id = DB::table('cron_report')->insertGetId($insertObj);

                        $res=KycVerification::leftjoin('users as u', 'u.user_id', '=', 'kyc_verification.user_id')->where('kyc_verification.status','approve')->whereRaw('DATE(kyc_verification.updated_at) = "'.$yesterday.'"')->whereRaw('DATE(u.created_at) < "2020-10-01"')->get();
                        $count = count($res);
                        Log::info("start: incentivesToMainAccountTransfer ".$count);
                        $batchSize = 5000;
                        for($start=0; $start <= $count; $start+=$batchSize) {

                                $userData=KycVerification::leftjoin('users as u', 'u.user_id', '=', 'kyc_verification.user_id')->where('kyc_verification.status','approve')->whereRaw('DATE(kyc_verification.updated_at) = "'.$yesterday.'"')->whereRaw('DATE(u.created_at) < "2020-10-01"')->skip($start)->take($batchSize)->get();
                                foreach ($userData as $key => $value) {
                                        $user_id = $value->user_id;
                                        //echo $user_id." userid <br/>";
                                        // < 2020-10-10 -- referral bonus
                                        $created_at = '2020-10-13';
                                        $upliners = ReferralBonus::select('*')->where('from_id',$user_id)
                                                                ->where(function ($query) use ($created_at) {
                                                               $query->whereRaw('DATE(created_at) < "'.$created_at.'"')
                                                                     ->orWhere('created_at', '=', NULL);
                                                           })->get();
                                        if($upliners){
                                                foreach ($upliners as $key => $value) {
                                                        $upliner_id = $value->user_id;//upliner user id

                                                        $bonus = $value->evr;
                                                        //echo $upliner_id." upliner_id ".$bonus." <br/>";
                                                        Log::info("user: incentivesToMainAccountTransfer from_id ".$user_id." upliner_id".$upliner_id);
                                                        $res = DB::table('reward_incentives')->select('*')->where('user_id',$upliner_id)->where('reward_id',1)->where('sufficient_evr',1)->first();
                                                        if($res){
                                                                if($res->balance !=0 && $res->balance >= $bonus){
                                                                        $query = DB::table('reward_incentives')
                                                                                  ->where('user_id',$upliner_id)->where('reward_id',1);
                                                                        $query->increment('used',floatval($bonus));
                                                                        $query->decrement('balance',floatval($bonus));

                                                                        $balance = Balance::where('user_id', $upliner_id)->where('currency_symbol', 'EVR')->first();
                                                                        if($balance===null){
                                                                                $b=array(
                                                                                        "user_id"=>$upliner_id,
                                                                                        "currency_symbol"=>'EVR',
                                                                                        "main_balance"=>$bonus
                                                                                );
                                                                                Balance::insert($b);
                                                                        }else{

                                                                        Balance::where('user_id', $upliner_id)->where('currency_symbol','EVR')->increment('main_balance', $bonus);
                                                                        }
                                                                }
                                                        }
                                                }
                                        }
                                        //new table for insufficient evr condition


                                }
                                Log::info("batch: incentivesToMainAccountTransfer ".$start);

                        }


                        $updata = array(
                                                                "end_date"=>date('Y-m-d H:i:s'),
                                                        );
                        DB::table('cron_report')->where('rec_id', $cron_id)->update($updata); // save cron end time
                        Log::info("end: incentivesToMainAccountTransfer ");
                }

        }

        public static function add_referal_bonus_to_bal(){

        $refbonus= ReferralBonus::select(DB::raw('SUM(evr) as evr'),'user_id')->groupBy('user_id')->get()->toArray();
        Log::info("start: ReferralBonusToMainAccount ");
        foreach ($refbonus as $rb) {
                Log::info("user_id: ReferralBonusToMainAccount user_id ".$rb['user_id']);
                $res= Balance::select('user_id')->where('user_id',$rb['user_id'])->where('currency_symbol','EVR')->first();
                if(empty($res)){
                        Log::info("users: ReferralBonusToMainAccount user_id ".$rb['user_id']);
                        //echo "user_id ".$rb['user_id']." <br/>";
                        $bdata=array(
                        "user_id"=>$rb['user_id'],
                        "currency_symbol"=>'EVR',
                        "main_balance"=>$rb['evr'],
                        "created_at"=>date("Y-m-d H:i:s")
                    );
                    Balance::insert($bdata);
                }

        }
        Log::info("end: ReferralBonusToMainAccount ");
    }

    public static function revertRewardIncentives(){
        Log::info("start: revertRewardIncentives ");
        $userscount = DB::select("select r.rec_id,r.user_id,r.total_qty,r.used,r.balance FROM reward_incentives r WHERE r.rec_id >= 7460 AND r.user_id BETWEEN 1 AND 5000 AND r.reward_id = 1 AND r.sufficient_evr=0");
        foreach ($userscount as $key => $value) {
                $user_id = $value->user_id;
                $rec_id = $value->rec_id;
                echo "second: revertRewardIncentives user_id ".$user_id." rec_id ".$rec_id;
                Log::info("second: revertRewardIncentives user_id ".$user_id." rec_id ".$rec_id);
                DB::table('reward_incentives')->where('rec_id', $rec_id)->delete();
                DB::table('reward_incentives_audit')->where('rec_id', $rec_id)->delete();

        }
        Log::info("end: revertRewardIncentives ");
    }

    public static function revertRewardIncentivesSufficient(){
        Log::info("start: revertRewardIncentivesSufficient ");
        $userscount = DB::select("select r.rec_id,r.user_id,r.total_qty,r.used,r.balance FROM reward_incentives r WHERE r.rec_id >= 7460 AND r.user_id BETWEEN 1 AND 5000 AND r.reward_id = 1 AND r.sufficient_evr=1 AND r.total_qty = r.balance");
        foreach ($userscount as $key => $value) {
                $user_id = $value->user_id;
                $rec_id = $value->rec_id;
                $total_qty = $value->total_qty;
                $used = $value->used;
                $balance = $value->balance;
                echo "second: revertRewardIncentivesSufficient user_id ".$user_id." rec_id ".$rec_id;
                        Log::info("second: revertRewardIncentivesSufficient user_id ".$user_id." rec_id ".$rec_id);
                        Balance::where('user_id', $user_id)->where('currency_symbol','EVR')->increment('main_balance', $total_qty);

                        DB::table('reward_incentives')->where('rec_id', $rec_id)->delete();
                        DB::table('reward_incentives_audit')->where('rec_id', $rec_id)->delete();


        }
        Log::info("end: revertRewardIncentivesSufficient ");
    }

    //websocket restart
    public static function websocketRestart(){

             Log::info("first: websocketRestart");
             $output = shell_exec('supervisorctl stop websockets');
             Log::info("stop: websocketRestart ".$output);
             $output1 = shell_exec('supervisorctl start websockets');
             Log::info("start: websocketRestart ".$output1);
    }
    //Deposit bonus - Deposit wallet to Main Wallet(USDT)

    public static function depositBonusToMainWallet(){

        Log::info("start: depositBonusToMainWallet");
        $dep_cont = DB::table('deposit_bonus_constants')->first();
        $bonus = $dep_cont->bonus_to_main_wallet;
        $trading_vol_limit = $dep_cont->trading_vol_limit;
        $date = '2020-10-01';
        // $result =  DB::table('executed_orders')->select( DB::raw('SUM(executed_orders.total_amount*coin_listing.coin_price) as total_amt , executed_orders.user_id'))->leftJoin('users', 'users.user_id', '=', 'executed_orders.user_id')->leftJoin('base_currency_pairing', DB::raw('(REPLACE(base_currency_pairing.trading_pairs, "/", "_"))'), '=', 'executed_orders.market_symbol')->leftJoin('coin_listing', 'coin_listing.id', '=', 'base_currency_pairing.coin_id')->where('executed_orders.status' , '=' , 1)->whereRaw('DATE_FORMAT(executed_orders.created_at, "%Y-%m-%d")>="'.$date.'"')->where('users.role','!=',2)->groupBy('executed_orders.user_id')->havingRaw('total_amt >= "'.$trading_vol_limit.'"')->get();
        $result =  DB::table('executed_orders')->select( DB::raw('SUM(executed_orders.total_amount*coin_listing.coin_price) as total_amt , executed_orders.user_id'))->leftJoin('users', 'users.user_id', '=', 'executed_orders.user_id')->leftJoin('coin_listing', 'executed_orders.market_symbol', 'LIKE', DB::raw("CONCAT('%', coin_listing.coin_symbol)"))->where('executed_orders.status' , '=' , 1)->whereRaw('DATE_FORMAT(executed_orders.created_at, "%Y-%m-%d")>="'.$date.'"')->where('users.role','!=',2)->groupBy('executed_orders.user_id')->havingRaw('total_amt >= "'.$trading_vol_limit.'"')->get();
        echo "<pre>";
        print_r($result);
        
        foreach ($result as $key => $value) {
            $user_id = $value->user_id;
            $current_trading_vol = $value->total_amt;
            
            $ri_res = DB::table('reward_incentives')->where('user_id',$user_id)->whereIn('reward_id',array(2,3))->where('balance','!=',0)->get();
            echo "<pre>";
            print_r($ri_res);
            if(count($ri_res) >= 1){
                $qs = DB::table('deposit_bonus_transactions')->select(DB::raw('SUM(tradevolume) as tradevolume'),'user_id')->where('user_id',$user_id)->first();
                echo "<pre>";
                print_r($qs);
                $previous_trade_vol = !empty($qs->tradevolume) ? $qs->tradevolume : 0;
                echo $user_id." previous_trade_vol ".$previous_trade_vol." <br />";
                $diffVolume = $current_trading_vol-$previous_trade_vol;                

                if($diffVolume >= $trading_vol_limit) {
                    $payoutCount = floor($diffVolume/$trading_vol_limit);

                   echo "payoutCount ".$payoutCount."<br/>";
                    foreach ($ri_res as $key => $value) {       
                        
                           if($value->reward_id == 2){
                                Log::info("welcome_bonus: depositBonusToMainWallet user_id ".$user_id);
                                $query = DB::table('reward_incentives')
                                  ->where('user_id',$user_id)->where('reward_id',2);
                                $query->increment('used',floatval($bonus));
                                $query->decrement('balance',floatval($bonus));

                                $balance = Balance::where('user_id', $user_id)->where('currency_symbol', 'USDT')->first();
                                if($balance===null){
                                        $b=array(
                                                "user_id"=>$user_id,
                                                "currency_symbol"=>'USDT',
                                                "main_balance"=>$bonus
                                        );
                                        Balance::insert($b);
                                }else{

                                Balance::where('user_id', $user_id)->where('currency_symbol','USDT')->increment('main_balance', $bonus);
                                }
                                $payoutCount--;
                                $insertObj2 = array(
                                          "user_id"=>$user_id,
                                          "from_user_id"=>$user_id,
                                          "reward_id"=>2,
                                          "bonus_wallet"=>'USDT',
                                          "bonus"=>$bonus,
                                          "type"=>'OUT',
                                          "tradevolume"=>$trading_vol_limit
                                        );
                                              
                                $cron_id = DB::table('deposit_bonus_transactions')->insertGetId($insertObj2);

                                $userinfo=Userinfo::join('users','users.user_id','=','userinfo.user_id')->select('userinfo.first_name','userinfo.last_name','users.email')->where("userinfo.user_id",$user_id)->first();
                                $email=$userinfo['email'];
                                $subject = "Welcome Bonus";
                                $title = "Welcome Bonus";
                                if($userinfo['first_name'] != ""){
                                    $username = ucwords($userinfo['first_name']." ".$userinfo['last_name']);
                                  }else{
                                    $username = $email;
                                  }
                                $message = "Welcome Bonus <strong>".number_format_two_dec($bonus)." USDT</strong> has been added to your Main account.";
                                $edata['useremail'] = array( 'username' => $username, 'email' => $email,"title"=>$title,'message'=>$message,"website_url"=>config('constants.APPLICATION_URL'));

                                Mail::send(['html'=>'email_templates.deposit_email_new'], $edata, function($message) use ($userinfo,$email,$subject) {
                                  $message->to($email, $userinfo['first_name']." ".$userinfo['last_name'])->subject($subject);
                                    $message->from('support@brexily.com ','Brexily');
                                  });
                                if (Mail::failures()) {
                                        Log::info("Email: depositBonusToMainWallet ".json_encode(Mail::failures()));
                                    }
                           }
                           
                           if($value->reward_id == 3){
                                $tot_bonus = $payoutCount*$bonus;
                                $tot_trade_vol = $payoutCount*$trading_vol_limit;

                                if($value->balance < $tot_bonus){
                                    $tot_bonus = floor($value->balance/$bonus)*$bonus;
                                    $tot_trade_vol = $trading_vol_limit*floor($value->balance/$bonus);
                                }
                             
                            if($tot_bonus>0){
                                Log::info("deposit_bonus: depositBonusToMainWallet user_id ".$user_id);
                                    $query = DB::table('reward_incentives')
                                      ->where('user_id',$user_id)->where('reward_id',3);
                                    $query->increment('used',floatval($tot_bonus));
                                    $query->decrement('balance',floatval($tot_bonus));

                                    $balance = Balance::where('user_id', $user_id)->where('currency_symbol', 'USDT')->first();
                                    if($balance===null){
                                            $b=array(
                                                    "user_id"=>$user_id,
                                                    "currency_symbol"=>'USDT',
                                                    "main_balance"=>$tot_bonus
                                            );
                                            Balance::insert($b);
                                    }else{

                                    Balance::where('user_id', $user_id)->where('currency_symbol','USDT')->increment('main_balance', $tot_bonus);
                                    }
                                    $insertObj2 = array(
                                          "user_id"=>$user_id,
                                          "from_user_id"=>$user_id,
                                          "reward_id"=>3,
                                          "bonus_wallet"=>'USDT',
                                          "bonus"=>$tot_bonus,
                                          "type"=>'OUT',
                                          "tradevolume"=>$tot_trade_vol
                                        );
                                              
                                    $cron_id = DB::table('deposit_bonus_transactions')->insertGetId($insertObj2);
                                
                                   $userinfo=Userinfo::join('users','users.user_id','=','userinfo.user_id')->select('userinfo.first_name','userinfo.last_name','users.email')->where("userinfo.user_id",$user_id)->first();
                                    $email=$userinfo['email'];
                                    $subject = "Deposit Bonus";
                                    $title = "Deposit Bonus";
                                    if($userinfo['first_name'] != ""){
                                        $username = ucwords($userinfo['first_name']." ".$userinfo['last_name']);
                                      }else{
                                        $username = $email;
                                      }
                                    $message = "Deposit Bonus <strong>".number_format_two_dec($tot_bonus)." USDT</strong> has been added to your Main account.";
                                    $edata['useremail'] = array( 'username' => $username, 'email' => $email,"title"=>$title,'message'=>$message,"website_url"=>config('constants.APPLICATION_URL'));

                                    Mail::send(['html'=>'email_templates.deposit_email_new'], $edata, function($message) use ($userinfo,$email,$subject) {
                                      $message->to($email, $userinfo['first_name']." ".$userinfo['last_name'])->subject($subject);
                                        $message->from('support@brexily.com ','Brexily');
                                      });
                                    if (Mail::failures()) {
                                        Log::info("Email1: depositBonusToMainWallet ".json_encode(Mail::failures()));
                                    }
                                }
                       }
                   }

                }


            }
            
            
        }
        Log::info("end: depositBonusToMainWallet");

    }


}
