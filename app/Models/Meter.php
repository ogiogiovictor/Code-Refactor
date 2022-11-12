<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Meter extends Model
{
    use HasFactory;

    public function storeMeter($response){
        $find_meter = Meter::where('number', $response['meter'])->where('name', $response['customer_name'])->first();
        if ($find_meter == null) {
            $meter = new Meter;
            $meter->number = $response['meter'];
            $meter->name = $response['customer_name'];
            $meter->address = $response['customer_address'];
            $meter->minimum_purchase = $response['minimum_purchase'] ? $response['minimum_purchase'] : 0;
            $meter->save();
            $meter_id = $meter->id;
            $meter_minimum_purchase = $meter->minimum_purchase;
        }else {
            $meter_id = $find_meter->id;
            $meter_minimum_purchase = $find_meter->minimum_purchase;
        }
        return [
            'meter_minimum_purchase' => $meter_minimum_purchase,
            'meter_id' => $meter_id
        ];
    }
}
