<?php

/* Brevo's HTTPS API works on Railway plans where outbound SMTP is disabled. */
function shoestagram_send_brevo_email($to, $subject, $message, $reply_to = '')
{
    shoestagram_mail_set_last_error('');

    $api_key = trim(shoestagram_env('BREVO_API_KEY'));
    $smtp_config = shoestagram_mail_config();
    $from_email = filter_var(trim(shoestagram_env('BREVO_FROM_EMAIL', (string) $smtp_config['from_email'])), FILTER_VALIDATE_EMAIL);
    $from_name = shoestagram_clean_mail_header(shoestagram_env('BREVO_FROM_NAME', (string) $smtp_config['from_name']));

    if ($api_key === '' || preg_match('/[\r\n]/', $api_key) || !$from_email) {
        shoestagram_mail_set_last_error('Brevo email settings are incomplete or invalid.');
        error_log('Shoestagram mail: Brevo email settings are incomplete or invalid.');
        return false;
    }

    if (!extension_loaded('curl')) {
        shoestagram_mail_set_last_error('The PHP curl extension is missing.');
        error_log('Shoestagram mail: The PHP curl extension is missing.');
        return false;
    }

    $payload = array(
        'sender' => array('email' => $from_email, 'name' => $from_name),
        'to' => array(array('email' => $to)),
        'subject' => $subject,
        'textContent' => (string) $message,
    );
    if ($reply_to) {
        $payload['replyTo'] = array('email' => $reply_to);
    }

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        shoestagram_mail_set_last_error('Could not encode the email.');
        error_log('Shoestagram mail: Could not encode the email.');
        return false;
    }

    $curl = curl_init('https://api.brevo.com/v3/smtp/email');
    if ($curl === false) {
        shoestagram_mail_set_last_error('Could not start the email request.');
        error_log('Shoestagram mail: Could not start the email request.');
        return false;
    }

    curl_setopt_array($curl, array(
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $json,
        CURLOPT_HTTPHEADER => array(
            'accept: application/json',
            'content-type: application/json',
            'api-key: ' . $api_key,
        ),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ));

    $response = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $curl_error_code = curl_errno($curl);
    curl_close($curl);

    if ($response !== false && $status === 201) {
        return true;
    }

    // Do not log the request body, API key, recipient, or reset code.
    $reason = $response === false
        ? 'Brevo request failed (curl error ' . $curl_error_code . ').'
        : 'Brevo rejected the email (HTTP ' . $status . ').';
    if ($response !== false) {
        $api_error = json_decode((string) $response, true);
        $api_code = is_array($api_error) ? ($api_error['code'] ?? '') : '';
        if (is_string($api_code) && preg_match('/\A[A-Za-z0-9_-]{1,64}\z/', $api_code)) {
            $reason .= ' Brevo code: ' . $api_code . '.';
        }
    }
    shoestagram_mail_set_last_error($reason);
    error_log('Shoestagram mail: ' . $reason);
    return false;
}
