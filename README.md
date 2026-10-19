# Shoestagram

## Railway deployment

1. Deploy this repository as a PHP web service. `composer.json` tells Railpack to install the required `mysqli` extension.
2. Add a Railway MySQL service to the same project.
3. In the **web service's** Variables tab, create `MYSQL_URL` as a reference to the database service's `MYSQL_URL` (for a service named `MySQL`, the value is `${{MySQL.MYSQL_URL}}`). Railway does not automatically share database variables with the web service. You can instead reference all five separate variables: `MYSQLHOST`, `MYSQLPORT`, `MYSQLUSER`, `MYSQLPASSWORD`, and `MYSQLDATABASE`.
4. Deploy the staged Railway changes, then redeploy the web service from the updated Git commit. Open `/customer/index.php` and inspect the web service's Deploy Logs if it still fails.

Railway must point to an already imported database. Production requests only connect; they do not run the local schema setup or seed accounts. On XAMPP, with no MySQL environment variables, the app still uses `localhost`, `root`, and `shoestagram_db` and initializes its local tables.

Customer order and profile data is also written to `database/runtime/`. Before accepting production orders, attach a Railway volume to the web service at `/app/database/runtime`; container files do not survive redeployments. Uploaded payment proofs in `customer/uploads/payment-proofs/` need persistent storage too.

## Password reset and registration email on Railway

Railway Free, Trial, and Hobby plans disable outbound SMTP, so Gmail SMTP settings that work on localhost cannot send from those deployments. Shoestagram uses Brevo's HTTPS email API when `BREVO_API_KEY` is set; otherwise it continues using the existing SMTP settings. The PHP `curl` extension is required and is declared in `composer.json`.

1. Create a Brevo account and register/verify a sender address under **Settings > Senders, Domains, IPs > Senders**. A Gmail address can be verified, but Brevo may replace its visible From address with a Brevo domain. An authenticated domain you own improves deliverability.
2. Create a **Brevo API key** (not an SMTP key). In the **Railway web service's** Variables tab, add `BREVO_API_KEY` and `BREVO_FROM_EMAIL` using the exact sender address verified in Brevo. Optionally set `BREVO_FROM_NAME=Shoestagram`. Store the API key only in Railway Variables; never commit it.
3. Redeploy the web service. Request a password reset for an existing active account and check its inbox/spam folder. If sending fails, check the Railway Deploy Logs for `Shoestagram mail: Brevo...` and Brevo's transactional email logs. A successful API response means Brevo accepted the email; final inbox delivery depends on the provider and sender setup.

Brevo's Free plan currently includes 300 email sends per day, including transactional mail. For local development, leave `BREVO_API_KEY` unset to keep using Gmail SMTP with a Google app password in the local `.env` file.
