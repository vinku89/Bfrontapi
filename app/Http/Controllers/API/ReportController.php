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

class ReportController extends BaseController 
{
	public function tradeCommissionReport(Request $request)
	{ 
		$data = $request->user();
		$user_id = $data['user_id'];
        $userId = $data['user_id'];
        $userEmail = $data['email'];
        $searchDate = $request->selectedDate;
        if(!empty($searchDate)) {
            $searchDateCondition = ' AND "'.$searchDate.'"=DATE_FORMAT(cp.commission_for, "%Y-%m")';
            $searchDateQuery = '"'.$searchDate.'"';
        } else {
            $searchDateCondition = ' AND DATE_FORMAT(NOW(), "%Y-%m")=DATE_FORMAT(cp.commission_for, "%Y-%m")';
            $searchDateQuery = ' DATE_FORMAT(NOW(), "%Y-%m") ';
        }

        $comm = DB::table('commission_payout')->select(DB::raw('SUM(commission_amount) as commission_amount'))->where('user_id',$user_id)->whereRaw('DATE_FORMAT(commission_for, "%Y-%m")='.$searchDateQuery)->get();
        if($comm[0]->commission_amount){
            $total_payout = $comm[0]->commission_amount;
        }else{
            $total_payout = '0.00';
        }
        
        $ords = DB::table('fee_deductions')->select(DB::raw('SUM(total_amount_usd) as amt'))->where('user_id',$user_id)->where('user_id',$user_id)->whereRaw('DATE_FORMAT(order_completed_at, "%Y-%m")='.$searchDateQuery)->get();
        if($ords[0]->amt){
            $total_trading = $ords[0]->amt;
        }else{
            $total_trading = '0.00';
        }
        //echo "second";
        //print_r($searchDateCondition);
        Log::info("first".json_encode($comm));
        Log::info("second".json_encode($ords));
       
        $query = 'SELECT table1.user_id, table1.email, table1.trade_date as payout_date, table1.level, COALESCE(table1.total_buy,0) as total_buy, COALESCE(table1.total_sell,0) as total_sell, COALESCE(table2.commission_amount,0) as commission_amount FROM
        (SELECT 
            DATE_FORMAT(order_completed_at, "%d-%m-%Y") trade_date, 
            0 as level, 
            '.$userId.' as user_id, 
            "'.$userEmail.'" as email,
            SUM(CASE WHEN bid_type="BUY" THEN COALESCE(total_amount_usd,0) ELSE 0 END) total_buy,
            SUM(CASE WHEN bid_type="SELL" THEN COALESCE(total_amount_usd,0) ELSE 0 END) total_sell
        FROM fee_deductions
        WHERE 
            user_id in (SELECT descendant_id from referrals where ancestor_id = '.$userId.' and 
            distance = 0) and status = 1 and
            DATE_FORMAT(order_completed_at, "%Y-%m")='.$searchDateQuery.'
            GROUP BY DATE_FORMAT(order_completed_at, "%d-%m-%Y")
        UNION ALL
        SELECT 
            DATE_FORMAT(order_completed_at, "%d-%m-%Y") trade_date, 
            1 as level, 
            '.$userId.' as user_id, 
            "'.$userEmail.'" as email,
            SUM(CASE WHEN bid_type="BUY" THEN COALESCE(total_amount_usd,0) ELSE 0 END) total_buy,
            SUM(CASE WHEN bid_type="SELL" THEN COALESCE(total_amount_usd,0) ELSE 0 END) total_sell
        FROM fee_deductions
        WHERE 
            user_id in (SELECT descendant_id from referrals where ancestor_id = '.$userId.' and  
            distance = 1) and status = 1 and
            DATE_FORMAT(order_completed_at, "%Y-%m")='.$searchDateQuery.'
            GROUP BY DATE_FORMAT(order_completed_at, "%d-%m-%Y")
        UNION ALL
        SELECT 
            DATE_FORMAT(order_completed_at, "%d-%m-%Y") trade_date, 
            2 as level, 
            '.$userId.' as user_id, 
            "'.$userEmail.'" as email,
            SUM(CASE WHEN bid_type="BUY" THEN COALESCE(total_amount_usd,0) ELSE 0 END) total_buy,
            SUM(CASE WHEN bid_type="SELL" THEN COALESCE(total_amount_usd,0) ELSE 0 END) total_sell
        FROM fee_deductions
        WHERE 
            user_id in (SELECT descendant_id from referrals where ancestor_id = '.$userId.' and  
            distance = 2) and status = 1 and
            DATE_FORMAT(order_completed_at, "%Y-%m")='.$searchDateQuery.'
            GROUP BY DATE_FORMAT(order_completed_at, "%d-%m-%Y")
        UNION ALL
        SELECT 
            DATE_FORMAT(order_completed_at, "%d-%m-%Y") trade_date, 
            3 as level, 
            '.$userId.' as user_id, 
            "'.$userEmail.'" as email,
            SUM(CASE WHEN bid_type="BUY" THEN COALESCE(total_amount_usd,0) ELSE 0 END) total_buy,
            SUM(CASE WHEN bid_type="SELL" THEN COALESCE(total_amount_usd,0) ELSE 0 END) total_sell
        FROM fee_deductions
        WHERE 
            user_id in (SELECT descendant_id from referrals where ancestor_id = '.$userId.' and  
            distance = 3) and status = 1 and
            DATE_FORMAT(order_completed_at, "%Y-%m")='.$searchDateQuery.'
            GROUP BY DATE_FORMAT(order_completed_at, "%d-%m-%Y")
        UNION ALL
        SELECT 
            DATE_FORMAT(order_completed_at, "%d-%m-%Y") trade_date, 
            4 as level, 
            '.$userId.' as user_id, 
            "'.$userEmail.'" as email,
            SUM(CASE WHEN bid_type="BUY" THEN COALESCE(total_amount_usd,0) ELSE 0 END) total_buy,
            SUM(CASE WHEN bid_type="SELL" THEN COALESCE(total_amount_usd,0) ELSE 0 END) total_sell
        FROM fee_deductions
        WHERE 
            user_id in (SELECT descendant_id from referrals where ancestor_id = '.$userId.' and  
            distance = 4) and status = 1 and
            DATE_FORMAT(order_completed_at, "%Y-%m")='.$searchDateQuery.'
            GROUP BY DATE_FORMAT(order_completed_at, "%d-%m-%Y")
        UNION ALL
        SELECT 
            DATE_FORMAT(order_completed_at, "%d-%m-%Y") trade_date, 
            5 as level, 
            '.$userId.' as user_id, 
            "'.$userEmail.'" as email,
            SUM(CASE WHEN bid_type="BUY" THEN COALESCE(total_amount_usd,0) ELSE 0 END) total_buy,
            SUM(CASE WHEN bid_type="SELL" THEN COALESCE(total_amount_usd,0) ELSE 0 END) total_sell
        FROM fee_deductions
        WHERE 
            user_id in (SELECT descendant_id from referrals where ancestor_id = '.$userId.' and 
            distance = 5) and status = 1 and
            DATE_FORMAT(order_completed_at, "%Y-%m")='.$searchDateQuery.'
            GROUP BY DATE_FORMAT(order_completed_at, "%d-%m-%Y")
        UNION ALL
        SELECT 
            DATE_FORMAT(order_completed_at, "%d-%m-%Y") trade_date, 
            6 as level, 
            '.$userId.' as user_id, 
            "'.$userEmail.'" as email,
            SUM(CASE WHEN bid_type="BUY" THEN COALESCE(total_amount_usd,0) ELSE 0 END) total_buy,
            SUM(CASE WHEN bid_type="SELL" THEN COALESCE(total_amount_usd,0) ELSE 0 END) total_sell
        FROM fee_deductions
        WHERE 
            user_id in (SELECT descendant_id from referrals where ancestor_id = '.$userId.' and  
            distance = 6) and status = 1 and
            DATE_FORMAT(order_completed_at, "%Y-%m")='.$searchDateQuery.'
            GROUP BY DATE_FORMAT(order_completed_at, "%d-%m-%Y")
        UNION ALL
        SELECT 
            DATE_FORMAT(order_completed_at, "%d-%m-%Y") trade_date, 
            7 as level, 
            '.$userId.' as user_id, 
            "'.$userEmail.'" as email,
            SUM(CASE WHEN bid_type="BUY" THEN COALESCE(total_amount_usd,0) ELSE 0 END) total_buy,
            SUM(CASE WHEN bid_type="SELL" THEN COALESCE(total_amount_usd,0) ELSE 0 END) total_sell
        FROM fee_deductions
        WHERE 
            user_id in (SELECT descendant_id from referrals where ancestor_id = '.$userId.' and  
            distance = 7) and status = 1 and
            DATE_FORMAT(order_completed_at, "%Y-%m")='.$searchDateQuery.'
            GROUP BY DATE_FORMAT(order_completed_at, "%d-%m-%Y")
        UNION ALL
        SELECT 
            DATE_FORMAT(order_completed_at, "%d-%m-%Y") trade_date, 
            8 as level, 
            '.$userId.' as user_id, 
            "'.$userEmail.'" as email,
            SUM(CASE WHEN bid_type="BUY" THEN COALESCE(total_amount_usd,0) ELSE 0 END) total_buy,
            SUM(CASE WHEN bid_type="SELL" THEN COALESCE(total_amount_usd,0) ELSE 0 END) total_sell
        FROM fee_deductions
        WHERE 
            user_id in (SELECT descendant_id from referrals where ancestor_id = '.$userId.' and  
            distance = 8) and status = 1 and
            DATE_FORMAT(order_completed_at, "%Y-%m")='.$searchDateQuery.'
            GROUP BY DATE_FORMAT(order_completed_at, "%d-%m-%Y")
        UNION ALL
        SELECT 
            DATE_FORMAT(order_completed_at, "%d-%m-%Y") trade_date, 
            9 as level, 
            '.$userId.' as user_id, 
            "'.$userEmail.'" as email,
            SUM(CASE WHEN bid_type="BUY" THEN COALESCE(total_amount_usd,0) ELSE 0 END) total_buy,
            SUM(CASE WHEN bid_type="SELL" THEN COALESCE(total_amount_usd,0) ELSE 0 END) total_sell
        FROM fee_deductions
        WHERE 
            user_id in (SELECT descendant_id from referrals where ancestor_id = '.$userId.' and  
            distance = 9) and status = 1 and
            DATE_FORMAT(order_completed_at, "%Y-%m")='.$searchDateQuery.'
            GROUP BY DATE_FORMAT(order_completed_at, "%d-%m-%Y")
        ) table1 
        INNER JOIN
        (
        SELECT 
            DATE_FORMAT(cp.commission_for, "%d-%m-%Y") as trade_date, 
            DATE_FORMAT(cp.payout_date, "%d-%m-%Y") as payout_date,
            cp.level,
            cp.user_id, 
            u.email,
            SUM(COALESCE(cp.commission_amount,0)) as commission_amount
        FROM commission_payout cp 
        LEFT JOIN users u on u.user_id=cp.user_id 
        WHERE cp.user_id = '.$userId;

        $query = $query.' '.$searchDateCondition;
        

        $query = $query.' GROUP BY DATE_FORMAT(cp.commission_for, "%d-%m-%Y"), DATE_FORMAT(cp.payout_date, "%d-%m-%Y"), cp.user_id, u.email, cp.level order by DATE_FORMAT(cp.payout_date, "%d-%m-%Y") DESC 
        ) table2 on table1.trade_date=table2.trade_date and table1.level=table2.level order by table1.trade_date desc, table1.level asc ';


        $resultSet = DB::select($query);
        $result = array();
Log::info("third".json_encode($resultSet));
        

        //return view('admin/tradeCommissionReport')->with($data);

        foreach($resultSet as $record) {
            if(array_key_exists($record->payout_date, $result)) {
                $resultKey = $result[$record->payout_date];
                $result[$record->payout_date][0]['totalTrade'] += $record->total_buy+$record->total_sell;
                $result[$record->payout_date][0]['totalCommissionAmount'] += $record->commission_amount ;

                $result[$record->payout_date][$record->level+1]['totalBuy'] = $record->total_buy;
                $result[$record->payout_date][$record->level+1]['totalSell'] = $record->total_sell;
                $result[$record->payout_date][$record->level+1]['totalTrade'] = $record->total_buy+$record->total_sell;
                $result[$record->payout_date][$record->level+1]['commissionAmount'] = $record->commission_amount;
            } else {
                $parseData = array();

                array_push($parseData, array('payoutDate'=>$record->payout_date, 'userId'=>$record->user_id, 'email'=>$record->email, 'totalLevels'=>10, 'totalTrade'=>$record->total_buy+$record->total_sell, 'totalCommissionAmount'=>$record->commission_amount));
                
                for($i=0; $i<10; $i++) {
                    $levelName = '';
                    if($i == 0) {
                        $levelName = 'Myself';
                    } else {
                        $levelName = 'Level '.$i.' TTL Sales';
                    }
                    if($record->level == $i) {
                        array_push($parseData, array('level'=>$levelName, 'totalBuy'=>$record->total_buy, 'totalSell'=>$record->total_sell, 'totalTrade'=>$record->total_buy+$record->total_sell, 'commissionAmount'=>$record->commission_amount));
                    } else {
                        array_push($parseData, array('level'=>$levelName, 'totalBuy'=>'0.00', 'totalSell'=>'0.00', 'totalTrade'=>'0.00', 'commissionAmount'=>'0.00'));
                    }
                }
                $result[$record->payout_date] = $parseData;
            }
        }

        //print_r(DB::getQueryLog());

        $data["result"] = $result;
        //echo 'Result is ';
        Log::info("final".json_encode($result));
        return response()->json(['status'=>'Success','Result'=>$result,'total_trading'=>number_format_eight_dec($total_trading),'total_payout'=>number_format_eight_dec($total_payout)], 200);
        
	}
	
}