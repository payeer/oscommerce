<?php
require ('includes/application_top.php');

if (isset($_POST["m_operation_id"]) && isset($_POST["m_sign"]))
{
	$err = false;
	$message = '';

	// запись логов

	$log_text = 
	"--------------------------------------------------------\n" .
	"operation id		" . $_POST['m_operation_id'] . "\n" .
	"operation ps		" . $_POST['m_operation_ps'] . "\n" .
	"operation date		" . $_POST['m_operation_date'] . "\n" .
	"operation pay date	" . $_POST['m_operation_pay_date'] . "\n" .
	"shop				" . $_POST['m_shop'] . "\n" .
	"order id			" . $_POST['m_orderid'] . "\n" .
	"amount				" . $_POST['m_amount'] . "\n" .
	"currency			" . $_POST['m_curr'] . "\n" .
	"description		" . base64_decode($_POST['m_desc']) . "\n" .
	"status				" . $_POST['m_status'] . "\n" .
	"sign				" . $_POST['m_sign'] . "\n\n";
	
	$log_file = MODULE_PAYMENT_PAYEER_LOG;
	
	if (!empty($log_file))
	{
		file_put_contents($_SERVER['DOCUMENT_ROOT'] . $log_file, $log_text, FILE_APPEND);
	}
	
	// проверка цифровой подписи и ip

	$sign_hash = strtoupper(hash('sha256', implode(":", array(
		$_POST['m_operation_id'],
		$_POST['m_operation_ps'],
		$_POST['m_operation_date'],
		$_POST['m_operation_pay_date'],
		$_POST['m_shop'],
		$_POST['m_orderid'],
		$_POST['m_amount'],
		$_POST['m_curr'],
		$_POST['m_desc'],
		$_POST['m_status'],
		MODULE_PAYMENT_PAYEER_SECRETKEY
	))));
	
	$valid_ip = true;
	$sIP = str_replace(' ', '', MODULE_PAYMENT_PAYEER_IPFILTER);
	
	if (!empty($sIP))
	{
		$arrIP = explode('.', $_SERVER['REMOTE_ADDR']);
		if (!preg_match('/(^|,)(' . $arrIP[0] . '|\*{1})(\.)' .
		'(' . $arrIP[1] . '|\*{1})(\.)' .
		'(' . $arrIP[2] . '|\*{1})(\.)' .
		'(' . $arrIP[3] . '|\*{1})($|,)/', $sIP))
		{
			$valid_ip = false;
		}
	}
	
	if (!$valid_ip)
	{
		$message .= " - ip-адрес сервера не является доверенным\n" .
		"   доверенные ip: " . $sIP . "\n" .
		"   ip текущего сервера: " . $_SERVER['REMOTE_ADDR'] . "\n";
		$err = true;
	}

	if ($_POST['m_sign'] != $sign_hash)
	{
		$message .= " - не совпадают цифровые подписи\n";
		$err = true;
	}
	
	if (!$err)
	{
		// загрузка заказа
		
		$order_id = preg_replace('/[^a-zA-Z0-9_-]/', '', substr($_POST['m_orderid'], 0, 32));
		
		$curr_query = tep_db_query('select currency, orders_status from ' . TABLE_ORDERS . ' where orders_id = "' . $order_id . '"');
		$amount_query = tep_db_query('select final_price from ' . TABLE_ORDERS_PRODUCTS . ' where orders_id = "' . $order_id . '"');
		$order = tep_db_fetch_array($curr_query);
		$amount_query_row = tep_db_fetch_array($amount_query);
		
		$order_curr = ($order['currency'] == 'RUR') ? 'RUB' : $order['currency'];
		$order_amount = number_format($amount_query_row['final_price'], 2, '.', '');

		// проверка суммы и валюты
	
		if ($_POST['m_amount'] != $order_amount)
		{
			$message .= " - неправильная сумма\n";
			$err = true;
		}

		if ($_POST['m_curr'] != $order_curr)
		{
			$message .= " - неправильная валюта\n";
			$err = true;
		}

		// проверка статуса
		
		if (!$err)
		{
			switch ($_POST['m_status'])
			{
				case 'success':
					$status = MODULE_PAYMENT_PAYEER_ORDER_STATUS;
					$comment = 'Заказ успешно оплачен через Payeer';
					break;
					
				default:
					$status = MODULE_PAYMENT_PAYEER_ORDER_STATUS_FAIL;
					$comment = 'Заказ оплачен неуспешно через Payeer';
					$message .= ' - статус платежа не является success' . "\n";
					$err = true;
					break;
			}
			
			if ($order['orders_status'] == 1)
			{
				$sql_data_array = array('orders_status' => $status);
				tep_db_perform('orders', $sql_data_array, 'update', "orders_id='" . $order_id . "'");
				
				$sql_data_arrax = array(
					'orders_id' => $order_id, 
					'orders_status_id' => $status, 
					'date_added' => 'now()', 
					'customer_notified' => '0', 
					'comments' => $comment
				);
				tep_db_perform('orders_status_history', $sql_data_arrax);
			}
		}
	}
	
	if ($err)
	{
		$to = MODULE_PAYMENT_PAYEER_EMAILERROR;

		if (!empty($to))
		{
			$message = "Не удалось провести платёж через систему Payeer по следующим причинам:\n\n" . $message . "\n" . $log_text;
			$headers = "From: no-reply@" . $_SERVER['HTTP_HOST'] . "\r\n" . 
			"Content-type: text/plain; charset=utf-8 \r\n";
			mail($to, 'Ошибка оплаты', $message, $headers);
		}

		exit($_POST['m_orderid'] . '|error');
	}
	else
	{
		exit($_POST['m_orderid'] . '|success');
	}
}
?>