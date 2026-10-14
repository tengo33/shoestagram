# Shoestagram

## Railway deployment

1. Deploy this repository as a PHP web service. `composer.json` tells Railpack to install the required `mysqli` extension.
2. Add a Railway MySQL service to the same project.
3. In the **web service's** Variables tab, create `MYSQL_URL` as a reference to the database service's `MYSQL_URL` (for a service named `MySQL`, the value is `${{MySQL.MYSQL_URL}}`). Railway does not automatically share database variables with the web service. You can instead reference all five separate variables: `MYSQLHOST`, `MYSQLPORT`, `MYSQLUSER`, `MYSQLPASSWORD`, and `MYSQLDATABASE`.
4. Deploy the staged Railway changes, then redeploy the web service from the updated Git commit. Open `/customer/index.php` and inspect the web service's Deploy Logs if it still fails.

Railway must point to an already imported database. Production requests only connect; they do not run the local schema setup or seed accounts. On XAMPP, with no MySQL environment variables, the app still uses `localhost`, `root`, and `shoestagram_db` and initializes its local tables.

Customer order and profile data is also written to `database/runtime/`. Before accepting production orders, attach a Railway volume to the web service at `/app/database/runtime`; container files do not survive redeployments. Uploaded payment proofs in `customer/uploads/payment-proofs/` need persistent storage too.
