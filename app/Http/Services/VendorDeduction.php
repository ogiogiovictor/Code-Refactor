<?php

namespace App\Services;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Auth;
use App\Models\Role;

class ServiceSetting extends ApiController
{

    public function chargeVendor($request,  $provider) {
        $AuthUser = auth()->user();

        /*calculate amount to be deducted for vendors users and api user and not test users*/
        $percentageToDeduct = $request->amount * $provider->percentage_commission;
        $deductCommission = $request->amount - $percentageToDeduct;

         /*calculate amount to be deducted for vendor*/
         if ($AuthUser->role->name == 'vendor') { 
            $role = auth()->user()->role->id;
            $amountPaid = $deductCommission;
            $service_charge = 0;
            $service_commission = $percentageToDeduct;
            $user = auth()->user()->id ? auth()->user()->id : "NULL";
         }else{
            $amountPaid = $request->amount + $provider->service_charge;
            $service_charge = $provider->service_charge;
            $service_commission = 0;
            $user_role = Role::where('name', 'visitor')->first();
            $role = $user_role->id;
            $user = 0;
         }

         return [
            'amountPaid' => $amountPaid,
            'service_charge' => $service_charge,
            'percentageToDeduct ' =>  $percentageToDeduct,
            'user' => $user,
            'role' => $role,
         ]

    }
}