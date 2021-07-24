<?php
defined('ABSPATH')||die('No Script Kiddies Please');
/**
 * Makes An API Request and returns response
 */
function COURYIER_makeApiRequest($api_url,$data){

        $args = array(
                'body'        => $data,
                'timeout'     => '5',
                'redirection' => '5',
                'httpversion' => '1.0',
                'blocking'    => true,
                'headers'     => array(),
                'cookies'     => array(),
            );
        $response = wp_remote_post( $api_url, $args );
        if($response['response']['code']!=200){
                return array('error'=>true,'msg'=>$response['response']['message']);
        }
        return array('error'=>false,'response'=>$response['body']);
}


function COURYIER_makeApiRequest2($api_url,$data){

        $args = array(
                'body'        => $data,
                'timeout'     => '15',
                'redirection' => '5',
                'httpversion' => '1.0',
                'blocking'    => true,
                'headers'     => array(),
                'cookies'     => array(),
            );
        $response = wp_remote_post( $api_url, $args );
		
		
		$response= json_decode($response['body']);
		$properties = get_object_vars($response);
		$allagentrates=$properties['RtagentDetailsRate'];
   
   
   $count = 0;
   $agent_rates = array();
			for ($j=0; $j < count($allagentrates); $j++) { 
			  	if($allagentrates[$j]->rate>0){
			  		
					
					
					$agent_rates[$count]['agentcode'] = $allagentrates[$j]->agentcode;
			  		$agent_rates[$count]['rate'] = $allagentrates[$j]->rate;
			  		$count++;
			  	}
			}
			
			if(!empty($agent_rates)){
				$temp_agent_rates = array();
				foreach ($agent_rates as $key => $row)
{
    $temp_agent_rates[$key]  = $row['rate'];
}    

// Sort the data with wek ascending order, add $mar as the last parameter, to sort by the common key

array_multisort($temp_agent_rates, SORT_ASC, $agent_rates);

return $agent_rates[0];
												
			}else{
				return false;
			}
		
		
}
/**Returns Data Array TO Process  */
function COURYIER_getDataArrayToProcess($order_id){

        $order=wc_get_order($order_id);
        $payment_method=$order->get_payment_method();
        $weight = 0;
        $cost = 0;
        if($payment_method=='cod'){
                $cost=$order->get_total();
        }
        $cost=COURYIER_currency_conversion($cost,'AED',get_woocommerce_currency());
        $country = $order->get_shipping_country();
        $state=$order->get_shipping_state();
        $city=$order->get_shipping_city();
        $length=0;
        $breadth=0;
        $height=0;
        $valueOfShipment=$order->get_total();
        $valueOfShipment=COURYIER_currency_conversion($valueOfShipment,'AED',get_woocommerce_currency());
        
        $store_raw_country = get_option( 'woocommerce_default_country' );

        // Split the country/state
        $split_country = explode( ":", $store_raw_country );   
        // Country and state separated:
        $store_country = $split_country[0];
        $store_state   = $split_country[1];
        $total_qty=0;
        $good_description="";
       
        foreach ( $order->get_items() as $item_id => $item ) 
        { 
        $total_qty+=$item->get_quantity();
        $_product = $item->get_product(); 
        $good_description.=$item->get_name().",";
        $weight = $weight + ((float)$_product->get_weight()) * $item->get_quantity(); 
        $height+=((float)$_product->get_height())*$item->get_quantity();
        $length=max($length,$_product->get_length());
        $breadth=max($breadth,$_product->get_width());
        }
        $client=COURYIER_SHIPPING::get_client_details();
        /**Data For API */
        list($length,$breadth,$height)=COURYIER_get_default_dimensions_for_API($length,$breadth,$height,$weight);
        list($store_country,$country)=COURYIER_get_origin_destination_for_API($country,$state,$city,$store_country);

        $shipment_data=array(
                        "Length"=>$length,
                        "Width"=>$breadth,
                        "codAmount"=>$cost,
                        "consignee"=>$order->get_formatted_shipping_full_name(),
                        "consigneeAddress1"=>$order->get_shipping_address_1(),
                        "consigneeAddress2"=>$order->get_shipping_address_2(),
                        "consigneeCity"=>$order->get_shipping_city(),
                        "consigneeCountry"=>$store_country,
                        "consigneeFax"=>"",
                        "consigneeMob"=>$order->get_billing_phone(),
                        "consigneeName"=>$order->get_formatted_shipping_full_name(),
                        "consigneePhone"=>$order->get_billing_phone(),
                        "destination"=>$order->get_shipping_country(),
                        "goodDescription"=>$good_description,
                        "height"=>$height,
                        "origin"=>$store_country,
                        "pcs"=>$total_qty,
                        "productType"=>"dox",
                        "serviceType"=>"nor",
                        /**ship Address is merchant address */
                        "shipAdd1"=>get_option( 'woocommerce_store_address' ),
                        "shipAdd2"=>get_option( 'woocommerce_store_address_2' ),
                        "shipCity"=>get_option( 'woocommerce_store_city' ),
                        "shipContPerson"=>$order->get_formatted_shipping_full_name(),
                        "shipCountry"=>$store_country,
                        "shipFax"=>"",
                        "shipName"=>$client['name'],
                        "shipPh"=>$client['phone'],
                        "shipperRef"=>"",
                        "specialInstruction"=>"",
                        "valueOfShipment"=>$valueOfShipment,
                        "weight"=>$weight
        );
        //print_r($shipment_data);
        return $shipment_data;

}

function COURYIER_get_default_dimensions_for_API($length,$width,$height,$weight){

        if($weight=0){
                $weight=1;//For division purpose
        }
        $w=$width;
        $l=$length;
        $h=$height;
        if($l&&$w&&$h){
                return [$l,$w,$h];
        }

        $get_weight=$weight;
        //$get_weight=($length*$width*$height)/$weight;
        if($get_weight <= 1.5 ){
                $w=18;
                $l=34;
                $h=10;
           }else if($get_weight > 1.5 && $get_weight<=3){
                $w=32;
                $l=34;
                $h=10;
           }
           else if($get_weight > 3 && $get_weight<=7){
                $w=32;
                $l=34;
                $h=18;
           }
          else if($get_weight > 7 && $get_weight<=12){
                $w=32;
                $l=34;
                $h=34;
        
           }
          else if($get_weight > 12 && $get_weight<=18){
                $w=36;
                $l=42;
                $h=37;
        
           }
          else if($get_weight > 18 && $get_weight<=25){
                $w=40;
                $l=48;
                $h=39;
        
           }else{
                $w=40;
                $l=48;
                $h=39;
           }
        return [$l,$w,$h];
        
}

function COURYIER_get_origin_destination_for_API($dest_country,$dest_state,$dest_city,$store_country){
        $org=$store_country;
        $dest=$dest_country;
        if($dest_country=="AE" && $store_country=="AE"){

                $province=$dest_state;
        
                  if(strtolower($province)=="dubai"||strtolower($dest_city)=="dubai"){  // if dubai(AE) to dubai
                          $dest="DXB";
                  }
                  else if(strtolower($province)=="abudhabi"||strtolower($dest_city)=='abudhabi' || strtolower($province)=="abu dhabi"||strtolower($dest_city)=='abu dhabi'){  // if dubai(AE) to abudhabi
        
                          $dest="AUH";
                  }
                   else if(strtolower($province)=="ajman"||strtolower($dest_city)=='ajman'){  // if dubai(AE) to Ajman
        
                          $dest="AJM";		
                  }
                   else if(strtolower($province)=="fujairah"||strtolower($dest_city)=='fujairah'){   // if dubai(AE) to Fujairah
                          $dest="FUJ";
                  }
                  else if(strtolower($province)=="rasalkhaima"||strtolower($dest_city)=='rasalkhaima' || strtolower($province)=="ras al khaima"||strtolower($dest_city)=='ras al khaima'){  // if dubai(AE) to Rasalkhaima
                         $dest="RAK";
                  }
                  else if(strtolower($province)=="sharjah"||strtolower($dest_city)=='sharjah'){   // if dubai(AE) to Sharjah
                          $dest="SHJ";
                  }
                  else if(strtolower($province)=="um al quwain"||strtolower($dest_city)=='um al quwain' ||strtolower($province)=="umalquwain" ||strtolower($dest_city)=='umalquwain'){   // if dubai(AE) to Um al quwain
                          $dest="UAQ";
                  }else{
                          $dest="AE";
                  }
                  
                  $org='DXB';
        }
        return [$org,$dest];
     
	 
}

function COURYIER_send_mail_on_completion($order_id,$awb_tracking_no){
        //return true;
        $order=wc_get_order($order_id);
      
        $email=$order->get_billing_email();
        $name=$order->get_formatted_shipping_full_name();
        $to = $order->get_billing_email();
        
        $account_no=get_option('couryier_account_no');

      
        //$to ='laiji.timexpress@gmail.com';
        $items="";
        
        foreach ( $order->get_items() as $item_id => $item ) {
                $item_name=$item->get_name();
                $item_qty=$item->get_quantity();
                $items.="<tr><td>{$item_qty} x {$item_name}</td></tr>";
        }
        
        $tracking_url='https://couryier.com/track/?awbno='.$awb_tracking_no;
        
        $download_awb='http:/timeexpress.dnsalias.com:880/A6/R.aspx?A='.$awb_tracking_no;
        $subject = "Order Processed to Couryier Parcels";

        $customer_message = "
                <p><b>Dear {$name},</b></p>
                <p>Your Order with id #{$order_id} containing following items has been processed to Couryier Parcels with Tracking No. {$awb_tracking_no}</p>
                <table>
                {$items}
                </table>
                <p>You can track the status of your parcel at <a href='{$tracking_url}'>{$tracking_url}</a></p>
        
        ";
        $timexpress_message="
                <p><b>Hi Team,</b></p>
                <p>A New Order with id #{$order_id} containing following items has been processed to Couryier with Tracking No. <b>{$awb_tracking_no}</b></p>
                <p>Account Number:<b>{$account_no}</b></p>
                <p><a href='{$download_awb}'>Download AWB</a></p>
                <table>
                {$items}
                </table>

        ";

        // Always set content-type when sending HTML email
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";

        // More headers
       $headers .= 'From: <no-reply@couryier.com>' . "\r\n";
        $headers .= 'Cc: laiji.timexpress@gmail.com' . "\r\n";
        $timexpress_add="woocommerce@timexpress.ae";

        $customer_mail_status=wp_mail($to,$subject,$customer_message,$headers);
        $timexpress_mail_status=wp_mail($timexpress_add,$subject,$timexpress_message,$headers);
        return array($customer_mail_status,$timexpress_mail_status);
}

function COURYIER_getCurrencyRates(){
        $currency_api="https://api.exchangerate-api.com/v4/latest/AED";
        $rates=get_transient('currency_rates');
        //delete_transient('currency_rates');
        if($rates===false){
                //print_r("rate not found");
                $currency_rates=file_get_contents($currency_api);
                $currency_rates=json_decode($currency_rates);
                $conversion_rates=json_encode((array)$currency_rates->rates);
                set_transient('currency_rates',$conversion_rates,DAY_IN_SECONDS);
                $rates=$conversion_rates;
        }
        //print_r($rates);
        //print_r($to);
        $rates=(array)(json_decode($rates));
        return $rates;
}

function COURYIER_currency_conversion($amount, $to="USD",$from="AED"){
        // print_r($amount);
        // print_r($from);
        // print_r($to);
        $from=strtoupper($from);
        $to=strtoupper($to);
        $rates=COURYIER_getCurrencyRates();
        $currency_rate_multiplier=((float)$rates[$to])/((float)$rates[$from]);
        //print_r($currency_rate_multiplier);
        
        return (float)(round($amount*$currency_rate_multiplier,2));
}
//export orders
function exportorders(){
$out = '';

$filename_prefix = 'csv';

if (isset($_POST['csv_hdr'])) {
$out .= sanitize_text_field($_POST['csv_hdr']);
$out .= "\n";
}

if (isset($_POST['csv_output'])) {
$out .= sanitize_text_field($_POST['csv_output']);
}

$filename = $filename_prefix."_".date("Y-m-d_H-i",time());


header("Content-type: application/vnd.ms-excel");
header("Content-Encoding: UTF-8");
header("Content-type: text/csv; charset=UTF-8");
header("Content-disposition: csv" . date("Y-m-d") . ".csv");
header("Content-disposition: filename=".$filename.".csv");
echo "\xEF\xBB\xBF"; 

print $out;

exit;
}

?>
