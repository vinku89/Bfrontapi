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



use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Connection\AMQPConnection;
use App\Http\Controllers\rabbitmq\RabbitmqController;
use App\Events\buy;

class EaringBtcTadingController extends BaseController
{
    

    
    public function earingBtcListOfRecords(Request $request)
    {
//DB::enableQueryLog();


  $data['earingRecords'] = DB::table('brexily_user_earnings')->select(\DB::raw('DATE(brexily_user_earnings.date) as dateonly , brexily_user_earnings.package_amount , brexily_user_earnings.lock_period_percentage, brexily_user_earnings.lock_period_months   , coin_listing.coin_name , coin_listing.coin_symbol,coin_listing.coin_price'))->leftJoin('coin_listing', 'brexily_user_earnings.coin_listing_id', '=', 'coin_listing.id')->where('user_id' , $request['user_id'])->get();


$data["package_list"] = DB::table('brexily_package_list')->get();
$data["locking_period_list"] = DB::table('brexily_lock_period')->get();
$data["coin_listing"] =   DB::table('coin_listing')->select('id', 'coin_listing.coin_name' , 'coin_listing.coin_symbol' )->whereIn('coin_symbol', ['evr','btc','eth','ltc'])->get();


//  echo $request['user_id'];
//dd($data['earingRecords']);


        return response()->json(["Success"=>true,'status' => 200,'Result' => $data], 200);
        


    }


}
