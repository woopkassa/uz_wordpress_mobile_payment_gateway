<?php

/**
 * The MIT License (MIT)
 *
 * Copyright (c) 2018-2021 Wooppay
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @copyright   Copyright (c) 2012-2021 Wooppay
 * @author      Artyom Narmagambetov <anarmagambetov@wooppay.com>
 * @version     2.0 mobile
 */
class WC_Gateway_Wooppay_Mobile extends WC_Payment_Gateway
{

	public function __construct()
	{
		$this->id = 'wooppay_mobile';
		$this->icon = apply_filters('woocommerce_wooppay_icon',
			plugin_dir_url(__FILE__) . 'assets/images/button_2.svg');
		$this->has_fields = false;
		$this->method_title = __('WOOPPAY', 'Wooppay');
		$this->init_form_fields();
		$this->init_settings();
		$this->title = $this->settings['title'];
		$this->description = $this->settings['description'];
		$this->instructions = $this->get_option('instructions');
		$this->enable_for_methods = $this->get_option('enable_for_methods', array());
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
		add_action('woocommerce_api_wc_gateway_wooppay_mobile', array($this, 'check_response'));
	}

	/**
	 * Web hook handler which triggers after success payment
	 */
	public function check_response()
	{
		if (isset($_REQUEST['id_order']) && isset($_REQUEST['key'])) {
			$order_id = $_REQUEST['id_order'];
			$order_key = $_REQUEST['key'];
			$order = wc_get_order((int)$order_id);
			if ($order && $order->key_is_valid($order_key)) {
				try {
					include_once('WooppayRestClient.php');
					$client = new WooppayRestClient($this->get_option('api_url'), array('trace' => 1));
					if ($client->login($this->get_option('api_username'), $this->get_option('api_password'))) {
						$order->update_status('processing', __('Payment processing.', 'woocommerce'));
						die('{"data":1}');
					}
				} catch (Exception $e) {
					$this->add_log($e->getMessage());
					if ($e->getCode() == 606) {
						throw new Exception("auth_failed");
					} else {
						wc_add_notice(__('Wooppay error:', 'woocommerce') . $e->getMessage() . print_r($order, true),
							'error');
					}
				}
			} else {
				$this->add_log('Error order key: ' . print_r($_REQUEST, true));
			}
		} else {
			$this->add_log('Error call back: ' . print_r($_REQUEST, true));
		}
		die('{"data":0}');
	}

	/**
	 * Admin Panel Options.
	 */
	public function admin_options()
	{
		?>
        <h3><?php _e('Wooppay', 'wooppay_mobile'); ?></h3>
        <table class="form-table">
			<?php $this->generate_settings_html(); ?>
        </table> <?php
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields()
	{
		global $woocommerce;

		$shipping_methods = array();

		if (is_admin()) {
			foreach ($woocommerce->shipping->load_shipping_methods() as $method) {
				$shipping_methods[$method->id] = $method->get_title();
			}
		}

		$this->form_fields = array(
			'enabled' => array(
				'title' => __('Enable/Disable', 'wooppay_mobile'),
				'type' => 'checkbox',
				'label' => __('Enable Wooppay Mobile', 'wooppay_mobile'),
				'default' => 'no'
			),
			'title' => array(
				'title' => __('Title', 'wooppay_mobile'),
				'type' => 'text',
				'description' => __('This controls the title which the user sees during checkout.', 'wooppay_mobile'),
				'desc_tip' => true,
				'default' => __('Wooppay Mobile', 'wooppay_mobile')
			),
			'description' => array(
				'title' => __('Description', 'wooppay_mobile'),
				'type' => 'textarea',
				'description' => __('This controls the description which the user sees during checkout.',
					'wooppay_mobile'),
				'default' => __('Оплата с номера мобильного телефона.', 'wooppay_mobile')
			),
			'instructions' => array(
				'title' => __('Instructions', 'wooppay_mobile'),
				'type' => 'textarea',
				'description' => __('Instructions that will be added to the thank you page.', 'wooppay_mobile'),
				'default' => __('Введите все необходимые данные, нажмите кнопку отправить, введите код из смс и нажмите кнопку отправить повторно.',
					'wooppay_mobile')
			),
			'api_details' => array(
				'title' => __('API Credentials', 'wooppay_mobile'),
				'type' => 'title',
			),
			'api_url' => array(
				'title' => __('API URL', 'wooppay_mobile'),
				'type' => 'text',
				'description' => __('Get your API credentials from Wooppay.', 'wooppay_mobile'),
				'default' => '',
				'desc_tip' => true,
				'placeholder' => __('Optional', 'wooppay_mobile')
			),
			'api_username' => array(
				'title' => __('API Username', 'wooppay_mobile'),
				'type' => 'text',
				'description' => __('Get your API credentials from Wooppay.', 'wooppay_mobile'),
				'default' => '',
				'desc_tip' => true,
				'placeholder' => __('Optional', 'wooppay_mobile')
			),
			'api_password' => array(
				'title' => __('API Password', 'wooppay_mobile'),
				'type' => 'text',
				'description' => __('Get your API credentials from Wooppay.', 'wooppay_mobile'),
				'default' => '',
				'desc_tip' => true,
				'placeholder' => __('Optional', 'wooppay_mobile')
			),
			'order_prefix' => array(
				'title' => __('Order prefix', 'wooppay_mobile'),
				'type' => 'text',
				'description' => __('Order prefix', 'wooppay_mobile'),
				'default' => '',
				'desc_tip' => true,
				'placeholder' => __('Optional', 'wooppay_mobile')
			),
			'service_name' => array(
				'title' => __('Service name', 'wooppay_mobile'),
				'type' => 'text',
				'description' => __('Service name', 'wooppay_mobile'),
				'default' => '',
				'desc_tip' => true,
				'placeholder' => __('Optional', 'wooppay_mobile')
			),
			'terms' => array(
				'title' => __('Terms', 'wooppay_mobile'),
				'type' => 'text',
				'description' => __('Link for terms.', 'wooppay_mobile'),
				'default' => '',
				'desc_tip' => true,
				'placeholder' => __('Optional', 'wooppay_mobile')
			)
		);

	}

	public function payment_fields()
	{
		?>
		<?php if ($this->get_option('terms')): ?>
        <fieldset>
			<?php parent::payment_fields() ?>
            <p>
                <label style="display: flex;align-items: baseline">
                    <input id="isAgree" type="checkbox" name="isAgree" style="margin-right: 10px"/> <span>Я прочитал(а) и ознакомлен с офертой Beeline Uzbekistan и принимаю условия </span>
                    <ul>
                        <li>1. <a href="https://beeline.uz/binaries/content/assets/other-documents/oferta/publicoferta-25122018uz.pdf">Оферта (UZ)</a></li>
                        <li>2. <a href="https://beeline.uz/binaries/content/assets/example/publicoferta-25122018ru.pdf">Оферта (RU)</a></li>
                    </ul>
                </label>
            </p>
            <div class="clear"></div>
        </fieldset>
	<?php else: ?>
		<?php parent::payment_fields() ?>
	<?php endif; ?>
		<?php
	}

	public function validate_fields()
	{
	    if ($this->get_option('terms') && $_POST['isAgree'] !== "on"){
		    wc_add_notice(__('Для старта процесса оплаты необходимо согласие с правилами и условиями сайта.', 'woocommerce'), 'error');
	    }
		return parent::validate_fields(); // TODO: Change the autogenerated stub
	}

	/**
	 * Creates invoice after checkout
	 * @param $order_id
	 * @return array
	 */
	function process_payment($order_id)
	{
		include_once('WooppayRestClient.php');
		global $woocommerce;
		session_start();
		$order = new WC_Order($order_id);
		$code = $order->get_customer_note();
		if (isset($_SESSION[$order_id . 'flag']) && isset($code)) {
			try {
				if (empty($code)) {
					throw new Exception("empty_sms_code");
				}
				$order->save();
				$client = new WooppayRestClient($this->get_option('api_url'));
				if ($client->login($this->get_option('api_username'), $this->get_option('api_password'))) {
					$client->approveMobilePayment($code);
					$woocommerce->cart->empty_cart();
					$order->update_status('pending', __('Payment Pending.', 'woocommerce'));
					unset($_SESSION["note"]);
					unset($_SESSION[$order_id . 'flag']);
					return array(
						'result' => 'success',
						'redirect' => $_SESSION['wooppay_invoice_back_url']
					);
				}
			} catch (Exception $e) {
				if ($e->getCode() == 603) {
					wc_add_notice(__('Недопустимый сотовый оператор для оплаты с мобильного телефона. Допустимые операторы UzTelecom, Beeline.',
						'woocommerce'), 'error');
				} elseif ($e->getCode() == 223) {
					wc_add_notice(__('Неверный код подтверждения.', 'woocommerce'), 'error');
				} elseif ($e->getCode() == 224) {
					wc_add_notice(__('Вы ввели неверный код подтверждения слишком много раз. Попробуйте через 5 минут.',
						'woocommerce'), 'error');
				} elseif ($e->getCode() == 226) {
					wc_add_notice(__('У вас недостаточно средств на балансе мобильного телефона.', 'woocommerce'),
						'error');
				} elseif ($e->getMessage() == 'empty_sms_code') {
					wc_add_notice('В поле комментария отсутствует смс код', 'error');
				} elseif ($e->getMessage() == 'auth_failed') {
					wc_add_notice('Оплата не прошла аутентификацию', 'error');
				} else {
					$this->add_log($e->getMessage());
					wc_add_notice(__($e->getMessage(), 'woocommerce'), 'error');
				}
			}
		} else {
			try {
				if (isset($_SESSION["note"]) || isset($_SESSION[$order_id . 'flag'])) {
					unset($_SESSION["note"]);
					unset($_SESSION[$order_id . 'flag']);
				}
				session_start();
				$phone = $order->get_billing_phone();
				$phone = preg_replace('/[^0-9]/', '', $phone);
				$client = new WooppayRestClient($this->get_option('api_url'));
				if ($client->login($this->get_option('api_username'), $this->get_option('api_password'))) {
					$backUrl = $this->get_return_url($order);
					$requestUrl = WC()->api_request_url('WC_Gateway_Wooppay_Mobile') . '?id_order=' . $order_id . '&key=' . $order->order_key;
					$orderPrefix = $this->get_option('order_prefix');
					$serviceName = $this->get_option('service_name');
					$invoice = $client->createInvoice($orderPrefix . '_' . $order_id, $backUrl, $requestUrl,
						$order->total, $serviceName, $code, '', $order->description, $order->billing_email, $phone);

					$client->requestConfirmationCode($phone, $invoice, $backUrl);
					wc_add_notice(__('Введите код из смс в поле комментария и нажмите отправить заказ. Ваш комментарий уже был сохранён.',
						'woocommerce'));
					$_SESSION[$order_id . 'flag'] = '';
					$_SESSION["note"] = $order->get_customer_note();
				}
			} catch (Exception $e) {
				if ($e->getCode() == 222) {
					wc_add_notice(__('Вы уже запрашивали код подтверждения на данный номер в течение предыдущих 5 минут',
						'woocommerce'), 'error');
				} else {
					$this->add_log($e->getMessage());
					wc_add_notice(__($e->getMessage(), 'woocommerce'), 'error');
				}
			}
		}
	}

	function thankyou()
	{
		echo $this->instructions != '' ? wpautop($this->instructions) : '';
	}

	function add_log($message)
	{
		if ($this->debug == 'yes') {
			if (empty($this->log)) {
				$this->log = new WC_Logger();
			}
			$this->log->add('Wooppay', $message);
		}
	}
}
