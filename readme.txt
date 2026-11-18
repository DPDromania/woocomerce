v3.3.13
Selecting a DPD locker/office no longer overwrites the address fields with the pickup point address. The address stays the customer's own (so the billing address is correct, also with "Use same address for billing"); the pickup point is kept in the DPD pickup fields and used for the shipping price and the AWB. Editing the street address no longer clears the selected pickup point. Fixed the pickup point name and type being sent to the server empty, or from the previously selected point, when a locker/office was selected.

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
