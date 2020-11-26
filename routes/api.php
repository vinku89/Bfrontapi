<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

/*Route::middleware('auth:api')->get('/user', function (Request $request) {
 return $request->user();
});*/

//Route::post('register', 'API\RegisterController@register');

Route::any('brexco_service', 'API\BrexcoController@brexco_service');
Route::post('brxlysignup', 'API\RegisterController@signup');
Route::post('createPassword', 'API\RegisterController@createPassword');
Route::post('verification-status', 'API\RegisterController@verificationStatus');

Route::post('login', 'API\LoginController@login');
Route::post('mobile_login', 'API\LoginController@mobile_login');

Route::post('recover-password', 'API\LoginController@recoverPassword');
Route::post('reset-password', 'API\LoginController@resetPassword');
Route::post('twofa_status', 'API\LoginController@twofa_status');   

Route::any('buy_orders', 'API\ExchangeController@buy_orders');
Route::any('sell_orders', 'API\ExchangeController@sell_orders');
Route::any('tradehistory', 'API\ExchangeController@tradehistory');
Route::any('getMarketPriceValues', 'API\ExchangeController@getMarketPriceValues');
Route::any('confirm_withdraw_transaction/{dtId?}', 'API\WalletController@confirm_withdraw_transaction');
Route::any('cancel_withdraw_transaction/{dtId?}', 'API\WalletController@cancel_withdraw_transaction');
Route::any('lockacc_withdraw_transaction/{dtId?}', 'API\WalletController@lockacc_withdraw_transaction');

Route::any('getExchangeDetails', 'API\ExchangeController@getExchangeDetails');
//Graph
Route::get('graph_data/{fsym}/{tsym}/{fromTs}/{toTs}/{tFilter}', 'API\GraphController@graph_data');
Route::any('get_graph_pairs', 'API\GraphController@get_graph_pairs');
// Base currency list
Route::any('baseCurrencyList', 'API\ExchangeController@baseCurrencyList');    
Route::any('getBaseCurrencyPairingList', 'API\ExchangeController@getBaseCurrencyPairingList');
Route::any('getPairingList', 'API\ExchangeController@getBaseCurrencyPairingList');

//accessries 
Route::any('coinInfo/{short_name?}', 'API\AccessController@coininfo');
Route::any('marketCurrncyList', 'API\AccessController@marketCurrncyList');


Route::middleware('auth:api')->group( function () { 
/////Mobile API
Route::get('allWalletsList', 'API\MobileWalletController@allWalletsList');
Route::get('activeWalletsList', 'API\MobileWalletController@activeWalletsList');
Route::get('mobileUserDetails', 'API\MobileUserController@mobileUserDetails');

///Mobile API

Route::any('userDetails', 'API\UserController@userDetails'); //done
Route::get('getCounties', 'API\UserController@getCounties');

Route::any('update-profile', 'API\UserController@updateProfile');
Route::any('fetch-referral', 'API\UserController@fetchReferral');
Route::any('my-referral', 'API\UserController@myReferral');
Route::any('add-2FA-authentication', 'API\UserController@addTwoFAAuthentication');
Route::any('google-qrcode-render', 'API\UserController@googleQrcodeRender');
Route::any('google-verify-token', 'API\UserController@googleVerifyToken');
Route::any('getExchange', 'API\ExchangeController@getExchange');


Route::any('buy', 'API\ExchangeController@buy');
Route::any('sell', 'API\ExchangeController@sell');

Route::any('ordersList', 'API\ExchangeController@ordersList');
Route::any('ordersHistory', 'API\ExchangeController@ordersHistory');

Route::any('order_cancel', 'API\ExchangeController@order_cancel');

//KYC
Route::any('save_kyc', 'API\KycController@save_kyc');
Route::any('get_kyc_status', 'API\KycController@get_kyc_status');



//Wallet
Route::any('walletlist', 'API\WalletController@walletlist');
Route::any('withdrawRequest', 'API\WalletController@withdrawRequest');
Route::any('transfer_main_to_trading_acc', 'API\WalletController@transfer_main_to_trading_acc');
Route::any('transfer_trading_to_main_acc', 'API\WalletController@transfer_trading_to_main_acc');
Route::any('verifyUserByEmail', 'API\WalletController@verifyUserByEmail');
Route::any('transfer_internally', 'API\WalletController@transfer_internally');

//Deposit
Route::any('wallet_address_check', 'API\DepositController@wallet_address_check');
Route::any('create_api_session', 'API\DepositController@create_api_session');





//Address Book
Route::any('save_address_book', 'API\AddressBookController@saveAddressBook');
Route::any('get_address_book', 'API\AddressBookController@getAddressBook');


//Payment History
Route::any('paymentHistory', 'API\TradeHistoryController@paymentHistory');

//btcearn trading 
Route::any('earingBtcListOfRecords', 'API\EaringBtcTadingController@earingBtcListOfRecords');



//Brexco
Route::get('brexco_countries_list', 'API\BrexcoController@brexcoCountriesList');
Route::get('getDefaultServices', 'API\BrexcoController@getDefaultServices');
Route::get('getCountryServices/{country}', 'API\BrexcoController@getCountryServices');

Route::get('getBrexcoCryptos', 'API\BrexcoController@getBrexcoCryptos');
Route::get('getCountryOperators/{country}/{portal_service_id}', 'API\BrexcoController@getCountryOperators');
Route::get('getBrexcoProducts/{country}/{portal_service_id}/{operator_id}', 'API\BrexcoController@getBrexcoProducts');
Route::get('getBrexcoConfirmDet/{crypto}/{amount}/{currency_type}', 'API\BrexcoController@getBrexcoConfirmDet');


Route::get('brexco_sel_country_data/{country}', 'API\BrexcoController@brexco_sel_country_data');
Route::post('brexcoTransaction', 'API\BrexcoController@brexcoTransaction');
Route::post('brexcoTransactionsList/{crypto?}/{page?}', 'API\BrexcoController@brexcoTransactionsList');
});

/******** Start of Mobile App Apis Everus to Brexily Users ********/

Route::post('AppLogin', 'MobileApp\AppLoginController@login');


/******** End of Mobile App Apis Everus to Brexily Users ********/