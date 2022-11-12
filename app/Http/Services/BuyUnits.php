<?php

namespace App\Services;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Auth;
use App\Models\Role;
use Illuminate\Http\Request;
use Validator;
use App\Services\ServiceSetting;

class BuyUnits extends ApiController
{

    public function buyUnits($request, $pay_method) {

        $validator = Validator::make($request->all(), [
            'transaction_id' => 'required',
            'payment_method' => 'required',
         ]);

         $paymentMethod = $this->payType($pay_method);

         if($request->devkey && $AuthUser->activate_client_api == "yes"){ 

            $provider = (new ServiceSetting())->checkAPIType($request,  $paymentMethod);

         }else {
             return $this->errorResponse('Invalid authorization. Please contact support for assistance.', Response::HTTP_UNAUTHORIZED);
         }
    }



    private function payType($pay_method) {
        if ($pay_method == "wallet") {
          return  $payment_method = "wallet";
        } elseif ($request->payment_method == "wallet") {
            return $payment_method = "wallet";
        } elseif ($request->payment_method == "card") {
            return $payment_method = "card";
        }else{
            return  $payment_method;
        }
    }

}