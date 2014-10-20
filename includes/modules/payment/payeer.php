<?php

class payeer
{
    var $code, $title, $description, $enabled;

    function payeer()
    {
        global $order;

        $this->code = 'payeer';
        $this->title = MODULE_PAYMENT_PAYEER_TEXT_TITLE;
        $this->description = MODULE_PAYMENT_PAYEER_TEXT_DESCRIPTION;
        $this->sort_order = MODULE_PAYMENT_PAYEER_SORT_ORDER;
        $this->enabled = ((MODULE_PAYMENT_PAYEER_STATUS == 'Да') ? true : false);
    }

    function update_status()
    {
        return false;
    }

    function javascript_validation()
    {
        return false;
    }

    function selection()
    {
        return array('id' => $this->code, 'module' => $this->title);
    }

    function pre_confirmation_check()
    {
        return false;
    }

    function confirmation()
    {
        return false;
    }

    function process_button()
    {
        return false;
    }

    function before_process()
    {
        return false;
    }

    function after_process()
    {
        global $insert_id, $cart, $order;

		$m_url = MODULE_PAYMENT_PAYEER_MERCHANTURL;
		
        $m_shop = MODULE_PAYMENT_PAYEER_MERCHANTID;
		
        $m_key = MODULE_PAYMENT_PAYEER_SECRETKEY;
		
        $m_orderid = $insert_id;
		
		$m_curr = MODULE_PAYMENT_PAYEER_CURR;
		
        $m_amount = ceil(100*$order->info['total'])*0.01;

        $m_desc = base64_encode('Payment order No. ' . $m_orderid . ' in the store ' . STORE_NAME);
		
		$arHash = array(
			$m_shop,
			$m_orderid,
			$m_amount,
			$m_curr,
			$m_desc,
			$m_key
		);
		$sign = strtoupper(hash('sha256', implode(":", $arHash)));
		
		$user_email = @$order->customer["email_address"];
		
        $url = "$m_url?m_shop=$m_shop&m_orderid=$m_orderid&m_amount=$m_amount&m_curr=$m_curr&m_desc=$m_desc&m_sign=$sign";

		// проверка принадлежности ip списку доверенных ip
		$list_ip_str = str_replace(' ', '', MODULE_PAYMENT_PAYEER_IPFILTER);
		
		if (!empty($list_ip_str)) 
		{
			$list_ip = explode(',', $list_ip_str);
			$this_ip = $_SERVER['REMOTE_ADDR'];
			$this_ip_field = explode('.', $this_ip);
			$list_ip_field = array();
			$i = 0;
			$valid_ip = FALSE;
			foreach ($list_ip as $ip)
			{
				$ip_field[$i] = explode('.', $ip);
				if ((($this_ip_field[0] ==  $ip_field[$i][0]) or ($ip_field[$i][0] == '*')) and
					(($this_ip_field[1] ==  $ip_field[$i][1]) or ($ip_field[$i][1] == '*')) and
					(($this_ip_field[2] ==  $ip_field[$i][2]) or ($ip_field[$i][2] == '*')) and
					(($this_ip_field[3] ==  $ip_field[$i][3]) or ($ip_field[$i][3] == '*')))
					{
						$valid_ip = TRUE;
						break;
					}
				$i++;
			}
		}
		else
		{
			$valid_ip = TRUE;
		}
		
        $cart->reset(true);
        tep_session_unregister('sendto');
        tep_session_unregister('billto');
        tep_session_unregister('shipping');
        tep_session_unregister('payment');
        tep_session_unregister('comments');
		
		switch ($valid_ip)
		{
			case true:
				tep_redirect($url);
			case false:
				$log_text = 
					"--------------------------------------------------------\n".
					"shop				".$m_shop."\n".
					"order id			".$m_orderid."\n".
					"amount				".$m_amount."\n".
					"currency			".$m_curr."\n".
					"description		".$m_desc."\n".
					"status				fail\n".
					"sign				".$sign."\n\n";

				$to = MODULE_PAYMENT_PAYEER_EMAILERROR;
				$subject = "Error payment";
				$message = "Failed to make the payment through the system Payeer for the following reasons:\n\n";
				$message.=" - the ip address of the server is not trusted\n";
				$message.="   trusted ip: " . MODULE_PAYMENT_PAYEER_IPFILTER . "\n";
				$message.="   ip of the current server: ".$_SERVER['REMOTE_ADDR']."\n";
				$message.="\n".$log_text;
				$headers = "From: no-reply@".$_SERVER['HTTP_SERVER']."\r\nContent-type: text/plain; charset=utf-8 \r\n";
				mail($to, $subject, $message, $headers);
				tep_redirect(MODULE_PAYMENT_PAYEER_FAIL);
		}
    }

    function output_error()
    {
        return false;
    }

    function check()
    {
        if (!isset($this->_check)) {
            $check_query = tep_db_query("select configuration_value from " .
                TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_PAYEER_STATUS'");
            $this->_check = tep_db_num_rows($check_query);
        }
        return $this->_check;
    }

    function install()
    {
        tep_db_query("insert into " . TABLE_CONFIGURATION .
            " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('To enable the module', 'MODULE_PAYMENT_PAYEER_STATUS', 'Yes', 'To enable payments via Payeer', '6', '3', 'tep_cfg_select_option(array(\'Yes\', \'No\'), ', now())");
         tep_db_query("insert into " . TABLE_CONFIGURATION .
            " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('The URL of the merchant', 'MODULE_PAYMENT_PAYEER_MERCHANTURL', '//payeer.com/merchant/', 'url for payment in the system Payeer', '6', '15', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION .
            " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('ID store', 'MODULE_PAYMENT_PAYEER_MERCHANTID', '', 'The store identifier registered in the system \"PAYEER\".<br/>it can be found in <a href=\"http://www.payeer.com/account/\">Payeer account</a>: \"Account -> My store -> Edit\".', '6', '4', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION .
            " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Secret key', 'MODULE_PAYMENT_PAYEER_SECRETKEY', '', 'The secret key notification about the payment,<br/>which is used to verify the integrity of the received information<br/>and unambiguous identification of the sender.<br/>Must match the secret key specified in the account Payeer', '6', '5', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION .
            " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('To enable the payment journal', 'MODULE_PAYMENT_PAYEER_LOG', 'Yes', 'Orders are saved in the file: /payeer.log', '6', '6', 'tep_cfg_select_option(array(\'Yes\', \'No\'), ', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION .
            " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('IP filter', 'MODULE_PAYMENT_PAYEER_IPFILTER', '', 'The list of trusted ip addresses, you can specify the mask', '6', '7', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION .
            " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Email for errors', 'MODULE_PAYMENT_PAYEER_EMAILERROR', '', 'Email to send payment errors', '6', '8', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION .
            " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Currency', 'MODULE_PAYMENT_PAYEER_CURR', 'RUB', 'The currency used for payment on the website (RUB, USD, EUR)', '6', '9', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION .
            " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('The status of the order', 'MODULE_PAYMENT_PAYEER_ORDER_STATUS', '0', 'The order status after successful payment', '6', '10', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION .
            " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('The order when sorting', 'MODULE_PAYMENT_PAYEER_SORT_ORDER', '0', 'Place in the list of payment systems', '6', '11', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION .
            " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('The URL for the response system Payeer', 'MODULE_PAYMENT_PAYEER_RESULT', '" .
            HTTP_SERVER . DIR_WS_CATALOG . "payeer.php', 'The parameter \"STATUS of the URL\" in the Cabinet Payeer', '6', '12', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION .
            " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Address upon successful payment', 'MODULE_PAYMENT_PAYEER_SUCCESS', '" .
            HTTP_SERVER . DIR_WS_CATALOG .
            "checkout_success.php', 'The parameter \"SUCCESS URL\" in the Cabinet Payeer', '6', '13', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION .
            " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('The parameter \"SUCCESS URL\" in the Cabinet Payeer', 'MODULE_PAYMENT_PAYEER_FAIL', '" .
            HTTP_SERVER . DIR_WS_CATALOG .
            "checkout_payment.php', 'Parameter \"FAIL URL\" in the personal Cabinet Payeer', '6', '14', now())");
    }

    function remove()
    {
        tep_db_query("delete from " . TABLE_CONFIGURATION .
            " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }

    function keys()
    {
        return array(
			'MODULE_PAYMENT_PAYEER_STATUS', 
			'MODULE_PAYMENT_PAYEER_MERCHANTURL',
			'MODULE_PAYMENT_PAYEER_MERCHANTID',
            'MODULE_PAYMENT_PAYEER_SECRETKEY', 
			'MODULE_PAYMENT_PAYEER_LOG',
			'MODULE_PAYMENT_PAYEER_IPFILTER',
			'MODULE_PAYMENT_PAYEER_EMAILERROR',
			'MODULE_PAYMENT_PAYEER_CURR',
            'MODULE_PAYMENT_PAYEER_ORDER_STATUS', 
			'MODULE_PAYMENT_PAYEER_SORT_ORDER',
            'MODULE_PAYMENT_PAYEER_RESULT', 
			'MODULE_PAYMENT_PAYEER_SUCCESS',
            'MODULE_PAYMENT_PAYEER_FAIL'
		);
    }
}
?>