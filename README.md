CSOB payment system integration
===

You can find complete documentation with examples here - https://github.com/csob/paymentgateway/wiki/eAPI-v1.6-EN

1. Testing and production environments
---

For testing purposes you can use merchantId **A1029DTmM7** and keys located in the *test_keys* folder.
Or you can generate your sandbox account here https://iplatebnibrana.csob.cz/keygen/

To create set of production keys use this service https://platebnibrana.csob.cz/keygen/

List of credit cards for testing https://github.com/csob/paymentgateway/wiki/Credit-Cards-for-Testing

2. Examples
---

Basic usage

```php

require 'Csob.php';

$production = false;
$merchantId = 'A1029DTmM7';
$privateKey = __DIR__ .'/test_keys/rsa_A1029DTmM7.key';
$publicKey = __DIR__ .'/test_keys/mips_iplatebnibrana.csob.cz.pub'; // This public key used for all test keys

$orderId = 123;
$amount = 50;

$csob = new payment\csob\Csob($merchantId, $privateKey, $publicKey, $production);

$csob->setDescription('Some description')
	->setOrderId($orderId)
	->setReturnUrl('http://yourshop.com/some-url')
	->addToCart('Item name', $amount);


try {
	$payId = $csob->init($amount, 'EUR');

	$url = $csob->getProcessUrl($payId);

	echo "<a target='_blank' href='$url'>$url</a>";

} catch (\Exception $e) {
	echo 'Error - ' . $e->getMessage();
}


```

Advanced options

```php

$csob->setDescription('Some description')
	->setOrderId($orderId)
	->setReturnUrl('http://yourshop.com/some-url')
	->addToCart('Item 1 name', $amount, 3, 'Description')
	->addToCart('Item 2 name', $amount) // Max 2 items
	->setMerchantData(base64_encode('some-string-here'))
	->setLanguage('en'); // Default is en

```

3. Receiving payment confirmation
---

```php

try {
    $csob->verifyResponse($_GET);

	if ($_GET['resultCode'] == 0 && $_GET['paymentStatus'] == 7) {

		// Payment complete
	} else {
		// Error or another payment statuses
	}

} catch (Exception $e) {
    echo 'Error - ' . $e->getMessage();
}

```