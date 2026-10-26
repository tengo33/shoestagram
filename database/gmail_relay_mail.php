<?php

/* Send through a Gmail-owned Apps Script web app over HTTPS on Railway. */
function shoestagram_send_gmail_relay_email($to, $subject, $message, $reply_to = '', $html_message = '')
{
    shoestagram_mail_set_last_error('');

    $url = trim(shoestagram_env('GMAIL_RELAY_URL'));
    $secret = trim(shoestagram_env('GMAIL_RELAY_SECRET'));
    if (!preg_match('#\Ahttps://script\.google\.com/macros/s/[A-Za-z0-9_-]+/exec\z#', $url)
        || strlen($secret) < 32 || preg_match('/[\r\n]/', $secret)) {
        shoestagram_mail_set_last_error('Gmail relay settings are incomplete or invalid.');
        error_log('Shoestagram mail: Gmail relay settings are incomplete or invalid.');
        return false;
    }

    if (!extension_loaded('curl')) {
        shoestagram_mail_set_last_error('The PHP curl extension is missing.');
        error_log('Shoestagram mail: The PHP curl extension is missing.');
        return false;
    }

    $payload = array(
        'secret' => $secret,
        'to' => $to,
        'subject' => $subject,
        'body' => (string) $message,
        'htmlBody' => (string) $html_message,
        'replyTo' => $reply_to ?: '',
    );
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        shoestagram_mail_set_last_error('Could not encode the email.');
        error_log('Shoestagram mail: Could not encode the email for Gmail relay.');
        return false;
    }

    $curl = curl_init($url);
    if ($curl === false) {
        shoestagram_mail_set_last_error('Could not start the Gmail relay request.');
        error_log('Shoestagram mail: Could not start the Gmail relay request.');
        return false;
    }

    curl_setopt_array($curl, array(
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $json,
        CURLOPT_HTTPHEADER => array('content-type: application/json', 'accept: application/json'),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 30,
        // Apps Script's ContentService redirects its JSON response once.
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 2,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ));
    if (defined('CURLOPT_REDIR_PROTOCOLS') && defined('CURLPROTO_HTTPS')) {
        curl_setopt($curl, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTPS);
    }

    $response = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $curl_error_code = curl_errno($curl);
    curl_close($curl);

    $result = $response !== false ? json_decode((string) $response, true) : null;
    if ($status === 200 && is_array($result) && ($result['ok'] ?? false) === true) {
        return true;
    }

    // Never log the response body: it could contain an email address or code.
    $reason = $response === false
        ? 'Gmail relay request failed (curl error ' . $curl_error_code . ').'
        : 'Gmail relay rejected the email (HTTP ' . $status . ').';
    $code = is_array($result) ? ($result['code'] ?? '') : '';
    if (is_string($code) && preg_match('/\A[A-Za-z0-9_-]{1,64}\z/', $code)) {
        $reason .= ' Relay code: ' . $code . '.';
    }
    shoestagram_mail_set_last_error($reason);
    error_log('Shoestagram mail: ' . $reason);
    return false;
}
