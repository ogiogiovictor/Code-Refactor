<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\ServiceSetting;
use App\ApiProviders\Irecharge;
use App\ApiProviders\Quickteller;
use App\ApiProviders\KoboPay;
use App\ApiProviders\Vtpass;
use App\ApiProviders\PPM;
use App\Models\Role;
use App\Models\PowerTransaction;
use App\Models\PowerTransactionTest;
use App\Models\WalletTransaction;
use App\Models\Wallet;
use App\Models\Meter;
use Validator;
use Auth;
use Faker\Generator as Faker;
use Illuminate\Support\Facades\Log;

use Symfony\Component\HttpFoundation\Response;
use App\Http\Requests\ValidateMeterRequest;
use App\Services\ServiceSetting;
use App\Services\VendorDeduction;
use App\Services\BuyUnits;
use App\Model\Meter;


class PowerController extends ApiController
{
   # Here we inject ValidateMeter Request to validate the meter.
    public function confirmMeter(ValidateMeterRequest $request, Faker $faker){
        #Generate a reference Key
        $referenceId = mt_rand(1000000000, mt_getrandmax());

        #Check if the provider is subscribe to the our service
        $provider = (new ServiceSetting())->validateProvider($request->disco,  $request->mode, $request->amount, $provider->minimum_amount, $provider->maximum_amount);

        #Check client API key
        $checkClientAPIKey = (new ServiceSetting())->checkClientAPIKey($request,  $referenceId, $faker);

        # Calculate the amount to be deducted from the vendor
        $chargeVendor = (new VendorDeduction())->chargeVendor($request,  $provider) 

        # Make the Payment Switch
        if($checkClientAPIKey['valid']){
            $paymentSwitch = $this->switchPayment($request, $provider->api_provider, $referenceId, $checkClientAPIKey['testing_api']);
        }
       
        #Save to Meter Table depending on condition
        $saveMeter = Meter::storeMeter($paymentSwitch);

        if ($request->amount < $saveMeter['meter_minimum_purchase']) {
            return $this->errorResponse('Transaction is not going to process. Please vend a higher amount', Response::HTTP_UNAUTHORIZED);
        }

        #store power transaction
        $storepowertransaction = $this->storepowerTransaction($checkClientAPIKey, $paymentSwitch, $request, $chargeVendor,  $referenceId, $provider);
    }



    private function storepowerTransaction($checkClientAPIKey, $response, $request, $chargeVendor,  $referenceId, $provider, $saveMeter){
        $transaction_message = 'Transaction Ready';
        // create accessoken to validate error
        $access_token = NULL;
        // if access token is unavailable from response
        $access_token = $response['access_token'] ? $response['access_token'] : 'NULL';
        // create new transaction 
        $powerTransaction = new PowerTransaction;
        $powerTransaction->title = $checkClientAPIKey['title'];
        $powerTransaction->meter_id = $saveMeter['meter_id'];
        $powerTransaction->disco = $provider->code;
        $powerTransaction->mode = $request->mode;
        $powerTransaction->amount = $request->amount;
        $powerTransaction->amount_paid = $chargeVendor['amountPaid'];
        $powerTransaction->service_charge = $chargeVendor['service_charge'];
        $powerTransaction->commission_applied = $chargeVendor['percentageToDeduct'];
        $powerTransaction->user_id = $chargeVendor['user'];
        $powerTransaction->role_id = $chargeVendor['role'];
        $powerTransaction->transaction_id = $referenceId;
        $powerTransaction->email = $request->email;
        $powerTransaction->phone = $request->phone;
        $powerTransaction->ip_address = $request->ip_address;
        $powerTransaction->device = $request->device;
        $powerTransaction->platform = $request->platform;
        $powerTransaction->api_access_token = $access_token;
        $powerTransaction->api_type = $request->disco;
        $powerTransaction->api_code = $response['api_code'];
        $powerTransaction->api_used = $provider->api_provider;
        $powerTransaction->api_reference = $referenceId;
        $powerTransaction->api_meter_response = $response['status'];
        $powerTransaction->api_meter_log = json_encode($response);

        /*if meter is unsuccessful*/
        if ($response['status'] != "00") {
            $powerTransaction->status = 'Failed';
            $powerTransaction->remarks = 'Failed Meter process ' . @$transaction_message . ' API Response Says: ' . @$response['message'] . ' For ' . $title;
        } else {
            $powerTransaction->status = 'Pending';
            $powerTransaction->remarks = 'Processing Meter ' . @$transaction_message . ' API Response Says: ' . @$response['message'] . ' For ' . $title;
        }

        $powerTransaction->save();

        $data = [
            'title' => $powerTransaction->status . ' ' . $title,
            'transaction_id' => $powerTransaction->identifier,
            'transaction_message' => $transaction_message,
            'minimum_vend_amount' => $meter_minimum_purchase,
            'receiver' => $request->meter,
            'contact' => $request->phone,
            'email' => $request->email,
            'amount' => $powerTransaction->amount,
            'service_charge' => $service_charge,
            'service_commission' => $service_commission,
            'total' => $service_charge + $powerTransaction->amount - $service_commission,
            'status' => $powerTransaction->status,
            'details' => $response,
        ];

          /*if the invinsible payment method is available*/
          if ($request->payment_method != "" && $request->payment_method == "wallet" &&  auth()->user()->wallet->balance < $powerTransaction->amount_paid) {
            $pay_method = "wallet";
            return (new BuyUnits())->buyUnits($powerTransaction, $pay_method, $request);
            //return $this->buyUnits($request, $pay_method);
             }

         return $this->showMessage($data, Response::HTTP_OK); //200
}



    private function switchPayment($request, $providerId, $referenceId, $testing_api){
        switch ($providerId) {
            case 'irecharge':
                $response = Irecharge::vendPowerForVendors($request, $referenceId, $testing_api);
                break;
            case 'quickteller':
                return $response = Quickteller::paymentAdvice($request, $referenceId, $testing_api);
                break;
            case 'kobopay':
                $response = KoboPay::validateTransaction($request, $referenceId, $testing_api);
                break;
            case 'vtpass':
                $response = Vtpass::vendVtpassVerifyMerchant($request, $referenceId, $testing_api);
                break;
            case 'ppm':
                $response = PPM::confirmCustomer($request, $referenceId, $testing_api);
                break;
            default:
                return response()->json([ 'error' => 'undefined' ]);
        }

        if ($response['status'] != 00) { return $this->errorResponse($response, Response::HTTP_UNAUTHORIZED); }
        return $response;
      
    }


   


}
