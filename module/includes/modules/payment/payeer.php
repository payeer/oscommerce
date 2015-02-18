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
		
		$m_curr = $order->info['currency'];
		
        $m_amount = number_format($order->info['total'], 2, '.', '');

        $m_desc = base64_encode(MODULE_PAYMENT_PAYEER_ORDER_DESC);
		
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

        $cart->reset(true);
        tep_session_unregister('sendto');
        tep_session_unregister('billto');
        tep_session_unregister('shipping');
        tep_session_unregister('payment');
        tep_session_unregister('comments');
		tep_redirect($url);
    }

    function output_error()
    {
        return false;
    }

    function check()
    {
        if (!isset($this->_check)) 
		{
            $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_PAYEER_STATUS'");
            $this->_check = tep_db_num_rows($check_query);
        }
		
        return $this->_check;
    }

    function install()
    {
		tep_db_query("insert into " . TABLE_CONFIGURATION .
            " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) 
			values ('Включить модуль', 'MODULE_PAYMENT_PAYEER_STATUS', 'Да', 'Активировать прием платежей через Payeer', '6', '3', 'tep_cfg_select_option(array(\'Да\', \'Нет\'), ', now())");
        
		tep_db_query("insert into " . TABLE_CONFIGURATION .
            " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) 
			values ('URL мерчанта', 'MODULE_PAYMENT_PAYEER_MERCHANTURL', '//payeer.com/merchant/', 'url для оплаты в системе Payeer', '6', '15', now())");
		
		tep_db_query("insert into " . TABLE_CONFIGURATION .
            " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) 
			values ('Идентификатор магазина', 'MODULE_PAYMENT_PAYEER_MERCHANTID', '', 'Идентификатор магазина, зарегистрированного в системе \"PAYEER\".<br/>Узнать его можно в <a href=\"http://www.payeer.com/account/\">аккаунте Payeer</a>: \"Аккаунт -> Мой магазин -> Изменить\".', '6', '4', now())");
        
		tep_db_query("insert into " . TABLE_CONFIGURATION .
            " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) 
			values ('Секретный ключ', 'MODULE_PAYMENT_PAYEER_SECRETKEY', '', 'Секретный ключ оповещения о выполнении платежа,<br/>который используется для проверки целостности полученной информации<br/>и однозначной идентификации отправителя.<br/>Должен совпадать с секретным ключем, указанным в аккаунте Payeer', '6', '5', now())");
		
		tep_db_query("insert into " . TABLE_CONFIGURATION .
            " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) 
			values ('Комментарий к оплате', 'MODULE_PAYMENT_PAYEER_ORDER_DESC', '', 'Пояснение оплаты заказа', '6', '6', now())");
		
		tep_db_query("insert into " . TABLE_CONFIGURATION .
            " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) 
			values ('Путь до файла для журнала оплат через Payeer (например, /payeer_orders.log)', 'MODULE_PAYMENT_PAYEER_LOG', '', 'Если путь не указан, то журнал не записывается', '6', '7', now())");
		
		tep_db_query("insert into " . TABLE_CONFIGURATION .
            " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) 
			values ('IP фильтр', 'MODULE_PAYMENT_PAYEER_IPFILTER', '', 'Список доверенных ip адресов, можно указать маску', '6', '8', now())");
		
		tep_db_query("insert into " . TABLE_CONFIGURATION .
            " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) 
			values ('Email для ошибок', 'MODULE_PAYMENT_PAYEER_EMAILERROR', '', 'Email для отправки ошибок оплаты', '6', '9', now())");
        
		tep_db_query("insert into " . TABLE_CONFIGURATION .
            " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) 
			values ('Статус заказа после успешной оплаты', 'MODULE_PAYMENT_PAYEER_ORDER_STATUS', '0', 'Статус заказа после успешной оплаты', '6', '11', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");
        
		tep_db_query("insert into " . TABLE_CONFIGURATION .
            " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) 
			values ('Статус заказа после неуспешной оплаты', 'MODULE_PAYMENT_PAYEER_ORDER_STATUS_FAIL', '0', 'Статус заказа после неуспешной оплаты', '6', '12', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");
			
		tep_db_query("insert into " . TABLE_CONFIGURATION .
            " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) 
			values ('Очередность при сортировке', 'MODULE_PAYMENT_PAYEER_SORT_ORDER', '0', 'Место в списке платежных систем', '6', '13', now())");
        
		tep_db_query("insert into " . TABLE_CONFIGURATION .
            " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) 
			values ('URL успешной оплаты', 'MODULE_PAYMENT_PAYEER_SUCCESS', '" . HTTP_SERVER . DIR_WS_CATALOG . "checkout_success.php', 'Параметр в личном кабинете Payeer', '6', '14', now())");
        
		tep_db_query("insert into " . TABLE_CONFIGURATION .
            " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) 
			values ('URL неуспешной оплаты', 'MODULE_PAYMENT_PAYEER_FAIL', '" . HTTP_SERVER . DIR_WS_CATALOG . "checkout_payment.php', 'Параметр в личном кабинете Payeer', '6', '15', now())");
		
		tep_db_query("insert into " . TABLE_CONFIGURATION .
            " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) 
			values ('URL обработчика Payeer', 'MODULE_PAYMENT_PAYEER_RESULT', '" . HTTP_SERVER . DIR_WS_CATALOG . "payeer.php', 'Параметр в личном кабинете Payeer', '6', '16', now())");
	}

    function remove()
    {
        tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }

    function keys()
    {
        return array(
			'MODULE_PAYMENT_PAYEER_STATUS', 
			'MODULE_PAYMENT_PAYEER_MERCHANTURL',
			'MODULE_PAYMENT_PAYEER_MERCHANTID',
            'MODULE_PAYMENT_PAYEER_SECRETKEY', 
			'MODULE_PAYMENT_PAYEER_ORDER_DESC', 
			'MODULE_PAYMENT_PAYEER_LOG',
			'MODULE_PAYMENT_PAYEER_IPFILTER',
			'MODULE_PAYMENT_PAYEER_EMAILERROR',
            'MODULE_PAYMENT_PAYEER_ORDER_STATUS', 
			'MODULE_PAYMENT_PAYEER_ORDER_STATUS_FAIL',
			'MODULE_PAYMENT_PAYEER_SORT_ORDER',
            'MODULE_PAYMENT_PAYEER_RESULT', 
			'MODULE_PAYMENT_PAYEER_SUCCESS',
            'MODULE_PAYMENT_PAYEER_FAIL'
		);
    }
}
?>