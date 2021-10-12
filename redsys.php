<?php
/*
 *      TPVV Redsýs for VirtueMart 3
 *      @package TPVV Redsýs for VirtueMart 3
 *      @subpackage Content
 *      @author José António Cidre Bardelás
 *      @copyright Copyright (C) 2012-2016 José António Cidre Bardelás and Joomla Empresa. All rights reserved
 *      @license GNU/GPL v3 or later
 *      
 *      Contact us at info@joomlaempresa.com (http://www.joomlaempresa.es)
 *      
 *      This file is part of TPVV Redsýs for VirtueMart 3.
 *      
 *          TPVV Redsýs for VirtueMart 3 is free software: you can redistribute it and/or modify
 *          it under the terms of the GNU General Public License as published by
 *          the Free Software Foundation, either version 3 of the License, or
 *          (at your option) any later version.
 *      
 *          TPVV Redsýs for VirtueMart 3 is distributed in the hope that it will be useful,
 *          but WITHOUT ANY WARRANTY; without even the implied warranty of
 *          MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *          GNU General Public License for more details.
 *      
 *          You should have received a copy of the GNU General Public License
 *          along with TPVV Redsýs for VirtueMart 3.  If not, see <http://www.gnu.org/licenses/>.
 */

defined('_JEXEC') or die();

if(!class_exists('vmPSPlugin'))
	require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');

class plgVmPaymentRedsys extends vmPSPlugin
{
	// instance of class
	public static $_this = false;

	function __construct(&$subject, $config)
	{
		// Drop payment table
		//$db = JFactory::getDBO();
		//$query = 'DROP TABLE IF EXISTS #__virtuemart_payment_plg_redsys;';
		//$db->setQuery($query);
		//$db->query();

		parent::__construct($subject, $config);
		$this->_loggable = true;
		$this->tableFields = array_keys($this->getTableSQLFields());
		$this->_tablepkey = 'id';
		$this->_tableId = 'id';

		$varsToPush = $this->getVarsToPush();
		$this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
	}

	public function getVmPluginCreateTableSQL()
	{
		return $this->createTableSQL('Payment Redsys Table');
	}

	function getTableSQLFields()
	{
		$SQLfields = array(
			'id' => 'int(11) UNSIGNED NOT NULL AUTO_INCREMENT',
			'virtuemart_order_id' => 'int(11) UNSIGNED',
			'order_number' => 'char(32)',
			'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
			'payment_name' => 'varchar(5000)',
			'payment_order_total' => 'decimal(15,5) NOT NULL',
			'payment_currency' => 'char(3) ',
			'cost_per_transaction' => 'decimal(10,2) ',
			'cost_percent_total' => 'decimal(10,2) ',
			'tax_id' => 'smallint(1)',
			'redsys_response' => 'char(4)',
			'redsys_response_date' => 'char(16)',
			'redsys_response_hour' => 'char(8)',
			'redsys_response_order' => 'char(16)',
			'redsys_response_amount' => 'char(16)',
			'redsys_response_authorisationcode' => 'char(16)',
			'redsys_response_card_country' => 'char(3)',
			'redsys_response_card_type' => 'char(1)',
			'redsys_response_currency' => 'char(4)',
			'redsys_response_merchantcode' => 'char(16)',
			'redsys_response_merchantdata' => 'varchar(1200)',
			'redsys_response_securepayment' => 'char(1)',
			'redsys_response_signature' => 'char(42)',
			'redsys_response_terminal' => 'char(3)',
			'redsys_response_transactiontype' => 'char(1)',
			'redsys_response_consumerlanguage' => 'char(3)'
		);

		return $SQLfields;
	}

	function plgVmConfirmedOrder($cart, $order)
	{
		if(!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id)))
		{
			return null; // Another method was selected, do nothing
		}
		
		if(!$this->selectedThisElement($method->payment_element))
		{
			return false;
		}

		$lang = JFactory::getLanguage();
		$filename = 'com_virtuemart';
		$lang->load($filename, JPATH_ADMINISTRATOR);

		$this->_debug = $method->debug;
		$this->logInfo('plgVmConfirmedOrder order number: ' . $order['details']['BT']->order_number, 'message');

		if(!class_exists('VirtueMartModelOrders'))
			require(VMPATH_ADMIN . DS . 'models' . DS . 'orders.php');
		if(!class_exists('VirtueMartModelCurrency'))
			require(VMPATH_ADMIN . DS . 'models' . DS . 'currency.php');

		// Double order checking
		if(method_exists($this, 'setInConfirmOrder'))
			$this->setInConfirmOrder($cart);

		$new_status = '';
		$this->getPaymentCurrency($method, true);

		$q = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="' . $method->payment_currency . '" ';
		$db = JFactory::getDBO();
		$db->setQuery($q);
		$currency_code_3 = $db->loadResult();
		$paymentCurrency = CurrencyDisplay::getInstance($method->payment_currency);
		$totalInPaymentCurrency = round($paymentCurrency->convertCurrencyTo($method->payment_currency, $order['details']['BT']->order_total, false), 2);
		$montante = $totalInPaymentCurrency * 100;
		$cd = CurrencyDisplay::getInstance($cart->pricesCurrency);
		$endereco = ((isset($order['details']['BT'])) ? $order['details']['BT'] : $order['details']['ST']);
		//$idEncomenda = $order['details']['BT']->order_number;
		$idEncomenda = time();
		$urlRespostaBase = 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&on=' . $order['details']['BT']->order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id . ($method->redsys_itemid != '' ? '&Itemid=' . (int) $method->redsys_itemid : (vRequest::getInt('Itemid') != '' ? '&Itemid=' . vRequest::getInt('Itemid') : ''));
		$urlOK = JRoute::_(JURI::root() . $urlRespostaBase . '&action=OK');
		$urlKO = JRoute::_(JURI::root() . $urlRespostaBase . '&action=KO');
		$local = JFactory::getLanguage();
		$localFull = $local->getLocale();
		$localShort = explode('.', $localFull[0]);
		$jooLang = $localShort[0];
		$languages = JLanguageHelper::getLanguages('lang_code');
		$urlRetorno = JRoute::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&tmpl=component&on=' . $order['details']['BT']->order_number) . (JLanguageMultilang::isEnabled() ? '&lang=' . $languages[$local->getTag()]->sef : '');

		if(trim($method->redsys_idioma) == 'AUTO')
		{
			switch(substr($jooLang, 0, 3))
			{
				case 'es_':
					$language = '001';
					break;
				case 'en_':
					$language = '002';
					break;
				case 'ca_':
					$language = '003';
					break;
				case 'fr_':
					$language = '004';
					break;
				case 'de_':
					$language = '005';
					break;
				case 'nl_':
					$language = '006';
					break;
				case 'it_':
					$language = '007';
					break;
				case 'sv_':
					$language = '008';
					break;
				case 'pt_':
					$language = '009';
					break;
				case 'pl_':
					$language = '011';
					break;
				case 'gl_':
					$language = '012';
					break;
				case 'eu_':
					$language = '013';
					break;
				default :
					$language = '0';
			}
		}
		else
			$language = $method->redsys_idioma;

		$longo = strlen($idEncomenda);
		if($longo < 11)
		{
			for($i = $longo; $i < 11; $i++)
			{
				$idEncomenda = "0" . $idEncomenda;
			}
			$idEncomenda = "0" . $idEncomenda;
		}
		elseif($longo > 12)
		{
			$idEncomenda = substr($idEncomenda, -12);
		}

		/* if(!is_int(substr($idEncomenda, 0, 4))) {
		  $numPrefix = time();
		  //$idEncomenda = substr($numPrefix, -4).substr($idEncomenda, 4);
		  $idEncomenda = substr($numPrefix, -12);
		  } */

		$redsysOrderParams = array(
			'Ds_Merchant_Amount' => (string)$montante,
			'Ds_Merchant_Currency' => $method->redsys_divisa,
			'Ds_Merchant_Order' => $idEncomenda,
			'Ds_Merchant_ProductDescription' => $method->redsys_descricom_produtos . ' (' . $order['details']['BT']->order_number . ')',
			'Ds_Merchant_Titular' => $endereco->first_name . ' ' . $endereco->last_name,
			'Ds_Merchant_MerchantCode' => $method->redsys_codigo_loja,
			'Ds_Merchant_MerchantData' => JText::_('VMPAYMENT_REDSYS_ORDER_NUMBER') . ' ' . $order['details']['BT']->order_number,
			'Ds_Merchant_MerchantURL' => $method->redsys_force_nossl ? str_replace('https://', 'http://', $urlRetorno) : $urlRetorno,
			'Ds_Merchant_UrlOK' => $urlOK,
			'Ds_Merchant_UrlKO' => $urlKO,
			'Ds_Merchant_MerchantName' => $method->redsys_nome_loja,
			'Ds_Merchant_ConsumerLanguage' => $language,
			'Ds_Merchant_Terminal' => $method->redsys_terminal,
			'Ds_Merchant_TransactionType' => $method->redsys_tipo_operacom,
			'Ds_Merchant_PayMethods' => trim($method->redsys_pos_payment_type) == 'A' ? ' ' : trim($method->redsys_pos_payment_type),
		);

		require_once JPATH_ADMINISTRATOR . '/components/com_jetpvvcommon/helpers/jetpvvcommon.php';
		require_once JPATH_ADMINISTRATOR . '/components/com_jetpvvcommon/helpers/redsys.php';
		$signatureVersion = "HMAC_SHA256_V1";
		$assinatura = JETPVvCommonHelperRedsys::createSendSignature('redsys_chave', $order['details']['BT']->virtuemart_paymentmethod_id, $redsysOrderParams);

		$post_variables = array(
			'Ds_SignatureVersion' => $signatureVersion,
			'Ds_MerchantParameters' => base64_encode(json_encode($redsysOrderParams)),
			'Ds_Signature' => $assinatura,
		);
		
		$this->_virtuemart_paymentmethod_id = $order['details']['BT']->virtuemart_paymentmethod_id;
		$dbValues['payment_name'] = $this->renderPluginName($method, $order);
		$dbValues['order_number'] = $order['details']['BT']->order_number;
		$dbValues['redsys_response_order'] = $idEncomenda;
		$dbValues['virtuemart_paymentmethod_id'] = $this->_virtuemart_paymentmethod_id;
		$dbValues['cost_per_transaction'] = $method->cost_per_transaction;
		$dbValues['cost_percent_total'] = $method->cost_percent_total;
		$dbValues['payment_currency'] = $currency_code_3;
		$dbValues['payment_order_total'] = $totalInPaymentCurrency;
		$dbValues['tax_id'] = $method->tax_id;
		$this->storePSPluginInternalData($dbValues);

		if(!$method->redsys_tpv_url || !isset($method->redsys_tipo_operacom) || !$method->redsys_codigo_loja || !$method->redsys_terminal || !$method->redsys_divisa || !$assinatura)
		{
			$html = '<html><head><title>Error</title></head><body><div style="margin: auto; text-align: center;">';
			$html .= '<img src="' . JURI::root() . 'plugins/vmpayment/redsys/' . (JVM_VERSION >= 2 ? 'redsys/' : '') . 'erro.png"><h1>' . JText::_('VMPAYMENT_REDSYS_NOM_CONFIGURADO_TIT') . '</h1>';
			$html .= '<p>' . JText::_('VMPAYMENT_REDSYS_NOM_CONFIGURADO') . '</p>';
			$html .= '</body></html>';
			return $this->processConfirmedOrderPaymentResponse(2, $cart, $order, $html, $dbValues['payment_name'], $new_status);
		}

		if(!class_exists('VirtueMartModelCurrency'))
		{
			require(VMPATH_ADMIN . DS . 'models' . DS . 'currency.php');
		}

		$html = '<html><head><title>Redirection</title></head><body>';

		$currency = CurrencyDisplay::getInstance('', $order['details']['BT']->virtuemart_vendor_id);

		if($method->redsys_template_files)
		{
			$method->redsys_checkout_page = "<p style=\"text-align: center;\">{TRANS_POS_TEXT}</p><p style=\"text-align: center;\">{TRANS_ORDER}: {ORDER_NUMBER}</p><p style=\"text-align: center;\">{TRANS_AMOUNT}: {AMOUNT}</p><div style=\"text-align: center;\">{REDSYS_FORM}</div>";
		}

		$html .= $this->replaceTags($method, $order, $currency, $post_variables);

		if($method->mail_debug)
		{
			$this->sendDebugMail($method->mail_debug_address, '[Debug] Data sent to POS', print_r($post_variables, true));
			// Send columns for debug
			$query = 'SHOW COLUMNS FROM #__virtuemart_payment_plg_redsys;';
			$db->setQuery($query);
			$dbTables = $db->loadColumn();
			$this->sendDebugMail($method->mail_debug_address, '[Debug] Table columns', print_r($dbTables, true));
		}

		if($method->redsys_encaminhar)
		{
			$html .= ' <script type="text/javascript">';
			$html .= ' document.vm_redsys_form.submit();';
			$html .= ' </script>';
		}

		$html .= '</body></html>';

		// 2 = don't delete the cart, don't send email and don't redirect
		return $this->processConfirmedOrderPaymentResponse(2, $cart, $order, $html, $dbValues['payment_name'], $new_status);
	}

	/**
	 * Display stored payment data for an order
	 *
	 */
	function plgVmOnShowOrderBEPayment($virtuemart_order_id, $virtuemart_payment_id)
	{
		if(!$this->selectedThisByMethodId($virtuemart_payment_id))
			return null; // Another method was selected, do nothing

		$db = JFactory::getDBO();
		$q = 'SELECT * FROM `' . $this->_tablename . '` ' . 'WHERE `virtuemart_order_id` = ' . $virtuemart_order_id;
		$db->setQuery($q);
		if(!($paymentTable = $db->loadObject()))
		{
			vmWarn(500, $q . " " . $db->getErrorMsg());
			return false;
		}

		//$this->getPaymentCurrency($paymentTable);
		$html = '<table class="adminlist">' . "\n";
		$html .= $this->getHtmlHeaderBE();
		$html .= $this->getHtmlRowBE('VMPAYMENT_REDSYS_NOME_PAGAMENTO', $paymentTable->payment_name);
		$html .= $this->getHtmlRowBE('VMPAYMENT_REDSYS_TOTAL_DIVISA', $paymentTable->payment_order_total . ' ' . $paymentTable->payment_currency);
		$code = "redsys_response_";
		foreach($paymentTable as $key => $value)
		{
			if(substr($key, 0, strlen($code)) == $code)
			{
				$html .= $this->getHtmlRowBE($key, $value);
			}
		}
		$html .= '</table>' . "\n";
		return $html;
	}

	function getCosts(VirtueMartCart$cart, $method, $cart_prices)
	{
		if(preg_match('/%$/', $method->cost_percent_total))
		{
			$cost_percent_total = substr($method->cost_percent_total, 0, - 1);
		}
		else
		{
			$cost_percent_total = $method->cost_percent_total;
		}

		return((float)$method->cost_per_transaction + ($cart_prices['salesPrice'] * (float)$cost_percent_total * 0.01));
	}

	/**
	 * Check if the payment conditions are fulfilled for this payment method
	 * @author: Valerie Isaksen
	 *
	 * @param $cart_prices: cart prices
	 * @param $payment
	 * @return true: if the conditions are fulfilled, false otherwise
	 *
	 */
	protected function checkConditions($cart, $method, $cart_prices)
	{

		// 		$params = new JParameter($payment->payment_params);

		$address = (($cart->STsameAsBT == 1) ? $cart->BT : $cart->ST);

		//$amount = $cart_prices['salesPrice'];
		$amount = $this->getCartAmount($cart_prices);
		$amount_cond = ($amount >= $method->min_amount AND $amount <= $method->max_amount OR ( $method->min_amount <= $amount AND ( $method->max_amount == 0)));

		if(!$amount_cond)
		{
			return false;
		}

		$countries = array();

		if(!empty($method->countries))
		{
			if(!is_array($method->countries))
			{
				$countries[0] = $method->countries;
			}
			else
			{
				$countries = $method->countries;
			}
		}

		// probably did not gave his BT:ST address

		if(!is_array($address))
		{
			$address = array();
			$address['virtuemart_country_id'] = 0;
		}

		if(!isset($address['virtuemart_country_id']))
		{
			$address['virtuemart_country_id'] = 0;
		}

		if(count($countries) == 0 || in_array($address['virtuemart_country_id'], $countries))
		{
			return true;
		}

		return false;
	}

	/*
	 * We must reimplement this triggers for joomla 1.7
	 */

	/**
	 * Create the table for this plugin if it does not yet exist.
	 * This functions checks if the called plugin is active one.
	 * When yes it is calling the standard method to create the tables
	 * @author Valérie Isaksen
	 *
	 */
	function plgVmOnStoreInstallPaymentPluginTable($jplugin_id)
	{
		return $this->onStoreInstallPluginTable($jplugin_id);
	}

	/**
	 * This event is fired after the payment method has been selected. It can be used to store
	 * additional payment info in the cart.
	 *
	 * @author Max Milbers
	 * @author Valérie isaksen
	 *
	 * @param VirtueMartCart $cart: the actual cart
	 * @return null if the payment was not selected, true if the data is valid, error message if the data is not vlaid
	 *
	 */
	public function plgVmOnSelectCheckPayment(VirtueMartCart$cart)
	{
		return $this->OnSelectCheck($cart);
	}

	/**
	 * plgVmDisplayListFEPayment
	 * This event is fired to display the pluginmethods in the cart (edit shipment/payment) for exampel
	 *
	 * @param object $cart Cart object
	 * @param integer $selected ID of the method selected
	 * @return boolean True on succes, false on failures, null when this plugin was not selected.
	 * On errors, JError::raiseWarning (or JError::raiseError) must be used to set a message.
	 *
	 * @author Valerie Isaksen
	 * @author Max Milbers
	 */
	public function plgVmDisplayListFEPayment(VirtueMartCart$cart, $selected = 0, &$htmlIn)
	{
		return $this->displayListFE($cart, $selected, $htmlIn);
	}

	/*
	 * plgVmonSelectedCalculatePricePayment
	 * Calculate the price (value, tax_id) of the selected method
	 * It is called by the calculator
	 * This function does NOT to be reimplemented. If not reimplemented, then the default values from this function are taken.
	 * @author Valerie Isaksen
	 * @cart: VirtueMartCart the current cart
	 * @cart_prices: array the new cart prices
	 * @return null if the method was not selected, false if the shiiping rate is not valid any more, true otherwise
	 *
	 *
	 */

	public function plgVmonSelectedCalculatePricePayment(VirtueMartCart$cart, array&$cart_prices, &$cart_prices_name)
	{
		return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
	}

	function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId)
	{

		if(!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id)))
		{
			return null; // Another method was selected, do nothing
		}
		if(!$this->selectedThisElement($method->payment_element))
		{
			return false;
		}
		$this->getPaymentCurrency($method);

		$paymentCurrencyId = $method->payment_currency;
	}

	// Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
	// The plugin must check first if it is the correct type
	function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter)
	{
		return $this->onCheckAutomaticSelected($cart, $cart_prices, $paymentCounter);
	}

	/**
	 * This method is fired when showing the order details in the frontend.
	 * It displays the method-specific data.
	 *
	 * @param integer $order_id The order ID
	 * @return mixed Null for methods that aren't active, text (HTML) otherwise
	 * @author Max Milbers
	 * @author Valerie Isaksen
	 */
	public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name)
	{
		$this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
	}

	/**
	 * This event is fired during the checkout process. It can be used to validate the
	 * method data as entered by the user.
	 *
	 * @return boolean True when the data was valid, false otherwise. If the plugin is not activated, it should return null.
	 * @author Max Milbers

	  public function plgVmOnCheckoutCheckDataPayment(  VirtueMartCart $cart) {
	  return null;
	  }
	 */

	/**
	 * This method is fired when showing when priting an Order
	 * It displays the the payment method-specific data.
	 *
	 * @param integer $_virtuemart_order_id The order ID
	 * @param integer $method_id  method used for this order
	 * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
	 * @author Valerie Isaksen
	 */
	function plgVmonShowOrderPrintPayment($order_number, $method_id)
	{
		return $this->onShowOrderPrint($order_number, $method_id);
	}

	function plgVmDeclarePluginParamsPayment($name, $id, &$data)
	{
		return $this->declarePluginParams('payment', $name, $id, $data);
	}

	function plgVmDeclarePluginParamsPaymentVM3(&$data)
	{
		return $this->declarePluginParams('payment', $data);
	}

	function plgVmSetOnTablePluginParamsPayment($name, $id, &$table)
	{
		return $this->setOnTablePluginParams($name, $id, $table);
	}

	//Notice: We only need to add the events, which should work for the specific plugin, when an event is doing nothing, it should not be added
	/**
	 * Save updated order data to the method specific table
	 *
	 * @param array $_formData Form data
	 * @return mixed, True on success, false on failures (the rest of the save-process will be
	 * skipped!), or null when this method is not actived.
	 * @author Oscar van Eijk
	 *
	  public function plgVmOnUpdateOrderPayment(  $_formData) {
	  return null;
	  }

	  /**
	 * Save updated orderline data to the method specific table
	 *
	 * @param array $_formData Form data
	 * @return mixed, True on success, false on failures (the rest of the save-process will be
	 * skipped!), or null when this method is not actived.
	 * @author Oscar van Eijk
	 *
	  public function plgVmOnUpdateOrderLine(  $_formData) {
	  return null;
	  }

	  /**
	 * plgVmOnEditOrderLineBE
	 * This method is fired when editing the order line details in the backend.
	 * It can be used to add line specific package codes
	 *
	 * @param integer $_orderId The order ID
	 * @param integer $_lineId
	 * @return mixed Null for method that aren't active, text (HTML) otherwise
	 * @author Oscar van Eijk
	 *
	  public function plgVmOnEditOrderLineBEPayment(  $_orderId, $_lineId) {
	  return null;
	  }

	  /**
	 * This method is fired when showing the order details in the frontend, for every orderline.
	 * It can be used to display line specific package codes, e.g. with a link to external tracking and
	 * tracing systems
	 *
	 * @param integer $_orderId The order ID
	 * @param integer $_lineId
	 * @return mixed Null for method that aren't active, text (HTML) otherwise
	 * @author Oscar van Eijk
	 *
	  public function plgVmOnShowOrderLineFE(  $_orderId, $_lineId) {
	  return null;
	  }

	  /**
	 * This event is fired when the  method notifies you when an event occurs that affects the order.
	 * Typically,  the events  represents for payment authorizations, Fraud Management Filter actions and other actions,
	 * such as refunds, disputes, and chargebacks.
	 *
	 * NOTE for Plugin developers:
	 *  If the plugin is NOT actually executed (not the selected payment method), this method must return NULL
	 *
	 * @param $return_context: it was given and sent in the payment form. The notification should return it back.
	 * Used to know which cart should be emptied, in case it is still in the session.
	 * @param int $virtuemart_order_id : payment  order id
	 * @param char $new_status : new_status for this order id.
	 * @return mixed Null when this method was not selected, otherwise the true or false
	 *
	 * @author Valerie Isaksen
	 *
	 */
	public function plgVmOnPaymentNotification()
	{
		$order_number = vRequest::getVar('on', 0);
		if(!isset($order_number) || empty($order_number))
		{
			//$this->logInfo('Technical Note: Order number not set or empty: exit ', 'ERROR');
			return false;
		}
		if(!class_exists('VirtueMartModelOrders'))
			require(VMPATH_ADMIN . DS . 'models' . DS . 'orders.php');
		$idEncomenda = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number);
		if(!$idEncomenda)
		{
			//$this->logInfo('Technical Note: Order id not found: exit ', 'ERROR');
			return false;
		}
		$payment = $this->getDataByOrderId($idEncomenda);
		if(!$payment)
		{
			//$this->logInfo('getDataByOrderId payment not found: exit ', 'ERROR');
			return false;
		}
		$method = $this->getVmPluginMethod($payment->virtuemart_paymentmethod_id);
		if($method->mail_debug)
		{
			$this->sendDebugMail($method->mail_debug_address, '[Debug] Notification data received from POS');
		}
		$this->_debug = $method->debug;
		if(!$this->selectedThisElement($method->payment_element))
		{
			return null;
		}

		$redsysPostData = vRequest::getPost();
		if(!$redsysPostData || empty($redsysPostData))
		{
			$this->logInfo('Technical Note: No post data received: exit ', 'ERROR');
			return false;
		}

		$redsysOrderParamsB64 = $redsysPostData['Ds_MerchantParameters'];
		$redsysOrderParamsJSon = base64_decode(strtr($redsysOrderParamsB64, '-_', '+/'));
		$redsysOrderParamsArray = json_decode($redsysOrderParamsJSon, true);

		require_once JPATH_ADMINISTRATOR . '/components/com_jetpvvcommon/helpers/jetpvvcommon.php';
		require_once JPATH_ADMINISTRATOR . '/components/com_jetpvvcommon/helpers/redsys.php';
		$signatureVersionLocal = "HMAC_SHA256_V1";
		$assinaturaCalc = JETPVvCommonHelperRedsys::createNotifySignature('redsys_chave', $payment->virtuemart_paymentmethod_id, $redsysOrderParamsB64);

		if(!$assinaturaCalc || empty($assinaturaCalc))
		{
			$this->logInfo('Technical Note: The required transaction key is empty! The payment method settings must be reviewed: exit ', 'ERROR');
			return false;
		}

		$bd = JFactory::getDBO();
		$q = 'SELECT redsys_response_order FROM `' . $this->_tablename . '` WHERE ' . " `virtuemart_order_id` = '" . $idEncomenda . "'";
		$bd->setQuery($q);
		$responseOrderNumber = $bd->loadResult();
		if($redsysOrderParamsArray['Ds_Order'] != $responseOrderNumber)
		{
			$this->logInfo('Technical Note: Order number don\'t match with that used in the POS order ID generation: exit ', 'ERROR');
			return false;
		}
		if((string) $redsysOrderParamsArray['Ds_Amount'] != (string) ($payment->payment_order_total * 100))
		{
			$this->logInfo('Technical Note: Amount in BD and received don\'t match ' . (string) $redsysOrderParamsArray['Ds_Amount'] . ' != ' . (string) ($payment->payment_order_total * 100) . ': exit ', 'ERROR');
			return false;
		}
		$this->logInfo('redsysOrderParamsArray ' . implode('   ', $redsysOrderParamsArray), 'MESSAGE');
		$this->_storeRedsysInternalData($method, $redsysOrderParamsArray, $idEncomenda, $order_number, $redsysPostData['Ds_Signature']);

		if($redsysPostData['Ds_Signature'] != $assinaturaCalc)
		{
			$this->logInfo('Technical Note: The verification signatures don\'t match: exit ', 'ERROR');
			return false;
		}
		$modelOrder = VmModel::getModel('orders');
		$order = array();
		if(($redsysOrderParamsArray['Ds_Response'] >= 0) && ($redsysOrderParamsArray['Ds_Response'] <= 99) && $redsysOrderParamsArray['Ds_AuthorisationCode'])
		{
			$new_status = $method->status_success;
			$order['comments'] = JText::sprintf('VMPAYMENT_REDSYS_PAGAMENTO_ACEITE', $order_number, $redsysOrderParamsArray['Ds_Response'], urldecode($redsysOrderParamsArray['Ds_AuthorisationCode']));
		}
		else
		{
			$new_status = $method->status_canceled;
			$order['comments'] = JText::sprintf('VMPAYMENT_REDSYS_PAGAMENTO_REJEITADO', $order_number, JETPVvCommonHelperRedsys::getPOSResponseErrorText($redsysOrderParamsArray['Ds_Response']));
			$this->logInfo('Payment not authorised. Status: ' . $new_status, 'ERROR');
		}
		$order['order_status'] = $new_status;
		$order['customer_notified'] = 1;
		$modelOrder->updateStatusForOneOrder($idEncomenda, $order, true);
		$this->logInfo('Notification, sentOrderConfirmedEmail ' . $order_number . ' ' . $new_status, 'MESSAGE');
		return true;
	}

	/**
	 * plgVmOnPaymentResponseReceived
	 * This event is fired when the  method returns to the shop after the transaction
	 *
	 *  the method itself should send in the URL the parameters needed
	 * NOTE for Plugin developers:
	 *  If the plugin is NOT actually executed (not the selected payment method), this method must return NULL
	 *
	 * @param int $virtuemart_order_id : should return the virtuemart_order_id
	 * @param text $html: the html to display
	 * @return mixed Null when this method was not selected, otherwise the true or false
	 *
	 * @author Valerie Isaksen
	 *
	 */
	function plgVmOnPaymentResponseReceived(&$html)
	{
		$itemId = vRequest::getInt('Itemid', '');
		$virtuemart_paymentmethod_id = vRequest::getInt('pm', 0);
		$order_number = vRequest::getVar('on', 0);
		$acom = vRequest::getVar('action');

		if(!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id)))
		{
			return null; // Another method was selected, do nothing
		}

		if($method->mail_debug)
		{
			$this->sendDebugMail($method->mail_debug_address, '[Debug] Response data received from POS');
		}

		if(!$this->selectedThisElement($method->payment_element))
		{
			return false;
		}

		if(!isset($order_number) || empty($order_number))
		{
			//vmError(JText::_('VMPAYMENT_REDSYS_NOM_HA_NUM_ENCOMENDA'));
			$html = '<img src="' . JURI::root() . 'plugins/vmpayment/redsys/' . (JVM_VERSION >= 2 ? 'redsys/' : '') . 'erro.png"><h1>' . JText::_('VMPAYMENT_REDSYS_NOM_HA_NUM_ENCOMENDA_TIT') . '</h1>';
			$html .= '<p>' . JText::_('VMPAYMENT_REDSYS_NOM_HA_NUM_ENCOMENDA') . '</p>';
			return false;
		}

		if(!class_exists('VirtueMartCart'))
		{
			require(VMPATH_SITE . DS . 'helpers' . DS . 'cart.php');
		}

		if(!class_exists('VirtueMartModelOrders'))
		{
			require(VMPATH_ADMIN . DS . 'models' . DS . 'orders.php');
		}

		$idEncomenda = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number);
		$modelOrder = VmModel::getModel('orders');

		if($acom == 'KO')
		{
			$payment = $this->getDataByOrderId($idEncomenda);
			$POSResponse = isset($payment->redsys_response) ? (int) $payment->redsys_response : null;
			require_once JPATH_ADMINISTRATOR . '/components/com_jetpvvcommon/helpers/redsys.php';
			$errorText = $POSResponse ? JETPVvCommonHelperRedsys::getPOSResponseErrorText($POSResponse) : '';
		}

		$order = $modelOrder->getOrder($idEncomenda);

		if($method->redsys_template_files)
		{
			$tplData = array(
				'errorText' => isset($errorText) ? $errorText : '',
				'imgSrc' => JURI::root() . 'plugins/vmpayment/redsys/' . (JVM_VERSION >= 2 ? 'redsys/' : '') . ($acom == 'OK' ? 'correto.png' : 'incorreto.png'),
				'imgAlt' => ($acom == 'OK' ? JText::_('VMPAYMENT_REDSYS_PAGAMENTO_CORRETO_TIT') : JText::_('VMPAYMENT_REDSYS_PAGAMENTO_INCORRETO_TIT')),
				'title' => ($acom == 'OK' ? JText::_('VMPAYMENT_REDSYS_PAGAMENTO_CORRETO_TIT') : JText::_('VMPAYMENT_REDSYS_PAGAMENTO_INCORRETO_TIT')),
				'text' => ($acom == 'OK' ? JText::_('VMPAYMENT_REDSYS_PAGAMENTO_CORRETO') : JText::_('VMPAYMENT_REDSYS_PAGAMENTO_INCORRETO')),
				'linkOrder' => JURI::root() . 'index.php?option=com_virtuemart&view=orders&layout=details&order_number=' . $order_number . '&order_pass=' . $order['details']['BT']->order_pass . ($itemId == '' ? '' : '&Itemid=' . $itemId)
			);
		}

		if($acom == 'OK')
		{
			$cart = VirtueMartCart::getCart();
			$cart->emptyCart();
		}

		if ($method->redsys_template_files)
		{
			$html = $this->renderByLayout(($acom == 'OK' ? 'url_ok' : 'url_ko'), $tplData);
		}
		else {
			$text = $acom == 'OK' ? $method->redsys_ok_page : $method->redsys_ko_page;
			$html = $this->replaceTags($method, $order, null, null, $text, $order_number, $itemId, isset($errorText) ? $errorText : '');
		}

		return true;
	}

	function _storeRedsysInternalData($method, $redsysPostData, $virtuemart_order_id, $order_number, $signature)
	{
		$bd = JFactory::getDBO();
		$q = 'SELECT * FROM `' . $this->_tablename . '` WHERE '
				. " `virtuemart_order_id` = '" . $virtuemart_order_id . "'";
		$bd->setQuery($q);
		$guardado = $bd->loadObject();
		$getEscaped = version_compare(JVERSION, '3.0.0', 'ge') ? 'escape' : 'getEscaped';
		$resposta['virtuemart_order_id'] = $bd->{$getEscaped}($virtuemart_order_id);
		$resposta['order_number'] = $bd->{$getEscaped}($order_number);
		$resposta['virtuemart_paymentmethod_id'] = $guardado->virtuemart_paymentmethod_id;
		$resposta['payment_name'] = $this->renderPluginName($method);
		$resposta['payment_order_total'] = $guardado->payment_order_total;
		$resposta['payment_currency'] = $guardado->payment_currency;
		$resposta['cost_per_transaction'] = $guardado->cost_per_transaction;
		$resposta['cost_percent_total'] = $guardado->cost_percent_total;
		$resposta['tax_id'] = $guardado->tax_id;
		$resposta['redsys_response'] = $bd->{$getEscaped}($redsysPostData['Ds_Response']);
		$resposta['redsys_response_date'] = $bd->{$getEscaped}($redsysPostData['Ds_Date']);
		$resposta['redsys_response_hour'] = $bd->{$getEscaped}($redsysPostData['Ds_Hour']);
		$resposta['redsys_response_order'] = $bd->{$getEscaped}($redsysPostData['Ds_Order']);
		$resposta['redsys_response_amount'] = $bd->{$getEscaped}($redsysPostData['Ds_Amount']);
		$resposta['redsys_response_authorisationcode'] = $bd->{$getEscaped}($redsysPostData['Ds_AuthorisationCode']);
		$resposta['redsys_response_card_country'] = $bd->{$getEscaped}($redsysPostData['Ds_Card_Country']);
		$resposta['redsys_response_card_type'] = $bd->{$getEscaped}($redsysPostData['Ds_Card_Type']);
		$resposta['redsys_response_currency'] = $bd->{$getEscaped}($redsysPostData['Ds_Currency']);
		$resposta['redsys_response_merchantcode'] = $bd->{$getEscaped}($redsysPostData['Ds_MerchantCode']);
		$resposta['redsys_response_merchantdata'] = $bd->{$getEscaped}($redsysPostData['Ds_MerchantData']);
		$resposta['redsys_response_securepayment'] = $bd->{$getEscaped}($redsysPostData['Ds_SecurePayment']);
		$resposta['redsys_response_signature'] = $bd->{$getEscaped}($signature);
		$resposta['redsys_response_terminal'] = $bd->{$getEscaped}($redsysPostData['Ds_Terminal']);
		$resposta['redsys_response_transactiontype'] = $bd->{$getEscaped}($redsysPostData['Ds_TransactionType']);
		$resposta['redsys_response_consumerlanguage'] = $bd->{$getEscaped}($redsysPostData['Ds_ConsumerLanguage']);
		//$preload=true   preload the data here to preserve not updated data -> actually not working
		$this->storePSPluginInternalData($resposta, 'virtuemart_order_id', true);
	}

	function sendDebugMail($address, $subject, $debugData = null)
	{
		if(!$debugData)
			$debugData = '$_POST:' . print_r($_POST, true) . '$_GET:' . print_r($_GET, true) . '$_SERVER:' . print_r($_SERVER, true);
		if(!$address)
		{
			$config = JFactory::getConfig();
			$address = version_compare(JVERSION, '3.0.0', 'ge') ? $config->get('mailfrom') : $config->getValue('config.mailfrom');
		}
		//mail($address, $subject, $debugData);
		$mail = JFactory::getMailer();
		$mail->addRecipient(trim($address));
		$mail->IsHTML(false);
		$mail->setSender(array(trim($address), 'Debug'));
		$mail->setSubject($subject);
		$mail->setBody($debugData);

		return $mail->Send();
	}

	/**
	 * @param      $method
	 * @param      $order
	 * @param      $currency
	 * @param null $postVariables
	 * @param null $text
	 * @param null $orderNumber
	 * @param null $itemId
	 * @param null $errorText
	 *
	 * @return mixed
	 */
	private function replaceTags($method, $order, $currency, $postVariables = null, $text = null, $orderNumber = null, $itemId = null, $errorText = null) {
		$text = $text ? $text : $method->redsys_checkout_page;
		$form = '';

		if ($postVariables)
		{
			$form  = '<form accept-charset="iso-8859-1" action="' . $method->redsys_tpv_url . '" method="post" name="vm_redsys_form" id="vm_redsys_form">';

			if($method->buttonimage)
			{
				$paymentLogo = $method->buttonimage;
			}
			else
			{
				$paymentLogo = JURI::root() . 'plugins/vmpayment/redsys/redsys/';
				$paymentLogo .= trim($method->redsys_pos_payment_type) == 'O' ? 'iupayBtnWhite.png' : (trim($method->redsys_pos_payment_type) == 'C' ? 'logo_pagamento.png' : 'logo_pagamento_redsys_iupay.png');
			}

			$form .= '<input type="image" src="' . $paymentLogo . '" name="submit" title="' . ($method->redsys_encaminhar ? JText::_('VMPAYMENT_REDSYS_MENSAGEM_ENCAMINHADO') : JText::_('VMPAYMENT_REDSYS_MENSAGEM_CLIQUE')) . '" alt="' . ($method->redsys_encaminhar ? JText::_('VMPAYMENT_REDSYS_MENSAGEM_ENCAMINHADO') : JText::_('VMPAYMENT_REDSYS_MENSAGEM_CLIQUE')) . '" />';

			foreach ($postVariables as $name => $value)
			{
				$form .= '<input type="hidden" name="' . $name . '" value="' . htmlspecialchars($value) . '" />';
			}

			$form .= '</form>';
		}

		$tagVars = array(
			'trans_pos_text' => JText::_('VMPAYMENT_REDSYS_TEXTO_TPV'),
			'trans_order' => JText::_('VMPAYMENT_REDSYS_ORDER_NUMBER'),
			'trans_amount' => JText::_('VMPAYMENT_REDSYS_AMOUNT'),
			'order_number'  => $order['details']['BT']->order_number,
			'amount'  => isset($currency) ? $currency->priceDisplay($order['details']['BT']->order_total) : 0,
			'redsys_form'  => $form,
			'trans_ok_title'  => JText::_('VMPAYMENT_REDSYS_PAGAMENTO_CORRETO_TIT'),
			'trans_ok_text'  => JText::_('VMPAYMENT_REDSYS_PAGAMENTO_CORRETO'),
			'order_details_link'  => JHTML::_('link', JURI::root() . 'index.php?option=com_virtuemart&view=orders&layout=details&order_number=' . $orderNumber . '&order_pass=' . $order['details']['BT']->order_pass . ($itemId == '' ? '' : '&Itemid=' . $itemId), JText::_('VMPAYMENT_REDSYS_CONSULTA_ENCOMENDA')),
			'trans_ko_title'  => JText::_('VMPAYMENT_REDSYS_PAGAMENTO_INCORRETO_TIT'),
			'trans_ko_text'  => JText::_('VMPAYMENT_REDSYS_PAGAMENTO_INCORRETO'),
			'response_error_text'  => $errorText,
		);

		// Perform substitutions
		foreach ($tagVars as $key => $value)
		{
			$tag = '{' . strtoupper($key) . '}';
			$text = str_replace($tag, $value, $text);
		}

		return $text;
	}
}
