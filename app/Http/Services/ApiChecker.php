<?php

namespace App\Services;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Auth;
use App\Models\Role;
use Illuminate\Http\Request;
use App\Models\WalletTransaction;
use App\Models\Wallet;

class ApiChecker extends ApiController
{

    public function apiCheckTransaction(Request $request)
    {
        $validator = Validator::make($request->all(), [
           'devkey' => 'required',
           'transaction_id' => 'required',
        ]);

        if ($validator->fails()) {
           return $this->errorResponse($validator->errors(), 401);
        }

        if($request->devkey && auth()->user() && auth()->user()->activate_client_api == "yes"){
            if(!auth()->user()->devkey->test_key == $request->devkey || !auth()->user()->devkey->live_key == $request->devkey){
                return $this->errorResponse('Invalid key authorization.', 401);
            }
            if ($request->devkey && auth()->user()->client_api_mode == "test"){
                if (auth()->user()->devkey->test_key === $request->devkey){
                    $api_mode = 'test_api';
                    $testing_api = 'test';
                    $platform = 'API TEST';
                    $balance = 1000000.00;
                    $message = 'success';

                    if($testing_api = 'test'){
                        $powerTransaction = PowerTransactionTest::where('identifier', $request->transaction_id)->first();
                    }

                }else{
                    return $this->errorResponse('Oh sorry!! Somthing is wrong here in TSTR. You are currently on the test mode. Please kindly contact support for assistance for live activation', 401);
                }
            }elseif ($request->devkey && auth()->user()->client_api_mode == "live"){
                if (auth()->user()->devkey->live_key === $request->devkey){
                    $api_mode = 'live_api';
                    $testing_api = 'live';
                    $platform = 'API LIVE';
                    $balance = auth()->user()->wallet->balance;
                    $message = 'success';

                    if($testing_api = 'live'){
                        $powerTransaction = PowerTransaction::where('identifier', $request->transaction_id)->first();
                    }

                }else{
                    return $this->errorResponse('Oh sorry!! Somthing is wrong here in LVRA. You are currently on the test mode. Please kindly contact support for assistance for live activation', 401);
                }
            }
        }

        if ($powerTransaction == null){
            return $this->errorResponse('Oh sorry!! We cannot find any related transaction. Please initiate a transaction.', 401);
        }

        $data = [
            "status" => "00",
            "message" => $powerTransaction->remarks,
            "meter_number" => $powerTransaction->meter->number ? $powerTransaction->meter->number : $powerTransaction->meter,
            "token" => $powerTransaction->token,
            "units" => $powerTransaction->units,
            'amount'            => $powerTransaction->amount ? $powerTransaction->amount : null,
            'amount_paid'            => $powerTransaction->amount_paid ? $powerTransaction->amount_paid : null,
            "disco"=> $powerTransaction->disco,
            "api_code"=> $powerTransaction->api_code,
            "access_token" => $powerTransaction->api_access_token,
            "api_reference" => $powerTransaction->api_reference,
            "customer_name"=> $powerTransaction->meter->name,
            "customer_address"=> $powerTransaction->meter->address,
            "minimum_purchase"=> $powerTransaction->meter->minimum_purchase,
            "type" => $powerTransaction->meter->type,
            "class" => $powerTransaction->meter->class,
        ];


        return $this->showMessage($data, 200);
    }

}