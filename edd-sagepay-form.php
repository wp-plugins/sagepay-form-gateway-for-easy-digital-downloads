<?php
/**
 * Plugin Name: SagePay Form Gateway for Easy Digital Downloads
 * Plugin URI: http://www.patsatech.com/
 * Description: Easy Digital Downloads Plugin for accepting payment through SagePay Form Gateway.
 * Version: 1.0.0
 * Author: PatSaTECH
 * Author URI: http://www.patsatech.com
 * Contributors: patsatech
 * Requires at least: 3.5
 * Tested up to: 4.1
 *
 * Text Domain: sagepay_patsatech
 * Domain Path: /lang/
 *
 * @package SagePay Form Gateway for Easy Digital Downloads
 * @author PatSaTECH
 */

add_action( 'plugins_loaded', 'edd_sagepay_load_textdomain' );

function edd_sagepay_load_textdomain() {
	load_plugin_textdomain('sagepay_patsatech', false, dirname( plugin_basename( __FILE__ ) ) . '/lang');
}

// registers the gateway
function sagepay_register_gateway($gateways) {
	$gateways['sagepay'] = array('admin_label' => 'SagePay Form', 'checkout_label' => __('Credit Card', 'sagepay_patsatech'));
	return $gateways;
}
add_filter('edd_payment_gateways', 'sagepay_register_gateway');

function edd_sagepay_remove_cc_form() {
    // we only register the action so that the default CC form is not shown
	edd_default_cc_address_fields();
}
add_action( 'edd_sagepay_cc_form', 'edd_sagepay_remove_cc_form' );

// processes the payment
function sagepay_process_payment($purchase_data) {
    global $edd_options;
    
    // check there is a gateway name
    if ( ! isset( $purchase_data['post_data']['edd-gateway'] ) )
    return;
    
    // collect payment data
    $payment_data = array( 
        'price'         => $purchase_data['price'],
        'date'          => $purchase_data['date'],
        'user_email'    => $purchase_data['user_email'],
        'purchase_key'  => $purchase_data['purchase_key'],
        'currency'      => edd_get_currency(),
        'downloads'     => $purchase_data['downloads'],
        'user_info'     => $purchase_data['user_info'],
        'cart_details'  => $purchase_data['cart_details'],
        'gateway'       => 'sagepay',
        'status'        => 'pending'
     );
    
	$errors = edd_get_errors();
	
	if ( $errors ) {
        // problems? send back
		edd_send_back_to_checkout('?payment-mode=' . $purchase_data['post_data']['edd-gateway']);
    }else{
	
    	$payment = edd_insert_payment( $payment_data );
		
	    // check payment
	    if ( !$payment ) {
		
			edd_send_back_to_checkout('?payment-mode=' . $purchase_data['post_data']['edd-gateway']);
			
	    } else {
			
			if( $edd_options['sagepay_mode'] == 'simulator' ){
				$gateway_url = 'https://test.sagepay.com/Simulator/VSPFormGateway.asp';
			}else if( $edd_options['sagepay_mode'] == 'test' ){
				$gateway_url = 'https://test.sagepay.com/gateway/service/vspform-register.vsp';
			}else if( $edd_options['sagepay_mode'] == 'live' ){
				$gateway_url = 'https://live.sagepay.com/gateway/service/vspform-register.vsp';
			}
			
	        $time_stamp = date("ymdHis");
	        $orderid = $edd_options['sagepay_vendor_name'] . "-" . $time_stamp . "-" . $payment;
			
	        $sp_arg['Amount'] 				= $purchase_data['price'];
			$sp_arg['CustomerName']			= substr($purchase_data['post_data']['edd_first'].' '.$purchase_data['post_data']['edd_last'], 0, 100);
	        $sp_arg['CustomerEMail'] 		= substr($purchase_data['post_data']['edd_email'], 0, 255);
	        $sp_arg['BillingFirstnames'] 	= substr($purchase_data['post_data']['edd_first'], 0, 20);
	        $sp_arg['BillingSurname'] 		= substr($purchase_data['post_data']['edd_last'], 0, 20);
	        $sp_arg['BillingAddress1'] 		= substr($purchase_data['post_data']['card_address'], 0, 100);
	        $sp_arg['BillingAddress2'] 		= substr($purchase_data['post_data']['card_address_2'], 0, 100);
	        $sp_arg['BillingCity'] 			= substr($purchase_data['post_data']['card_city'], 0, 40);
			if( $purchase_data['post_data']['billing_country'] == 'US' ){
	        	$sp_arg['BillingState'] 		= $purchase_data['post_data']['card_state'];
			}else{
	        	$sp_arg['BillingState'] 		= '';
			}
	        $sp_arg['BillingPostCode'] 		= substr($purchase_data['post_data']['card_zip'], 0, 10);
	        $sp_arg['BillingCountry'] 		= $purchase_data['post_data']['billing_country'];
	        $sp_arg['BillingPhone'] 		= '';
	        $sp_arg['DeliveryFirstnames'] 	= substr($purchase_data['post_data']['edd_first'], 0, 20);
	        $sp_arg['DeliverySurname'] 		= substr($purchase_data['post_data']['edd_last'], 0, 20);
	        $sp_arg['DeliveryAddress1'] 		= substr($purchase_data['post_data']['card_address'], 0, 100);
	        $sp_arg['DeliveryAddress2'] 		= substr($purchase_data['post_data']['card_address_2'], 0, 100);
	        $sp_arg['DeliveryCity'] 			= substr($purchase_data['post_data']['card_city'], 0, 40);
			if( $purchase_data['post_data']['billing_country'] == 'US' ){
	        	$sp_arg['DeliveryState'] 		= $purchase_data['post_data']['card_state'];
			}else{
	        	$sp_arg['DeliveryState'] 		= '';
			}
	        $sp_arg['DeliveryPostCode'] 	= substr($purchase_data['post_data']['card_zip'], 0, 10);
	        $sp_arg['DeliveryCountry'] 		= $purchase_data['post_data']['billing_country'];
	        $sp_arg['DeliveryPhone'] 		= '';
	        $sp_arg['FailureURL'] 			= trailingslashit(home_url()).'?sagepay=ipn';
	        $sp_arg['SuccessURL'] 			= trailingslashit(home_url()).'?sagepay=ipn';
	        $sp_arg['Description'] 			= sprintf(__('Order #%s' , 'sagepay_patsatech'), $payment);
	        $sp_arg['Currency'] 			= $edd_options['currency'];
	        $sp_arg['VendorTxCode'] 		= $orderid;
	        $sp_arg['VendorEMail'] 			= $edd_options['sagepay_email'];
	        $sp_arg['SendEMail'] 			= $edd_options['sagepay_sendemails'];
	        $sp_arg['eMailMessage']			= $edd_options['sagepay_customer_message'];
	        $sp_arg['Apply3DSecure'] 		= $edd_options['sagepay_apply3d'];
			
	        $post_values = "";
	        foreach( $sp_arg as $key => $value ) {
	            $post_values .= "$key=" . trim( $value ) . "&";
	        }
	      	$post_values = substr($post_values, 0, -1);
			
			$params['VPSProtocol'] = 3.00;
			$params['TxType'] = $edd_options['sagepay_transaction_type'];
			$params['Vendor'] = $edd_options['sagepay_vendor_name'];
		    $params['Crypt'] = encryptAndEncode($post_values);
		  	
			$sagepay_arg_array = array();
			
			foreach ($params as $key => $value) {
				$sagepay_arg_array[] = '<input type="hidden" name="'.esc_attr( $key ).'" value="'.esc_attr( $value ).'" />';
			}
			
			echo '<form action="'.$gateway_url.'" method="post" name="sagepay_payment_form" >
					' . implode('', $sagepay_arg_array) . '
					</form>		
					<b> Please wait while you are being redirected.</b>			
					<script type="text/javascript" event="onload">
							document.sagepay_payment_form.submit();
					</script>';
			
	    }
		
	}
	
}
add_action('edd_gateway_sagepay', 'sagepay_process_payment');

function sagepay_ipn() {
	global $edd_options;

	if ( isset($_GET['crypt']) && !empty($_GET['crypt']) && $_GET['sagepay'] == 'ipn') {
	
        $return = decode(str_replace(' ', '+',$_REQUEST['crypt']));
        
		$payment = explode('-',$return['VendorTxCode']);
			
		if ( $return['Status'] == 'OK' || $return['Status'] == 'AUTHENTICATED'|| $return['Status'] == 'REGISTERED' ) {
			
			edd_empty_cart();
							
			edd_update_payment_status($payment[2], 'publish');	
				
			$returnurl = add_query_arg( 'payment-confirmation', 'sagepay', get_permalink( $edd_options['success_page'] ) );
						
			edd_insert_payment_note( $payment[2], 'Payment Completed.' );
			
			edd_set_payment_transaction_id( $payment[2], $return['VPSTxId'] );
				
			wp_redirect( $returnurl ); exit;
						        
		}else{
			
			$message = sprintf(__('Transaction Failed. The Error Message was %s', 'sagepay_patsatech'), $return['StatusDetail'] );
			
			edd_insert_payment_note( $payment[2], $message );
			
			edd_set_error( 'error_tranasction_failed', $message );
			
			edd_send_back_to_checkout('?payment-mode=sagepay');
			
		}	
	}
}
add_action( 'init', 'sagepay_ipn' );

function sagepay_add_settings($settings) {
 	
	$sagepay_settings = array(
		array(
			'id' => 'sagepay_settings',
			'name' => '<strong>' . __('SagePay Form Settings', 'sagepay_patsatech') . '</strong>',
			'desc' => __('Configure the gateway settings', 'sagepay_patsatech'),
			'type' => 'header'
		),
		array(
			'id' => 'sagepay_vendor_name',
			'name' => __('Vendor Name', 'sagepay_patsatech'),
			'desc' => __('Please enter your vendor name provided by SagePay.', 'sagepay_patsatech'),
			'type' => 'text',
			'size' => 'regular'
		),
		array(
			'id' => 'sagepay_vendor_password',
			'name' => __('Encryption Password', 'sagepay_patsatech'),
			'desc' => __('Please enter your encryption password provided by SagePay.', 'sagepay_patsatech'),
			'type' => 'text',
			'size' => 'regular'
		),
		array(
			'id' => 'sagepay_email',
			'name' => __('Vendor E-Mail', 'sagepay_patsatech'),
			'desc' => __('An e-mail address on which you can be notified when a transaction completes.', 'sagepay_patsatech'),
			'type' => 'text',
			'size' => 'regular'
		),
		array(
			'id' => 'sagepay_sendemails',
			'name' => __('Send E-Mail', 'sagepay_patsatech'),
			'desc' => __('Select Who to send e-mails to.', 'sagepay_patsatech'),
			'type' => 'select',
			'options' => array(
								'0' => 'No One',
								'1' => 'Customer and Vendor',
								'2' => 'Vendor Only'
								),
			'size' => 'regular'
		),
		array(
			'id' => 'sagepay_customer_message',
			'name' => __('Customer E-Mail Message', 'sagepay_patsatech'),
			'desc' => __('A message to the customer which is inserted into the successful transaction e-mails only.', 'sagepay_patsatech'),
			'type' => 'text',
			'size' => 'regular'
		),
		array(
			'id' => 'sagepay_mode',
			'name' => __('Mode Type', 'sagepay_patsatech'),
			'desc' => __('Select Simulator, Test or Live modes.', 'sagepay_patsatech'),
			'type' => 'select',
			'options' => array(
								'test' => 'Test',
								'live' => 'Live'
								),
			'size' => 'regular'
		),
		array(
			'id' => 'sagepay_apply3d',
			'name' => __('Apply 3D Secure', 'sagepay_patsatech'),
			'desc' => __('Select whether to allow 3dsecure verificaiton or not.', 'sagepay_patsatech'),
			'type' => 'select',
			'options' => array(
								'1' => 'Yes',
								'0' => 'No'
								),
			'size' => 'regular'
		),
		array(
			'id' => 'sagepay_transaction_type',
			'name' => __('Transaction Type', 'sagepay_patsatech'),
			'desc' => __('Select Trasaction Type Payment, Deferred or Authenticated.', 'sagepay_patsatech'),
			'type' => 'select',
			'options' => array(
								'PAYMENT' => 'Payment', 
								'DEFFERRED' => 'Deferred', 
								'AUTHENTICATE' => 'Authenticate'
								),
			'size' => 'regular'
		),
	);
 
	return array_merge($settings, $sagepay_settings);	
}
add_filter('edd_settings_gateways', 'sagepay_add_settings');

function encryptAndEncode($strIn) {
	global $edd_options, $post;
	$strIn = pkcs5_pad($strIn, 16);
	return "@".bin2hex(mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $edd_options['sagepay_vendor_password'], $strIn, MCRYPT_MODE_CBC, $edd_options['sagepay_vendor_password']));
}
		
function decodeAndDecrypt($strIn) {
	global $edd_options, $post;
	$strIn = substr($strIn, 1);
	$strIn = pack('H*', $strIn);
	return mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $edd_options['sagepay_vendor_password'], $strIn, MCRYPT_MODE_CBC, $edd_options['sagepay_vendor_password']);
}

function pkcs5_pad($text, $blocksize)	{
	$pad = $blocksize - (strlen($text) % $blocksize);
	return $text . str_repeat(chr($pad), $pad);
}

function decode($strIn) {
	$decodedString = decodeAndDecrypt($strIn);
	parse_str($decodedString, $sagePayResponse);
	return $sagePayResponse;
}

?>
