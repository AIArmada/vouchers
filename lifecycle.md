# Vouchers — Lifecycle Field Audit

## Executive Summary (3-5 sentences)

The vouchers package has a well-structured state machine (Active/Paused/Expired/Depleted) backed by `spatie/model-states`, but the migration layer is missing three critical transition timestamps (`paused_at`, `depleted_at`, `last_activated_at`) and has an incongruent default value for the `status` column. The `voucher_wallets` table violates the "prefer `*_at` over `is_*` booleans" principle with redundant `is_claimed`/`is_redeemed` boolean flags alongside their timestamp counterparts. The `voucher_assignments` table has no lifecycle model at all — no status column, no revoke timestamp, and no associated Eloquent model. These gaps prevent accurate time-based reporting, auditable state-transition histories, and clean query patterns.

---

## Full Inventory by Table

### `vouchers`

| Column | Type | Nullable | Default | Purpose | Lifecycle Role |
|---|---|---|---|---|---|
| `id` | uuid | no | — | PK | — |
| `code` | string | no | — | unique voucher code | identity |
| `name` | string | no | — | display name | — |
| `description` | text | yes | — | human-readable description | — |
| `type` | string | no | — | percentage, fixed, free_shipping, buy_x_get_y, tiered, bundle, cashback | — |
| `value` | bigInteger | no | — | cents (fixed) or basis points (percentage) | — |
| `value_config` | json | yes | — | compound type config (BOGO, Tiered, Bundle, Cashback) | — |
| `credit_destination` | string(50) | yes | — | wallet, next_order, points | — |
| `credit_delay_hours` | integer | no | 0 | hours before cashback credits | — |
| `currency` | string(3) | no | MYR | ISO currency code | — |
| `min_cart_value` | bigInteger | yes | — | minimum cart value in cents | — |
| `max_discount` | bigInteger | yes | — | max discount cap in cents | — |
| `usage_limit` | integer | yes | — | global redemption cap | — |
| `usage_limit_per_user` | integer | yes | — | per-user redemption cap | — |
| `applied_count` | unsignedBigInteger | no | 0 | cart-application counter | — |
| `allows_manual_redemption` | boolean | no | false | behavioral config flag (keep as boolean) | — |
| `starts_at` | timestampTz | yes | — | planned validity start | **lifecycle boundary** |
| `expires_at` | timestampTz | yes | — | planned validity end | **lifecycle boundary** |
| `status` | string | no | `'active'` | FQCN or morph value for VoucherStatus state | **lifecycle state** |
| `created_at` | timestampTz | no | auto | record creation | **lifecycle** |
| `updated_at` | timestampTz | no | auto | last write | — |
| *missing* | — | — | — | — | — |
| `paused_at` | ❌ | — | — | when voucher was paused | **MISSING** |
| `depleted_at` | ❌ | — | — | when usage limit was reached | **MISSING** |
| `last_activated_at` | ❌ | — | — | when voucher was (re)activated | **MISSING** |

**State machine transitions** (from `VoucherStatus::config()`):
```
Active ──→ Paused    (no timestamp recorded)
Active ──→ Expired   (no distinct transition timestamp; exploits `expires_at` for time-based reads)
Active ──→ Depleted  (no timestamp recorded)
Paused ──→ Active    (no timestamp recorded)
Paused ──→ Expired   (no distinct transition timestamp)
Paused ──→ Depleted  (no timestamp recorded)
```

**Critical inconsistency in `status` default:**
The migration defaults `status` to the plain string `'active'`. However, `CreateVoucher` sets `status` to `Active::class` (the FQCN `AIArmada\Vouchers\States\Active`). The `spatie/model-states` package stores FQCNs by default when `storeAsMorphMap()` is not called. The `VoucherStatus::resolveStateClassFor()` has a string-matching fallback that makes this survive at runtime, but the storage format is inconsistent across code paths. Producing mixed-format rows silently.

---

### `voucher_usage`

| Column | Type | Nullable | Default | Purpose | Lifecycle Role |
|---|---|---|---|---|---|
| `id` | uuid | no | — | PK | — |
| `voucher_id` | foreignUuid | no | — | FK to vouchers | — |
| `discount_amount` | bigInteger | no | — | discount in cents | — |
| `currency` | string(3) | no | — | ISO code | — |
| `channel` | string | yes | — | automatic, manual, api | — |
| `redeemed_by_type` | string | yes | — | polymorphic type | — |
| `redeemed_by_id` | uuid | yes | — | polymorphic id | — |
| `notes` | text | yes | — | free-text notes | — |
| `target_definition` | json | yes | — | snapshot of voucher target at redemption time | — |
| `metadata` | json | yes | — | extensible payload | — |
| `used_at` | timestampTz | no | — | when redemption occurred | **lifecycle event** |

**Assessment: Clean.** This is an immutable event log. `used_at` is exactly correct. No `is_*` booleans, no `status` column. The model sets `$timestamps = false` and relies solely on `used_at`.

---

### `voucher_wallets`

| Column | Type | Nullable | Default | Purpose | Lifecycle Role |
|---|---|---|---|---|---|
| `id` | uuid | no | — | PK | — |
| `voucher_id` | foreignUuid | no | — | FK to vouchers | — |
| `owner_type` | string | yes | — | polymorphic owner | tenancy |
| `owner_id` | uuid | yes | — | polymorphic owner | tenancy |
| `holder_type` | string | yes | — | polymorphic holder | — |
| `holder_id` | uuid | yes | — | polymorphic holder | — |
| `is_claimed` | boolean | no | false | claimed flag | **lifecycle flag (redundant)** |
| `claimed_at` | timestampTz | yes | — | when claimed | **lifecycle timestamp** |
| `is_redeemed` | boolean | no | false | redeemed flag | **lifecycle flag (redundant)** |
| `redeemed_at` | timestampTz | yes | — | when redeemed | **lifecycle timestamp** |
| `metadata` | json | yes | — | extensible payload | — |
| `created_at` | timestampTz | no | auto | record creation | — |
| `updated_at` | timestampTz | no | auto | last write | — |

**Assessment: Redundant booleans.** Both `is_claimed` and `is_redeemed` are always set together with their `*_at` counterparts (see `claim()` and `markAsRedeemed()` in `VoucherWallet.php`). The booleans add no information beyond what `claimed_at IS NOT NULL` / `redeemed_at IS NOT NULL` already provide. They also create maintenance burden: any code path that sets the timestamp must also toggle the boolean, and vice versa — a desync risk.

The unique constraint `UNIQUE(voucher_id, holder_type, holder_id, is_redeemed)` currently relies on `is_redeemed` as part of the key. This must be replaced with a partial unique index.

---

### `voucher_assignments`

| Column | Type | Nullable | Default | Purpose | Lifecycle Role |
|---|---|---|---|---|---|
| `id` | uuid | no | — | PK | — |
| `voucher_id` | foreignUuid | no | — | FK to vouchers | — |
| `assignee_type` | string | no | — | polymorphic assignee | — |
| `assignee_id` | uuid | no | — | polymorphic assignee | — |
| `assigned_at` | timestampTz | no | now() | when assigned | **lifecycle** |
| `expires_at` | timestampTz | yes | — | optional expiry | **lifecycle boundary** |
| `metadata` | json | yes | — | extensible payload | — |
| `created_at` | timestampTz | no | auto | record creation | — |
| `updated_at` | timestampTz | no | auto | last write | — |
| *missing* | — | — | — | — | — |
| `status` | ❌ | — | — | active, expired, revoked | **MISSING** |
| `revoked_at` | ❌ | — | — | when assignment was revoked | **MISSING** |

**Assessment: No lifecycle model.** This table has no Eloquent model, no `status` column, and no mechanism to track revocation. The config key exists in `vouchers.database.tables.voucher_assignments` (config line 11), so table-name resolution via `getTable()` would work — the gap is purely in application-layer lifecycle management.

---

### `voucher_transactions`

| Column | Type | Nullable | Default | Purpose | Lifecycle Role |
|---|---|---|---|---|---|
| `id` | uuid | no | — | PK | — |
| `voucher_id` | foreignUuid | no | — | FK to vouchers | — |
| `voucher_wallet_id` | foreignUuid | yes | — | FK to voucher_wallets | — |
| `walletable_type` | string | no | — | polymorphic owner | — |
| `walletable_id` | uuid | no | — | polymorphic owner | — |
| `amount` | integer | no | — | cents, positive or negative | — |
| `balance` | integer | no | — | running balance in cents | — |
| `type` | string | no | — | credit, debit | — |
| `currency` | string(3) | no | — | ISO code | — |
| `description` | string | yes | — | free-text | — |
| `metadata` | json | yes | — | extensible payload | — |
| `created_at` | timestampTz | no | auto | when transaction occurred | **lifecycle** |
| `updated_at` | timestampTz | no | auto | last write | — |

**Assessment: Clean.** Immutable ledger. `created_at` serves as the transaction timestamp. No lifecycle state needed.

---

## Problems Summary

### P1 — Missing state-transition timestamps on `vouchers`

The state machine allows 6 transitions. Only `created_at` and `updated_at` (framework-level) track time. No dedicated columns record when a voucher was paused, depleted, or (re)activated. The `expires_at` column records the *planned* expiry boundary, not when the status was *transitioned* to Expired. This means:

- Cannot answer "When was this voucher paused?" without parsing activity logs
- Cannot answer "When did this voucher run out?" without cross-referencing `voucher_usage.used_at`
- Cannot distinguish "planned expiry changed" from "voucher manually expired"
- The `ExpireVoucher` action transitions `status → Expired` but does not set any timestamp

### P2 — Redundant `is_*` booleans on `voucher_wallets`

`is_claimed` and `is_redeemed` are always written together with `claimed_at` and `redeemed_at` by the model methods `claim()` and `markAsRedeemed()`. The booleans are derived information. Nullable timestamps can serve as both the state indicator and the temporal record. The unique constraint `UNIQUE(voucher_id, holder_type, holder_id, is_redeemed)` couples to the boolean, so must be replaced with a partial unique index.

### P3 — `voucher_assignments` has no lifecycle at all

No model, no `status`, no `revoked_at`. The table exists but is effectively unmanaged. The config entry exists (`vouchers.database.tables.voucher_assignments`), but there is no Eloquent model, no lifecycle state column, and no revocation tracking.

### P4 — Inconsistent `status` default between migration and code

Migration default: `'active'` (plain string). Code default: `Active::class` (FQCN). The spatie/model-states `resolveStateClassFor()` has a fallback that makes this not crash, but produces mixed-format rows.

---

## Recommended Structure

### `vouchers` target schema (additions only)

```sql
ALTER TABLE vouchers
  ADD COLUMN paused_at           TIMESTAMPTZ,
  ADD COLUMN depleted_at         TIMESTAMPTZ,
  ADD COLUMN last_activated_at   TIMESTAMPTZ,
  ALTER COLUMN status DROP DEFAULT,
  ALTER COLUMN status SET DEFAULT 'AIArmada\Vouchers\States\Active';
```

**Column semantics:**

| Column | Set when | Cleared when |
|---|---|---|
| `paused_at` | status transitions **to** Paused | status transitions **away from** Paused (→ Active) |
| `depleted_at` | status transitions **to** Depleted | never (terminal) |
| `last_activated_at` | voucher created as Active; status transitions **to** Active from Paused | never (accumulating) |

**State machine with timestamps:**

```
Active ──→ Paused    → SET paused_at = NOW()
Active ──→ Expired   → (handled by expires_at; manual expire sets expires_at = NOW() if null)
Active ──→ Depleted  → SET depleted_at = NOW()
Paused ──→ Active    → SET last_activated_at = NOW(), SET paused_at = NULL
Paused ──→ Expired   → SET expires_at = NOW() if null
Paused ──→ Depleted  → SET depleted_at = NOW()
```

**Note:** The `status` column default should be changed from `'active'` to the FQCN for consistency with the state system. Any existing rows with the string `'active'` must be migrated.

### `voucher_wallets` target schema

```sql
ALTER TABLE voucher_wallets
  DROP COLUMN is_claimed,
  DROP COLUMN is_redeemed;

-- Replace the unique constraint with a partial unique index (PostgreSQL)
-- Ensures one non-redeemed entry per voucher+holder
DROP INDEX IF EXISTS voucher_wallets_voucher_id_holder_type_holder_id_is_redeemed_unique;
CREATE UNIQUE INDEX voucher_wallets_one_active_per_holder
  ON voucher_wallets (voucher_id, holder_type, holder_id)
  WHERE redeemed_at IS NULL;
```

**Query migration guide:**

| Old query | New query |
|---|---|
| `->where('is_claimed', true)` | `->whereNotNull('claimed_at')` |
| `->where('is_redeemed', false)` | `->whereNull('redeemed_at')` |
| `->where('is_claimed', true)->where('is_redeemed', false)` | `->whereNotNull('claimed_at')->whereNull('redeemed_at')` |
| Model `$attributes` default `'is_claimed' => false` | Remove (no equivalent needed; `claimed_at` starts null) |
| Model `$attributes` default `'is_redeemed' => false` | Remove |

**Model method changes:**
- `isAvailable()`: `$this->is_claimed && ! $this->is_redeemed` → `$this->claimed_at !== null && $this->redeemed_at === null`
- `canBeUsed()`: uses `isAvailable()` which chains through — indirect fix
- `claim()`: stop setting `is_claimed`; leave only `claimed_at`
- `markAsRedeemed()`: stop setting `is_redeemed`; leave only `redeemed_at`
- Remove `'is_claimed'` and `'is_redeemed'` from `$fillable`, `casts()`, `getAuditInclude()`, `getActivitylogOptions()`
- Remove `$attributes` defaults for `is_claimed` and `is_redeemed`

**Accessor considerations:**
`Voucher` model has `getWalletClaimedCountAttribute()`, `getWalletRedeemedCountAttribute()`, and `getWalletAvailableCountAttribute()` that query `where('is_claimed', true)` and `where('is_redeemed', true/false)`. These must be updated to use `whereNotNull('claimed_at')` / `whereNull('redeemed_at')`.

**Indexes that reference `is_claimed`/`is_redeemed`:**
- `index('is_claimed')` → `index('claimed_at')` (already exists)
- `index('is_redeemed')` → `index('redeemed_at')` (already exists)
- `index(['is_redeemed', 'is_claimed'])` → remove (replaced by `whereNotNull('claimed_at')->whereNull('redeemed_at')` pattern)
- `index(['voucher_id', 'is_claimed', 'is_redeemed'], 'voucher_wallets_available_idx')` → replace with `index(['voucher_id', 'claimed_at', 'redeemed_at'], 'voucher_wallets_available_idx')` or a partial index

### `voucher_assignments` target schema

```sql
ALTER TABLE voucher_assignments
  ADD COLUMN status       VARCHAR(50) NOT NULL DEFAULT 'active',
  ADD COLUMN revoked_at   TIMESTAMPTZ;
```

Create `VoucherAssignment` Eloquent model with `HasUuids` (config table key already exists at `vouchers.database.tables.voucher_assignments`).

---

## Refactoring Plan — Parallel-Agent Checklist

The work splits naturally into three independent workstreams.

### Agent A: `vouchers` transition timestamps
- [x] **Migration**: Add `paused_at`, `depleted_at`, `last_activated_at` timestampTz nullable columns
- [x] **Migration**: Update `status` default from `'active'` to FQCN
- [x] **Data fix**: Migrate existing `status = 'active'` rows to FQCN
- [x] **Model**: Update `booted()` or create observer to set `paused_at`/`last_activated_at` on `saving` when status changes
- [x] **Model**: Update `booted()` or create observer to set `depleted_at` on transition to Depleted
- [x] **Action**: Update `ExpireVoucher` to set `expires_at = now()` if null when manually expiring
- [x] **Action**: Update `RecordVoucherUsage` — when setting `status = Depleted::class`, also set `depleted_at = now()`
- [x] **Model**: Add `paused_at`, `depleted_at`, `last_activated_at` to `casts()` and `$fillable`
- [x] **Tests**: Add state-transition timestamp assertions

### Agent B: `voucher_wallets` boolean removal
- [x] **Migration**: Drop `is_claimed` and `is_redeemed` columns
- [x] **Migration**: Replace `UNIQUE(voucher_id, holder_type, holder_id, is_redeemed)` with partial unique index `WHERE redeemed_at IS NULL`
- [x] **Migration**: Drop indexes on `is_claimed`, `is_redeemed`, and composite `(is_redeemed, is_claimed)`
- [x] **Model**: Update `VoucherWallet` — remove boolean from `$fillable`, `casts()`, `$attributes`, `getAuditInclude()`, `getActivitylogOptions()`
- [x] **Model**: Update `claim()` to only set `claimed_at`
- [x] **Model**: Update `markAsRedeemed()` to only set `redeemed_at`
- [x] **Model**: Update `isAvailable()` to use `$this->claimed_at !== null && $this->redeemed_at === null`
- [x] **Model**: Update `Voucher` accessors (`getWalletClaimedCountAttribute`, `getWalletRedeemedCountAttribute`, `getWalletAvailableCountAttribute`) to use timestamp-based queries
- [x] **Model**: Remove `is_claimed` and `is_redeemed` from VoucherWallet `$fillable`
- [x] **Tests**: Update all references to `is_claimed`/`is_redeemed` in tests

### Agent C: `voucher_assignments` lifecycle
- [x] **Migration**: Add `status` VARCHAR(50) NOT NULL DEFAULT 'active'
- [x] **Migration**: Add `revoked_at` timestampTz nullable
- [x] **Config**: Add `voucher_assignments` to config `tables` array
- [x] **Model**: Create `VoucherAssignment` Eloquent model with `HasUuids`
- [x] **Model**: Add `BelongsTo<Voucher>` relationship
- [x] **Model**: Add `MorphTo` relationship for `assignee`
- [x] **Tests**: Cover creation, expiry, and revocation

### Cross-cutting follow-up
- [x] **Pint** on changed files within `packages/vouchers/src`
- [x] **PHPStan** on `packages/vouchers/src` at level 6
- [x] **Tests** run: `./vendor/bin/pest --parallel packages/vouchers/tests`
- [x] **Search for stale `is_claimed`/`is_redeemed` references**: `rg -n "is_claimed|is_redeemed" packages/vouchers/src packages/filament-vouchers`
- [x] **Search for hardcoded `'active'` status string**: `rg -n "'active'" packages/vouchers/database`
- [x] **Filament vouchers**: If `packages/filament-vouchers` references wallet booleans or assignment columns, coordinate updates

---

## Migration Strategy

### Phase 1: Add columns (non-breaking)
Deploy new nullable columns first:
1. `vouchers`: add `paused_at`, `depleted_at`, `last_activated_at` timestampTz
2. `voucher_assignments`: add `status`, `revoked_at`
3. Update code to start writing to new columns (backward-compatible — nulls are fine)

### Phase 2: Backfill existing data
1. Set `depleted_at = updated_at` for vouchers where `status` matches Depleted
2. Set `paused_at = updated_at` for vouchers where `status` matches Paused
3. Backfill `last_activated_at = created_at` for vouchers where `status = Active`
4. Migrate `status` column from `'active'` string to FQCN
5. Backfill `voucher_assignments.status = 'active'` for all existing rows

### Phase 3: Drop redundant booleans (breaking, requires code changes)
1. Coordinate deployment with all code changes (Agent B checklist)
2. Run migration to drop `is_claimed` and `is_redeemed` from `voucher_wallets`
3. Create partial unique index

---

## Verification Commands

```bash
# 1. Find all references to is_claimed / is_redeemed outside the migration itself
rg -n "is_claimed|is_redeemed" packages/vouchers/src packages/filament-vouchers/src

# 2. Find all status-default 'active' strings in migrations
rg -n "default\('active'\)" packages/vouchers/database

# 3. Find all hardcoded string status checks (not using the state system)
rg -n "status.*=.*'active'|status.*=.*'paused'|status.*=.*'expired'|status.*=.*'depleted'" packages/vouchers/src

# 4. Confirm voucher_assignments is in the config tables array
rg -n "voucher_assignments" packages/vouchers/config

# 5. PHPStan analysis
./vendor/bin/phpstan analyse packages/vouchers/src --level=6

# 6. Run voucher package tests
./vendor/bin/pest --parallel packages/vouchers/tests

# 7. Check that no migration adds constrained() or cascadeOnDelete()
rg -n "constrained\(|cascadeOnDelete\(" packages/vouchers/database

# 8. Verify all HasOwner models have owner scoping
rg -n "HasOwner|ownerScopeConfig|owner_scoping" packages/vouchers/src/Models

# 9. Check filament-vouchers for stale column references (if applicable)
rg -n "is_claimed|is_redeemed|voucher_assignments" packages/filament-vouchers --include="*.php"

# 10. Verify pivoted index migration correctness on PostgreSQL
# (partial unique indexes are PostgreSQL-only; confirm json_column_type setting)
php artisan config:show vouchers.database.json_column_type
```
