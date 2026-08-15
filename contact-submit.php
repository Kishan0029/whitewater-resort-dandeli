<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$recipientEmail = 'desmonlatandos@gmail.com';

function respond(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function sanitizeLine(string $value): string
{
    return trim((string) preg_replace("/[\r\n]+/", ' ', $value));
}

function getSenderEmail(): string
{
    $host = (string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost');
    $host = strtolower(trim($host));
    $host = preg_replace('/:\d+$/', '', $host) ?? $host;
    $host = preg_replace('/^www\./', '', $host) ?? $host;

    if ($host === '' || $host === 'localhost' || filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
        return 'noreply@localhost';
    }

    return 'noreply@' . $host;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    respond(405, [
        'success' => false,
        'message' => 'Invalid request method.',
    ]);
}

$name = sanitizeLine((string) ($_POST['name'] ?? $_POST['fullName'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$countryCode = sanitizeLine((string) ($_POST['country_code'] ?? ''));
$phone = sanitizeLine((string) ($_POST['phone'] ?? $_POST['contactNo'] ?? ''));
$country = sanitizeLine((string) ($_POST['country'] ?? ''));
$stayType = sanitizeLine((string) ($_POST['stay_type'] ?? ''));
$pageName = sanitizeLine((string) ($_POST['page_name'] ?? ''));
$formLabel = sanitizeLine((string) ($_POST['form_label'] ?? ''));
$terms = sanitizeLine((string) ($_POST['terms'] ?? ''));
$newsletter = sanitizeLine((string) ($_POST['newsletter'] ?? 'No'));

if ($name === '' || $email === '' || $phone === '') {
    respond(422, [
        'success' => false,
        'message' => 'Please complete all required fields.',
    ]);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(422, [
        'success' => false,
        'message' => 'Please enter a valid email address.',
    ]);
}

if ($terms === '' || strtolower($terms) === 'not agreed') {
    respond(422, [
        'success' => false,
        'message' => 'Please agree to the policies and terms first.',
    ]);
}

$phoneCombined = trim($countryCode . ' ' . $phone);
$senderEmail = getSenderEmail();
$subjectSuffix = $pageName !== '' ? ' - ' . $pageName : '';
$subject = 'Ombara Website Form Submission' . $subjectSuffix;

$messageLines = [
    'New enquiry submitted from the Ombara website.',
    '',
    'Full Name: ' . $name,
    'Email: ' . $email,
    'Phone Number: ' . ($phoneCombined !== '' ? $phoneCombined : '-'),
    'Country: ' . ($country !== '' ? $country : '-'),
    'Stay Preference: ' . ($stayType !== '' ? $stayType : '-'),
    'Page: ' . ($pageName !== '' ? $pageName : '-'),
    'Form Label: ' . ($formLabel !== '' ? $formLabel : '-'),
    'Newsletter Consent: ' . ($newsletter !== '' ? $newsletter : 'No'),
    'Terms Accepted: ' . $terms,
];

$message = implode(PHP_EOL, $messageLines);

$headers = [
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'From: Ombara Website <' . $senderEmail . '>',
    'Reply-To: ' . $email,
    'Return-Path: ' . $senderEmail,
    'X-Mailer: PHP/' . phpversion(),
];

$additionalParams = '-f' . $senderEmail;
$sent = @mail($recipientEmail, $subject, $message, implode("\r\n", $headers), $additionalParams);

if (!$sent) {
    $lastError = error_get_last();
    respond(500, [
        'success' => false,
        'message' => 'Email failed to send. Please check hosting mail configuration and sender email domain.',
        'debug' => $lastError['message'] ?? null,
    ]);
}

respond(200, [
    'success' => true,
    'message' => 'Thank you. Your enquiry has been sent successfully and will be reviewed shortly.',
]);
