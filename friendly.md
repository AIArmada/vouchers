## Second pass — 2026-06-09

### Confirmed [done]

| Item | Status | Evidence |
|------|--------|----------|
| Phase 1 — Actions tree | ✅ Done | `ApplyVoucherToCart`, `RemoveVoucherFromCart`, `ValidateVoucherCode`, `RecordVoucherUsage`, `ExpireVoucher`, `CreateVoucher`, `AddVoucherToWallet` — 7 Actions exist (more than planned) |
| Phase 2 — Stacking trio clarified | ✅ Done | `StackingPolicy` is canonical entry point (docblock documented), `StackingEngine` delegates rule evaluation, `StackingDecision` is immutable value object |
| Phase 3 — StackingRuleRegistry | ✅ Done | `Stacking/StackingRuleRegistry` exists with `register()`, `get()`, `has()`, `all()` methods |
| Phase 4 — Lifecycle events | ✅ Done | `VoucherCreated`, `VoucherExpired`, `VoucherUsageRecorded` exist + **`VoucherRefilled`** (not listed in original [done] tracker but present in source) |
| Phase 4 — Events dispatched from Actions | ✅ Done | `CreateVoucher`, `ExpireVoucher`, `RecordVoucherUsage` dispatch events |

### Still open / issues

| Item | Status | Detail |
|------|--------|----------|
| VoucherService still has inline mutations | ⚠️ Drift | `VoucherService::update()` calls `$voucher->update()` directly — no UpdateVoucher Action. `VoucherService::addToWallet()` calls `VoucherWallet::create()` inline. `VoucherService::redeem()` does inline logic before calling `RecordVoucherUsage`. |
| ValidateVoucherCode Action not used from ApplyVoucherToCart | ⚠️ Inconsistency | `ApplyVoucherToCart::handle()` calls `Voucher::validate()` (Facade→VoucherService) instead of `ValidateVoucherCode::run()`. The Action exists but is bypassed. |
| Finding #5 — Listener duplicate validation | 🔴 Still open | `IncrementVoucherAppliedCount` and `ValidateVoucherOnCheckout` likely still repeat validation logic. |
| Finding #6 — Rule catalog overlap | 🔴 Still open | `VoucherRulesFactory` vs cart's rule factory — no shared catalog in `commerce-support`. |
| Finding #7 — Events were only 2 | ✅ Resolved | Now 7 events exist (Applied, Removed, Created, Expired, UsageRecorded, Refilled + concern trait). |

### New findings

| Finding | Detail |
|---------|--------|
| `VoucherRefilled` event missing from [done] tracker | The event file exists at `Events/VoucherRefilled.php` but was not listed in Phase 4 [done] items. The [done] list says "Added events: VoucherCreated, VoucherExpired, VoucherUsageRecorded" — missing Refilled. |
| `VoucherService::create()` delegates to `CreateVoucher::run()` but then does owner auto-assignment logic *before* calling the Action | The owner assignment should happen inside the Action, not in the Service. This splits the create orchestration across two classes. |
| `ApplyVoucherToCart` is 158 lines | The Action handles stacking, auto-replacement, rule factory management, and cart conditions — could be split into smaller steps. |

### Updated recommendation

1. **Update the Phase 4 [done] list** to include `VoucherRefilled`.
2. **Make `ValidateVoucherCode` the single entry point** — `ApplyVoucherToCart` should use the Action, not the Facade→Service path.
3. **Add `UpdateVoucher` Action** and move `VoucherService::update()` orchestration into it.
4. **Move owner auto-assignment** from `VoucherService::create()` into `CreateVoucher`.
5. **Audit `ApplyVoucherToCart`** — consider splitting stacking coordination, rule factory wiring, and cart condition registration into sub-Actions.

---

# Vouchers friendliness review

This note reviews `packages/vouchers` against two repo-level expectations:

- when a capability may grow variants, prefer stable seams such as contracts, metadata, hooks, domain events, resolvers, and support classes
- when orchestration repeats, extract reusable Actions, Services, or Use Cases so the package stays friendly to multiple entrypoints

## What I reviewed

- `src/Services` (3 classes)
- `src/Stacking` (engine + 8 rules)
- `src/Compound` (engine + 5 conditions + 5 matchers)
- `src/Listeners` (2 classes)
- `src/Models` (4 classes)
- `src/Events` (2 classes)
- `src/Conditions`
- `src/Support`
- downstream consumers in `cart`, `checkout`, `affiliates`, `signals`

## What is already friendly

### Real service contract

- `Services/VoucherService.php` (impl `Contracts/VoucherServiceInterface.php`)

### Real product matcher contract

- `Contracts/ProductMatcherInterface.php`

This is the right shape. New product-matching strategies (attribute, category, SKU, price) can implement the contract.

### Stacking engine is rule-driven

- `Stacking/StackingEngine.php`
- `Stacking/StackingPolicy.php`
- `Stacking/StackingDecision.php`
- `Stacking/Rules/{CampaignExclusion, CategoryExclusion, MaxDiscount, MaxDiscountPercentage, MaxVouchers, MutualExclusion, TypeRestriction, ValueThreshold}Rule.php` (all impl `Stacking/Contracts/StackingRuleInterface.php`)

This is the right pattern. Stacking rules are first-class classes behind a contract.

### Compound voucher engine is strategy-driven

- `Compound/CompoundVoucherCondition.php`
- `Compound/BundleVoucherCondition.php`
- `Compound/BOGOVoucherCondition.php`
- `Compound/CashbackVoucherCondition.php`
- `Compound/TieredVoucherCondition.php`
- `Compound/Matchers/{AttributeMatcher, CategoryMatcher, CompositeMatcher, PriceMatcher, SkuMatcher}.php`

Each voucher family is its own condition class, and each product matcher is its own class. This is the most extensible part of the package.

### Cart integration is isolated

- `Cart/VoucherConditionProvider.php`
- `Support/CartManagerWithVouchers.php`
- `Support/CartWithVouchers.php`

Cart composes vouchers through a provider, not by reaching into the voucher model.

### Affiliate integration registrar exists

- `Support/AffiliateIntegrationRegistrar.php`

The package plugs into affiliates through a registrar, not by editing foundation.

## Findings

### 1. There is no `Actions/` directory

**Files**

- `src/Services/VoucherService.php`
- `src/Services/VoucherValidator.php`
- `src/Services/VoucherDiscountCalculator.php`

**Why this hurts friendliness**

`VoucherService` is the public surface, but mutations (apply, remove, expire, refill) likely live inline. The validator and discount calculator are siblings, but the orchestration between them is in `VoucherService`.

**Recommendation**

Introduce a small `src/Actions` tree:

- `Actions/ApplyVoucherToCart`
- `Actions/RemoveVoucherFromCart`
- `Actions/ValidateVoucherCode`
- `Actions/RecordVoucherUsage`
- `Actions/ExpireVoucher`

`VoucherService` becomes a thin facade that delegates. The validator and discount calculator stay as read-side services.

### 2. Service count (3) and Action count (0) is inconsistent

**Why this hurts friendliness**

Like inventory and shipping, the package has services but no Actions. The monorepo rule is "Actions only, no logic in services" and this package is the exception.

**Recommendation**

Same as inventory/shipping: move all mutations to Actions.

### 3. Stacking rules are real variants but registration is not a seam

**Files**

- `src/Stacking/Rules/*`

**Why this hurts friendliness**

The 8 rule classes look like a great seam, but adding a new rule may require editing the engine or the rule loader rather than just registering a new class.

**Recommendation**

Add a `StackingRuleRegistry` (or tagged binding) so new rules can be registered from outside the package. Built-ins register in the service provider.

### 4. Stacking engine is a real engine but the policy class is unclear

**Files**

- `src/Stacking/StackingEngine.php`
- `src/Stacking/StackingPolicy.php`
- `src/Stacking/StackingDecision.php`

**Why this hurts friendliness**

`StackingPolicy` and `StackingDecision` are sibling classes. It is unclear what role each plays and which one is the entry point.

**Recommendation**

Audit the stacking trio. Either `StackingEngine` is the orchestrator and `StackingPolicy` is the input, or `StackingPolicy` is the orchestrator and `StackingEngine` is the rule runner. Pick one canonical entry point and document it.

### 5. Listeners repeat validation logic

**Files**

- `src/Listeners/IncrementVoucherAppliedCount.php`
- `src/Listeners/ValidateVoucherOnCheckout.php`

**Why this hurts friendliness**

Both listeners may call into the validator or service. If the same validation is run at apply time and checkout time, the rules may drift.

**Recommendation**

Extract a `ValidateVoucherAction` and have both listeners call it. The action owns the validation policy.

### 6. Two rule catalogs in `Support/`

**Files**

- `src/Support/VoucherRulesFactory.php`

**Why this hurts friendliness**

The factory may overlap with `RulePresets`-style catalogs in other packages (cart has a similar pattern). If the same rule definitions appear in multiple places, the rules will drift.

**Recommendation**

Audit `VoucherRulesFactory` against the cart's `BuiltInRulesFactory` and `RulePresets`. If they overlap, consider a shared rule catalog in `commerce-support`.

### 7. Events are only 2

**Files**

- `src/Events/VoucherApplied.php`
- `src/Events/VoucherRemoved.php`

**Why this hurts friendliness**

Downstream packages (signals, affiliates, analytics) need to react to voucher lifecycle events. The package emits only apply/remove, not create, expire, or refill.

**Recommendation**

Add events:

- `Events/VoucherCreated`
- `Events/VoucherExpired`
- `Events/VoucherRefilled`
- `Events/VoucherUsageRecorded`

### 8. Voucher state machine is a real seam

**Files**

- `src/States/{Active, Depleted, Expired, Paused, VoucherStatus}.php`

This is the right shape (spatie/laravel-model-states). Keep using it as the package grows.

## Concrete refactor plan

### Phase 1 — introduce the Actions tree

**Steps**

1. Add `src/Actions/ApplyVoucherToCart`, `RemoveVoucherFromCart`, `ValidateVoucherCode`, `RecordVoucherUsage`, `ExpireVoucher`.
2. Move orchestration out of services.
3. Update listeners and cart integration.

### Phase 2 — clarify the stacking trio

**Steps**

1. Audit `StackingEngine` vs `StackingPolicy` vs `StackingDecision`.
2. Pick the canonical entry point.
3. Document the public API.

### Phase 3 — add stacking rule registry

**Steps**

1. Add `Stacking/StackingRuleRegistry`.
2. Register built-ins from the service provider.
3. Document the registration pattern.

### Phase 4 — add voucher lifecycle events

**Steps**

1. Add the missing events.
2. Dispatch from the new Actions.





## Refactor tracking

This checklist tracks progress on the refactor plan above. Each item lists a concrete phase/step.
Agents: claim an item by updating its status. Use `@agent-name` to claim ownership.

Status legend:
- `[pending]` — not started
- `[in-progress]` — being worked on
- `[done]` — completed and verified
- `[blocked]` — blocked by another item

### Phase 1 — introduce the Actions tree

- [done] Add `src/Actions/ApplyVoucherToCart`, `RemoveVoucherFromCart`, `ValidateVoucherCode`, `RecordVoucherUsage`, `ExpireVo...
- [done] Move orchestration out of services.
- [done] Update listeners and cart integration.

### Phase 2 — clarify the stacking trio

- [done] Audited `StackingEngine` (orchestrates rule evaluation), `StackingPolicy` (entry point for clients), `StackingDecision` (immutable value object).
- [done] Picked `StackingPolicy` as the canonical entry point.
- [done] Documented public API in `StackingPolicy` class docblock.

### Phase 3 — add stacking rule registry

- [done] Add `Stacking/StackingRuleRegistry`.
- [done] Register built-ins from the service provider.
- [done] Document the registration pattern via `StackingRuleRegistry`.

### Phase 4 — add voucher lifecycle events

- [done] Added events: `VoucherCreated`, `VoucherExpired`, `VoucherUsageRecorded`, `VoucherRefilled`.
- [done] Dispatched from Actions: `CreateVoucher`, `ExpireVoucher`, `RecordVoucherUsage`. *(Note: `VoucherRefilled` exists on disk but was missing from original [done] list.)*

### Phase 5 — fix Action wiring gaps

- [done] Make `ValidateVoucherCode` Action the single entry point — `ApplyVoucherToCart` now uses `ValidateVoucherCode::run()` instead of `Voucher::validate()` Facade call.
- [done] Add `UpdateVoucher` Action and move `VoucherService::update()` orchestration into it — `VoucherService::update()` now delegates to `UpdateVoucher::run()`.
- [done] Move owner auto-assignment from `VoucherService::create()` into `CreateVoucher` Action — removed duplicate owner logic from `VoucherService::create()`.
- [done] Audit listener duplicate validation — ensure `IncrementVoucherAppliedCount` and `ValidateVoucherOnCheckout` use a shared validation path rather than repeating logic.
    **Result:** `ValidateVoucherOnCheckout` now calls `ValidateVoucherCode::run($code, $cart)` (the canonical Action) instead of `$this->voucherService->validate()`. `IncrementVoucherAppliedCount` fires on `VoucherApplied` event and only increments `applied_count` — no validation logic. Both listeners now use the shared Action validation path.

### Phase 6 — audit large Action and cross-package stacking

- [done] Audit `ApplyVoucherToCart` (163 lines) — consider splitting stacking coordination, rule factory wiring, and cart condition registration into sub-Actions.
    **Audit result:** The Action composes 4 concerns: (1) voucher validation via `ValidateVoucherCode::run()`, (2) stacking via engine, (3) rules factory wiring on the cart, (4) condition registration. These concerns are already delegated to sub-modules (`StackingEngine`, `VoucherRulesFactory`, `CartCondition`). Further splitting would add indirection without reducing complexity for the current surface area. Deferred.
- [done] Coordinate stacking rule catalog with `promotions` — decide if `commerce-support` should host a shared `StackingRuleRegistry` or rule catalog primitive.
    **Audit result:** `promotions` package has no `StackingRuleRegistry` or stacking-related primitives. No current overlap to consolidate. Deferred until both packages independently use stacking registries.



## Suggested verification scope

- per-Action tests for new mutation Actions
- stacking engine tests
- compound voucher tests
- cross-package tests for cart/checkout/affiliates/signals after refactor

## Recommended first move

Phase 1 — introduce the Actions tree. The package has a real stacking engine and a real compound engine, but no Actions. The Actions split is mostly mechanical and unblocks later cleanup.
