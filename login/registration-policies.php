<?php

function shoestagram_registration_policy_versions()
{
    return array('terms' => '2026-10-29', 'privacy' => '2026-10-29');
}

function shoestagram_registration_agreement_is_current($agreement)
{
    $versions = shoestagram_registration_policy_versions();
    return is_array($agreement)
        && ($agreement['accepted'] ?? false) === true
        && ($agreement['terms_version'] ?? '') === $versions['terms']
        && ($agreement['privacy_version'] ?? '') === $versions['privacy']
        && !empty($agreement['accepted_at_utc']);
}

function shoestagram_registration_agreement_requested($value)
{
    return $value === '1';
}

function shoestagram_registration_agreement_description($agreement)
{
    return 'Accepted Terms and Conditions version ' . $agreement['terms_version']
        . ' and Privacy Policy version ' . $agreement['privacy_version']
        . ' at ' . $agreement['accepted_at_utc'] . '.';
}

function shoestagram_registration_terms()
{
    return array(
        array('Acceptance of Terms', 'By registering an account, you agree to these Terms and Conditions. Please read them before you check the agreement box. These terms apply to your use of the Shoestagram website and its customer features.'),
        array('Customer Account Registration', 'You must provide your own accurate name and email address, create a password, and complete email verification. Your account is created only after a valid verification code is entered.'),
        array('Customer Responsibilities', 'Keep your account information reasonably accurate. Use the site for genuine shopping and reservation activity, and contact Shoestagram if you notice an error in your account or order details.'),
        array('Product Information and Availability', 'Product descriptions, images, sizes, and stock are provided to help you choose. Availability can change before checkout is completed. Contact the store if a product detail needs clarification.'),
        array('Product Prices', 'Prices are shown in Philippine pesos. Review the price displayed at checkout before submitting a reservation. Shoestagram may update catalog prices; a price shown earlier may differ from the current checkout price.'),
        array('Ordering and Checkout', 'Submitting checkout creates a reservation for store processing. A reservation may move through Pending, Confirmed, Ready for Pickup, Completed, or Cancelled status. You can follow its status in your customer account. Checkout does not itself confirm payment or delivery.'),
        array('Payments and Transactions', 'Shoestagram offers in-store and online payment options. Online payment uses a customer-entered reference and uploaded proof for staff review; it is not an automatic payment confirmation. Do not treat a submitted reference or screenshot as proof that staff have accepted payment.'),
        array('Order Cancellation and Returns', 'The customer account allows cancellation while a reservation is Pending or Confirmed. For a later status, payment concern, return, or refund request, contact the store team. Shoestagram reviews such requests under applicable Philippine consumer law; this website does not provide an automatic return or refund approval.'),
        array('Product Inventory and Stock Availability', 'Shoestagram checks product and available-size stock during checkout. A product shown in the catalog may become unavailable before a reservation is accepted or fulfilled.'),
        array('Account Security', 'Keep your password and verification codes private. Use the password reset process if you lose access, and contact the store if you suspect unauthorized use of your account.'),
        array('Prohibited Activities', 'Do not use false account information, interfere with the website, attempt unauthorized access, upload misleading payment proof, or make reservations for fraudulent purposes.'),
        array('Changes to the Terms', 'Shoestagram may publish an updated version of these terms. The version displayed during registration is the version accepted with that new account. Contact the store with questions about the version that applies to your account.'),
        array('Contact and Customer Support', 'For product, reservation, payment, account, or policy questions, use the Contact page or email Shoestagram support.'),
    );
}

function shoestagram_registration_privacy_policy()
{
    return array(
        array('Information Collected During Registration', 'Shoestagram collects your name, email address, password, selected sizes, and shopping preferences when you register. Preferences may include brands, categories, styles, budget, and preferred shoe colors. A verification code is used to check control of your email address. The registration checkbox records that you have reviewed this notice and agreed to the Terms; it is not a separate request for marketing messages.'),
        array('Customer Account Information', 'If you provide them later, your account may also contain a phone number and address. After account creation, the database stores a password hash. During email verification, the pending registration session temporarily holds the submitted password. The system records login and account activity needed to operate and protect accounts.'),
        array('Order and Transaction Records', 'The system records cart items, reservation details, selected sizes, order status, payment method, and any online payment reference or proof image you submit. Staff and administrators use these records to process reservations and review payments. Do not upload unrelated sensitive information in a payment proof image.'),
        array('How Customer Information Is Used', 'Shoestagram uses account details to authenticate users, send verification and reset messages, manage reservations, respond to support requests, and show account history. Saved shopping preferences help rank product recommendations. Product views and transaction information may be used for store analytics.'),
        array('Email Verification and Password Reset', 'Shoestagram sends one-time verification and password reset messages to the email address involved in the request. Depending on the configured mail service, the recipient address and message content are sent through a Google Apps Script Gmail relay, Brevo, or the configured SMTP provider.'),
        array('Data Storage and Security', 'Account, catalog, and cart data are stored in MySQL. The current application also uses application JSON files for order, review, contact, and preference data, and application file storage for uploaded payment proof. Shoestagram uses access checks, password hashing, and HTTPS on the live site. No internet service can guarantee absolute security.'),
        array('Third-Party Services', 'Railway hosts the website and MySQL service and may process information needed to provide hosting. Google Apps Script and Gmail may process verification or password reset messages when the Gmail relay is configured. Brevo or another configured SMTP provider may process these messages when used instead. The registration page loads Google Fonts and Font Awesome from external providers, which may receive browser request information. These providers have their own service and privacy terms.'),
        array('Data Retention', 'Shoestagram has not set a fixed automatic deletion period in the current application. Account and transaction information may remain while needed for account operation, reservation support, records, or applicable legal obligations. Application JSON files and uploads depend on the hosting storage setup; ask the store team about a specific record or deletion request.'),
        array('Customer Privacy Rights', 'Under the Philippine Data Privacy Act of 2012, you may have rights to be informed, access and correct your data, object to certain processing, request blocking or erasure, seek data portability or damages, and file a complaint where applicable. Contact Shoestagram to make a request. The store will assess the request under applicable law and verify identity before releasing or changing account information.'),
        array('Contact Information', 'For privacy questions or requests, use the Shoestagram Contact page or email Shoestagram support. Include the email address connected to your account, but do not send your password or verification code.'),
    );
}
