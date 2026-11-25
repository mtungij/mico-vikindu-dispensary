# RCH Reception catalogue update

Apply this release after review and approval. Use the approved release directory, production environment and the verified Mico Vikindu facility ID. The command operates on one facility and defaults to a read-only audit.

| Reception service | Service code | Standard cash tariff |
| --- | --- | ---: |
| ANC Consultation | `RCH-ANC` | TZS 1,000 |
| PNC Consultation | `RCH-PNC` | TZS 1,000 |
| Child Growth Monitoring | `CGM` | TZS 500 |
| Family Planning | `FPCON` | TZS 0 |
| Ultrasound | `RCH-US` | TZS 15,000 |

The updater preserves current cash prices for ANC, PNC and Ultrasound when present. It reuses catalogue IDs, sets the cash tariff for Child Growth Monitoring and Family Planning, and deactivates conflicting current or future cash price versions for those two services. Family Planning records a zero-value consultation line and proceeds to Triage. Existing paid visits keep their original invoice and payment records.

## Deployment and verification

1. Prepare the approved release with its production assets built using `npm ci` and `npm run build`. Take a production backup through the normal release process. Run the audit from the prepared release against the production database before switching live traffic. Enter the verified numeric facility ID.

   ```bash
   read -r -p "Production facility ID: " RCH_FACILITY_ID
   php artisan rch:configure-catalogue --facility="$RCH_FACILITY_ID"
   ```

2. Review the service IDs, price versions, and proposed changes. If the audit flags a legacy service with another code or an incompatible type, confirm its mapping with the facility administrator and correct the existing record through the service catalogue before auditing again. If the audit reports an active or scheduled positive insurance or corporate Family Planning tariff, pause the release and have the contract owner review and resolve it. This updater leaves contractual tariff records intact.
3. In the approved release window, pause Reception writes through the normal release process. Run the audited update and immediately repeat the read-only check. Use the numeric ID of an authorized service administrator for the selected facility.

   ```bash
   read -r -p "Service administrator user ID: " RCH_ADMIN_ID
   php artisan rch:configure-catalogue --facility="$RCH_FACILITY_ID" --apply --actor="$RCH_ADMIN_ID" &&
       php artisan rch:configure-catalogue --facility="$RCH_FACILITY_ID"
   ```

4. Activate the release, rebuild Laravel's configuration and view caches, and restart long-running application workers through the normal release process if used.

   ```bash
   php artisan config:cache
   php artisan view:cache
   ```

5. Smoke-test the Reception selector and review screens. Confirm Child Growth Monitoring displays TZS 500 and routes cash visits through Billing. Confirm Family Planning displays TZS 0 and routes directly to Triage. After Triage, open each linked programme from the RCH worklist as an authorized user. Use a designated test patient if registration is part of the production smoke test.

This release uses the existing schema. The scoped catalogue command is the only service-data update required. Re-running it after successful application keeps the same service IDs and prices.
