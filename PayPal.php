<?php

class PayPal extends MerchantInterface
{	

	const USERNAME = 'johndoe'; //sandbox
	const PASSWORD = 'iofa89en8924nf'; //sandbox
	const SIGNATURE = 'jkfdfaoifeijwfiejjvmiemv'; //sandbox
	
	const API_URL = 'https://api-3t.paypal.com/nvp';
	const CHECKOUT_URL = 'https://www.paypal.com/cgi-bin/webscr?cmd=_express-checkout';
	
	function SetExpressCheckout($transaction,$order)
	{                                                                 
		$totalAmount = number_format($transaction->totalAmount,2);             
		$currencyCode = $transaction->currencyCode;                            
		$salesUrl = $order->salesUrl;                                     
		
		if(strpos($salesUrl,'?')!==false)
			$salesUrl = substr($salesUrl,0,strpos($salesUrl,'?'));
			
		$params = array (
			'METHOD' => 'SetExpressCheckout',
			'PAYMENTREQUEST_0_AMT' => $totalAmount,
			'PAYMENTREQUEST_0_CURRENCYCODE' => $currencyCode,
			'RETURNURL' => $salesUrl."?paypalAccept=1",
			'CANCELURL' => $salesUrl,
			'VERSION' => '95',
			'USER' => $this->apiUsername,
			'PWD' => $this->apiPassword,
			'SIGNATURE' => $this->apiSignature,
			'CALLBACKTIMEOUT'=>'6',
			'PAYMENTREQUEST_0_PAYMENTACTION' => 'Sale'
		);

		$request = new HttpRequest(self::API_URL,'POST');
		$request->body =  http_build_query( $params ) ;
		HttpClient::sendRequest($request);
		
		$this->logRequest($request);
		
		parse_str($request->response->body,$rparams);
		$rparams = (object) $rparams;
		
		if($rparams->ACK == 'Success')
		{
			$token = $rparams->TOKEN;
			$order->paypalUrl = self::CHECKOUT_URL."&token=".$token;			
		}
		else
		{
			$error = 'Unknown Paypal Error';
			if(!empty($rparams->L_SHORTMESSAGE0))
				$error = $rparams->L_SHORTMESSAGE0;
			
			
			throw new ValidationException($error);	
		}
	}
	
	function DoExpressCheckout($transaction,$order)
	{
		
		$totalAmount = number_format($transaction->totalAmount,2);
		$currencyCode = $transaction->currencyCode;
		
		$params = array(
			'METHOD' => 'DoExpressCheckoutPayment',
			'VERSION' => '95',
			'USER' => $this->apiUsername,
			'PWD' => $this->apiPassword,
			'SIGNATURE' => $this->apiSignature,
			'TOKEN' => $order->paypalToken,
			'PAYERID' => $order->paypalPayerId,
			'PAYMENTREQUEST_0_PAYMENTACTION' => 'Sale',
			'PAYMENTREQUEST_0_AMT' => $totalAmount,
			'PAYMETNREQUEST_0_CURRENCYCODE' => $currencyCode
		);
		
		$request = new HttpRequest(self::API_URL,'POST');
		$request->body =  http_build_query( $params ) ;
		HttpClient::sendRequest($request);
		
		$this->logRequest($request);
		
		parse_str($request->response->body,$rparams);
		$rparams = (object) $rparams;
		
		if($rparams->PAYMENTINFO_0_ACK == 'Success')
		{
			$transaction->responseType = 'SUCCESS';
			$transaction->merchantTransactionId = $rparams->PAYMENTINFO_0_TRANSACTIONID;
			$transaction->responseText = $rparams->PAYMENTINFO_0_PAYMENTSTATUS;
			return;
		}
	
		$transaction->responseType = 'HARD_DECLINE';
		$transaction->merchantTransactionId = $rparams->PAYMENTINFO_0_TRANSACTIONID;
		$transaction->responseText = $rparams->L_LONGMESSAGE0;
		
	}
	
	function authorize(Transaction $transaction, $order)
	{
		
		$merchant = $this->merchant;
		
		$params = (object) array();
		$params->USER = $this->merchant->apiUsername;
		$params->PWD = $this->merchant->apiPassword;
		$params->SIGNATURE = $this->merchant->apiSignature;
		
		$params->type = 'auth';
		$params->orderid = $order->clientOrderId;
		$params->METHOD = 'DoDirectPayment';
		$params->PAYMENTACTION = 'Authorization';
		$params->VERSION = 95;
		$params->ITEMAMT = $transaction->totalPrice;
		$params->SHIPPINGAMT = $transaction->totalShipping;
		$params->CURRENCYCODE = $transaction->currencyCode;
		$params->AMT = $transaction->totalAmount;
		$params->TAXAMT = $transaction->salesTax;
					
		$params->ACCT = $order->cardNumber;
		$params->EXPDATE = $order->cardExpiryDate->format('mY');
		$params->CVV2 = $order->cardSecurityCode;
		
		$params->EMAIL = $order->emailAddress;
		$params->FIRSTNAME = $order->firstName;
		$params->LASTNAME = $order->lastName;
		$params->STREET = $order->address1;
		$params->STREET2 = $order->address2;
		$params->CITY = $order->city;
		$params->STATE = $order->state;
		$params->COUNTRYCODE = $order->country;
		$params->ZIP = $order->postalCode;
		
		$params->SHIPPINGAMT = $transaction->totalShipping;
		$params->SHIPTONAME = $order->shipFirstName .' '. $order->shipLastName;
		$params->SHIPTOSTREET = $order->shipAddress1;
		$params->SHIPTOSTREET2 = $order->shipAddress2;
		$params->SHIPTOCITY = $order->shipCity;
		$params->SHIPTOSTATE = $order->shipState;
		$params->SHIPTOCOUNTRY = $order->shipCountry;
		$params->SHIPTOZIP = $order->shipPostalCode;
		$params->SHIPTOPHONENUM= $order->phoneNumber;
		
		$request = new HttpRequest(self::API_URL,'POST');
		$request->body =  http_build_query( $params ) ;
		HttpClient::sendRequest($request);
		
		$this->logRequest($request);
		
		parse_str($request->response->body,$rparams);
		$rparams = (object) $rparams;
		
		$transaction->merchantTransactionId = $rparams->TRANSACTIONID;
		$transaction->responseType = $rparams->ACK == 'Success' ? "SUCCESS" : "HARD_DECLINE";
		$transaction->responseText = $rparams->ACK == 'Success' && !empty($rparams->L_SHORTMESSAGE0) ? $rparams->L_SHORTMESSAGE0 : $rparams->ACK;
		$transaction->avsResponse = $rparams->AVSCODE;
		$transaction->cvvResponse = $rparams->CVV2MATCH;
	}
	
	function capture(Transaction $transaction, $order)
	{ 

		$transactionId = $transaction->merchantTransactionId;
		
		$merchant = $this->merchant;
		$params = (object) array();
		
		$params->USER = $this->merchant->apiUsername;
		$params->PWD = $this->merchant->apiPassword;
		$params->SIGNATURE = $this->merchant->apiSignature;
		
		$params->type = 'capture';
		$params->METHOD = 'DoCapture';
		$params->VERSION = '95';
		$params->COMPLETETYPE = 'Complete';
		$params->AUTHORIZATIONID = $transactionId;
		$params->AMT = $transaction->totalAmount;
		$params->CURRENCYCODE = $transaction->currencyCode;
				
		$request = new HttpRequest(self::API_URL,'POST');
		$request->body =  http_build_query( $params ) ;
		HttpClient::sendRequest($request);
		
		$this->logRequest($request);
		
		parse_str($request->response->body,$rparams);
		$rparams = (object) $rparams;

		$transaction->merchantTransactionId = $rparams->TRANSACTIONID;
		$transaction->responseType = $rparams->ACK == 'Success' ? "SUCCESS" : "HARD_DECLINE";
		$transaction->responseText = $rparams->ACK == 'Success' && !empty($rparams->L_SHORTMESSAGE0) ? $rparams->L_SHORTMESSAGE0 : $rparams->ACK;
		
	}
	
	function void(Transaction $transaction, $order)
	{
		$authorizationId = $transaction->merchantTransactionId;
		$merchant = $this->merchant;
		$params = (object) array();
		
		$params->type = 'void';
		$params->METHOD = 'DoVoid';
		$params->VERSION = '95';
		$params->AUTHORIZATIONID = $authorizationId;
				
		$params->USER = $this->merchant->apiUsername;
		$params->PWD = $this->merchant->apiPassword;
		$params->SIGNATURE = $this->merchant->apiSignature;
		
		$request = new HttpRequest(self::API_URL,'POST');
		$request->body =  http_build_query( $params ) ;
		HttpClient::sendRequest($request);
		
		$this->logRequest($request);
		
		parse_str($request->response->body,$rparams);
		$rparams = (object) $rparams;

		$transaction->responseType = $rparams->ACK == 'Success' ? "SUCCESS" : "HARD_DECLINE";
		$transaction->responseText = $rparams->ACK == 'Success' && !empty($rparams->L_SHORTMESSAGE0) ? $rparams->L_SHORTMESSAGE0 : $rparams->ACK;
	}
	
	function sale(Transaction $transaction, $order)
	{
		
		$merchant = $this->merchant;
		$params = (object) array();
		
		$params->METHOD = 'DoDirectPayment';
		$params->VERSION = 95;
		$params->type = 'sale';
		$params->USER = $this->merchant->apiUsername;
		$params->PWD = $this->merchant->apiPassword;
		$params->SIGNATURE = $this->merchant->apiSignature;
		
		$params->ITEMAMT = $transaction->totalPrice;
		$params->AMT = $transaction->totalAmount;
		$params->SHIPPINGAMT = $transaction->totalShipping;
		$params->TAXAMT = $transaction->salesTax;
		$params->CURRENCYCODE = $transaction->currencyCode;
		
		$params->IPADRESS = $order->ipAddress;
		$params->ACCT = $order->cardNumber;
		$params->EXPDATE = $order->cardExpiryDate->format('mY');
		$params->CVV2 = $order->cardSecurityCode;
		
		$params->EMAIL = $order->emailAddress;
		$params->FIRSTNAME = $order->firstName;
		$params->LASTNAME = $order->lastName;
		$params->STREET = $order->address1;
		$params->STREET2 = $order->address2;
		$params->CITY = $order->city;
		$params->STATE = $order->state;
		$params->COUNTRYCODE = $order->country;
		$params->ZIP = $order->postalCode;
		
		$params->SHIPTONAME = $order->shipFirstName .' '. $order->shipLastName;
		$params->SHIPTOSTREET = $order->shipAddress1;
		$params->SHIPTOSTREET2 = $order->shipAddress2;
		$params->SHIPTOCITY = $order->shipCity;
		$params->SHIPTOSTATE = $order->shipState;
		$params->SHIPTOCOUNTRY = $order->shipCountry;
		$params->SHIPTOZIP = $order->shipPostalCode;
		
		$request = new HttpRequest(self::API_URL,'POST');
		$request->body =  http_build_query( $params ) ;
		HttpClient::sendRequest($request);
		
		$this->logRequest($request);
		
		parse_str($request->response->body,$rparams);
		$rparams = (object) $rparams;
		

		$transaction->merchantTransactionId = $rparams->TRANSACTIONID;
		$transaction->responseType = $rparams->ACK == 'Success' ? "SUCCESS" : "HARD_DECLINE";
		$transaction->responseText = $rparams->ACK == 'Success' && !empty($rparams->L_SHORTMESSAGE0) ? $rparams->L_SHORTMESSAGE0 : $rparams->ACK;
		$transaction->avsResponse = $rparams->AVSCODE;
		$transaction->cvvResponse = $rparams->CVV2MATCH;
		
	}
	
	function refund(Transaction $transaction)
	{
		
		$merchant = $this->merchant;
		$params = (object) array();
	
		$params->METHOD = 'RefundTransaction';
		$params->VERSION = 95;
		$params->type = 'refund';
		$params->USER = $this->merchant->apiUsername;
		$params->PWD = $this->merchant->apiPassword;
		$params->SIGNATURE = $this->merchant->apiSignature;
		$params->TRANSACTIONID = $transaction->merchantTransactionId;
		$params->REFUNDTYPE = 'Partial';
		$params->AMT = $transaction->totalAmount;
		$params->CURRENCYCODE = $transaction->currencyCode;
				
		$request = new HttpRequest(self::API_URL,'POST');
		$request->body =  http_build_query( $params ) ;
		HttpClient::sendRequest($request);
		
		$this->logRequest($request);
		
		parse_str($request->response->body,$rparams);
		$rparams = (object) $rparams;
		
		$transaction->merchantTransactionId = $rparams->TRANSACTIONID;
		$transaction->responseType = $rparams->ACK == 'Success' ? "SUCCESS" : "HARD_DECLINE";
		$transaction->responseText = $rparams->ACK != 'Success' && !empty($rparams->L_LONGMESSAGE0) ? $rparams->L_LONGMESSAGE0 :  $rparams->ACK;
		
	}
}
