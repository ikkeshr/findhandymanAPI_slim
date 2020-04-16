<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require "../vendor/autoload.php";
require "../src/config/DB.php";
require "../src/config/Authentication.php";
require "../src/config/Helpers.php";
require "../src/config/Storage.php"; // To download pictures from firebase storage


// Create and configure Slim app
$config = ['settings' => [
    'addContentLengthHeader' => false,
    'displayErrorDetails' => true,
]];
$app = new \Slim\App($config);


// BRAINTREE API TEST
$app->post('/payment-test', function(Request $request, Response $response){
    $gateway = new Braintree\Gateway([
        'environment' => 'sandbox',
        'merchantId' => 'zjgjkrsb8xdw5dw5',
        'publicKey' => '873tbgzjrkh3zk3k',
        'privateKey' => '4b7e17f8301e9dd11c295183a5542fdc'
    ]);

    $body = $request->getParsedBody();

    if (empty($body['job_id']) || empty($body['nonce'])) {
        return $response->withStatus(400);
    }

    $result = $gateway->transaction()->sale([
        'amount' => '10.00',
        'paymentMethodNonce' => $body['nonce'],
        'billing' => [
            'countryCodeAlpha2' => 'MU'
        ],
        'options' => [ 'submitForSettlement' => true ]
    ]);
    
    $res['statusMsg'] = "";
    $res['transactionId'] = null;
    $res['result'] = null;

    if ($result->success) {
        $res['statusMsg'] = "SUCCESS";
        $res['transactionId'] = $result->transaction->id;
        $res['result'] = $result;
    } else if ($result->transaction) {
        $errMsg = "Error processing transaction:";
        // $errMsg .= " code: " . $result->transaction->processorResponseCode;
        // $errMsg .= "  text: " . $result->transaction->processorResponseText;
        $res['statusMsg'] = $errMsg;
        $res['result'] = $result;
    } else {
        $errMsg = "Validation errors: ";
        // $errMsg .= $result->errors->deepAll();
        $res['statusMsg'] = $errMsg;
        $res['result'] = $result;
    }

    return $response->withJson($res);
});

$app->post('/payment/register', function(Request $request, Response $response){
    $body = $request->getParsedBody();

    $gateway = new Braintree\Gateway([
        'environment' => 'sandbox',
        'merchantId' => 'zjgjkrsb8xdw5dw5',
        'publicKey' => '873tbgzjrkh3zk3k',
        'privateKey' => '4b7e17f8301e9dd11c295183a5542fdc'
    ]);

    // $gateway = new Braintree\Gateway([
    //     'environment' => 'sandbox',
    //     'merchantId' => '2v34ymcxxfckncyh',
    //     'publicKey' => 'bbt6xjdj6hmpxjzt',
    //     'privateKey' => 'f1a17b534fc37075ad13dcbdba1aaef3'
    // ]);

    $merchantAccountParams = [
        'individual' => [
          'firstName' => Braintree\Test\MerchantAccount::$approve,
          'lastName' => 'Doddde',
          'email' => 'jane@gmail.com',
          'dateOfBirth' => '1981-11-19',
          'address' => [
            'streetAddress' => '111 Main St',
            'locality' => 'Chicago',
            'region' => 'IL',
            'postalCode' => '60622'
          ]
        ],
        'funding' => [
          'destination' => Braintree\MerchantAccount::FUNDING_DESTINATION_BANK,
          'accountNumber' => '1123581321',
          'routingNumber' => '071101307'
        ],
        'tosAccepted' => true,
        'masterMerchantAccountId' => "xcwe4534tgdr4tre",
        'id' => "xxxxuidfromfirebasexxxxxx"
      ];

      $result = $gateway->merchantAccount()->create($merchantAccountParams);

      return $response->withJson($result);
});

// STRIPE CONNECT API TEST
$app->post('/payment', function(Request $request, Response $response){
    \Stripe\Stripe::setApiKey('sk_test_8iCp41fdeuKUWnnkr6mnYY0j00MHIRxbhA');

    $body = $request->getParsedBody();

    if (empty($body['amount']) || empty($body['token']) || empty($body['stripe_account']) || empty($body['job_id'])) {
        return $response->withStatus(400);
    }

    $_amount = $body['amount'];
    $_token = $body['token'];//'tok_threeDSecureOptional';
    $_stripe_account = $body['stripe_account'];
    $_job_id = $body['job_id'];
    $_job_title = $body['job_title'];

    $fee = $_amount * 0.15;

    // amount is in cents
    // minimum amount is $0.50 US -> Rs 19.85
    $result = \Stripe\Charge::create([
        'amount' => $_amount,
        'application_fee_amount' => $fee,
        'currency' => 'mur',
        'source' => $_token,
        'description' => $_job_title,
      ], [
        "stripe_account" => $_stripe_account
    ]);

    // Update job table
    $db = new DB();
    $sql = "UPDATE jobs SET online_payment_made=? WHERE job_id=?";
    $db->exec($sql, [1, $_job_id]);
    
    //return $response->withJson($result);
    return $response->withStatus(200);
});

$app->post('/payment/account', function(Request $request, Response $response){
    \Stripe\Stripe::setApiKey('sk_test_8iCp41fdeuKUWnnkr6mnYY0j00MHIRxbhA');

    $result = \Stripe\Account::create([
        'type' => 'custom',
        'country' => 'US',
        'email' => 'test@email.com',
        'requested_capabilities' => [
            'card_payments',
            'transfers',
        ],
        'business_type' => 'individual',
        'tos_acceptance' => [
            "date" => 1586943291,
            "ip" => "102.112.161.255"
        ],
        'individual' => [
            'dob' => ['day'=>'1', 'month'=>'1', 'year'=>'1901'],
            'email' => 'test@email.com',
            'first_name' => 'fname',
            'last_name' => 'lname',
            'phone' => '1 202 555 0191',
            'ssn_last_4' => '0000',
            'address' => [
                'city' => 'Long Beach',
                'country' => 'US',
                'line1' => '8 North Wilson Street Hayward, CA 94544',
                'postal_code' => '90848',
                'state' => 'CA'
            ],
            'id_number' => "000000000"
        ],
        'external_account' => [
            'object' => 'bank_account',
            'country' => 'US',
            'currency' => 'USD',
            'routing_number' => '110000000',
            'account_number' => '000123456789'
        ],
        "business_profile"=> [
            "mcc"=> "7299",
            "name"=> 'Handyman2',
            "product_description"=> "Provide handyman services",
            "url"=> "https://www.facebook.com/ikesh00"
        ]
    ]);
    
    $stripeAccountId = $result->id;
    // $stripeAccount = \Stripe\Account::retrieve(
    //     $stripeAccountId
    // );

    // Insert stripe account id in database

    //return $response->withJson($stripeAccount);
    return $response->withStatus(200);
});

$app->put('/payment/account', function(Request $request, Response $response){
    \Stripe\Stripe::setApiKey('sk_test_8iCp41fdeuKUWnnkr6mnYY0j00MHIRxbhA');
    
    $stripeAccountId = 'acct_1GYWJhL0GKKVePfo';

    // -SSN last 4
    // -Bank account or debit card
    // -Business website
    // -Date of birth
    // -Legal name
    // -Representative's address
    // -Email
    // -Representative's phone number
    // -Industry

    $result = \Stripe\Account::update(
        $stripeAccountId,
        [
            'individual' => [
                'dob' => ['day'=>'1', 'month'=>'1', 'year'=>'1901'],
                'email' => 'test@email.com',
                'first_name' => 'fname',
                'last_name' => 'lname',
                'phone' => '1 202 555 0191',
                'ssn_last_4' => '0000',
                'address' => [
                    'city' => 'Long Beach',
                    'country' => 'US',
                    'line1' => '8 North Wilson Street Hayward, CA 94544',
                    'postal_code' => '90848',
                    'state' => 'CA'
                ],
                'id_number' => "000000000"
            ],
            'external_account' => [
                'object' => 'bank_account',
                'country' => 'US',
                'currency' => 'USD',
                'routing_number' => '110000000',
                'account_number' => '000123456789'
            ],
            "business_profile"=> [
                "mcc"=> "7299",
                "name"=> 'Handyman1',
                "product_description"=> "Provide handyman services",
                "url"=> "https://www.facebook.com/ikesh00"
            ]
        ]
    );

    return $response->withJson($result);
});

$app->delete('/payment/account', function(Request $request, Response $response){
    \Stripe\Stripe::setApiKey('sk_test_8iCp41fdeuKUWnnkr6mnYY0j00MHIRxbhA');

    $account = \Stripe\Account::retrieve(
        'acct_1GYWH3KVZ3XfBlod'
    );
    $account->delete();
});

$app->get('/payment/account/{id}', function(Request $request, Response $response){
    $stripeAccountId = $request->getAttribute('id');

    \Stripe\Stripe::setApiKey('sk_test_8iCp41fdeuKUWnnkr6mnYY0j00MHIRxbhA');

    $balanceObj = \Stripe\Balance::retrieve(
        ['stripe_account' => $stripeAccountId]
    );

    $balance = [
        'balance' => ($balanceObj->pending[0]->amount / 100),
        'currency' => $balanceObj->pending[0]->currency
    ];

    $res = [
        'balance' => $balance
    ];

    return $response->withJson($res);
});

// routes
require '../src/routes/users.php';
require '../src/routes/services.php';
require '../src/routes/jobs.php';

// Run app
$app->run();

?>