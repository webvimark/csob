<?php
namespace payment\csob;

/**
 * https://github.com/csob/paymentgateway/wiki/eAPI-v1.6-EN
 *
 * Class Csob
 * @package payment\csob
 */
class Csob
{
    const PRODUCTION_URL = 'https://api.platebnibrana.csob.cz/api/v1.6/payment/';
    const TEST_URL = 'https://iapi.iplatebnibrana.csob.cz/api/v1.6/payment/';

    /**
     * Use production or test url
     *
     * @var bool
     */
    protected $production = false;
    /**
     * Merchant ID issued by CSOB
     *
     * @var string
     */
    protected $merchantId;
    /**
     * Path to privateKey file
     *
     * @var string
     */
    protected $privateKeyFile;
    /**
     * @var array
     */
    protected $cart = [];
    /**
     * @var string
     */
    protected $language = 'EN';
    /**
     * Any additional data which are returned in the redirect from the payment gateway to the merchant’s page.
     * Such data may be used to keep continuity of the process in the e-shop, they must be BASE64 encoded.
     * Maximum length for encoding is 255 characters
     *
     * @var string
     */
    protected $merchantData = '';
    /**
     * @var string
     */
    protected $returnUrl;
    /**
     * @var string
     */
    protected $description;
    /**
     * @var int
     */
    protected $orderId;

    /**
     * @param string $merchantId
     * @param string $privateKeyFile
     * @param bool   $production
     */
    public function __construct($merchantId, $privateKeyFile, $production)
    {
        $this->merchantId = $merchantId;
        $this->privateKeyFile = $privateKeyFile;
        $this->production = $production;
    }

    /**
     * @param string $payId
     *
     * @return string
     */
    public function getProcessUrl($payId)
    {
        $dttm = date('YmdHis');

        $sign = $this->sign($this->createSignString([
                    $this->merchantId,
                    $payId,
                    $dttm
                ]));

        return $this->getBaseUrl() . sprintf('process/%s/%s/%s/%s', $this->merchantId, $payId, $dttm, urlencode($sign));
    }

    /**
     * @param double $totalAmount
     * @param string $currency
     *
     * @return string
     * @throws \Exception
     */
    public function init($totalAmount, $currency)
    {
        if (!$this->cart) {
            throw new \Exception('Cart is empty');
        }

        if (!$this->returnUrl) {
            throw new \Exception('Return url is required');
        }

        if (!$this->orderId) {
            throw new \Exception('OrderId is required');
        }

        if (!$this->description) {
            throw new \Exception('Description is required');
        }

        $currency = strtoupper($currency);

        $data = [
            'merchantId'   => $this->merchantId,
            'orderNo'      => $this->orderId,
            'dttm'         => date('YmdHis'),
            'payOperation' => 'payment',
            'payMethod'    => 'card',
            'totalAmount'  => $totalAmount * 100,
            'currency'     => $currency,
            'closePayment' => 'true',
            'returnUrl'    => $this->returnUrl,
            'returnMethod' => 'GET',
            'cart'         => $this->cart,
            'description'  => $this->description,
            'merchantData' => $this->merchantData,
            'language'     => $this->language,
        ];

        $response = $this->connect('init', $data);

        if ($response && $response['resultCode'] == 0 && $response['paymentStatus'] == 1) {
            return $response['payId'];
        } else {
            throw new \Exception($response['resultMessage']);
        }
	}

    /**
     * @param string $payId
     *
     * @return string
     * @throws \Exception
     */
    public function reverse($payId)
    {
        $data = [
            'merchantId' => $this->merchantId,
            'payId'      => $payId,
            'dttm'       => date('YmdHis'),
        ];

        $response = $this->connect('reverse', $data);

        if ($response && $response['resultCode'] == 0 && $response['paymentStatus'] == 5) {
            return true;
        } else {
            throw new \Exception($response['resultMessage']);
        }
	}

    /**
     * WARNING!!! Max 2 items can be in the cart (e.g. “Your purchase” and “Shipping & Handling”).
     *
     * @param string $name max 20 characters
     * @param double $amount
     * @param int    $quantity
     * @param string $description max 40 characters
     *
     * @return $this
     * @throws \Exception
     */
    public function addToCart($name, $amount, $quantity = 1, $description = '')
    {
        if (count($this->cart) === 2) {
            throw new \Exception('Max 2 items can be in the cart');
        }

        if (mb_strlen($description) > 40) {
            // max 40 characters
            $description = mb_substr(trim($description), 0, 37, 'utf-8') . "...";
        }
        array_push($this->cart, [
                'name'        => $name,
                'quantity'    => $quantity,
                'amount'      => $amount * 100,
                'description' => $description,
            ]);

        return $this;
    }

    /**
     * Remove current items from cart, allowing to add another items
     *
     * @return $this
     */
    public function clearCart()
    {
        $this->cart = [];

        return $this;
    }

    /**
     * @param string $language
     *
     * @return $this
     */
    public function setLanguage($language)
    {
        $language = strtoupper($language);

        $availableLanguages = ['CZ', 'EN', 'DE', 'FR', 'HU', 'IT', 'JP', 'PL', 'PT', 'RO', 'RU', 'SK', 'ES', 'TR', 'VN'];

        if (in_array($language, $availableLanguages)) {
            $this->language = $language;
        }

        return $this;
    }

    /**
     * Any additional data which are returned in the redirect from the payment gateway to the merchant’s page.
     * Such data may be used to keep continuity of the process in the e-shop, they must be BASE64 encoded.
     * Maximum length for encoding is 255 characters
     *
     * @param string $merchantData
     *
     * @return $this
     */
    public function setMerchantData($merchantData)
    {
        $this->merchantData = $merchantData;

        return $this;
    }

    /**
     * @param string $returnUrl
     *
     * @return $this
     */
    public function setReturnUrl($returnUrl)
    {
        $this->returnUrl = $returnUrl;

        return $this;
    }

    /**
     * @param string $description
     *
     * @return $this
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @param string $orderId
     *
     * @return $this
     */
    public function setOrderId($orderId)
    {
        $this->orderId = $orderId;

        return $this;
    }

    /**
     * @param string $action
     * @param array  $postData
     *
     * @throws \Exception
     * @return array
     */
    protected function connect($action, array $postData)
    {
        $postData['signature'] = $this->sign($this->createSignString($postData));

        $httpMethod = $action === 'reverse' ? 'PUT' : 'POST';

        $ch = curl_init($this->getBaseUrl() . $action);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $httpMethod);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json;charset=UTF-8'
        ]);

        $result = curl_exec($ch);

        $responseHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_errno($ch) ? curl_error($ch) : false;

        curl_close($ch);

        if ($curlError) {
            throw new \Exception( 'Curl error - ' . $curlError );
        }

        if ($responseHttpCode != 200) {
            throw new \Exception( 'Response error http code - ' . $responseHttpCode );
        }

        $response = json_decode($result, true);

        $this->verifyResponse($response);

        return $response;
    }

    /**
     * @param array $data
     *
     * @return string
     */
    protected function createSignString(array $data)
    {
        $output = [];

        foreach ($data as $item) {
            if (is_array($item)) {
                $output[] = $this->createSignString($item);
            } else {
                $output[] = $item;
            }
        }

        return implode('|', $output);
    }

    /**
     * @param string $text
     *
     * @return string
     * @throws \Exception
     */
    protected function sign($text)
    {
        $fp = fopen($this->privateKeyFile, "r");
        if (!$fp) {
            throw new \Exception('Private key not found');
        }

        $private = fread($fp, filesize($this->privateKeyFile));
        fclose($fp);

        $privateKeyId = openssl_get_privatekey($private);
        openssl_sign($text, $signature, $privateKeyId);
        $signature = base64_encode($signature);
        openssl_free_key($privateKeyId);

        return $signature;
    }

    /**
     * @param array $response
     *
     * @throws \Exception
     */
    public function verifyResponse(array $response)
    {
        $signatureBase64 = $response['signature'];
        unset($response['signature']);

        $text = $response ['payId'] . "|" . $response ['dttm'] . "|" . $response ['resultCode'] . "|" . $response ['resultMessage'];

        if(isset($response['paymentStatus']) && !is_null($response ['paymentStatus'])) {
            $text = $text  . "|" . $response ['paymentStatus'];
        }

        if(isset($response ['authCode']) && !is_null($response ['authCode'])) {
            $text = $text  . "|" . $response ['authCode'];
        }

        if(isset($response ['merchantData']) && !is_null($response ['merchantData'])) {
            $text = $text  . "|" . $response ['merchantData'];
        }

        if ($this->production) {
            $publicKeyFile = __DIR__ . '/keys/mips_platebnibrana.csob.cz.pub';
        } else {
            $publicKeyFile = __DIR__ . '/keys/mips_iplatebnibrana.csob.cz.pub';
        }

        $fp = fopen ( $publicKeyFile, "r" );
        if (! $fp) {
            throw new \Exception('Public key not found');
        }
        $public = fread ( $fp, filesize ( $publicKeyFile ) );
        fclose ( $fp );
        $publicKeyId = openssl_get_publickey ( $public );
        $signature = base64_decode ( $signatureBase64 );
        $res = openssl_verify ( $text, $signature, $publicKeyId );
        openssl_free_key ( $publicKeyId );

        if ($res != '1') {
            throw new \Exception('Response verification failed');
        }
    }

    /**
     * @return string
     */
    protected function getBaseUrl()
    {
        return $this->production ? static::PRODUCTION_URL : static::TEST_URL;
    }
}
