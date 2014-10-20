<?php

require ('includes/application_top.php');

if (isset($_POST["m_operation_id"]) && isset($_POST["m_sign"]))
{
	$m_key = MODULE_PAYMENT_PAYEER_SECRETKEY;
	
	$arHash = array($_POST['m_operation_id'],
			$_POST['m_operation_ps'],
			$_POST['m_operation_date'],
			$_POST['m_operation_pay_date'],
			$_POST['m_shop'],
			$_POST['m_orderid'],
			$_POST['m_amount'],
			$_POST['m_curr'],
			$_POST['m_desc'],
			$_POST['m_status'],
			$m_key);
	$sign_hash = strtoupper(hash('sha256', implode(":", $arHash)));
	
	$log_text = 
		"--------------------------------------------------------\n".
		"operation id		".$_POST["m_operation_id"]."\n".
		"operation ps		".$_POST["m_operation_ps"]."\n".
		"operation date		".$_POST["m_operation_date"]."\n".
		"operation pay date	".$_POST["m_operation_pay_date"]."\n".
		"shop				".$_POST["m_shop"]."\n".
		"order id			".$_POST["m_orderid"]."\n".
		"amount				".$_POST["m_amount"]."\n".
		"currency			".$_POST["m_curr"]."\n".
		"description		".base64_decode($_POST["m_desc"])."\n".
		"status				".$_POST["m_status"]."\n".
		"sign				".$_POST["m_sign"]."\n\n";

	if (MODULE_PAYMENT_PAYEER_LOG == "Yes")
	{
		file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/payeer.log', $log_text, FILE_APPEND);
	}

	if ($_POST["m_sign"] == $sign_hash && $_POST['m_status'] == "success")
	{
		$sql_data_array = array('orders_status' => MODULE_PAYMENT_PAYEER_ORDER_STATUS);
		
		tep_db_perform('orders', $sql_data_array, 'update', "orders_id='" . $_POST['m_orderid'] . "'");

		$sql_data_arrax = array('orders_id' => $_POST['m_orderid'], 'orders_status_id' =>
			MODULE_PAYMENT_ONPAY_ORDER_STATUS, 'date_added' => 'now()', 'customer_notified' =>
			'0', 'comments' => 'Payeer accepted this order payment');
			
		tep_db_perform('orders_status_history', $sql_data_arrax);

		echo $_POST['m_orderid']."|success";
		exit;
	}

	echo $_POST['m_orderid']."|error";
	
	$to = MODULE_PAYMENT_PAYEER_EMAILERROR;
	$subject = "Error payment";
	$message = "Failed to make the payment through the system Payeer for the following reasons:\n\n";
	if ($_POST["m_sign"] != $sign_hash)
	{
		$message.=" - Do not match the digital signature\n";
	}
	if ($_POST['m_status'] != "success")
	{
		$message.=" - The payment status is not success\n";
	}
	$message.="\n".$log_text;
	$headers = "From: no-reply@".$_SERVER['HTTP_SERVER']."\r\nContent-type: text/plain; charset=utf-8 \r\n";
	mail($to, $subject, $message, $headers);
}
?>