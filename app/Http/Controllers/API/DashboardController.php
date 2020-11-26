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
use App\CoinListing;
//use Amqp;
use Log;
use App\BaseCurrency;
use App\Coinhistory;
use App\Balance;
use App\Biding;

use App\LatestTradeData;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Connection\AMQPConnection;
use App\Http\Controllers\rabbitmq\RabbitmqController;
use App\Events\buy;

class DashboardController extends BaseController
{
    
public static function findRank($totalvalue , &$currentRank , $res1 , $user_id, $ref_code, $monthYear, &$result)
{
    foreach($res1 as $ke=>$ve) {

      if($totalvalue >= $ve->min_trading_volume)
      {
        ++$currentRank;
        if($currentRank <= 10){
          $currentReward = $res1[$currentRank-1] ? $res1[$currentRank-1]->reward : 0;
        }
        else{
          $currentReward = 0;
        }
        //echo "user".$currentRank."value:".$totalvalue."<br>";
        Log::info("LoadTopIndividualPerformance findRank: user".$currentRank."value:".$totalvalue." Reward ".$currentReward);
        $arr["totalAmtRank"] = $totalvalue;

        
        $result[$currentRank] = ["rank"=>$currentRank , "min_trade_volume"=>$totalvalue , "user_id"=>$user_id, "ref_code"=>$ref_code, 'reward'=> $currentReward, 'rewarded_for_monthyear'=>$monthYear];
        break;
      } else {
        if($currentRank <= $ke) {
          ++$currentRank;
           if($currentRank <= 10){
            $currentReward = $res1[$currentRank-1] ? $res1[$currentRank-1]->reward : 0;
          }
          else{
            $currentReward = 0;
          }
          Log::info("LoadTopIndividualPerformance findRank: no user".$currentRank."value:".$totalvalue." Reward ".$currentReward);
          //echo "no user".$currentRank."value:".$totalvalue."<br>";

         
          $result[$currentRank] = ["rank"=>$currentRank , "min_trade_volume"=>0 , "user_id"=>0, "ref_code"=>"", 'reward'=> $currentReward, 'rewarded_for_monthyear'=>$monthYear];
          self::findRank($totalvalue , $currentRank , $res1 , $user_id, $ref_code, $monthYear, $result);
          break;
        }
      }
  }
 
//print_r($arr);
}


public static function loadTop10IndividualPerformanceData()
{

$monthYear = date("Y-m");

Log::info("start: loadTop10IndividualPerformanceData".$monthYear);

$topTrades = DB::table('executed_orders')->select( DB::raw('SUM(executed_orders.total_amount*coin_listing.coin_price) as total_amt , executed_orders.user_id , userinfo.ref_code'))->leftJoin('userinfo', 'userinfo.user_id', '=', 'executed_orders.user_id')->leftJoin('users', 'users.user_id', '=', 'executed_orders.user_id')
->leftJoin('base_currency_pairing', DB::raw('(REPLACE(base_currency_pairing.trading_pairs, "/", "_"))'), '=', 'executed_orders.market_symbol')->leftJoin('coin_listing', 'coin_listing.id', '=', 'base_currency_pairing.coin_id')->where('executed_orders.status' , '=' , 1)->whereRaw('DATE_FORMAT(executed_orders.created_at, "%Y-%m")="'.$monthYear.'"')->where('users.role','!=',2)->groupby("executed_orders.user_id")->groupby("userinfo.ref_code")->orderby('total_amt' , 'DESC')->offset(0)->limit(10)->get()->toArray();
Log::info("updateOrderstopTradesNew".json_encode($topTrades));
// Add dummy data with increased percentage from a table.
$dummyTrades = DB::table('dummy_orders')->select(DB::raw('dummy_orders.total_amount as total_amt, dummy_orders.user_id, userinfo.ref_code'))->leftJoin('userinfo', 'userinfo.user_id', '=', 'dummy_orders.user_id')->leftJoin('users', 'users.user_id', '=', 'dummy_orders.user_id')->orderby('total_amt' , 'DESC')->get()->toArray();

DB::table('dummy_orders')->delete();
$arr = array();
Log::info("updateOrders ".json_encode($dummyTrades));
foreach ($dummyTrades as $key => $value) {
  $rand = rand(1,3);
  $value->total_amt = $value->total_amt+$value->total_amt*$rand/100;
  $dummyTrades[$key] = $value;
  $arrayName = array('total_amount' => $value->total_amt,'user_id'=>$value->user_id );
  array_push($arr, $arrayName);

}
Log::info("updateOrders after increment".json_encode($arr));
DB::table('dummy_orders')->insert($arr);
// Sort Dummy data.
$topTrades = array_merge($topTrades, $dummyTrades);
$topTrades = collect($topTrades);



$topTrades = $topTrades->sortByDesc('total_amt');
Log::info("updateOrderstopTrades".json_encode($topTrades));
//$sorted = $c->sortBy(function ($item, $key) {
// return $item->id;
//});

$topTrades = $topTrades->toArray();
//get first 10 elements
$topTrades = array_slice($topTrades,0,10);
$ranking = DB::table('individual_performance_ranking')->select('min_trading_volume' , 'reward', 'rank')->orderby('min_trading_volume', 'DESC')->get();
Log::info("LoadTopIndividualPerformance executed query");
$result = [];
//$currentRank = 0;
$currentRank = 0;
foreach($topTrades as $k=>$v){ //var i=0; i<top10.length;i++
echo $currentRank;
self::findRank($v->total_amt , $currentRank , $ranking , $v->user_id, $v->ref_code, $monthYear, $result);
}
//print_r($result);
$res_cnt = count($result);
$tot_cnt = 10-count($result);
for($i=1;$i<=$tot_cnt;$i++){
$new_id = $res_cnt+$i;
$arr = ["rank"=> $new_id , "min_trade_volume"=>0 , "user_id"=>0, "ref_code"=>"", 'reward'=> $ranking[$new_id-1]->reward, 'rewarded_for_monthyear'=>$monthYear];
array_push($result, $arr);

}
Log::info("result: loadTop10IndividualPerformanceData".json_encode($result));
DB::table('individual_performance_data')->insert($result);

Log::info("end: loadTop10IndividualPerformanceData".$monthYear);

}

public function getTop10IndividualPerformanceData(Request $request)
{

	$data = $request->user();
	$user_id = $data['user_id'];

try {

    $sqlwery = "SELECT rank, min_trade_volume, ref_code, user_id,  reward FROM individual_performance_data ORDER BY created_date DESC,  rank ASC LIMIT 10";
    $coinpair = DB::select(\DB::raw($sqlwery));

	foreach ($coinpair as $key => $value) {
		

		if($value->ref_code) {
			if($value->user_id== $user_id) {

			} else {
			  

			$len = strlen($value->ref_code);
			// $mask = '';
			// for($i=0; $i<$len-5; $i++) {
			// 	$mask = $mask."*";
			// }
			$value->ref_code	 = substr($value->ref_code, 0, 2).'**********'.substr($value->ref_code, $len-3, $len);

			}
		}

	}


     $result['top10RecIndividual'] = $coinpair;

      return response()->json(["Success"=>true,'status' => 200,'Result' => $result], 200);
      
    } catch (Exception $e) {
      
      return response()->json(["Success"=>false,'status' => 400,'Result' => array()], 400);


    } 

}

public static function userRankingUpdate()
{

	try
	{
			
			DB::table('userinfo')->update(array('rank'=>''));

			$monthYear = date("Y-m");
			Log::info("start: userRankingUpdate".$monthYear);

			$topTrades =  DB::table('executed_orders')->select( DB::raw('SUM(executed_orders.total_amount*coin_listing.coin_price) as total_amt , executed_orders.user_id '))->leftJoin('userinfo', 'userinfo.user_id', '=', 'executed_orders.user_id')->leftJoin('users', 'users.user_id', '=', 'executed_orders.user_id')->leftJoin('base_currency_pairing', DB::raw('(REPLACE(base_currency_pairing.trading_pairs, "/", "_"))'), '=', 'executed_orders.market_symbol')->leftJoin('coin_listing', 'coin_listing.id', '=', 'base_currency_pairing.coin_id')->where('executed_orders.status' , '=' , 1)->where('users.role' , '!=' , 2)->whereRaw('DATE_FORMAT(executed_orders.created_at, "%Y-%m")="'.$monthYear.'"')->groupby("executed_orders.user_id")->orderby('total_amt' , 'DESC')->get()->toArray();
      Log::info("userRankingUpdate one".json_encode($topTrades));
      // Add dummy data with increased percentage from a table.
      $dummyTrades = DB::table('dummy_orders')->select(DB::raw('dummy_orders.total_amount as total_amt, dummy_orders.user_id'))->orderby('total_amt' , 'DESC')->get()->toArray();

      // Sort Dummy data.
      $topTrades = array_merge($topTrades, $dummyTrades);
      $topTrades = collect($topTrades);

      $topTrades = $topTrades->sortByDesc('total_amt'); 

      // $topTrades = $topTrades->sort(function ($a, $b) {
      //   $a = floatval($a->total_amt);
      //   $b = floatval($b->total_amt);
      //     if ($a == $b) {
      //         return 0;
      //     }
      //     return ($a > $b) ? -1 : 1;
      // });

      //$sorted = $c->sortBy(function ($item, $key) {
      // return $item->id;
      //});

      $topTrades = $topTrades->unique('user_id')->toArray();
       Log::info("userRankingUpdate two".json_encode($topTrades));
      //get first 10 elements
      //$topTrades = array_slice($topTrades,0,9);

       $i =0;
			foreach ($topTrades as $key => $value) {
				echo "user_id ".$value->user_id."<br>";

				Log::info("userId's: userRankingUpdate".$value->user_id." key ".$key);
				DB::table('userinfo')->where('user_id' , $value->user_id)->update(array('rank'=>($i+1)));
        $i++;
			}

			Log::info("end: userRankingUpdate".$monthYear);

	}
	catch(Exception $e)
	{
 		Log::info('userRankingUpdate: cron failed'.date('y/m/d'));
	}
}


public function Last5DaysCommitionUser(Request $request)
{
    $data = $request->user();
    $user_id = $data['user_id'];

        try
        {
        $query = 'SELECT table1.trade_date as payout_date, SUM(COALESCE(table1.total_buy,0)+COALESCE(table1.total_sell,0)) as total_trade, SUM(COALESCE(table2.commission_amount,0)) as commission_amount,"Trading commission" as details FROM
    (SELECT 
      DATE_FORMAT(order_completed_at, "%d-%m-%Y") trade_date, 
      0 as level, 
      '.$user_id.' as user_id, 
      SUM(CASE WHEN bid_type="BUY" THEN COALESCE(total_amount_usd,0) ELSE 0 END) total_buy,
      SUM(CASE WHEN bid_type="SELL" THEN COALESCE(total_amount_usd,0) ELSE 0 END) total_sell
    FROM fee_deductions
    WHERE 
      user_id in (SELECT descendant_id from referrals where ancestor_id = '.$user_id.' and 
      distance = 0) and status = 1
      GROUP BY DATE_FORMAT(order_completed_at, "%d-%m-%Y")
    UNION ALL
    SELECT 
      DATE_FORMAT(order_completed_at, "%d-%m-%Y") trade_date, 
      1 as level, 
      '.$user_id.' as user_id, 
      SUM(CASE WHEN bid_type="BUY" THEN COALESCE(total_amount_usd,0) ELSE 0 END) total_buy,
      SUM(CASE WHEN bid_type="SELL" THEN COALESCE(total_amount_usd,0) ELSE 0 END) total_sell
    FROM fee_deductions
    WHERE 
      user_id in (SELECT descendant_id from referrals where ancestor_id = '.$user_id.' and  
      distance = 1) and status = 1 
      
      GROUP BY DATE_FORMAT(order_completed_at, "%d-%m-%Y")
    UNION ALL
    SELECT 
      DATE_FORMAT(order_completed_at, "%d-%m-%Y") trade_date, 
      2 as level, 
      '.$user_id.' as user_id, 
      SUM(CASE WHEN bid_type="BUY" THEN COALESCE(total_amount_usd,0) ELSE 0 END) total_buy,
      SUM(CASE WHEN bid_type="SELL" THEN COALESCE(total_amount_usd,0) ELSE 0 END) total_sell
    FROM fee_deductions
    WHERE 
      user_id in (SELECT descendant_id from referrals where ancestor_id = '.$user_id.' and  
      distance = 2) and status = 1 
      
      GROUP BY DATE_FORMAT(order_completed_at, "%d-%m-%Y")
    UNION ALL
    SELECT 
      DATE_FORMAT(order_completed_at, "%d-%m-%Y") trade_date, 
      3 as level, 
      '.$user_id.' as user_id, 
      SUM(CASE WHEN bid_type="BUY" THEN COALESCE(total_amount_usd,0) ELSE 0 END) total_buy,
      SUM(CASE WHEN bid_type="SELL" THEN COALESCE(total_amount_usd,0) ELSE 0 END) total_sell
    FROM fee_deductions
    WHERE 
      user_id in (SELECT descendant_id from referrals where ancestor_id = '.$user_id.' and  
      distance = 3) and status = 1 
      
      GROUP BY DATE_FORMAT(order_completed_at, "%d-%m-%Y")
    UNION ALL
    SELECT 
      DATE_FORMAT(order_completed_at, "%d-%m-%Y") trade_date, 
      4 as level, 
      '.$user_id.' as user_id,
      SUM(CASE WHEN bid_type="BUY" THEN COALESCE(total_amount_usd,0) ELSE 0 END) total_buy,
      SUM(CASE WHEN bid_type="SELL" THEN COALESCE(total_amount_usd,0) ELSE 0 END) total_sell
    FROM fee_deductions
    WHERE 
      user_id in (SELECT descendant_id from referrals where ancestor_id = '.$user_id.' and  
      distance = 4) and status = 1 
      GROUP BY DATE_FORMAT(order_completed_at, "%d-%m-%Y")
    UNION ALL
    SELECT 
      DATE_FORMAT(order_completed_at, "%d-%m-%Y") trade_date, 
      5 as level, 
      '.$user_id.' as user_id,  
      SUM(CASE WHEN bid_type="BUY" THEN COALESCE(total_amount_usd,0) ELSE 0 END) total_buy,
      SUM(CASE WHEN bid_type="SELL" THEN COALESCE(total_amount_usd,0) ELSE 0 END) total_sell
    FROM fee_deductions
    WHERE 
      user_id in (SELECT descendant_id from referrals where ancestor_id = '.$user_id.' and 
      distance = 5) and status = 1 
      GROUP BY DATE_FORMAT(order_completed_at, "%d-%m-%Y")
    UNION ALL
    SELECT 
      DATE_FORMAT(order_completed_at, "%d-%m-%Y") trade_date, 
      6 as level, 
      '.$user_id.' as user_id, 
      SUM(CASE WHEN bid_type="BUY" THEN COALESCE(total_amount_usd,0) ELSE 0 END) total_buy,
      SUM(CASE WHEN bid_type="SELL" THEN COALESCE(total_amount_usd,0) ELSE 0 END) total_sell
    FROM fee_deductions
    WHERE 
      user_id in (SELECT descendant_id from referrals where ancestor_id = '.$user_id.' and  
      distance = 6) and status = 1 
      GROUP BY DATE_FORMAT(order_completed_at, "%d-%m-%Y")
    UNION ALL
    SELECT 
      DATE_FORMAT(order_completed_at, "%d-%m-%Y") trade_date, 
      7 as level, 
      '.$user_id.' as user_id, 
      SUM(CASE WHEN bid_type="BUY" THEN COALESCE(total_amount_usd,0) ELSE 0 END) total_buy,
      SUM(CASE WHEN bid_type="SELL" THEN COALESCE(total_amount_usd,0) ELSE 0 END) total_sell
    FROM fee_deductions
    WHERE 
      user_id in (SELECT descendant_id from referrals where ancestor_id = '.$user_id.' and  
      distance = 7) and status = 1
      GROUP BY DATE_FORMAT(order_completed_at, "%d-%m-%Y")
    UNION ALL
    SELECT 
      DATE_FORMAT(order_completed_at, "%d-%m-%Y") trade_date, 
      8 as level, 
      '.$user_id.' as user_id,  
      SUM(CASE WHEN bid_type="BUY" THEN COALESCE(total_amount_usd,0) ELSE 0 END) total_buy,
      SUM(CASE WHEN bid_type="SELL" THEN COALESCE(total_amount_usd,0) ELSE 0 END) total_sell
    FROM fee_deductions
    WHERE 
      user_id in (SELECT descendant_id from referrals where ancestor_id = '.$user_id.' and  
      distance = 8) and status = 1 
      GROUP BY DATE_FORMAT(order_completed_at, "%d-%m-%Y")
    UNION ALL
    SELECT 
      DATE_FORMAT(order_completed_at, "%d-%m-%Y") trade_date, 
      9 as level, 
      '.$user_id.' as user_id, 
      SUM(CASE WHEN bid_type="BUY" THEN COALESCE(total_amount_usd,0) ELSE 0 END) total_buy,
      SUM(CASE WHEN bid_type="SELL" THEN COALESCE(total_amount_usd,0) ELSE 0 END) total_sell
    FROM fee_deductions
    WHERE 
      user_id in (SELECT descendant_id from referrals where ancestor_id = '.$user_id.' and  
      distance = 9) and status = 1 
      GROUP BY DATE_FORMAT(order_completed_at, "%d-%m-%Y")
    ) table1 
    INNER JOIN
    (
    SELECT 
      DATE_FORMAT(cp.commission_for, "%d-%m-%Y") as trade_date, 
      DATE_FORMAT(cp.payout_date, "%d-%m-%Y") as payout_date,
      cp.level,
      cp.user_id,
      SUM(COALESCE(cp.commission_amount,0)) as commission_amount
    FROM commission_payout cp 
    LEFT JOIN users u on u.user_id=cp.user_id 
    WHERE cp.user_id = '.$user_id.' GROUP BY DATE_FORMAT(cp.commission_for, "%d-%m-%Y"), DATE_FORMAT(cp.payout_date, "%d-%m-%Y"), cp.user_id, u.email, cp.level order by DATE_FORMAT(cp.payout_date, "%d-%m-%Y") DESC 
    ) table2 on table1.trade_date=table2.trade_date and table1.level=table2.level group by table1.trade_date order by STR_TO_DATE(table2.payout_date,"%d-%m-%Y") DESC limit 5';


           $result['Last5DaysCommition'] = DB::select(\DB::raw($query));
           return response()->json(["Success"=>true,'status' => 200,'Result' => $result], 200);
            }
            catch(Exception $e)
            {
             return response()->json(["Success"=>true,'status' => 200,'Result' => $result], 200);
            }
// print_r($data);

}

public function getOpenTradesForIndividivalUser(Request $request)
{
    $data = $request->user();
    $user_id = $data['user_id'];

    try
    {
      
     $openTrade = Biding::select(DB::raw('DATE_FORMAT(created_at, "%d-%m-%Y %H:%i:%s") as trade_date , market_symbol as pair, bid_qty_available as amount, bid_price  as price, bid_type as type, "Inorder" as status'))->where('user_id' , $user_id)->where('status' , 2)->orderby('created_at' , 'DESC')->limit(50)->get();


      	foreach ($openTrade as $key => $value) 
      	{
      	
	      	$value->amount = number_format_eight_dec($value->amount);
	      	$value->price = number_format_eight_dec($value->price);	

      	}
 	$result['openTradeForUser']  =   $openTrade ;

      return response()->json(["Success"=>true,'status' => 200,'Result' => $result], 200);
    }
    catch(Exception $e)
    {
      return response()->json(["Success"=>false,'status' => 400,'Result' => array()], 400);

    }

}

public function walletRewards(Request $request)
{

	$data = $request->user();
	$user_id = $data['user_id'];
    try
    {
    	$result['rewards']    = DB::table('rewards as r')->select(\DB::raw('r.name, COALESCE(sum(ri.total_qty),0) total_qty,  COALESCE(sum(ri.used),0) used, COALESCE(sum(ri.balance),0) balance , cl.coin_image'))->join('coin_listing as cl' , 'cl.coin_symbol' , '=' , 'r.coin_symbol')->leftjoin('reward_incentives as ri', function($join) use ($user_id){
                    $join->on('ri.reward_id','=','r.rec_id')
                        ->where('ri.user_id' , '=' , $user_id);
                })->groupBy('cl.coin_image','r.name')->orderBy('r.rec_id','ASC')->get();
    	 return response()->json(["Success"=>true,'status' => 200,'Result' => $result], 200);

    }
	catch(Exception $e)
	{
		return response()->json(["Success"=>false,'status' => 400,'Result' => array()], 400);

	}
}

}

?>