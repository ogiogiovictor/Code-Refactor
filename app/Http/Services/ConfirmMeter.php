<?php

namespace App\Services;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Auth;
use App\Models\Role;
use Illuminate\Http\Request;
use App\Models\PowerTransaction;
use App\Models\PowerTransactionTest;

class ConfirmMeter extends ApiController
{
    public function confirmMeterApiTest($request, $provider, $api_mode, $title, $referenceId, $faker)
    {
        $percentageToDeduct = $request->amount * $provider->percentage_commission;
        $deductCommission = $request->amount - $percentageToDeduct;

        /*calculate amount to be deducted for vendor*/
        if (auth()->user() && auth()->user()->role->name == 'vendor') {
            $role = auth()->user()->role->id;
            $amountPaid = $deductCommission;
            $service_charge = 0;
            $service_commission = $percentageToDeduct;
            $user = auth()->user()->id ? auth()->user()->id : "NULL";
        } else {
            $amountPaid = $request->amount + $provider->service_charge;
            $service_charge = $provider->service_charge;
            $service_commission = 0;
            $user_role = Role::where('name', 'visitor')->first();
            $role = $user_role->id;
            $user = 0;
        }

        $response = [
            'status'                => '00',
            'meter'                 => $request->meter ? $request->meter : null,
            'message'               => 'Successful',
            'api_code'              => $request->disco,
            'access_token'          => $faker->numberBetween(110034850, 90000000),
            'customer_name'         => $faker->name,
            'customer_address'      => 'G/LADA Und St. Lekki phase 1',
            'util'                  => 'Power Utility Bill' . $request->disco,
            'minimum_purchase'      => '1023',
        ];


        if ($response['status'] != 00) {
            return $this->showMessage($response, 401);
        }

        $transaction_message = 'Transaction Ready';
        // create accessoken to validate error
        $access_token = NULL;
        // if access token is unavailable from response
        $access_token = $response['access_token'] ? $response['access_token'] : 'NULL';

        // create new transaction 
        $powerTransaction = new PowerTransactionTest;
        $powerTransaction->title = $title;
        $powerTransaction->meter = $request->meter;
        $powerTransaction->disco = $provider->code;
        $powerTransaction->mode = $request->mode;
        $powerTransaction->customer_name = $response['customer_name'];
        $powerTransaction->customer_address = $response['customer_address'];
        $powerTransaction->minimum_purchase = $response['minimum_purchase'];
        $powerTransaction->amount = $request->amount;
        $powerTransaction->amount_paid = $amountPaid;
        $powerTransaction->service_charge = $service_charge;
        $powerTransaction->commission_applied = $percentageToDeduct;
        $powerTransaction->user_id = $user;
        $powerTransaction->role_id = $role;
        $powerTransaction->transaction_id = $referenceId;
        $powerTransaction->email = $request->email;
        $powerTransaction->phone = $request->phone;
        $powerTransaction->ip_address = $request->ip_address;
        $powerTransaction->device = $request->device;
        $powerTransaction->platform = 'API TEST';
        $powerTransaction->api_access_token = $response['access_token'];
        $powerTransaction->api_type = $request->disco;
        $powerTransaction->api_code = $response['status'];
        $powerTransaction->api_used = $provider->api_provider;
        $powerTransaction->api_reference = $response['status'];
        $powerTransaction->api_meter_response = $response['status'];
        $powerTransaction->api_meter_log = json_encode($response);

        /*if meter is unsuccessful*/
        if ($response['status'] != "00") {
            $powerTransaction->status = 'Pending';
            $powerTransaction->remarks = 'Processing Meter ' . $transaction_message . ' API Response Says: ' . $response['message'] . ' For ' . $title;
        }

        $powerTransaction->save();

        $data = [
            'title' => $powerTransaction->status . ' ' . $title,
            'transaction_id' => $powerTransaction->identifier,
            'transaction_message' => $transaction_message,
            'minimum_vend_amount' => '1023',
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
        if ($request->payment_method != "" && $request->payment_method == "wallet" && auth()->user() && auth()->user()->wallet->balance < $powerTransaction->amount_paid) {
            $pay_method = "wallet";
            return $this->buyUnits($request, $pay_method);
        }

        return $this->showMessage($data, 200);

    }

}