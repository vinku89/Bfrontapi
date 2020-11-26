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
use App\Balance;
use App\BaseCurrency;
use App\Coinhistory;


use App\LatestTradeData;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Connection\AMQPConnection;
use App\Http\Controllers\rabbitmq\RabbitmqController;
use App\Events\buy;

class AccessController extends BaseController
{
    

    public function coininfo(Request $request){

  $coinsymble =  request('short_name') ; // $data['short_name'];
if($coinsymble != "" && !empty($coinsymble))
{


$shortNameArr =  explode("_",$coinsymble); // $data['short_name'];


        $res = DB::table('coin_listing')->select('coin_image' , 'coin_price' , 'coin_name' , 'coin_symbol' , 'links' , 'coin_description' , 'coin_price')->where('coin_symbol' ,  $shortNameArr[0])->get();

   $valuechange =  DB::table('crypto_value_history')->select('fiat_value')->where('created_at','>',\DB::raw( 'DATE_SUB(NOW(), INTERVAL 24 HOUR)'))->where('crypto_symbol' ,  $shortNameArr[0])->orderby('id', 'ASC')->offset(0)->limit(1)->get();
  $query = \DB::getQueryLog();




        //$res = DB::table('latest_trade_data')->select('coin_market_cap_id' , 'coin_price' , 'coin_name' , 'coin_symbol' , 'links' , 'coin_description')->where('coin_symbol' ,  $coinsymble)->get();      

        if (count($res) > 0)
        {



        $linkun  =  unserialize(@$res[0]->links);
       // $valueofTotal =   DB::table('latest_trade_data')->select('volume_24h') ->where('market_symbol', $coinsymble)->orderBy('id' , 'DESC')->offset(0)->limit(1)->get();

    $valueofTotal=LatestTradeData::where('market_symbol', $coinsymble)->where('date','>',\DB::raw( 'DATE_SUB(NOW(), INTERVAL 24 HOUR)'))->first(); 
    

    if($valueofTotal===null){
      $valueofTotal = 0;
    }


        $res['links'] = $linkun;
        $res['coin_valume_amt']  = number_format_two_dec(@$valueofTotal['volume_24h']*$res[0]->coin_price)?:0;

              if (count($valuechange) > 0)
              {

              $res['24hrsBackVal'] = round((((@$res[0]->coin_price-@$valuechange[0]->fiat_value)/@$valuechange[0]->fiat_value)*100),2)?:0.00;
              }
              else

              {
              $res['24hrsBackVal'] = 0;


              }

              // print_r($res);
              return response()->json(["Success"=>true,'status' => 200,'Result' => $res], 200);

        }

        else
        {
        return response()->json(["Success"=>true,'status' => 200,'Result' => "currency not exist"], 200);

        }
}
else
{
        return response()->json(["Success"=>false ,'status' => 400,'Result' => array()], 200);

}
    }

    public function marketCurrncyList(Request $request)
    {


  //  $coindata['total_Amt'] = Coinhistory::select(\DB::raw('coin_symbol' , 'last_price' , 'market_symbol' , 'MAX(price_high_24h) as price_high_24h','MIN(price_low_24h) as price_low_24h'))->groupby('market_symbol ')->orderBy('date','DESC');
       
            //print_r($coindata);exit;

//, max(dbt_coinhistory.volumeto/(SELECT coin_price FROM `coin_listing` WHERE `coin_symbol` = "btc")) as btcTotalAmt


if(strtolower($request["currency_Type"]) == "all") 
{

//$totalAmt = Coinhistory::select(\DB::raw(' dbt_coinhistory.volume_24h*coin_listing.coin_price as activeTradingTotalAmt ') )->leftJoin('coin_listing', 'coin_listing.coin_symbol', '=', 'dbt_coinhistory.coin_symbol')->groupby('dbt_coinhistory.market_symbol')->whereRaw("DATE(dbt_coinhistory.date) = CURDATE()" )->orderby('dbt_coinhistory.date' , 'DESC')->get();



//where(DATE('dbt_coinhistory.date') , '=' , CURDATE())
//$totalUsdAmount = 0.00;

//foreach ($totalAmt as $key => $amt) 
//{

//$totalUsdAmount = $totalUsdAmount + $amt['activeTradingTotalAmt'] ;

//}




$sqlwery = 'select
 REPLACE(cp.trading_pairs, "/", "_") as ticker_id,
 cl1.coin_symbol as base_currency,
 cl.coin_symbol as target_currency,cl.coin_image as image , cl.coin_name as coinname ,
 CASE WHEN (COALESCE(t.last_price,0)*cl1.coin_price)=0 THEN cl.coin_price ELSE (COALESCE(t.last_price,0)*cl1.coin_price) END as last_price_dollar, 
 CASE WHEN (t.changed_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) THEN (COALESCE(t.base_volume,0)*cl1.coin_price) ELSE
 0 END as base_volume_dollar,
  CASE WHEN (t.changed_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) THEN (COALESCE(t.high,0)*cl1.coin_price) ELSE 0 END as high_dollar,
 CASE WHEN (t.changed_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) THEN (COALESCE(t.low,0)*cl1.coin_price) ELSE
 0 END as low_dollar
from base_currency_pairing cp
inner join coin_listing cl on cl.id=cp.pairing_id and cp.status=1 and cl.status=1
inner join coin_listing cl1 on cl1.id=cp.coin_id and cl1.status=1 and cl1.is_base_currency = 1 
left join tickers t on (t.market_symbol=REPLACE(cp.trading_pairs, "/","_")) order by base_volume_dollar DESC';


$coinpair = DB::select(\DB::raw($sqlwery));
$totalVoleme = 0.00;
$mapping = [];
foreach ($coinpair as $key => $value) {

$mapping[$key]["coin_image"] = $value->image;
$mapping[$key]["coin_name"] = $value->coinname;
$mapping[$key]["valumeAmt"] = number_format_two_dec($value->base_volume_dollar);
$mapping[$key]["coin_symbol"] = $value->target_currency;
$mapping[$key]["market_symbol"] = $value->ticker_id;
$mapping[$key]["price_high_24h"] = number_format_four_dec($value->high_dollar);
$mapping[$key]["price_low_24h"] = number_format_four_dec($value->low_dollar);
$mapping[$key]["currentPrice"] = number_format_four_dec($value->last_price_dollar);

$totalVoleme = ($totalVoleme) + ($value->base_volume_dollar); 

}


$coindata['total_sum_volume'] = number_format_two_dec($totalVoleme);
$coindata['active_tading'] = $mapping;

$btcPrice = CoinListing::select('coin_price')->where('coin_symbol' , 'btc')->first();

$coindata['total_activeTradingTotalAmt'] = number_format_two_dec($totalVoleme);

$coindata['btcTotalAmt'] = number_format_eight_dec($totalVoleme/$btcPrice->coin_price) ;



}
     //  die;
else if (strtolower($request["currency_Type"]) == "historicaldata")
{



//  $totalAmt = Coinhistory::select(\DB::raw(' (MAX(volumeto)-MIN(volumeto))*coin_listing.coin_price as activeTradingTotalAmt ') )->leftJoin('coin_listing', 'coin_listing.coin_symbol', '=', 'dbt_coinhistory.coin_symbol')->whereBetween('dbt_coinhistory.date' , [\DB::raw('CURDATE() - INTERVAL 30 DAY') , \DB::raw('CURDATE()')])->groupby('dbt_coinhistory.market_symbol')->get();



//`dbt_coinhistory`.`date` BETWEEN CURDATE() - INTERVAL 30 DAY AND CURDATE()
//whereBetween('age', [$ageFrom, $ageTo])
//whereRaw("DATE(dbt_coinhistory.date) = CURDATE()")->

//where(DATE('dbt_coinhistory.date') , '=' , CURDATE())
//$totalUsdAmount = 0.00;
/*foreach ($totalAmt as $key => $amt) 
{

$totalUsdAmount = $totalUsdAmount + $amt['activeTradingTotalAmt'] ;

}


$btcPrice = CoinListing::select('coin_price')->where('coin_symbol' , 'btc')->first();

$coindata['total_activeTradingTotalAmt'] = number_format_eight_dec($totalUsdAmount);

$coindata['btcTotalAmt'] = number_format_two_dec($totalUsdAmount/$btcPrice->coin_price) ;
*/
  //\DB::enableQueryLog();


   //  $res=Coinhistory::select(\DB::raw('max(total_coin_supply) AS volume, DATE_FORMAT(date(date) , "%d/%m/%Y") AS day,market_symbol'))->whereBetween('dbt_coinhistory.date' , [\DB::raw('CURDATE() - INTERVAL 30 DAY') , \DB::raw('CURDATE()')])->groupBy('day','market_symbol')->orderBy('id','desc')->get()->toArray();


  $res=Coinhistory::select(\DB::raw('DATE_FORMAT(date, "%d/%m/%Y") as day, CASE WHEN COALESCE(volumefrom,0)=COALESCE(volumeto,0) THEN MAX(COALESCE(volumeto,0)) ELSE MAX(COALESCE(volumeto,0))-MIN(COALESCE(volumeto,0)) END as volume,
   market_symbol '))->whereRaw('DATE_FORMAT(date, "%d/%m/%Y")<>DATE_FORMAT(CURDATE(), "%d/%m/%Y")')->whereBetween('dbt_coinhistory.date' , [\DB::raw('subdate(CURDATE(),1) - INTERVAL 30 DAY') , \DB::raw('CURDATE()')])->groupBy('day','market_symbol')->orderBy('id','desc')->get()->toArray();




//$sqlwery = 'SELECT DATE_FORMAT(date, "%d/%m/%Y") as day, (MAX(volumeto)-MIN(volumeto)) as volume, market_symbol FROM `dbt_coinhistory` ch where date BETWEEN CURDATE() - INTERVAL 30 DAY AND CURDATE() group by DATE_FORMAT(date, "%d/%m/%Y"), market_symbol';

//$res = DB::select(\DB::raw($sqlwery));

        // $res = DB::table('latest_trade_data')->select(\DB::raw('market_symbol,total_coin_supply , DATE(updated_at) as  DateOnl'))->get();
// dd(DB::getQueryLog());exit;

Log::info("res ".json_encode($res));
$mapping = [];
$data = "";
$i = 0;
$valueOf24 = 0;

$total_usd = 0.0;
$finalres=array();
$btcCoinPrice = CoinListing::select('coin_price')->where('coin_symbol' , "btc" )->first();


$coins = DB::table('coin_listing')->select('coin_symbol' , 'coin_price')->get();



  $coin_listing = [];
  foreach ($coins as $key => $value) {

  $coin_listing[$value->coin_symbol] = $value->coin_price;

  }



foreach ($res as $key => $value) {
   

      

    $currency = explode("_" , $value['market_symbol']);

     // $currencyUsd = CoinListing::select('coin_price')->where('coin_symbol' , $currency[0] )->first();

// echo $currency[0]; 
// print_r($coin_listing);
$currencyUsd = $coin_listing[strtoupper($currency[0])];
      //Log::info("usd coin ".json_encode($currencyUsd));
      if($currencyUsd!==null){
        //Log::info("usd value ".$currencyUsd->coin_price);
        //$mapping[$i]['DateOnl'] = $value->DateOnl;
        //$mapping[$i]['valumeAmt'] =  $valueOf24*$currencyUsd['coin_price'];
        $usdvolume=$value['volume']*$currencyUsd;
        //$mapping[$i]['valumeOfBtcAmt'] = $mapping[$i]['valumeAmt']/$btcCoinPrice['coin_price'];
         $btcvolume = $usdvolume/$btcCoinPrice->coin_price;
        //$total_usd = $total_usd + floatval($usdvolume);
        $total_usd = floatval(@$total_usd)+floatval($usdvolume);
         //$valueOf24 = $valueOf24 + $value->total_coin_supply;

        //$data = $value->DateOnl; 
        $finalres[$value['day']]['valumehAmt']= (floatval(@$finalres[$value['day']]['valumehAmt'])+floatval($usdvolume));
        $finalres[$value['day']]['valumeOfBtcAmt']= (floatval(@$finalres[$value['day']]['valumeOfBtcAmt'])+floatval($btcvolume));
        $finalres[$value['day']]['DateOnl']=$value['day'];
      }
    
}

foreach ($finalres as $key => $value) {
 
 $finalres[$key]['valumehAmt']= number_format_two_dec(floatval($finalres[$key]['valumehAmt']));
        $finalres[$key]['valumeOfBtcAmt']= number_format_eight_dec(floatval($finalres[$key]['valumeOfBtcAmt']));


}

$coindata['total_activeTradingTotalAmt'] = number_format_two_dec($total_usd);

$coindata['btcTotalAmt'] = number_format_eight_dec($total_usd/$btcCoinPrice->coin_price);

/*
$latestData = []
$i=0;
$lastDate = "";
foreach ($finalres as $key => $value) {

$latestData = $key;
if ( count($finalres)-1 == $i ) {
// No comparision
$latestData[$key]['valumehAmt']= $value['$value']
$latestData[$key]['valumeOfBtcAmt']= $value['$value']
$latestData[$key]['DateOnl']= $value['$DateOnl']

}
else
{
  // Reduce from previous month
  $latestData[$key]['valumehAmt']= $finalres[$lastDate]['value']
$latestData[$key]['valumeOfBtcAmt']= $value['$value']
$latestData[$key]['DateOnl']= $value['$DateOnl']


}
$i = $i+1;
}
*/


$coindata['active_tading'] = $finalres;

  


}
else if (strtolower($request["currency_Type"]) != "" && !empty(strtolower($request["currency_Type"])) )
{

/*
\DB::enableQueryLog();
$totalAmt = Coinhistory::select(\DB::raw(' dbt_coinhistory.volume_24h*coin_listing.coin_price as activeTradingTotalAmt ') )->leftJoin('coin_listing', 'coin_listing.coin_symbol', '=', 'dbt_coinhistory.coin_symbol')->groupby('dbt_coinhistory.market_symbol')->whereRaw("DATE(dbt_coinhistory.date) = CURDATE()" )->where('dbt_coinhistory.market_symbol', 'like', '%_' . $request["currency_Type"] . '%')->orderby('dbt_coinhistory.date' , 'DESC')->get();

  $query = \DB::getQueryLog();

       print_r(end($query)); exit();


//where(DATE('dbt_coinhistory.date') , '=' , CURDATE())
$totalUsdAmount = 0.00;

foreach ($totalAmt as $key => $amt) 
{

$totalUsdAmount = $totalUsdAmount + $amt['activeTradingTotalAmt'] ;

}


$btcPrice = CoinListing::select('coin_price')->where('coin_symbol' , 'btc')->first();

$coindata['total_activeTradingTotalAmt'] = number_format_two_dec($totalUsdAmount);

$coindata['btcTotalAmt'] = number_format_eight_dec($totalUsdAmount/$btcPrice->coin_price) ;

*/

$sqlwery = 'select
 REPLACE(cp.trading_pairs, "/", "_") as ticker_id,
 cl1.coin_symbol as base_currency,
 cl.coin_symbol as target_currency,cl.coin_image as image , cl.coin_name as coinname ,
 CASE WHEN (COALESCE(t.last_price,0)*cl1.coin_price)=0 THEN cl.coin_price ELSE (COALESCE(t.last_price,0)*cl1.coin_price) END as last_price_dollar, 
 CASE WHEN (t.changed_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) THEN (COALESCE(t.base_volume,0)*cl1.coin_price) ELSE
 0 END as base_volume_dollar,
  CASE WHEN (t.changed_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) THEN (COALESCE(t.high,0)*cl1.coin_price) ELSE 0 END as high_dollar,
 CASE WHEN (t.changed_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) THEN (COALESCE(t.low,0)*cl1.coin_price) ELSE
 0 END as low_dollar
from base_currency_pairing cp
inner join coin_listing cl on cl.id=cp.pairing_id and cp.status=1 and cl.status=1
inner join coin_listing cl1 on cl1.id=cp.coin_id and cl1.status=1 and cl1.is_base_currency = 1 and cl1.coin_symbol ="'.$request["currency_Type"].'"
left join tickers t on (t.market_symbol=REPLACE(cp.trading_pairs, "/","_")) order by base_volume_dollar DESC
';


$coinpair = DB::select(\DB::raw($sqlwery));

$mapping = [];
$totalVoleme = 0.00;
foreach ($coinpair as $key => $value) {

$mapping[$key]["coin_image"] = $value->image;
$mapping[$key]["coin_name"] = $value->coinname;
$mapping[$key]["valumeAmt"] = number_format_two_dec($value->base_volume_dollar);
$mapping[$key]["coin_symbol"] = $value->target_currency;
$mapping[$key]["market_symbol"] = $value->ticker_id;
$mapping[$key]["price_high_24h"] = number_format_four_dec($value->high_dollar);
$mapping[$key]["price_low_24h"] = number_format_four_dec($value->low_dollar);
$mapping[$key]["currentPrice"] = number_format_four_dec($value->last_price_dollar);

$totalVoleme = $totalVoleme + $value->base_volume_dollar ;

}

$coindata['active_tading'] = $mapping;

$coindata['total_sum_volume'] = number_format_two_dec($totalVoleme);

$btcPrice = CoinListing::select('coin_price')->where('coin_symbol' , 'btc')->first();

$coindata['total_activeTradingTotalAmt'] = number_format_two_dec($totalVoleme);

$coindata['btcTotalAmt'] = number_format_eight_dec($totalVoleme/$btcPrice->coin_price) ;



}

else
{
        return response()->json(["Success"=>false,'status' => 402,'Result' => array('parameter missing')], 200);
    

}
        return response()->json(["Success"=>true,'status' => 200,'Result' => $coindata], 200);
        


    }


}
