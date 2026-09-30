# Privacy policy — SaleSnap (DRAFT TEMPLATE — replace before publishing)

**Effective date:** [DATE]  
**App operator / legal entity:** [LEGAL ENTITY]  
**Contact:** [PRIVACY EMAIL]  
**Business address:** [ADDRESS]  
**Public support/privacy URL:** [HTTPS URL]

This app is an embedded Shopify Admin tool that reads product and variant information to create scheduled promotions. It requests only `read_products` and `write_products` in the current release. It does not request or store buyer/customer information.

## Data processed
For each connected shop, the app stores the shop's Shopify domain, encrypted Shopify access/refresh tokens, promotion settings, product and variant IDs, and snapshots of original product descriptions, tags, statuses, and variant prices needed to restore campaigns. Campaign event logs contain action status and operational error details. Do not put personal/customer data in campaign names or product content.

## Purposes and retention
We process this information to authenticate the merchant, apply a scheduled product promotion, prevent overlapping campaigns, and restore the saved values. Completed/cancelled campaign snapshots and operational event logs are automatically purged after 90 days. Active/unresolved campaign records are retained until resolved or until app uninstall. On uninstall the app deletes the shop, token, campaign, snapshot, and related log records. We process Shopify privacy webhook requests and delete data as applicable.

## Security and subprocessors
Tokens are encrypted at rest using the application's Laravel encryption key; data is transmitted only over HTTPS in production. [LIST HOSTING, DATABASE, BACKUP, ERROR MONITORING, AND OTHER SUBPROCESSORS, THEIR PURPOSES, AND REGIONS.] Restrict production access and establish key/backup rotation and incident-response procedures.

## Merchant rights and contact
Contact [PRIVACY EMAIL] to ask questions or exercise applicable rights. Merchants can uninstall the app to revoke API access; uninstall cleanup removes connected shop records. This policy must be reviewed by your qualified privacy counsel and updated to reflect the real deployed system and applicable laws.

**Do not submit this template with bracketed placeholders.**
