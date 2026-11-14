
<?php

header('Content-Type: application/json; charset=utf-8');

$webhookSecret = 'PASTE_THE_WEBHOOK_SIGNING_SECRET_HERE';

$rawBody = file_get_contents('php://input');

$signature = $_SERVER['HTTP_X_PAYMENT_SIGNATURE'] ?? '';

if ($rawBody === false || $rawBody === '' || $signature === '') {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Missing webhook data or signature.'
    ]);

    exit;
}

$expectedSignature = 'sha256=' . hash_hmac(
    'sha256',
    $rawBody,
    $webhookSecret
);

if (!hash_equals($expectedSignature, $signature)) {
    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid webhook signature.'
    ]);

    exit;
}

$event = json_decode($rawBody, true);

if (
    !is_array($event) ||
    empty($event['id']) ||
    empty($event['event']) ||
    empty($event['data']['transaction_ref'])
) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid webhook payload.'
    ]);

    exit;
}

// Log the received event during development.
// Do not log the signing secret or raw credentials.
error_log(
    'Payment webhook received: ' .
    $event['event'] .
    ' - ' .
    $event['data']['transaction_ref']
);

http_response_code(200);

echo json_encode([
    'success' => true,
    'message' => 'Webhook received successfully.'
]);
