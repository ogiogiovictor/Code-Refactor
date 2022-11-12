<?php

namespace App\Services;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Auth;
use App\Models\Role;
use Illuminate\Http\Request;
use App\Models\WalletTransaction;
use App\Models\Wallet;

class UpdateTransaction extends ApiController
{

    public function update($powerTransaction, $response, $payment_details) {
        // check if the transaction is initially failed!!! kill the transaction
        if ($powerTransaction->status == 'success'){
            return $this->errorResponse('you have already performed a successful transaction with the transaction id', 401);
        }

        // check if the transaction is initially failed!!! kill the transaction
        if ($powerTransaction->status == 'failed'){
            return $this->errorResponse('this transaction id failed at previous attempt. please try again later', 401);
        }

        // check if the transaction is initially failed!!! kill the transaction
        $powerTransaction->payment_method = $payment_details['payment_method'];
        $powerTransaction->payment_gateway = $payment_details['gateway'];
        $powerTransaction->payment_log = $payment_details['log'];
        $powerTransaction->payment_message = $payment_details['message'];
        $powerTransaction->payment_status = $payment_details['status'];
        $powerTransaction->payment_reference = $payment_details['reference'];

        if ($response['status'] == 00) {
            $powerTransaction->units = $response['units'];
            $powerTransaction->token = $response['token'];
            $powerTransaction->api_vend_response = $response['status'];
            $powerTransaction->remarks = 'Successful Meter Token ' . $response['token'] . ' ' . $powerTransaction->title;
            $powerTransaction->api_vend_log = json_encode($response['token_process_log']);
            $powerTransaction->status = 'success';

            $status_code = "00";

            if(auth()->user()){
                $find_wallet = Wallet::where('user_id', Auth::id())->first();
                $find_wallet->balance -= $powerTransaction->amount_paid;
                $find_wallet->save();

                $walletTransaction = new walletTransaction;
                $walletTransaction->title = $powerTransaction->title;
                $walletTransaction->identifier = $powerTransaction->identifier;
                $walletTransaction->user_id = $powerTransaction->user_id;
                $walletTransaction->details = $powerTransaction->details;
                $walletTransaction->amount = $powerTransaction->amount;
                $walletTransaction->amount_paid = $powerTransaction->amount_paid;
                $walletTransaction->category = 'debit';
                $walletTransaction->status = $powerTransaction->status;
                $walletTransaction->balance = $find_wallet->balance;

                $powerTransaction->walletTransaction()->save($walletTransaction);
            }
        } else {
            $powerTransaction->api_vend_response = $response['status'];
            $powerTransaction->api_vend_log = json_encode($response['token_process_log']);

            // incase the user has paid for the transaction and api providers pay set status to retry and create a cron jobs  that would help execute till the token gets to the owner
            if ($payment_details['message'] == 'success' && $payment_details['payment_method'] == 'card') {

                $powerTransaction->status = 'retry';
                $powerTransaction->remarks = 'Sorry!!! Your payment was successful but there is power service network error and system would retry in 20 minutes to generate your token. We would be in touch with you as we are aware of this error on Transaction ID ' . $powerTransaction->title.' Please kindly be patient with us.';

                $status_code = "666";
            } else {
                $powerTransaction->status = 'failed';
                $powerTransaction->remarks = 'Failed Meter Token ' . $powerTransaction->title;
                $status_code = "999";
            }
        }

        $powerTransaction->save();

        $data = [
            "status" => $status_code,
            "message" => $powerTransaction->remarks,
            "meter_number" => $powerTransaction->meter->number,
            "token" => $powerTransaction->token,
            "units" => $powerTransaction->units,
            'amount'  => $powerTransaction->amount ? $powerTransaction->amount : null,
            'amount_paid' => $powerTransaction->amount_paid ? $powerTransaction->amount_paid : null,
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



    public function updateTransactionForTestApis($transaction, $faker){
     
        $response = [
            'meter_number'      => $transaction->meter ? $transaction->meter : null,
            'disco'             => $transaction->disco ? $transaction->disco : null,
            'api_code'          => $transaction->api_code ? $transaction->api_code : null,
            'access_token'      => $transaction->api_access_token ? $transaction->api_access_token : null,
            'api_reference'     => $transaction->api_reference ? $transaction->api_reference : null,
            'customer_name'     => $transaction->customer_name,
            'customer_address'  => $transaction->customer_address,
            'minimum_purchase'  => $transaction->minimum_purchase,
            'type'              => 'Business',
            'class'             => 'Ten',
            'status'            => '00',
            'message'           => 'successful',
            'amount'            => $transaction->amount ? $transaction->amount : null,
            'units'             => round($transaction->amount / 25.5, 1),
            'token'             => $faker->numberBetween(1340, 4349) . $faker->numberBetween(1940, 4949). $faker->numberBetween(1300, 4949) . $faker->numberBetween(1940, 4349) . $faker->numberBetween(1940, 4349),
        ];

        if ($transaction->status == 'success'){
            return $this->showMessage('you have already performed a successful transaction with the transaction id', 401);
        }

        if ($transaction->status == 'failed'){
            return $this->showMessage('this transaction id failed at previous attempt. please try again later', 401);
        }

        $transaction->payment_method = 'wallet api';
        $transaction->payment_gateway = 'wallet api';
        $transaction->payment_log = 'wallet';
        $transaction->payment_message = 'success';
        $transaction->payment_status = 00;
        $transaction->payment_reference = 'mtR'.uniqid(true);;

        if ($response['status'] == 00) {
            $transaction->units = $response['units'];
            $transaction->token = $response['token'];
            $transaction->api_vend_response = $response['status'];
            $transaction->remarks = 'Successful Meter Token ' . $response['token'] . ' ' . $transaction->title;
            $transaction->status = 'success';
        } 

        $transaction->save();
        return $this->showMessage($response, 200);
    }

}