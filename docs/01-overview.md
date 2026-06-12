---
title: Overview
---

# Vouchers Package

## Purpose

The `aiarmada/vouchers` package owns voucher and coupon issuance, redemption rules, voucher wallets, and voucher usage tracking for the Commerce ecosystem.

## What this package owns

- Voucher definitions and redemption behavior
- Voucher usage tracking and redemption history
- Voucher wallets, assignments, and transaction tracking for saved or credit-style voucher flows
- Manual redemption rules and owner-aware voucher behavior

## What this package does not own

- Cart persistence or totals orchestration; it extends the cart condition system instead of owning the cart domain
- Promotion campaigns (`aiarmada/promotions`)
- Filament admin surfaces (`aiarmada/filament-vouchers`)
- Checkout and payment orchestration

## Related packages

- [`aiarmada/cart`](../../cart/docs/01-overview.md) — required cart condition and redemption integration surface
- [`aiarmada/commerce-support`](../../commerce-support/docs/01-overview.md) — owner scoping and shared helpers
- [`aiarmada/filament-vouchers`](../../filament-vouchers/docs/01-overview.md) — Filament admin resources, widgets, and config pages for vouchers
- [`aiarmada/affiliates`](../../affiliates/docs/01-overview.md) — optional affiliate metadata attachment during voucher application

## Main models services or surfaces

- **Models and records** — vouchers, voucher usage, voucher wallets, voucher assignments, and voucher transactions
- **Actions** — `CreateVoucher`, `UpdateVoucher`, `ExpireVoucher`, `ApplyVoucherToCart`, `RemoveVoucherFromCart`, `RecordVoucherUsage`, `ValidateVoucherCode`. Each is a `lorisleiva/laravel-actions` action callable via `::run()`.
- **Events** — `VoucherCreated`, `VoucherExpired`, `VoucherRefilled`, `VoucherUsageRecorded`, `VoucherApplied`, `VoucherRemoved`. Dispatched by the corresponding actions and services.
- **Stacking** — `StackingPolicy` (configurable strategy), `StackingRuleRegistry` (extensible rule lookup), and built-in rules (max vouchers, max discount percentage, type restriction, value threshold, mutual exclusion, category/campaign exclusion). Controlled by `StackingEngine`.
- **Core surface** — cart-condition powered voucher application, validation, redemption, and usage bookkeeping
- **Companion docs** — creation, cart integration, voucher wallet, multitenancy, manual redemption, usage tracking, and API reference pages

## Owner scoping and security notes

- Voucher ownership is configurable and should follow the same owner-boundary rules defined by `commerce-support`
- Consuming code should validate voucher eligibility and ownership server-side rather than trusting filtered admin or cart UI state
- Manual redemption should remain an explicit privileged flow when enabled

## Read next

- [Installation](02-installation.md)
- [Configuration](03-configuration.md)
- [Usage](04-usage.md)
- [Cart integration](05-cart-integration.md)
- [Voucher wallet](06-voucher-wallet.md)
- [Multi-tenancy](07-multi-tenancy.md)
- [Manual redemption](08-manual-redemption.md)
- [Usage tracking](09-usage-tracking.md)
- [API reference](10-api-reference.md)
- [Filament Vouchers overview](../../filament-vouchers/docs/01-overview.md)