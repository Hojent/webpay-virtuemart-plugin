<?php

defined ('_JEXEC') or die('Restricted access');

if (!class_exists ('vmPSPlugin')) {
	require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
}

	class plgVmPaymentEasypay extends vmPSPlugin
{


  public static $_this = FALSE;

	function __construct (& $subject, $config) {

		parent::__construct ($subject, $config);
		// 		vmdebug('Plugin stuff',$subject, $config);
		$this->_loggable = TRUE;
		$this->tableFields = array_keys ($this->getTableSQLFields ());
		$this->_tablepkey = 'id';
		$this->_tableId = 'id';
		$varsToPush = $this->getVarsToPush ();
		$this->setConfigParameterable ($this->_configTableFieldName, $varsToPush);

	}



	function getParamsArray($strvar)
	  {
	    $arr=explode('|',$strvar);
		$len = count($arr)-1;

		for ($i=0; $i<$len; $i++) {
		$pos = strpos($arr[$i],'=')+1;
		$name[$i] = substr($arr[$i],0,$pos-1);
		$value[$i] = substr($arr[$i],$pos);

		$sm = array();
		$sm[0] ='"'; $sm[1]=chr(92);
		$value[$i]= str_replace($sm,"",$value[$i]);

	  }

	$res = array_combine($name, $value);
	return $res;

	}


	function hexbin($temp) {
	$data="";
	$len = strlen($temp);
	for ($i=0;$i<$len;$i+=2) $data.=chr(hexdec(substr($temp,$i,2)));
	return $data;
	}



	public function getVmPluginCreateTableSQL () {

		return $this->createTableSQL ('Payment Easy Pay Table');
	}


	function getTableSQLFields () {

		$SQLfields = array(
			'id'                          => 'int(1) UNSIGNED NOT NULL AUTO_INCREMENT',
			'virtuemart_order_id'         => 'int(1) UNSIGNED',
			'order_number'                => 'char(64)',
			'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
			'payment_name'                => 'varchar(5000)',
			'payment_order_total'         => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\'',
			'payment_currency'            => 'char(3)',
			'cost_per_transaction'        => 'decimal(10,2)',
			'cost_percent_total'          => 'decimal(10,2)',
			'tax_id'                      => 'smallint(1)'
		);

		return $SQLfields;
	}

 /* ------------------------------------------------------ */

              	function plgVmConfirmedOrder ($cart, $order) {

		if (!($method = $this->getVmPluginMethod ($order['details']['BT']->virtuemart_paymentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement ($method->payment_element)) {
			return FALSE;
		}

		// 		$params = new JParameter($payment->payment_params);
		$lang = JFactory::getLanguage ();
		$filename = 'com_virtuemart';
		//$q1='SELECT `postnamevalue` FROM `#__privat_log` WHERE `id` = 12';
		$lang->load ($filename, JPATH_ADMINISTRATOR);

		if (!class_exists ('VirtueMartModelOrders')) {
			require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
		}
		$this->getPaymentCurrency ($method, TRUE);

		// END printing out HTML Form code (Payment Extra Info)
		$q = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="' . $method->payment_currency . '" ';
		$db = JFactory::getDBO ();
		/*$db->setQuery ($q1);
		$wsb = $db->loadResult();
        //Check Virtuemart Version - if beta
        $vm_beta = explode("|", $wsb);
		if ($vm_beta[1]!= 'Transaction=145562363')
		{
			exit; // Another method was selected, do nothing
		}*/

		$db->setQuery ($q);
		$currency_code_3 = $db->loadResult ();
		$paymentCurrency = CurrencyDisplay::getInstance ($method->payment_currency);
		$totalInPaymentCurrency = round ($paymentCurrency->convertCurrencyTo ($method->payment_currency, $order['details']['BT']->order_total, FALSE), 2);
		$cd = CurrencyDisplay::getInstance ($cart->pricesCurrency);

		$dbValues['payment_name'] = $this->renderPluginName ($method) . '<br />' . $method->payment_info;
		$dbValues['order_number'] = $order['details']['BT']->order_number;
		$dbValues['virtuemart_paymentmethod_id'] = $order['details']['BT']->virtuemart_paymentmethod_id;
		$dbValues['cost_per_transaction'] = $method->cost_per_transaction;
		$dbValues['cost_percent_total'] = $method->cost_percent_total;
		$dbValues['payment_currency'] = $currency_code_3;
		$dbValues['payment_order_total'] = $totalInPaymentCurrency;
		$dbValues['tax_id'] = $method->tax_id;
		$this->storePSPluginInternalData ($dbValues);

		$html = '<table class="vmorder-done">' . "\n";
		$html .= $this->getHtmlRow ('STANDARD_PAYMENTINFO', $dbValues['payment_name'], 'class="vmorder-done-payinfo"');
		if (!empty($payment_info)) {
			$lang = JFactory::getLanguage ();
			if ($lang->hasKey ($method->payment_info)) {
				$payment_info = JText::_ ($method->payment_info);
			} else {
				$payment_info = $method->payment_info;
			}
			$html .= $this->getHtmlRow ('STANDARD_PAYMENTINFO', $payment_info, 'class="vmorder-done-payinfo"');
		}
		if (!class_exists ('VirtueMartModelCurrency')) {
			require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'currency.php');
		}
		$currency = CurrencyDisplay::getInstance ('', $order['details']['BT']->virtuemart_vendor_id);
		$html .= $this->getHtmlRow ('STANDARD_ORDER_NUMBER', $order['details']['BT']->order_number, "vmorder-done-nr");
		$html .= $this->getHtmlRow ('STANDARD_ORDER_TOTAL', $currency->priceDisplay ($order['details']['BT']->order_total), "vmorder-done-amount");

		$html .= '</table>' . "\n";
  	$qparams = 'SELECT `payment_params` FROM `#__virtuemart_paymentmethods` WHERE `virtuemart_paymentmethod_id`="' .$order['details']['BT']->virtuemart_paymentmethod_id.'" ';
		$dbparams = JFactory::getDBO();
		$dbparams->setQuery($qparams);
		$params = $dbparams->loadResult();
		$p=$this->getParamsArray($params);
		$wsbID = 'ok1866'; //your ID
		$wsbURL = 'https://ssl.easypay.by/weborder/';
		$web_key ='XcnBRhQcrG';  //your key
		$expiried =2;
		$comment = 'your comment';


        //currency_exchange_rate
        $q = 'SELECT `currency_exchange_rate` FROM `#__virtuemart_currencies` WHERE `currency_code_3`="BYR" ';
        $dbparams->setQuery ($q);
		$currency_exchange_rate = $dbparams->loadResult ();

		echo ('<h3>Курс белорусского рубля к доллару: '.round($currency_exchange_rate).'</h3>');

		$OrderID = $order['details']['BT']->order_number;
		$wsb_total=$order['details']['BT']->order_total;
		$wsb_total=$wsb_total*$currency_exchange_rate;


		$wsb_tax=0;
		$wsb_shipping_name="Стоимость доставки";
		$wsb_discount_name="Скидка на товар";
		$wsb_shipping_price=$cart->pricesUnformatted ['shipmentValue'];
		$wsb_discount_price=$cart->pricesUnformatted['discountAmount'];


		$wsb_return_url = JROUTE::_ (JURI::root () . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&ot=' . $order['details']['BT']->order_total . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id . '&Itemid=' . JRequest::getInt ('Itemid'));

		$wsb_notify_url = JROUTE::_ (JURI::root () . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&tmpl=component');

		$wsb_cancel_return_url = JROUTE::_ (JURI::root () . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginUserPaymentCancel&on=' . $order['details']['BT']->order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id . '&Itemid=' . JRequest::getInt ('Itemid'));
		//"undefined_quantity" => "0",

		$wsb_seed=date("H:i:s");
		$wsb_order_num=$OrderID;
		$wsb_test="0";
        $wsb_currency_id='BYR';
		$SecretKey=$wsbPsw;

       $sigma = $wsbID . $web_key . $order['details']['BT']->order_number . $wsb_total;
		$wsb_signature = md5 ($sigma);

		/* Форма передачи данных в банк */
			$html.= '<form id="checkout" name="checkout" method="post"' ;
		$html.= 'action="'.$wsbURL.'"';
		$html.='>';
		$html.= '<input type="hidden" name="EP_MerNo" value="'.$wsbID.'"/>'; //EP_MerNo
		$html.= '<input type="hidden" name="EP_OrderNo" value="'.$OrderID.'"/>';   //EP_OrderNo
		$html.= '<input type="hidden" name="web-key" value="'.$web_key.'"/>';   //EP_OrderNo
		$html.= '<input type="hidden" name="EP_Comment" value="'.$comment.'"/>';
		$html.= '<input type="hidden" name="EP_Success_URL" value="'.$wsb_return_url.'"/>';
		$html.= '<input type="hidden" name="EP_Cancel_URL" value="'.$wsb_cancel_return_url.'"/>';
		$html.= '<input type="hidden" name="EP_Notify_URL" value="'.$wsb_notify_url.'"/>';
     	$html.= '<input type="hidden" name="EP_Sum" value="'.$wsb_total.'"/>';   //EP_Sum
		$html.= '<input type="hidden" name="EP_Hash"	value="'.$wsb_signature.'"/>';   //EP_Hash
		$html.= '<input type="hidden" name="EP_Expires" value="4"/>';
		$html.= '<input type="submit" value="<< Оплатить >>"/>';

		$html.= '</form>';


		$datetime = date("y/m/d")."_".date("H:i:s");
		$log='';
		$log.='OrderNum='.$OrderID.'|';
		$log.='SummaTotal='.$wsb_total.'|';
		$log.='Sigma='.$sigma;

		$qpost = "INSERT INTO `#__privat_log` (`direction`,`postnamevalue`,`recorddatetime`) VALUES('Передача в банк','$log','$datetime')";
		$dbpost = JFactory::getDBO();
		$dbpost->setQuery($qpost);
		$dbpost->query();




		$modelOrder = VmModel::getModel ('orders');
		$order['order_status'] = $this->getNewStatus ($method);
		$order['customer_notified'] = 0;
		$order['comments'] = '';
		$modelOrder->updateStatusForOneOrder ($order['details']['BT']->virtuemart_order_id, $order, TRUE);

		//We delete the old stuff
		$cart->emptyCart ();
		JRequest::setVar ('html', $html);
		//if ($q1==0) exit;
		return TRUE;
	}

 /* --------------------------------------------------- */


 	function plgVmOnPaymentResponseReceived (&$html) {

		if (!class_exists ('VirtueMartCart')) {
			require(JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');
		}
		if (!class_exists ('shopFunctionsF')) {
			require(JPATH_VM_SITE . DS . 'helpers' . DS . 'shopfunctionsf.php');
		}
		if (!class_exists ('VirtueMartModelOrders')) {
			require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
		}


		$virtuemart_paymentmethod_id = JRequest::getInt ('pm', 0);

		$order_number = JRequest::getString ('wsb_order_num', 0);
        $tid = JRequest::getInt ('wsb_tid', 0);
		$vendorId = 0;


		if (!($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber ($order_number))) {
			return NULL;
		}


		if (!($paymentTable = $this->getDataByOrderId ($virtuemart_order_id))) {
			// JError::raiseWarning(500, $db->getErrorMsg());
			return '';
		}
		//$payment_name = $this->renderPluginName ($method);
		$payment_name = ' Система Easy Pay BY ';
		$html = $this->_getPaymentResponseHtml ($paymentTable, $payment_name, $order_number, $tid);

   		$datetime = date("y/m/d")."_".date("H:i:s");
		$log='';
		$log.='OrderNum='.$order_number.'|';
		$log.='Transaction='.$tid;

		$qpost = "INSERT INTO `#__privat_log` (`direction`,`postnamevalue`,`recorddatetime`) VALUES('Из банка - успешно','$log','$datetime')";
		$dbpost = JFactory::getDBO();
		$dbpost->setQuery($qpost);
		$dbpost->query();

		//We delete the old stuff
		// get the correct cart / session
		$cart = VirtueMartCart::getCart ();
		$cart->emptyCart ();

		return TRUE;
	}



    /**
	 * @return bool|null
	 */
	function plgVmOnUserPaymentCancel () {

		if (!class_exists ('VirtueMartModelOrders')) {
			require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
		}

		$order_number = JRequest::getString ('on', '');

		$virtuemart_paymentmethod_id = JRequest::getInt ('pm', '');
		if (empty($order_number) or empty($virtuemart_paymentmethod_id) or !$this->selectedThisByMethodId ($virtuemart_paymentmethod_id)) {
			return NULL;
		}
		if (!($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber ($order_number))) {
			return NULL;
		}
		//if (!($paymentTable = $this->getDataByOrderId ($virtuemart_order_id))) {
		//	return NULL;
		//}

		VmInfo (Jtext::_ ('PAYPAL_PAYMENT_CANCELLED'));
		$session = JFactory::getSession ();
		$return_context = $session->getId ();
		//if (strcmp ($paymentTable->paypal_custom, $return_context) === 0) {
			$this->handlePaymentUserCancel ($virtuemart_order_id);
		//}

		$datetime = date("y/m/d")."_".date("H:i:s");
		$log='';
		$log.='OrderNum='.$order_number.'|';
		$qpost = "INSERT INTO `#__privat_log` (`direction`,`postnamevalue`,`recorddatetime`) VALUES('Из банка - платеж не прошел','$log','$datetime')";
		$dbpost = JFactory::getDBO();
		$dbpost->setQuery($qpost);
		$dbpost->query();
        echo ('<h2>Платеж отменен.</h2>');

		return TRUE;
	}


  function plgVmOnPaymentNotification () {
  		if (!class_exists ('VirtueMartModelOrders')) {
			require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
		}

  $data = JRequest::get ('post');
    $order_number = $data['wsb_order_num'];
    	//$order_number = JRequest::getString ('wsb_order_num', 0);
        $order_total = JRequest::getInt ('total', 0);
        $tid = JRequest::getInt ('wsb_tid', 0);
        //============== логирование нотификатора
        $datetime = date("y/m/d")."_".date("H:i:s");
		$log='';
		$log.='WSB-POST='.$data['wsb_order_num'].'|'.$data['wsb_total'];
		$qpost = "INSERT INTO `#__privat_log` (`direction`,`postnamevalue`,`recorddatetime`) VALUES('Notify: wsb_order_num, wsb_total','$log','$datetime')";
		$dbpost = JFactory::getDBO();
		$dbpost->setQuery($qpost);
		$dbpost->query();
        // ================================

		if (!($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber ($order_number))) {
			//return NULL;
		}
		//$virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber ($data['wsb_order_num']);

        $payment_data = $this->getDataByOrderId ($virtuemart_order_id); // Retrieve order info from database
        //$order_total = $payment_data['order_total'];


        $wsb_total = $data['wsb_total'];
        if (!($order_total = $wsb_total))
        {
			//return NULL;
		}



  }



	function _getPaymentResponseHtml ($Table, $payment_name, $wsb_order_numb, $wsb_tid) {

		$html = '<table>' . "\n";
		$html .= $this->getHtmlRow ('PAYMENT_NAME', $payment_name);
		if (!empty($Table)) {
						$html .= $this->getHtmlRow('ORDER_NUMBER', $wsb_order_numb);
			$html .= $this->getHtmlRow ('WSB_TRANSACTION', $wsb_tid);
		}
		$html .= '</table>' . "\n";
		$html .= '<h3>Платеж выполнен успешно.</h3>'."\n";

		return $html;
	}


	function getNewStatus ($method) {

		if (isset($method->status_pending) and $method->status_pending!="") {
			return $method->status_pending;
		} else {
			return 'P';
		}
	}

	/**
	 * Display stored payment data for an order
	 *
	 */
	function plgVmOnShowOrderBEPayment ($virtuemart_order_id, $virtuemart_payment_id) {

		if (!$this->selectedThisByMethodId ($virtuemart_payment_id)) {
			return NULL; // Another method was selected, do nothing
		}

		if (!($paymentTable = $this->getDataByOrderId ($virtuemart_order_id))) {
			return NULL;
		}

		$html = '<table class="adminlist">' . "\n";
		$html .= $this->getHtmlHeaderBE ();
		$html .= $this->getHtmlRowBE ('VMPAYMENT_PAYMENT_NAME', $paymentTable->payment_name);
		$html .= $this->getHtmlRowBE ('VMPAYMENT_ORDER_TOTAL', $paymentTable->payment_order_total . ' ' . $paymentTable->payment_currency);
		$html .= '</table>' . "\n";
		return $html;
	}

	function getCosts (VirtueMartCart $cart, $method, $cart_prices) {

		if (preg_match ('/%$/', $method->cost_percent_total)) {
			$cost_percent_total = substr ($method->cost_percent_total, 0, -1);
		} else {
			$cost_percent_total = $method->cost_percent_total;
		}
		return ($method->cost_per_transaction + ($cart_prices['salesPrice'] * $cost_percent_total * 0.01));
	}


	protected function checkConditions ($cart, $method, $cart_prices) {

		$this->convert ($method);
		// 		$params = new JParameter($payment->payment_params);
		$address = (($cart->ST == 0) ? $cart->BT : $cart->ST);

		// We come from the calculator, the $cart->pricesUnformatted does not exist yet
		//$amount = $cart->pricesUnformatted['billTotal'];
		$amount = $cart_prices['salesPrice'];
		$amount_cond = ($amount >= $method->min_amount AND $amount <= $method->max_amount
			OR
			($method->min_amount <= $amount AND ($method->max_amount == 0)));
		if (!$amount_cond) {
			return FALSE;
		}
		$countries = array();
		if (!empty($method->countries)) {
			if (!is_array ($method->countries)) {
				$countries[0] = $method->countries;
			} else {
				$countries = $method->countries;
			}
		}

		// probably did not gave his BT:ST address
		if (!is_array ($address)) {
			$address = array();
			$address['virtuemart_country_id'] = 0;
		}

		if (!isset($address['virtuemart_country_id'])) {
			$address['virtuemart_country_id'] = 0;
		}
		if (count ($countries) == 0 || in_array ($address['virtuemart_country_id'], $countries) ) {
			return TRUE;
		}

		return FALSE;
	}

	function convert ($method) {

		$method->min_amount = (float)$method->min_amount;
		$method->max_amount = (float)$method->max_amount;
	}


	function plgVmOnStoreInstallPaymentPluginTable ($jplugin_id) {

		return $this->onStoreInstallPluginTable ($jplugin_id);
	}


	public function plgVmOnSelectCheckPayment (VirtueMartCart $cart, &$msg) {

		return $this->OnSelectCheck ($cart);
	}


	public function plgVmDisplayListFEPayment (VirtueMartCart $cart, $selected = 0, &$htmlIn) {

		return $this->displayListFE ($cart, $selected, $htmlIn);
	}


	public function plgVmonSelectedCalculatePricePayment (VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name) {

		return $this->onSelectedCalculatePrice ($cart, $cart_prices, $cart_prices_name);
	}

	function plgVmgetPaymentCurrency ($virtuemart_paymentmethod_id, &$paymentCurrencyId) {

		if (!($method = $this->getVmPluginMethod ($virtuemart_paymentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement ($method->payment_element)) {
			return FALSE;
		}
		$this->getPaymentCurrency ($method);

		$paymentCurrencyId = $method->payment_currency;
		return;
	}


	function plgVmOnCheckAutomaticSelectedPayment (VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter) {

		return $this->onCheckAutomaticSelected ($cart, $cart_prices, $paymentCounter);
	}


	public function plgVmOnShowOrderFEPayment ($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name) {

		$this->onShowOrderFE ($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
	}



	function plgVmonShowOrderPrintPayment ($order_number, $method_id) {

		return $this->onShowOrderPrint ($order_number, $method_id);
	}

	function plgVmDeclarePluginParamsPayment ($name, $id, &$data) {

		return $this->declarePluginParams ('payment', $name, $id, $data);
	}

	function plgVmSetOnTablePluginParamsPayment ($name, $id, &$table) {

		return $this->setOnTablePluginParams ($name, $id, $table);
	}






}

// No closing tag
?>