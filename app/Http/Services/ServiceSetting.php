<?php

namespace App\Services;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\ServiceSetting;
use App\Models\PowerTransaction;
use App\Models\PowerTransactionTest;
use Illuminate\Support\Facades\Log;
use App\ApiProviders\Irecharge;
use App\ApiProviders\Quickteller;
use App\ApiProviders\KoboPay;
use App\ApiProviders\Vtpass;
use App\ApiProviders\PPM;
use App\Services\UpdateTransaction;
use App\Services\ConfirmMeter;


class ServiceSetting extends ApiController
{
  
   public function validateProvider($code, $mode, $amount, $promin, $promax){
        $provider = ServiceSetting::where('code', $code)->where('mode', $mode)->first();
       
        if($provider && $provider->enable == 'no' || $provider === NULL){
            return $this->errorResponse('Service is momentarily unavailable. Please Try again Later', Response::HTTP_SERVICE_UNAVAILABLE); // 503
        } 

        if($provider && $amount < $promin || $amount > $promax){
            return response()->json([ 'message' => 'You can only process amount between ' . $promin . ' and ' . $promax], Response::HTTP_UNAUTHORIZED);
        }

        return $provider;
     
   }


  #Check client API key
    public function checkClientAPIKey($request,  $referenceId, $faker) {
        $AuthUser = auth()->user();
        #I already know the user is authenticated from the route not check to be checking in the condition
        if($request->devkey && $AuthUser->activate_client_api == "yes"){ 
            //$referenceId = mt_rand(1000000000, mt_getrandmax());
            if ($AuthUser->client_api_mode == "test" && $AuthUser->devkey->test_key === $request->devkey){
                $api_mode = 'test_api';
                $testing_api = 'test';
                $platform = 'API TEST';
                $title = 'API Test Client ' . $request->disco . ' ' . $request->amount . " to " . $request->meter;
                return (new ConfirmMeter())->confirmMeterApiTest($request, $provider, $api_mode, $title, $referenceId, $faker);
                //return $this->confirmMeterApiTest($request, $provider, $api_mode, $title, $referenceId, $faker);
            } else if ($AuthUser->client_api_mode == "live" && $AuthUser->devkey->live_key === $request->devkey){
                $api_mode = 'live_api';
                $testing_api = 'live';
                $platform = 'API LIVE';
                $title = 'API Live Client ' . $request->disco . ' ' . $request->amount . " to " . $request->meter;
            }else {
                return $this->errorResponse('Oh sorry!! Somthing is wrong here in LVRA. Check your key or kindly contact support for assistance for support',  Response::HTTP_UNAUTHORIZED);
            }

            return [
                'testing_api' => $testing_api,
                'title' => $title,
            ];

        }else {
            return $this->errorResponse('Invalid authorization. Please contact support for assistance.', Response::HTTP_UNAUTHORIZED);
        }
    }


    public function checkAPIType($request, $paymentMethod){
        $AuthUser = auth()->user();
        
        $powerTransaction = PowerTransactionTest::where('identifier', $request->transaction_id)->first();
        
        if ($powerTransaction == null) {
            return $this->errorResponse('Transaction ID not Found', 401);
        }

        if ($AuthUser->client_api_mode == "test" && $AuthUser->devkey->test_key === $request->devkey){
            $testing_api = 'test';
           // return $this->updateTransactionForTestApis($powerTransaction, $faker);
            return  (new UpdateTransaction())->updateTransactionForTestApis($powerTransaction, $faker);
        }else if($AuthUser->client_api_mode == "live" && $AuthUser->devkey->live_key === $request->devkey) {
            // find the transaction identifier from live
            $powerTransaction = PowerTransaction::where('identifier', $request->transaction_id)->first();
            $testing_api = 'live';
        }else{
            return $this->errorResponse('Oh sorry!! Somthing is wrong here in LVRA. Check your key or kindly contact support for assistance for support',  Response::HTTP_UNAUTHORIZED);
        }

        # I really don't know why I am doing this here maybe when i look at the Model PowerTransaction I will know
        if ($powerTransaction->status == "Fail" || $powerTransaction->status == "Success") {
            return $this->errorResponse("Please reconfirm transaction. Current Transaction ID is " . $powerTransaction->status . "no longer available for processing", 401);
        }

        $gatewayRequestLog = json_decode($request->payment_response_log);
        $gateway_log = $request->payment_response_log;

        if ($request->payment_gateway == 'paystack' || $gatewayRequestLog->status == "1") {
            $verifying_gateway_transaction = 'success';
        }
        
        if (!$request->payment_response_log) {
            $this->errorResponse('Transaction card status failed.', 401);
        }

        $provider = ServiceSetting::where('code', $powerTransaction->disco)->where('mode', $powerTransaction->mode)->first();
        $processTransaction = $this->processTrasaction($paymentMethod, $request, $powerTransaction, $provider, $testing_api);
        
    }
 
 
    private function processTransaction($paymentMethod, $request, $powerTransaction, $provider, $testing_api){
        if ($payment_method == "card"){ 
             // make sure to collect from verified transaction from api
             $payment_details = [
                'payment_method' => 'card',
                'log' => $request->payment_response_log,
                'reference' => $request->payment_reference,
                'message' => 'success',
                'status' => "00",
                'gateway' => $request->payment_gateway,
            ];
            
        }else if($payment_method == "wallet"){
            if (auth()->user()->wallet->balance < $powerTransaction->amount_paid) {
                return $this->errorResponse('Low Wallet!!!. Your wallet balance is '.auth()->user()->wallet->balance.' and its too low to make this transaction of '.$powerTransaction->amount_paid.'. Please kindly contact support to fund wallet', 401);
            }
            $payment_details = [
                'payment_method' => 'wallet',
                'log' => 'wallet',
                'reference' => auth()->user()->first_name,
                'message' => 'successful wallet withdrawal',
                'status' => 'wallet',
                'gateway' => 'wallet',
            ];
        }

        switch ($provider->api_provider) {
            case 'irecharge':
                $response = Irecharge::vendPower($powerTransaction, $testing_api);
                break;
            case 'quickeller':
                return response()->json([ 'provider' => 'interswitch' ]);
            case 'kobopay':
                KoboPay::processTransaction($powerTransaction, $testing_api = "live");
                break;
            case 'vtpass':
                $response = Vtpass::vendVtpassFix($powerTransaction, $testing_api);
                break;
            case 'ppm':
                $response = PPM::vendPower($powerTransaction, $testing_api);
                break;
            default:
                return response()->json([
                    'error' => 'undefined connection'
                ]);
        }

        Log::info($response);

        // go vend id payment method is successful
        return  (new UpdateTransaction())->update($powerTransaction, $response, $payment_details);
       // return $this->updateTransaction($powerTransaction, $response, $payment_details);

    }
 

}