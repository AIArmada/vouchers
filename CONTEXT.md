---
title: Vouchers Context
package: vouchers
status: current
surface: domain
family: growth-and-incentives
keywords:
  - voucher
  - coupon
  - wallet
  - redemption
  - stacking
---

# Vouchers Context

## Snapshot
- Composer: `aiarmada/vouchers`
- Role: Voucher issuance, cart-condition redemption, wallets, stacking, usage tracking.
- Triggers: voucher, coupon, wallet, redemption, stacking
- Search first: `src/Models, src/Actions, src/Services, config, docs`
- Related: `filament-vouchers`, `cart`, `checkout`, `affiliates`
- Paired: `filament-vouchers` (Filament admin adapter)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../filament-vouchers/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Owns models, actions, services, events, calculations, and persistence rules.
- If admin UI changes too, audit `filament-vouchers`.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Coupons, wallets, or stacking rules.
- Skip when: Auto discounts — see promotions.
- Owner/security: Owner-scoped (Voucher, Wallet; configurable).

## Key surfaces
- Models: `Voucher`, `VoucherUsage`, `VoucherWallet`
- Actions/Services: `Actions/AddVoucherToWallet`, `Actions/ApplyVoucherToCart`, `Actions/CreateVoucher`, `Actions/ExpireVoucher`, `Actions/RecordVoucherUsage`, `Actions/RemoveVoucherFromCart`, `Actions/UpdateVoucher`, `Actions/ValidateVoucherCode`
- Config `vouchers.php`: `vouchers`, `voucher_usage`, `voucher_wallets`, `database`, `table_prefix`, `tables`, `json_column_type`, `default_currency`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: `05-cart-integration.md`, `06-voucher-wallet.md`, `07-multi-tenancy.md`, `08-manual-redemption.md`, `09-usage-tracking.md`, `10-api-reference.md`
