---
title: Troubleshooting
---

# Troubleshooting

## Voucher code is not found

**Likely cause:** the voucher does not exist in the current owner scope, the code casing is wrong in seed data, or the voucher belongs to a different owner.

**Fix:**

- confirm the voucher exists in the `vouchers` table,
- confirm owner mode is configured the way you expect,
- keep using uppercase codes consistently when seeding or importing.

**Verify:** retrieve the voucher through the package API for the same owner context that the failing request uses.

## Voucher applies in admin but fails in cart

**Likely cause:** cart validation rules reject the voucher because of minimum cart value, usage limits, targeting, or stacking rules.

**Fix:** review `vouchers.validation.*`, `vouchers.cart.*`, and `vouchers.stacking.*` together. A voucher can exist and still be ineligible for the current cart.

**Verify:** validate the same voucher against the same cart payload and confirm the returned reason matches the expected business rule.

## Voucher disappears when owner mode is enabled

**Likely cause:** `VOUCHERS_OWNER_ENABLED=true` is on, but the current owner resolver does not resolve the owner you expect.

**Fix:** bind `OwnerResolverInterface` correctly and verify whether you want owner-only vouchers or owner-plus-global behavior.

**Verify:** compare voucher queries under the current owner context and explicit global context.

## Manual redemption is rejected

**Likely cause:** the voucher was not created with `allows_manual_redemption = true`, or manual redemption is intentionally guarded by `vouchers.redemption.manual_requires_flag`.

**Fix:** enable manual redemption on the voucher record or relax the flag only if your process truly needs that behavior.

**Verify:** redeem the voucher through the manual redemption API and confirm a `voucher_usage` record is created with the expected channel.

## Affiliate-linked voucher creation does nothing

**Likely cause:** the affiliates integration block is disabled or `aiarmada/affiliates` is not installed.

**Fix:** enable `vouchers.affiliates.enabled` and verify the affiliate package is present before expecting auto-created voucher behavior.

**Verify:** activate or create the affiliate record that should seed the voucher and confirm the voucher is created with the configured defaults.