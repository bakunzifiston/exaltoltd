# Stock Integrity & Error Handling — Mitigation Plan

> The system currently lets you record consumption/sale of stock you don't actually have, which
> erodes trust in the numbers. This plan audits every place stock decreases, then lays out how to
> **prevent recording what we don't have** and **handle errors gracefully** (no raw 500s, no silent
> data corruption). Companion to [INVENTORY-PLAN.md](INVENTORY-PLAN.md). **Plan only — no code yet.**
> Reviewed 2026-06-14.

---

## 1. Where the system lets us record stock we don't have

Every flow that *decreases* stock, and whether it's protected today:

| Flow | Guard today | Verdict |
|------|-------------|---------|
| **Online checkout** (`StorefrontCheckoutService`) | Checks `sellable_qty`, throws a caught error, shows a friendly message | ✅ Safe |
| **Admin sale** (`SaleItem::created`, [SaleItem.php:32](../app/Models/SaleItem.php)) | **None.** Does `quantity_remaining = max(remaining − qty, 0)` — silently floors at zero | 🔴 **Oversells finished goods; the shortfall is hidden, stock looks like 0 instead of erroring** |
| **Production consuming raw materials** (`Production::applyMaterials`, [Production.php:178](../app/Models/Production.php)) | Throws `Exception` if `used > remaining`, but **inside a post-insert hook with no controller transaction** ([ProductionController.php:55](../app/Http/Controllers/Admin/ProductionController.php)) | 🟠 **Blocks the deduction but leaves an orphan batch + raw 500; partial/inconsistent state** |
| **Manual inventory record** (`Store/UpdateInventoryRecordRequest`) | No rule that `quantity_out ≤ quantity_in`; `remainingQuantity()` floors at 0 | 🟠 **Phantom negative on-hand can be entered directly** |
| **Quantities generally** (sales, production, inventory) | `numeric` only — negatives accepted | 🟠 Negative qty / damaged values possible |

**Two root causes:**

1. **Silent clamping instead of rejecting.** `max(… , 0)` makes an impossible action *look* successful while quietly losing the discrepancy. The user never learns stock was insufficient.
2. **Checks live too deep and too late.** The only real stock check (production) runs in an Eloquent `created` hook *after* the row is committed and *outside* a controller transaction, so a failure corrupts state and surfaces as a 500.

There's also **no single source of truth** for "available": availability is recomputed ad hoc in checkout, admin sales, and production.

---

## 2. Guiding principles

- **Validate at the boundary, reject don't clamp.** Block the write in the FormRequest *before* anything is saved; never silently floor to zero.
- **One operation = one transaction.** Wrap each multi-step write (sale + items, batch + material deduction) in a single DB transaction so a failure leaves *nothing* behind.
- **Lock before check-then-deduct.** Use `lockForUpdate()` on the stock row across the read→write so two concurrent operations can't both pass the check (production already does this; sales don't).
- **One availability authority.** A small `StockService` used by every flow, so the rule is defined once.
- **Domain errors become friendly messages.** A typed `InsufficientStockException` mapped to an inline form error (HTTP 422), with the technical detail logged — never a raw 500.

---

## 3. Mitigations, flow by flow

### 3.1 Admin sales — stop overselling finished goods (highest priority)

- **Validate in `StoreSaleRequest` / `UpdateSaleRequest`** (`withValidator`): sum `quantity_sold` per `production_id` across all lines and reject if it exceeds that batch's `quantity_remaining`. On **edit**, first add back the quantities the sale currently holds (so re-saving an unchanged sale doesn't false-trip).
- **Remove the silent `max(… , 0)`** in `SaleItem::created`; trust validated input and keep only a defensive `InsufficientStockException` as a last resort.
- **Message:** *"Only 12 units of Neza Sauce 250ml (batch BATCH-0007) are available — you tried to sell 20."*

### 3.2 Production — move the check up and make it atomic

- **Validate in `StoreProductionRequest` / `UpdateProductionRequest`**: each material's `quantity_used ≤ inventory.remainingQuantity()`. Reject before the batch is created.
- **Wrap batch creation + material deduction in one transaction** at the controller/service level so a failure rolls back the batch too (no orphan). Today the insert and the deduction are in separate transactions.
- **Catch the domain exception** in the controller → `back()->withErrors()`; keep the model-level check as defence-in-depth.

### 3.3 Manual inventory entry

- **Rules:** `quantity_out ≤ quantity_in`; `quantity_in, quantity_out, damaged ≥ 0`; `damaged ≤ quantity_in`.
- **Protect consumed lots:** block reducing `quantity_in` below what's already been consumed (`quantity_out`) — extends the "consumed lot can't be deleted" guard already shipped.

### 3.4 Cross-cutting

- **`StockService`** with `availableFinishedGoods(productionId)` and `availableRawMaterial(inventoryId)` — the single check used by checkout, admin sales, and production.
- **Negative-value rules** (`min:0` / `gt:0`) added consistently across sale, production, and inventory requests.

---

## 4. Error-handling strategy

- **Typed exception:** `App\Exceptions\InsufficientStockException` (carries item name, requested, available) thrown by `StockService` as the final safety net.
- **Controllers wrap writes in `DB::transaction` + try/catch**, converting domain exceptions to `back()->withErrors([...])->withInput()` with the item-specific message; log the technical cause.
- **Global mapping:** register a handler so any domain exception that escapes a controller still renders as a 422 with the message (JSON or inline), never a stack-trace 500.
- **Consistency:** sales already run in a transaction (`SaleWorkflowService`); production must be brought to the same standard.

---

## 5. Clean up the data already in a bad state

Prevention stops *new* bad data; we also need to surface what's already wrong so the owner can trust the system again.

- **"Data Health" report** (admin, owner/manager): 
  - productions where `quantity_remaining` was clamped (units sold > produced),
  - `inventory_records` where `quantity_out > quantity_in` (negative on-hand),
  - items whose **ledger doesn't reconcile** — `Σ stock_movements` ≠ current `quantity_remaining`, or `Σ inventory_movements` ≠ `quantity_in − quantity_out` (the new ledgers from Phase 1 make this checkable).
- **A `php artisan stock:audit` command** producing the same findings for ops/cron, so discrepancies are caught early.
- Each finding links to the record for manual correction.

---

## 6. Phased rollout

| Phase | Scope | Risk | Why |
|-------|-------|------|-----|
| 🔴 **P0** | Boundary validation: admin-sale stock check, production material check, inventory `out ≤ in`, non-negative quantities — all with friendly errors | Low (additive rules) | **Stops new "phantom stock" immediately** |
| 🟠 **P1** | `StockService` single authority; production wrapped in one transaction; `InsufficientStockException`; remove silent `max(…,0)` clamps; global exception mapping | Medium | Makes writes atomic & errors graceful |
| 🟡 **P2** | "Data Health" reconciliation report + `stock:audit` command; clean up historical discrepancies | Low | Restores trust in existing numbers |
| 🟢 **P3** | `lockForUpdate` on sale stock check (race safety); low-stock / negative-stock alerts | Medium | Hardening under concurrency |

**Recommendation:** do **P0 first** — it's low-risk, additive validation that immediately stops the system from accepting sales/production it can't back with real stock, with clear messages instead of silent clamping or 500s. P1–P2 follow to make the engine atomic and to clean up what's already recorded.

---

## 7. Implementation log

**2026-06-15 — P0 shipped (boundary validation: refuse stock we don't have):**

| Guard | Status | Where |
|-------|--------|-------|
| **Admin sale** rejects selling more finished goods than a batch holds; edit-aware (adds back the quantities the sale currently holds before checking) | ✅ Done | `ChecksFinishedGoodsStock` trait → `Store/UpdateSaleRequest` |
| **Production** rejects consuming more raw material than is on hand; edit-aware (adds back the batch's current materials) | ✅ Done | `ChecksRawMaterialStock` trait → `Store/UpdateProductionRequest` |
| **Inventory** `quantity_out ≤ quantity_in`; `damaged ≤ quantity_in`; all quantities `≥ 0`, with friendly messages | ✅ Done | `Store/UpdateInventoryRecordRequest` |
| **Non-negative** `quantity_produced` / `damaged` on production | ✅ Done | production requests |
| Validation summary now shown on sale & production create/edit | ✅ Done | `sales/_form`, `productions/_form` |

Messages are specific, e.g. *"Not enough stock: only 12 unit(s) of Sauce 250ml (batch BATCH-0007) are
available, but 20 were entered."* Verified with a 12-assertion smoke test covering oversell, over-consume,
negative quantities, `out > in`, and the edit-path "held quantity" add-back (edit to 35 of an effective 40
passes; 45 fails).

**2026-06-15 — P1, P2, P3 shipped:**

| Item | Status | Where |
|------|--------|-------|
| **P1** `StockService` (single availability authority, row-locking) + typed `InsufficientStockException` | ✅ Done | `app/Services/StockService.php`, `app/Exceptions/InsufficientStockException.php` |
| **P1** Removed silent `max(…,0)` clamps — `SaleItem` and `Production` now assert availability and throw; the whole sale/batch rolls back | ✅ Done | `SaleItem` (row-locked assert), `Production::applyMaterials` |
| **P1** Production wrapped in one transaction (no orphan batch on failure); global handler renders the exception as a friendly 422 / redirect, never a 500 | ✅ Done | `ProductionController`, `bootstrap/app.php` |
| **P2** `StockAuditService` — oversold batches, oversold products, raw lots with out > in, negative finished goods | ✅ Done | `app/Services/StockAuditService.php` |
| **P2** Data Health report (owner/manager) + `stock:audit` console command | ✅ Done | `/admin-app/data-health`, `php artisan stock:audit` |
| **P3** Row-lock on the sale deduction (concurrency-safe, delivered with P1) + low-stock dashboard alert banner | ✅ Done | `SaleItem`, `AdminDashboardService` (`low_stock_materials`), dashboard banner |

Verified with a 10-assertion smoke test: over-consume/over-sell throw the typed exception with **no
orphan batch and no silent clamp**, the failed sale leaves stock untouched, the audit detects oversold
batches/products and negative lots, and the low-stock count fires below reorder level. `stock:audit`
runs and reports; all Blade views compile.

**All four stock-integrity phases (P0–P3) are now complete.** The system refuses to record stock it
can't back, fails gracefully, and surfaces any pre-existing bad data on the Data Health page.
