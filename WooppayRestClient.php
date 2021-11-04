<?php
/**
 * The MIT License (MIT)
 *
 * Copyright (c) 2012-2021 Wooppay
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
 * @version     2.0
 */
class WooppayRestClient
{
	/**
	 * Новая
	 */
	const OPERATION_STATUS_NEW = 1;
	/**
	 * На рассмотрении
	 */
	const OPERATION_STATUS_CONSIDER = 2;
	/**
	 * Отклонена
	 */
	const OPERATION_STATUS_REJECTED = 3;
	/**
	 * Проведена
	 */
	const OPERATION_STATUS_DONE = 4;
	/**
	 * Сторнирована
	 */
	const OPERATION_STATUS_CANCELED = 5;
	/**
	 * Сторнирующая
	 */
	const OPERATION_STATUS_CANCELING = 6;
	/**
	 * Удалена
	 */
	const OPERATION_STATUS_DELETED = 7;
	/**
	 * На квитовании
	 */
	const OPERATION_STATUS_KVITOVANIE = 4;
	/**
	 * На ожидании подверждения или отказа мерчанта
	 */
	const OPERATION_STATUS_WAITING = 9;

	/**
	 * Error list from Beeline UZ
	 */
	const ERROR_BEELINE_UZ_PAYMENT_FAILED = 2501;
	const ERROR_BEELINE_UZ_REVOKE_FAILED = 2502;
	const ERROR_BEELINE_UZ_ALREADY_PERFORMED = 2503;
	const ERROR_BEELINE_UZ_OTP_REQUIRED = 2504;
	const ERROR_BEELINE_UZ_SMS_REQUIRED = 2505;
	const ERROR_BEELINE_UZ_INSUFFICIENT_FUNDS = 2506;
	const ERROR_BEELINE_UZ_UNKNOWN_MSISDN = 2507;
	const ERROR_BEELINE_UZ_INVALID_STATE = 2508;
	const ERROR_BEELINE_UZ_HAS_EXTRA_BALANCE = 2509;
	const ERROR_BEELINE_UZ_TARIFF_PLAN_MISMATCH = 2510;

	/**
	 * @var string
	 */
	private $hostUrl;

	/**
	 * @var resource
	 */
	private $connection;

	/**
	 * @var string - user token
	 */
	private $authToken;

	/**
	 * @param string $url
	 */
	public function __construct($url)
	{
		$this->hostUrl = $url;
	}

	/**
	 * @param string $url
	 */
	private function createConnection($url)
	{
		$this->connection = curl_init($url);
	}

	/**
	 * @return string[]
	 */
	private function getDefaultHeaders()
	{
		return array("Content-Type:application/json", "ip-address:{$_SERVER['REMOTE_ADDR']}");
	}

	/**
	 * @param string $body - json encoded string with request params
	 * @param array $headerList
	 */
	private function setRequestOptions($body, $headerList)
	{
		curl_setopt($this->connection, CURLOPT_HEADER, 1);
		curl_setopt($this->connection, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($this->connection, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($this->connection, CURLOPT_TIMEOUT, 120);
		curl_setopt($this->connection, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($this->connection, CURLOPT_SSL_VERIFYHOST, 0);


		curl_setopt($this->connection, CURLOPT_HTTPHEADER, $headerList);
		if ($body) {
			curl_setopt($this->connection, CURLOPT_POSTFIELDS, $body);
		}
	}

	/**
	 * @return bool|string
	 */
	private function sendRequest()
	{
		return curl_exec($this->connection);
	}

	/**
	 * @param $rawResponse
	 * @return stdClass|null
	 */
	private function getResponse($rawResponse)
	{
		$headerSize = curl_getinfo($this->connection, CURLINFO_HEADER_SIZE);
		$result = substr($rawResponse, $headerSize);
		return json_decode($result);
	}

	/**
	 * Checks response status. If 4xx or 5xx then throws exception.
	 * @throws Exception
	 */
	private function checkResponse($response)
	{
		$responseStatus = curl_getinfo($this->connection, CURLINFO_HTTP_CODE);
		if ($responseStatus >= 400) {
			$errorCode = isset($response[0]->error_code) ? $response[0]->error_code : 0;
			$errorMessage = self::getErrorMessage($errorCode, $response[0]->message);
			throw new Exception($errorMessage, $errorCode);
		}
	}

	/**
	 * @param $errorCode
	 * @param $defaultMessage
	 * @return mixed|string
	 */
	private static function getErrorMessage($errorCode, $defaultMessage)
	{
		$messageList = [
			self::ERROR_BEELINE_UZ_PAYMENT_FAILED => "Oshibka oplaty. Pojaluysta, poprobuyte pozje. To'lovda xatolik. Iltimos, keyinroq qayta urinib ko'ring.",
			self::ERROR_BEELINE_UZ_INSUFFICIENT_FUNDS => "Platej ne byl sovershen. Nedostatochno sredstv. Na Vashem schetu doljno ostat'sya ne menee 2000 sum. To'lov amalga oshirilmadi.
Yetarli mablag' mavjud emas. Hisobingizda kamida 2000 so'm qolishi kerak.",
			self::ERROR_BEELINE_UZ_UNKNOWN_MSISDN => "Ukazanniy nomer ne prinadlejit operatoru. Ko'rsatilgan raqam operatorga tegishli emas.",
			self::ERROR_BEELINE_UZ_INVALID_STATE => "Ukazanniy nomer zablokirovan. Oplata dlya dannogo nomera nedostupna. Ko'rsatilgan raqam bloklangan. Ushbu raqam uchun to'lovni amalga oshirib bo'lmaydi.",
			self::ERROR_BEELINE_UZ_HAS_EXTRA_BALANCE => "Oplata nedostupna pri podklyuchennoy usluge Doveritel'nyy platej. Pojaluysta, povtorite popytku pozje. To'lov amalga oshirilmadi.
Ishonchli to'lov xizmati yoqilgan paytda to'lovni amalga oshirib bo'lmaydi.
Iltimos, keyinroq qayta urinib ko'ring.",
			self::ERROR_BEELINE_UZ_TARIFF_PLAN_MISMATCH => "Platej ne byl sovershen. Oplata nedostupna dlya Vashego tarifnogo plana. To'lov amalga oshirilmadi.
To'lov sizning tarif rejangiz uchun amal qilmaydi.",
		];

		return isset($messageList[$errorCode]) ? $messageList[$errorCode] : $defaultMessage;
	}

	/**
	 * Close CURL session
	 */
	private function closeConnection()
	{
		curl_close($this->connection);
	}

	/**
	 * Returns response after POST request into API
	 *
	 * @param string $coreApiMethod - URL of called method
	 * @param string $body - json encoded attributes of request
	 * @param $headerList
	 * @return stdClass | null
	 * @throws Exception
	 */
	private function handlePostRequest($coreApiMethod, $body, $headerList)
	{
		$this->createConnection($coreApiMethod);
		$this->setRequestOptions($body, $headerList);
		$rawResponse = $this->sendRequest();
		$response = $this->getResponse($rawResponse);
		$this->checkResponse($response);
		$this->closeConnection();
		return $response;
	}

	/**
	 * @param string $login
	 * @param string $pass
	 * @return boolean
	 * @throws Exception
	 */
	public function login($login, $pass)
	{
		$coreApiMethod = $this->hostUrl . '/auth';
		$body = json_encode(array('login' => $login, 'password' => $pass));
		$response = $this->handlePostRequest($coreApiMethod, $body, $this->getDefaultHeaders());
		try {
			$this->authToken = $response->token;
			return true;
		} catch (Exception $exception) {
			wc_add_notice(__('Ошибка при авторизации, скорее всего неверный логин/пароль мерчанта'), 'error');
			return false;
		}
	}

	/**
	 * @param $operationId
	 * @return array|null
	 * @throws Exception
	 */
	public function getOperationData($operationId)
	{
		$coreApiMethod = $this->hostUrl . '/history/transaction/get-operations-data';
		$body = json_encode(['operation_ids' => [$operationId]]);
		$headers = $this->getDefaultHeaders();
		$headers[] = "Authorization:$this->authToken";
		return $this->handlePostRequest($coreApiMethod, $body, $headers);
	}
	
	/**
	 * @param string $referenceId
	 * @param string $backUrl
	 * @param string $requestUrl
	 * @param float $amount
	 * @param string $serviceName
	 * @param string $addInfo
	 * @param string $deathDate
	 * @param string $description
	 * @param string $userEmail
	 * @param string $userPhone
	 * @param int $option
	 * @return stdClass
	 * @throws Exception
	 */
	public function createInvoice(
		$referenceId,
		$backUrl,
		$requestUrl,
		$amount,
		$serviceName = '',
		$addInfo = '',
		$deathDate = '',
		$description = '',
		$userEmail = '',
		$userPhone = '',
		$option = 0
	)
	{
		$coreApiMethod = $this->hostUrl . '/invoice/create';
		$attributes = array(
			'reference_id' => $referenceId,
			'back_url' => $backUrl,
			'request_url' => ['url' => $requestUrl, 'type' => 'POST'],
			'amount' => (float)$amount,
			'option' => $option,
			'death_date' => $deathDate,
			'description' => $description,
			'user_email' => $userEmail,
			'user_phone' => $userPhone,
			'add_info' => $addInfo,
		);
		if ($serviceName) {
			$attributes['serviceName'] = $serviceName;
		}
		$body = json_encode($attributes);
		$headers = $this->getDefaultHeaders();
		$headers[] = "Authorization:$this->authToken";

		return $this->handlePostRequest($coreApiMethod, $body, $headers);
	}

	/**
	 * @param $phone
	 * @param $invoiceData
	 * @param $backUrl
	 * @throws Exception
	 */
	public function requestConfirmationCode($phone, $invoiceData, $backUrl)
	{
		$coreApiMethod = $this->hostUrl . '/invoice/pay-from-mobile';
		$body = json_encode(
			array(
				"invoice_id" => $invoiceData->response->invoice_id,
				"key" => $invoiceData->response->key,
				"user_phone" => $phone
			)
		);
		$headers = $this->getDefaultHeaders();
		$headers[] = "Authorization:$this->authToken";

		$response = $this->handlePostRequest($coreApiMethod, $body, $headers);

		$_SESSION['wooppay_operation_id'] = $response->operation->id;
		$_SESSION['wooppay_invoice_back_url'] = $backUrl;
	}

	/**
	 * @param string $smsCode
	 * @throws Exception
	 */
	public function approveMobilePayment($smsCode)
	{
		$coreApiMethod = $this->hostUrl . '/payment/approve-pay-from-mobile';
		$body = json_encode(
			array(
				"operation_id" => $_SESSION['wooppay_operation_id'],
				"sms_code" => $smsCode,
			)
		);
		$headers = $this->getDefaultHeaders();
		$headers[] = "Authorization:$this->authToken";

		$this->handlePostRequest($coreApiMethod, $body, $headers);
	}

	/**
	 * @param $operation_id
	 * @return array|null
	 * @throws Exception
	 */
	public function getOperationData($operation_id)
	{
		$coreApiMethod = $this->hostUrl . "/history/$operation_id";
		$headers = $this->getDefaultHeaders();
		$headers[] = "Authorization:$this->authToken";

		return $this->handlePostRequest($coreApiMethod, null, $headers);
	}


}
