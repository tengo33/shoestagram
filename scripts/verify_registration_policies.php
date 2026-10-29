<?php
declare(strict_types=1);

require_once __DIR__ . '/../login/registration-policies.php';

function ensure_policy(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$versions = shoestagram_registration_policy_versions();
ensure_policy(count(shoestagram_registration_terms()) === 13, 'Terms coverage is incomplete.');
ensure_policy(count(shoestagram_registration_privacy_policy()) === 10, 'Privacy coverage is incomplete.');
ensure_policy(!shoestagram_registration_agreement_requested(null), 'Missing checkbox was accepted.');
ensure_policy(!shoestagram_registration_agreement_requested('on'), 'Unexpected checkbox value was accepted.');
ensure_policy(shoestagram_registration_agreement_requested('1'), 'Manual checkbox value was rejected.');

$agreement = array(
    'accepted' => true,
    'accepted_at_utc' => '2026-10-29T08:00:00Z',
    'terms_version' => $versions['terms'],
    'privacy_version' => $versions['privacy'],
);
ensure_policy(shoestagram_registration_agreement_is_current($agreement), 'Current agreement was rejected.');
ensure_policy(!shoestagram_registration_agreement_is_current(array_merge($agreement, array('accepted' => false))), 'Unchecked agreement was accepted.');
ensure_policy(!shoestagram_registration_agreement_is_current(array_merge($agreement, array('terms_version' => 'older'))), 'Old Terms version was accepted.');
ensure_policy(!shoestagram_registration_agreement_is_current(array_merge($agreement, array('privacy_version' => 'older'))), 'Old Privacy version was accepted.');
ensure_policy(!shoestagram_registration_agreement_is_current(array_merge($agreement, array('accepted_at_utc' => ''))), 'Missing acceptance time was accepted.');
$description = shoestagram_registration_agreement_description($agreement);
foreach (array($versions['terms'], $versions['privacy'], $agreement['accepted_at_utc']) as $expected) {
    ensure_policy(strpos($description, $expected) !== false, 'Audit description omitted agreement metadata.');
}

$markdown = '# Shoestagram Policies for Owner Approval' . "\n\n"
    . 'These are the exact draft policy sections displayed in the customer registration modals. Publishing them requires the business owner to confirm the points below.' . "\n\n"
    . '## Owner decisions before publication' . "\n\n"
    . '1. Confirm the legal business or data-controller name behind Shoestagram and the correct privacy contact address. The current site uses its configured support email.' . "\n"
    . '2. Confirm the return, refund, and cancellation handling described below, including any required consumer-law process.' . "\n"
    . '3. Confirm how long account, order, contact, payment-proof, and agreement records are kept, and how privacy requests are handled. The current app has no automatic deletion schedule.' . "\n"
    . '4. Confirm Railway, Google Apps Script/Gmail, Brevo, any configured SMTP provider, Google Fonts, and Cloudflare CDN disclosures against actual production services.' . "\n"
    . '5. Confirm how existing customers will be told about future material Terms changes.' . "\n\n";
foreach (array(
    'Terms and Conditions' => array($versions['terms'], shoestagram_registration_terms()),
    'Privacy Policy' => array($versions['privacy'], shoestagram_registration_privacy_policy()),
) as $title => $document) {
    [$version, $sections] = $document;
    $markdown .= '## ' . $title . ' — version ' . $version . "\n\n";
    $headings = array();
    foreach ($sections as [$heading, $body]) {
        ensure_policy($heading !== '' && $body !== '' && !in_array($heading, $headings, true), 'Empty or duplicate policy section.');
        $headings[] = $heading;
        $markdown .= '### ' . $heading . "\n\n" . $body . "\n\n";
    }
}
file_put_contents(__DIR__ . '/../docs/Shoestagram-Policies-For-Approval.md', $markdown);
echo "Policy coverage, explicit agreement guards, version checks, and audit metadata passed.\n";
