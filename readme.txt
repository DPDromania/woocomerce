v3.3.12
Security: the DPD API request log no longer contains the account credentials and is disabled by default (enable it from Advanced settings > Debug log; entries go to WooCommerce > Status > Logs, source "dpdro"). The plain-text wp-content/dpdro/logs.json written by earlier versions is deleted automatically. The cache folder is now protected (index.php + .htaccess) and cache older than today is removed daily. Recommended after updating: change the DPD account password.

v3.3.11
DPDBox tax rate for service 2212 (DPD REGIONAL CEE) now applies to all destination countries, not only Hungary.

v3.3.10
Fixed blocks checkout writing the internal "-1" sentinel into the postcode field after selecting a county for non-RO countries (e.g. Hungary).

v3.3.9
Use default-zone (000000001) DPDBox rate for HU shipments via service 2212 when a pickup point is selected.

v3.2.26
Enabled update from github.
