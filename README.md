# Shoestagram

## Railway deployment

1. Deploy this repository as a PHP web service. `composer.json` tells Railpack to install the required `mysqli` extension.
2. Add a Railway MySQL service to the same project.
3. In the **web service's** Variables tab, create `MYSQL_URL` as a reference to the database service's `MYSQL_URL` (for a service named `MySQL`, the value is `${{MySQL.MYSQL_URL}}`). Railway does not automatically share database variables with the web service. You can instead reference all five separate variables: `MYSQLHOST`, `MYSQLPORT`, `MYSQLUSER`, `MYSQLPASSWORD`, and `MYSQLDATABASE`.
4. Deploy the staged Railway changes, then redeploy the web service from the updated Git commit. Open `/customer/index.php` and inspect the web service's Deploy Logs if it still fails.

Railway must point to an already imported database. Production requests only connect; they do not run the local schema setup or seed accounts. On XAMPP, with no MySQL environment variables, the app still uses `localhost`, `root`, and `shoestagram_db` and initializes its local tables.

Customer order and profile data is also written to `database/runtime/`. Before accepting production orders, attach a Railway volume to the web service at `/app/database/runtime`; container files do not survive redeployments. Uploaded payment proofs in `customer/uploads/payment-proofs/` need persistent storage too.

## Password reset and registration email on Railway

Railway Free, Trial, and Hobby plans disable outbound SMTP, so Gmail SMTP settings that work on localhost cannot send from those deployments. Shoestagram can send through a Gmail Apps Script HTTPS relay or Brevo's HTTPS email API. If both are configured, the Gmail relay takes priority. If neither is configured, Shoestagram uses its existing SMTP settings. The PHP `curl` extension is required and is declared in `composer.json`.

### Fast Gmail relay setup (no new email provider account)

This uses the existing `shoestagram00@gmail.com` Google account and Google's daily Apps Script email quota. It only changes outgoing mail; keep the PHP app and MySQL database on Railway.

1. Sign in to `shoestagram00@gmail.com` and open <https://script.google.com/home/projects/create>. Replace the default `Code.gs` content with the complete source from [`scripts/gmail-relay.gs`](scripts/gmail-relay.gs), then save.
2. On your computer, generate a private secret in PowerShell with `([guid]::NewGuid().ToString('N') + [guid]::NewGuid().ToString('N'))`. Copy the output privately. In Apps Script, open **Project Settings > Script Properties > Add script property**. Set property name `SHOESTAGRAM_MAIL_SECRET` and value to that secret; save it. Never put the secret in the script code or GitHub.
3. In the Apps Script editor, choose the `authorizeMail` function and click **Run**. Approve Google's mail permission for the account. The function reads your remaining quota and sends no message.
4. Select **Deploy > New deployment > Web app**. Set **Execute as: Me** and **Who has access: Anyone**. Deploy and copy the URL ending in `/exec` (not the development `/dev` URL). The relay requires the private secret on every POST.
5. In Railway, open the **shoestagram web service > Variables**. Set `GMAIL_RELAY_URL` to that `/exec` URL and `GMAIL_RELAY_SECRET` to the same secret from step 2. Apply or deploy the variable changes. The existing `BREVO_API_KEY` can stay; the relay takes priority when configured.
6. Redeploy the updated application code, then request a password reset for an existing active account. Check its inbox, spam folder, and the Apps Script **Executions** page. If the site shows an error, inspect the Railway deploy logs for `Shoestagram mail: Gmail relay`. Do not share a reset code or the secret.

The Gmail relay's response confirms whether Google accepted the send call, not whether the message reached an inbox. Google's current consumer-account Apps Script limit is 100 email recipients per day and may change. If the limit is reached, the app reports `quota_exceeded` in Railway logs. Keep the relay URL and secret private, rotate the secret in both places if exposed, and disable the deployment when no longer needed.

### Brevo setup

1. Create a Brevo account and register/verify a sender address under **Settings > Senders, Domains, IPs > Senders**. A Gmail address can be verified, but Brevo may replace its visible From address with a Brevo domain. An authenticated domain you own improves deliverability.
2. Create a **Brevo API key** (not an SMTP key). In the **Railway web service's** Variables tab, add `BREVO_API_KEY` and `BREVO_FROM_EMAIL` using the exact sender address verified in Brevo. Optionally set `BREVO_FROM_NAME=Shoestagram`. Store the API key only in Railway Variables; never commit it.
3. Redeploy the web service. Request a password reset for an existing active account and check its inbox/spam folder. If sending fails, check the Railway Deploy Logs for `Shoestagram mail: Brevo...` and Brevo's transactional email logs. A successful API response means Brevo accepted the email; final inbox delivery depends on the provider and sender setup.

Brevo's Free plan currently includes 300 email sends per day, including transactional mail. For local development, leave `BREVO_API_KEY` unset to keep using Gmail SMTP with a Google app password in the local `.env` file.

If a reset attempt still shows an email error, make one attempt and inspect the latest `Shoestagram mail:` line in the Railway web service's deployment logs. `BREVO_API_KEY is not configured` means the variable is missing from that service or its active deployment. Brevo HTTP 401 points to an invalid or inactive API key; HTTP 400 commonly points to a sender or request validation problem. Check the accompanying Brevo code and verify that `BREVO_FROM_EMAIL` exactly matches an active sender. If Brevo reports that transactional sending is disabled, request activation in Brevo. Never paste the API key or a reset code into logs or support messages.
