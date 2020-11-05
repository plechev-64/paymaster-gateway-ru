<?php

add_action( 'rcl_payments_gateway_init', 'rcl_add_paymaster_gateway' );
function rcl_add_paymaster_gateway() {
	rcl_gateway_register( 'paymaster', 'Rcl_Paymaster_Payment' );
}

class Rcl_Paymaster_Payment extends Rcl_Gateway_Core {
	function __construct() {
		parent::__construct( array(
			'request'	 => 'PMRU_RCL_BAGGAGE',
			'name'		 => rcl_get_commerce_option( 'pmru_custom_name', 'PayMaster' ),
			'submit'	 => __( 'Оплатить через PayMaster' ),
			'image'		 => rcl_addon_url( 'icon.jpg', __FILE__ )
		) );
	}

	function get_options() {

		return array(
			array(
				'type'			 => 'text',
				'slug'			 => 'pmru_custom_name',
				'title'			 => __( 'Наименование платежной системы' ),
				'placeholder'	 => 'PayMaster',
				'notice'		 => '<b>Настройки внутри системы Paymaster</b><br>'
				. 'Страница настроек: https://paymaster.ru/partners/authentication/login<br>'
				. 'Метод формирования контрольной подписи: SHA256<br>'
				. 'В полях RESULT, FAIL и SUCCESS указать URL на страницы созданные для этих целей на сайте.<br>'
				. 'Метод отправки данных: POST'
			),
			array(
				'type'	 => 'text',
				'slug'	 => 'pmru_merchant_id',
				'title'	 => __( 'Идентификатор продавца' )
			),
			array(
				'type'	 => 'password',
				'slug'	 => 'pmru_skey',
				'title'	 => __( 'Секретный ключ' )
			),
			array(
				'type'		 => 'select',
				'slug'		 => 'pmru_fn',
				'title'		 => __( 'Фискализация платежа' ),
				'values'	 => array(
					__( 'Отключено' ),
					__( 'Включено' )
				),
				'childrens'	 => array(
					1 => array(
						array(
							'type'	 => 'select',
							'slug'	 => 'pmru_nds',
							'title'	 => __( 'Ставка НДС' ),
							'values' => array(
								'no_vat' => __( 'без НДС' ),
								'vat0'	 => __( 'НДС по ставке 0%' ),
								'vat10'	 => __( 'НДС по ставке 10%' ),
								'vat18'	 => __( 'НДС по ставке 18%' ),
								'vat110' => __( 'НДС по ставке 10/110' ),
								'vat118' => __( 'НДС по ставке 18/118' )
							)
						)
					)
				)
			)
		);
	}

	function get_form( $data ) {

		$fields = array(
			'LMI_MERCHANT_ID'			 => rcl_get_commerce_option( 'pmru_merchant_id' ),
			'LMI_PAYMENT_AMOUNT'		 => $data->pay_summ,
			'LMI_PAYMENT_NO'			 => $data->pay_id,
			'LMI_CURRENCY'				 => $data->currency,
			'LMI_PAYMENT_DESC_BASE64'	 => base64_encode( $data->description ),
			'PMRU_USER_ID'				 => $data->user_id,
			'PMRU_TYPE'					 => $data->pay_type,
			'PMRU_RCL_BAGGAGE'			 => $data->baggage_data
		);

		if ( rcl_get_commerce_option( 'pmru_fn' ) ) {

			if ( $data->pay_type == 1 ) {

				$fields["LMI_SHOPPINGCART.ITEM[0].NAME"]	 = __( 'Пополнение личного счета' );
				$fields["LMI_SHOPPINGCART.ITEM[0].QTY"]		 = 1;
				$fields["LMI_SHOPPINGCART.ITEM[0].PRICE"]	 = $data->pay_summ;
				$fields["LMI_SHOPPINGCART.ITEM[0].TAX"]		 = rcl_get_commerce_option( 'pmru_nds' );
			} else if ( $data->pay_type == 2 ) {

				$order = rcl_get_order( $data->pay_id );

				if ( $order ) {
					$fields["LMI_SHOPPINGCART.ITEM[0].NAME"]	 = __( 'Оплата заказа' ) . ' №' . $order->order_id;
					$fields["LMI_SHOPPINGCART.ITEM[0].QTY"]		 = 1;
					$fields["LMI_SHOPPINGCART.ITEM[0].PRICE"]	 = $order->order_price;
					$fields["LMI_SHOPPINGCART.ITEM[0].TAX"]		 = rcl_get_commerce_option( 'pmru_nds' );
				}
			} else {

				$fields["LMI_SHOPPINGCART.ITEM[0].NAME"]	 = $data->description;
				$fields["LMI_SHOPPINGCART.ITEM[0].QTY"]		 = 1;
				$fields["LMI_SHOPPINGCART.ITEM[0].PRICE"]	 = $data->pay_summ;
				$fields["LMI_SHOPPINGCART.ITEM[0].TAX"]		 = rcl_get_commerce_option( 'pmru_nds' );
			}

			$fields['LMI_PAYER_EMAIL'] = get_the_author_meta( 'email', $data->user_id );
		}

		return parent::construct_form( [
				'action' => "https://paymaster.ru/payment/init",
				'fields' => $fields
			] );
	}

	function result( $data ) {

		if ( $_REQUEST['LMI_MERCHANT_ID'] != rcl_get_commerce_option( 'pmru_merchant_id' ) ) {
			echo 'ERROR';
			exit;
		}

		if ( $_REQUEST['LMI_PREREQUEST'] == 1 ) {
			echo 'YES';
			exit;
		}

		$_POST = stripslashes_deep( $_POST );

		$string = $_POST['LMI_MERCHANT_ID']
			. ";" . $_POST['LMI_PAYMENT_NO']
			. ";" . $_POST['LMI_SYS_PAYMENT_ID']
			. ";" . $_POST['LMI_SYS_PAYMENT_DATE']
			. ";" . $_POST['LMI_PAYMENT_AMOUNT']
			. ";" . $_POST['LMI_CURRENCY']
			. ";" . $_POST['LMI_PAID_AMOUNT']
			. ";" . $_POST['LMI_PAID_CURRENCY']
			. ";" . $_POST['LMI_PAYMENT_SYSTEM']
			. ";" . $_POST['LMI_SIM_MODE']
			. ";" . rcl_get_commerce_option( 'pmru_skey' );


		$hash = base64_encode( hash( 'sha256', $string, true ) );

		if ( $hash != $_REQUEST['LMI_HASH'] ) {
			rcl_mail_payment_error( $hash );
			echo 'ERROR';
			exit;
		}

		if ( ! parent::get_payment( $_REQUEST['LMI_PAYMENT_NO'] ) ) {
			parent::insert_payment( array(
				'pay_id'		 => $_REQUEST['LMI_PAYMENT_NO'],
				'pay_summ'		 => $_REQUEST['LMI_PAYMENT_AMOUNT'],
				'user_id'		 => $_REQUEST["PMRU_USER_ID"],
				'pay_type'		 => $_REQUEST["PMRU_TYPE"],
				'baggage_data'	 => $_REQUEST["PMRU_RCL_BAGGAGE"]
			) );
			echo 'YES';
			exit;
		}
	}

	function success( $process ) {

		if ( parent::get_payment( $_REQUEST['LMI_PAYMENT_NO'] ) ) {
			wp_redirect( get_permalink( $process->page_successfully ) );
			exit;
		} else {
			wp_die( 'Платеж не найден в базе данных!' );
		}
	}

}
