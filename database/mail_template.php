<?php

/* Email clients support inline styles more consistently than the storefront CSS. */
function shoestagram_mail_html($subject, $message)
{
    $subject = htmlspecialchars((string) $subject, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $message = str_replace(array("\r\n", "\r"), "\n", (string) $message);
    $is_code_email = stripos((string) $subject, 'code') !== false;
    $sections = preg_split('/\n\s*\n/', trim($message));
    $content = '';

    foreach ($sections as $section) {
        $section = trim($section);
        if ($section === '') {
            continue;
        }
        if ($is_code_email && preg_match('/\A[0-9]{6}\z/', $section)) {
            $content .= '<div style="margin:24px 0;padding:18px 20px;border:1px solid #f2d2ae;border-radius:14px;background:#fff4e8;text-align:center;">'
                . '<div style="margin-bottom:7px;color:#8f4c30;font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;">Your one-time code</div>'
                . '<div style="color:#121722;font-size:32px;font-weight:800;letter-spacing:8px;line-height:1.3;">' . $section . '</div></div>';
            continue;
        }

        $lines = explode("\n", $section);
        $safe_lines = array();
        foreach ($lines as $line) {
            $safe_lines[] = htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        $content .= '<p style="margin:0 0 18px;color:#303745;font-size:15px;line-height:1.7;">'
            . implode('<br>', $safe_lines) . '</p>';
    }

    return '<!doctype html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . $subject . '</title></head>'
        . '<body style="margin:0;padding:0;background:#f7f4ef;font-family:Arial,Helvetica,sans-serif;">'
        . '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background:#f7f4ef;"><tr><td align="center" style="padding:32px 14px;">'
        . '<table role="presentation" cellpadding="0" cellspacing="0" width="600" style="width:100%;max-width:600px;border:1px solid #e8e1d8;border-radius:20px;background:#fff;overflow:hidden;">'
        . '<tr><td style="padding:30px 34px;background:#151925;border-bottom:4px solid #eb7b4e;">'
        . '<div style="color:#f2d2ae;font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;">Sneakers &amp; streetwear</div>'
        . '<div style="margin-top:8px;color:#fff;font-size:27px;font-weight:800;letter-spacing:-1px;">Shoestagram<span style="color:#eb7b4e;">.</span></div>'
        . '</td></tr><tr><td style="padding:34px 34px 16px;">'
        . '<h1 style="margin:0 0 24px;color:#121722;font-size:23px;line-height:1.3;">' . $subject . '</h1>'
        . $content
        . '</td></tr><tr><td style="padding:22px 34px;border-top:1px solid #eee7df;background:#fbf8f3;color:#69717d;font-size:12px;line-height:1.6;">'
        . 'Shoestagram &middot; Sneakers &amp; streetwear<br>This is an automated message from the store.'
        . '</td></tr></table></td></tr></table></body></html>';
}
