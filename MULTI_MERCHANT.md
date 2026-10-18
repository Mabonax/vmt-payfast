# Merchant and tenant isolation

Bind `Vmt\PayFast\Contracts\MerchantResolver` to a host implementation. `resolve(string $context, ?string $tenantId): Merchant` must authorize the trusted server context, load the exact merchant/tenant account, and return its identity, tenant ID, mode, credentials and callback URLs. Never resolve tenant or credentials from arbitrary frontend fields. The default resolver accepts only `default` with a null tenant; other contexts are rejected.

Store dynamic merchant credentials using Laravel encrypted casts or a managed secret store. The package stores only the original merchant ID and passphrase in an encrypted transaction snapshot; merchant keys are never persisted there. Notifications resolve their globally unique internal UUID to that snapshot and fixed merchant/tenant identity, never the active browser tenant or newly rotated credentials. Changing the request reference invalidates its signature. Distinct merchant accounts must use distinct credentials; do not represent one shared PayFast account as independent merchant accounts.

Applications must scope all transaction reads by both `merchant_identity` and `tenant_id` and authorize the owning order. Package models do not install a global tenant scope: notifications must work without tenant sessions. No unauthenticated transaction detail route is supplied. Both `FulfilmentService` and `PaymentLifecycleService` require the exact stored context. A single-clinic deployment can use the default merchant with no tenant.

Rotate current credentials for new checkouts while preserving encrypted snapshots and application encryption keys for outstanding payments. If a provider invalidates the old passphrase, original in-flight notifications may fail verification; arrange a provider-supported transition and reconciliation procedure. Never silently retry signatures against another merchant or accept any available passphrase. Apply an explicit retention policy to snapshots and audit history after reconciliation and legal retention review.

No central VMT wallet, pooling, settlement transfer, or marketplace feature is implemented.
