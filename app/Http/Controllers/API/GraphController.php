<?php
namespace App\Http\Controllers\API;
use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;
use App\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Userinfo;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;
use Log;
use Illuminate\Support\Facades\Validator;
use DB;
use App\Coinhistory;
use App\CoinListing;
use App\BaseCurrencyPairing;
class GraphController extends BaseController 
{
	public function graph_data(Request $request,$fsym,$tsym,$fromTs,$toTs,$tFilter){
		//echo $fsym;exit;
		$market_symbol=$fsym."_".$tsym;
		$todate= date("Y-m-d H:i:s",$toTs);
		$fromTs= date("Y-m-d H:i:s",$fromTs);
		//DB::enableQueryLog(); 
		if($tFilter=="D" || $tFilter=="1D"){
			$coinhistory =	Coinhistory::select(\DB::raw('MAX(open) as max_open,MAX(close) as max_close,MIN(open) as min_open,MIN(close) as min_close, AVG(volumeto) as volumeto,AVG(volumefrom) as volumefrom, substring_index(group_concat(cast(open as CHAR) order by date), ",", 1 ) as first_open, substring_index(group_concat(cast(close as CHAR) order by date desc), ",", 1 ) as last_close,date(date) AS day'))->where('market_symbol', $market_symbol)->where('date',">=",$fromTs)->where('date',"<=",$todate)->groupBy('day')->orderBy('day')->limit(2000)->get()->toArray();

		}else if($tFilter=="M" || $tFilter=="1M"){
			$coinhistory =	Coinhistory::select(\DB::raw('MAX(open) as max_open,MAX(close) as max_close,MIN(open) as min_open,MIN(close) as min_close, AVG(volumeto) as volumeto,AVG(volumefrom) as volumefrom, substring_index(group_concat(cast(open as CHAR) order by date), ",", 1 ) as first_open, substring_index(group_concat(cast(close as CHAR) order by date desc), ",", 1 ) as last_close,DATE_FORMAT(date,"%Y-%m") AS day'))->where('market_symbol', $market_symbol)->where('date',">=",$fromTs)->where('date',"<=",$todate)->groupBy('day')->orderBy('day')->limit(2000)->get()->toArray();
		}else if($tFilter=="Y" || $tFilter=="1Y"){
			$coinhistory =	Coinhistory::select(\DB::raw('MAX(open) as max_open,MAX(close) as max_close,MIN(open) as min_open,MIN(close) as min_close, AVG(volumeto) as volumeto,AVG(volumefrom) as volumefrom, substring_index(group_concat(cast(open as CHAR) order by date), ",", 1 ) as first_open, substring_index(group_concat(cast(close as CHAR) order by date desc), ",", 1 ) as last_close,date AS day'))->where('market_symbol', $market_symbol)->where('date',">=",$fromTs)->where('date',"<=",$todate)->groupBy('day')->orderBy('day')->limit(2000)->get()->toArray();
		}else if($tFilter=="1W" || $tFilter=="W"){
			$coinhistory =	Coinhistory::select(\DB::raw('MAX(open) as max_open,MAX(close) as max_close,MIN(open) as min_open,MIN(close) as min_close, AVG(volumeto) as volumeto,AVG(volumefrom) as volumefrom, substring_index(group_concat(cast(open as CHAR) order by date), ",", 1 ) as first_open, substring_index(group_concat(cast(close as CHAR) order by date desc), ",", 1 ) as last_close,week(date) as wdate,date AS day'))->where('market_symbol', $market_symbol)->where('date',">=",$fromTs)->where('date',"<=",$todate)->groupBy('wdate')->orderBy('wdate')->limit(2000)->get()->toArray();
		}else if($tFilter=="60"){
			$coinhistory =	Coinhistory::select(\DB::raw('MAX(open) as max_open,MAX(close) as max_close,MIN(open) as min_open,MIN(close) as min_close, AVG(volumeto) as volumeto,AVG(volumefrom) as volumefrom, substring_index(group_concat(cast(open as CHAR) order by date), ",", 1 ) as first_open, substring_index(group_concat(cast(close as CHAR) order by date desc), ",", 1 ) as last_close,date AS day,from_unixtime(FLOOR(UNIX_TIMESTAMP(date)/(60*60))*(60*60)) GroupTime'))->where('market_symbol', $market_symbol)->where('date',">=",$fromTs)->where('date',"<=",$todate)->groupBy('GroupTime')->orderBy('GroupTime')->limit(2000)->get()->toArray();
		}else if($tFilter=="240"){
				$coinhistory =	Coinhistory::select(\DB::raw('MAX(open) as max_open,MAX(close) as max_close,MIN(open) as min_open,MIN(close) as min_close, AVG(volumeto) as volumeto,AVG(volumefrom) as volumefrom, substring_index(group_concat(cast(open as CHAR) order by date), ",", 1 ) as first_open, substring_index(group_concat(cast(close as CHAR) order by date desc), ",", 1 ) as last_close,date AS day,from_unixtime(FLOOR(UNIX_TIMESTAMP(date)/(240*60))*(240*60)) GroupTime'))->where('market_symbol', $market_symbol)->where('date',">=",$fromTs)->where('date',"<=",$todate)->groupBy('GroupTime')->orderBy('GroupTime')->limit(2000)->get()->toArray();
			
		}else if($tFilter=="1"){
			$coinhistory =	Coinhistory::select(\DB::raw('MAX(open) as max_open,MAX(close) as max_close,MIN(open) as min_open,MIN(close) as min_close, AVG(volumeto) as volumeto,AVG(volumefrom) as volumefrom, substring_index(group_concat(cast(open as CHAR) order by date), ",", 1 ) as first_open, substring_index(group_concat(cast(close as CHAR) order by date desc), ",", 1 ) as last_close,date AS day,from_unixtime(FLOOR(UNIX_TIMESTAMP(date)/(1*60))*(1*60)) GroupTime'))->where('market_symbol', $market_symbol)->where('date',">=",$fromTs)->where('date',"<=",$todate)->groupBy('GroupTime')->orderBy('GroupTime')->limit(2000)->get()->toArray();
		}else if($tFilter=="3"){
				$coinhistory =	Coinhistory::select(\DB::raw('MAX(open) as max_open,MAX(close) as max_close,MIN(open) as min_open,MIN(close) as min_close, AVG(volumeto) as volumeto,AVG(volumefrom) as volumefrom, substring_index(group_concat(cast(open as CHAR) order by date), ",", 1 ) as first_open, substring_index(group_concat(cast(close as CHAR) order by date desc), ",", 1 ) as last_close,date AS day,from_unixtime(FLOOR(UNIX_TIMESTAMP(date)/(3*60))*(3*60)) GroupTime'))->where('market_symbol', $market_symbol)->where('date',">=",$fromTs)->where('date',"<=",$todate)->groupBy('GroupTime')->orderBy('GroupTime')->limit(2000)->get()->toArray();
		}else if($tFilter=="5"){
				$coinhistory =	Coinhistory::select(\DB::raw('MAX(open) as max_open,MAX(close) as max_close,MIN(open) as min_open,MIN(close) as min_close, AVG(volumeto) as volumeto,AVG(volumefrom) as volumefrom, substring_index(group_concat(cast(open as CHAR) order by date), ",", 1 ) as first_open, substring_index(group_concat(cast(close as CHAR) order by date desc), ",", 1 ) as last_close,date AS day,from_unixtime(FLOOR(UNIX_TIMESTAMP(date)/(5*60))*(5*60)) GroupTime'))->where('market_symbol', $market_symbol)->where('date',">=",$fromTs)->where('date',"<=",$todate)->groupBy('GroupTime')->orderBy('GroupTime')->limit(2000)->get()->toArray();
		}else if($tFilter=="15"){
				$coinhistory =	Coinhistory::select(\DB::raw('MAX(open) as max_open,MAX(close) as max_close,MIN(open) as min_open,MIN(close) as min_close, AVG(volumeto) as volumeto,AVG(volumefrom) as volumefrom, substring_index(group_concat(cast(open as CHAR) order by date), ",", 1 ) as first_open, substring_index(group_concat(cast(close as CHAR) order by date desc), ",", 1 ) as last_close,date AS day,from_unixtime(FLOOR(UNIX_TIMESTAMP(date)/(15*60))*(15*60)) GroupTime'))->where('market_symbol', $market_symbol)->where('date',">=",$fromTs)->where('date',"<=",$todate)->groupBy('GroupTime')->orderBy('GroupTime')->limit(2000)->get()->toArray();
		}else if($tFilter=="30"){
				$coinhistory =	Coinhistory::select(\DB::raw('MAX(open) as max_open,MAX(close) as max_close,MIN(open) as min_open,MIN(close) as min_close, AVG(volumeto) as volumeto,AVG(volumefrom) as volumefrom, substring_index(group_concat(cast(open as CHAR) order by date), ",", 1 ) as first_open, substring_index(group_concat(cast(close as CHAR) order by date desc), ",", 1 ) as last_close,date AS day,from_unixtime(FLOOR(UNIX_TIMESTAMP(date)/(30*60))*(30*60)) GroupTime'))->where('market_symbol', $market_symbol)->where('date',">=",$fromTs)->where('date',"<=",$todate)->groupBy('GroupTime')->orderBy('GroupTime')->limit(2000)->get()->toArray();
			
		}
		//dd(DB::getQueryLog());exit;
        //DB::enableQueryLog(); 
		//$coinhistory =	Coinhistory::select('*')->where(\DB::raw('date >= DATE_SUB(NOW(), INTERVAL 1 MONTH)'))->where('market_symbol', $market_symbol)->orderBy('date')->get()->toArray();
		//$coinhistory =	Coinhistory::select(\DB::raw('MAX(close) as price_high_24h,MIN(close) as price_low_24h, AVG(volumeto) as volumeto,AVG(volumefrom) as volumefrom, substring_index(group_concat(cast(open as CHAR) order by date), ",", 1 ) as first_open, substring_index(group_concat(cast(close as CHAR) order by date desc), ",", 1 ) as last_close,date(date) AS day'))->where('market_symbol', $market_symbol)->where('date',"<=",$todate)->groupBy('day')->orderBy('day')->limit(2000)->get()->toArray();
		//$coinhistory =	Coinhistory::select(\DB::raw('MAX(close) as price_high_24h,MIN(close) as price_low_24h, AVG(volumeto) as volumeto,AVG(volumefrom) as volumefrom, substring_index(group_concat(cast(open as CHAR) order by date), ",", 1 ) as first_open, substring_index(group_concat(cast(close as CHAR) order by date desc), ",", 1 ) as last_close,date'))->where('market_symbol', $market_symbol)->where('date',"<=",$todate)->groupBy('date')->orderBy('date')->limit(2000)->get()->toArray();
		//dd(DB::getQueryLog());exit;
		$graph_data=array();
		$open=0;
		$close=0;
		$price_high_24h=0;
		$price_low_24h=0;
		$starttime=1421366400;
		$endtime=strtotime(date("Y-m-d H:i:s"));
		$i=1;
		$c=sizeof($coinhistory);
		foreach ($coinhistory as $ch) {
			//echo $ch['day']." ";
			if($i==1){
				$starttime=strtotime($ch['day']);
			}
			if($i==$c){
				$endtime=strtotime($ch['day']);
			}
			$open=$ch['first_open'];
			$close=$ch['last_close'];

			$max_open=$ch['max_open'];
			$max_close=$ch['max_close'];
			$min_open=$ch['min_open'];
			$min_close=$ch['min_close'];

			if($max_open>$max_close){
				$high_price =$max_open;
			}else{
				$high_price =$max_close;
			}
			if($min_open<$min_close){
				$low_price =$min_open;
			}else{
				$low_price =$min_close;
			}



			/*if($open==0){
				$open=$ch['open'];
			}else{
				if($open>$ch['open']){
					$open=$ch['open'];
				}
			}*/
			/*if($close==0){
				$close=$ch['close'];
			}else{
				if($close<$ch['close']){
					$close=$ch['close'];
				}
			}*/
			//$price_high_24h=$ch['price_high_24h'];
			/*if($price_high_24h==0){
				$price_high_24h=$ch['price_high_24h'];
			}else{
				if($price_high_24h<$ch['price_high_24h']){
					$price_high_24h=$ch['price_high_24h'];
				}
			}*/
			//$price_low_24h=$ch['price_low_24h'];
			/*if($price_low_24h==0){
				$price_low_24h=$ch['price_low_24h'];
			}else{
				if($price_low_24h>$ch['price_low_24h']){
					$price_low_24h=$ch['price_low_24h'];
				}
			}*/

			$graph_data[]=array(
				"time"=>strtotime($ch['day']),"open"=>floatval($open),"high"=>floatval($high_price),"low"=>floatval($low_price),"close"=>floatval($close),"volumefrom"=>$ch['volumefrom'],"volumeto"=>$ch['volumeto']
			);
			$i++;
		}
		$gdata=array(
				"Response"=>'Success',
				"Aggregated"=> false,
				"ConversionType"=> array("type"=> "force_direct", "conversionSymbol"=> ""),
				"FirstValueInArray"=> true,
				"HasWarning"=> false,
				"RateLimit"=>[],
				"TimeFrom"=> $starttime,
				"TimeTo"=> $endtime,
				"Type"=> 100,
				"Data"=>$graph_data
			);
		return response()->json($gdata);
		//dd(DB::getQueryLog());
		//print_r($coinhistory);
	}

	public function get_graph_pairs(Request $request){
		//$coinList = CoinListing::where("is_base_currency","=",1)->where("status","=",1)->orderBy("coin_symbol", "ASC")->get();
		$coinList = CoinListing::where("status","=",1)->orderBy("coin_symbol", "ASC")->get();
		$basecurrencyList = array();
		if(@count($coinList)>0){
			$coinpair_list=array(
				"Response"=>'Success',
				"Message"=>"",
				"HasWarning"=>false,
				"Type"=>100,
				"RateLimit"=>array(),
				"Data"=>array(
					"Brexily"=>array(
						"pairs"=>array(

						),
						"isActive"=>true,
						"isTopTier"=>true
					)
				)
			);
			$pairingarr=array();
			foreach($coinList as $cv){
				$coin_id = $cv['id'];
				$basePairingCurrency = BaseCurrencyPairing::where("pairing_id","=",$coin_id)->where("status","=",1)->orderBy('trading_pairs')->get();
                
                if(@count($basePairingCurrency) > 0){
                    
                    $x=0;
                    foreach($basePairingCurrency as $pair){
                        
                        $pairingArrPrice = array();
                        $pairs = $pair['trading_pairs'];
                        $ptemp=explode("/",$pairs);
                        $pairingarr[$cv['coin_symbol']][]=$ptemp[1];
                        
                    }
                }
			}
			$coinpair_list['Data']['Brexily']['pairs']=$pairingarr;
		}
		return response()->json($coinpair_list);
		
	}
}