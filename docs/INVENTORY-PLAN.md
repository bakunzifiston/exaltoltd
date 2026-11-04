# Inventory — Evaluation & Implementation Plan

> Focused review of the raw-material / inventory module: CRUD completeness, intake (lot)
> identification, usage traceability, and how purchase cost is recorded. Companion to
> [SYSTEM-ASSESSMENT.txt](SYSTEM-ASSESSMENT.txt). **This is a plan — no code changed yet.**
> Reviewed 2026-06-14.

---

## 1. What we have today

### 1.1 CRUD — complete

| Operation | Status | Where |
|-----------|--------|-------|
| Create | ✅ | `InventoryRecordController@create/store`, `_form` |
| List / search / filter / sort | ✅ | `index` — search supplier/item/invoice/location; filter by payment status & item type; sort by date/qty/amount |
| View detail | ✅ | `show` |
| Edit / update | ✅ | `edit/update` |
| Delete | ✅ (hard delete) | `destroy` |
| Access control | ✅ | Gated to Owner/Manager (`can:manage-catalog`) |
| Cost & AP capture | ✅ | `unit_cost` (auto = total ÷ qty), `total_amount`, `amount_paid`, `payment_status`, `payment_due_date`; supplier auto-linked |

**CRUD itself is solid.** The gaps below are about *traceability* and *cost accounting*, not basic CRUD.

### 1.2 How an intake is modelled

Each row in `inventory_records` **is one intake/delivery** — it has its own `record_date`,
`supplier`, `invoice_number`, `quantity_in`, and `unit_cost`. So **different intakes of the
same material are already separate, identifiable records** at the data level, and the index
lists them individually.

### 1.3 How consumption is tracked today

- When a **production batch** is created, the form lets the user pick which inventory record(s)
  to draw from and how much (`productions.inventory_record_id` = JSON `[{inventory_id, quantity_used}]`).
- `Production::applyMaterials()` then **increments `quantity_out`** on each chosen inventory record.
- `on hand = quantity_in − quantity_out`. FIFO valuation (`InventoryValuationService`) builds
  cost layers per item for stock value and COGS.

---

## 2. The gaps (answering your questions directly)

### 2.1 "Can we identify different intakes, or only show what we have now?"

**Partly.** Each intake is its own record, so they exist separately — but:

- There is **no consolidated lot view**: e.g. "Fresh Chili — 3 deliveries on hand: 12 kg @ 800 (10 Jun),
  20 kg @ 850 (3 Jun)…". The Reports page even *aggregates* valuation by `item_name`, hiding per-intake detail.
- No **lot/batch number** or **expiry date** on raw materials, so two deliveries of the same item are
  distinguished only by date/invoice, not a label you can track or trace for spoilage/recall.
- The FIFO layers the system computes internally are **never surfaced** in the UI.

### 2.2 "Details on how we used that item" — the biggest gap

**This is essentially missing.** Today an inventory record shows only **net** `quantity_out` and
`on hand`. It does **not** show:

- *Which* production batches consumed it, *when*, and *how much each took*.
- Any consumption history / ledger for raw materials. (Finished goods got a `stock_movements`
  ledger in the recent work; **raw materials did not** — they only have the running `quantity_out` total.)
- Damage/spoilage as events — `damaged` is a single static number, not dated entries.

The link *exists* (a production knows which intake it drew from), but it's locked inside a JSON
blob on `productions` and is **not viewable from the inventory side**. So you cannot answer
"where did our 50 L of oil go?" — only "30 L left."

### 2.3 "Since it cost us, how do we record that expense?" — a real accounting ambiguity

There are currently **two disconnected places** a raw-material cost can land, with **no link
between them** and a **double-counting risk**:

1. **Inventory record** — captures `total_amount` / `unit_cost` / supplier AP. This cost flows into
   **COGS via FIFO when the material is consumed and the product sold** (correct accrual accounting:
   inventory is an *asset* until used).
2. **Expenses module** — already ships with **COGS categories named after raw materials**
   ("Fresh Red Chili", "Sun Flower Oil", …). If a user logs the chili purchase here *as well*,
   the same cost is counted **twice** in profit: once as a manual expense, once as COGS.

There is no rule, link, or guard preventing this. The net effect: **"the expense of buying stock"
is recorded inconsistently** and the P&L can be wrong depending on staff habits.

### 2.4 Smaller CRUD risks

- **Hard delete, no guard:** deleting an inventory record that production already consumed from
  silently corrupts FIFO/COGS history. No soft delete, no "this lot has been used" block.
- **No `created_by`** on intakes — can't see who recorded a delivery (the rest of the system now has attribution).
- Delete is allowed for any Manager; not behind the stricter `delete-records` gate used for sales/production.

---

## 3. Implementation plan

Four phases, each independently valuable. Phase 1 closes the traceability gap you care about most;
Phase 4 resolves the cost/expense ambiguity.

### Phase 1 — Raw-material movement ledger (traceability) ⭐ highest value

1. **New table `inventory_movements`**: `inventory_record_id`, `type` (intake / consumption /
   damage / adjustment / reversal), `quantity` (signed), `unit_cost` (snapshot), `reference_type`
   + `reference_id` (e.g. the production), `notes`, `user_id`, `moved_at`.
2. **Write movements automatically:**
   - Intake → `+quantity_in` row when a record is created.
   - `Production::applyMaterials()` → a `consumption` row per material, referencing the batch.
   - `releaseMaterials()` (batch edit/delete) → reversal rows.
   - Keep `quantity_out` as a cached rollup (or derive it from movements).
3. **"Usage history" panel on the inventory show page** — a dated table: *date · production batch
   (linked) · quantity used · running balance*. This directly answers "how did we use this item."
4. **Reverse link on the production page** — list the exact lots and quantities each batch consumed.

*Backfill:* generate `intake` rows from existing records and `consumption` rows by parsing the
existing `productions.inventory_record_id` JSON, so history isn't blank on day one.

### Phase 2 — Intake / lot clarity

5. Add `lot_number` (optional, auto-suggestable), `expiry_date`, and `created_by` to `inventory_records`.
6. **"Stock on hand by item" view** that surfaces the FIFO layers already computed: each item with
   its open lots (remaining qty, unit cost, value, age, FIFO order).
7. **Low-stock and near-expiry indicators** (reorder threshold per item — optional `reorder_level`).

### Phase 3 — CRUD hardening

8. `SoftDeletes` on `inventory_records`.
9. **Guard**: block deleting (or reducing `quantity_in` below consumed) for any record that has
   consumption movements; reserve delete for the `delete-records` ability (Owner/Manager), matching sales/production.
10. Stamp and display `created_by` on each intake.

### Phase 4 — Purchase-cost / expense model (resolve double-counting) ⭐ the accounting fix

**Recommended model (accountant-correct, avoids double counting):**

- Raw-material purchases are recorded **once**, in inventory, as an **asset + supplier payable**.
  They become an **expense (COGS) automatically when consumed**, which the FIFO valuation already feeds
  into the P&L. **They should not also be entered in the Expenses module.**
- **Re-scope the Expenses module to operating expenses only** (rent, salaries, energy, transport…).
  Retire or clearly relabel the COGS-style categories so staff don't double-enter material costs;
  add an inline note: *"Raw-material costs are tracked in Inventory and counted as COGS automatically."*
- **Add a "Purchases" summary** (cash-out view): total spent on inventory in a period, from
  `inventory_records.total_amount`, shown **separately** from COGS — so you still see "what we spent
  buying stock" without polluting profit.
- Add a one-line note on the P&L: *"COGS is derived from inventory consumption (FIFO); purchases are
  not double-counted as expenses."*
- *(Optional, later)* a `supplier_payments` ledger mirroring the customer `sale_payments` ledger, so
  supplier invoices can be paid down over time — extends the Suppliers AP module already built.

**Decision needed from you:** confirm the accrual model above (recommended), **or** if you prefer the
simpler cash view where every purchase is booked straight to expenses, we instead drop FIFO-COGS from
the P&L and treat purchases as the expense — but we must pick **one** to keep profit correct.

---

## 4. Suggested priority

| Priority | Phase | Why |
|----------|-------|-----|
| 🔴 P0 | Phase 1 — movement ledger + usage history | Directly answers "how did we use this item"; stops history living in a JSON blob |
| 🟠 P1 | Phase 4 — purchase/expense model | Fixes a real double-counting risk in profit; decision-gated |
| 🟠 P1 | Phase 3 — soft delete + delete guard | Protects FIFO/COGS integrity from an accidental delete |
| 🟡 P2 | Phase 2 — lot view, expiry, low-stock | Operational visibility & spoilage/reorder control |

---

## 5. Implementation log

**2026-06-14 — Phase 1 shipped (raw-material traceability):**

| Item | Status | Where |
|------|--------|-------|
| `inventory_movements` ledger (intake / consumption / damage / adjustment / reversal), signed qty + cost snapshot + reference + user | ✅ Done | `inventory_movements` migration, `App\Models\InventoryMovement` |
| Intake movement auto-logged when a delivery is recorded | ✅ Done | `InventoryRecord::created` hook |
| Consumption / reversal movements auto-logged when production batches consume or release materials | ✅ Done | `Production::applyMaterials/releaseMaterials` |
| Backfill of historical intakes + consumption (parsed from `productions.inventory_record_id`) | ✅ Done | migration backfill |
| **Usage history panel** on the inventory detail page (date · movement · batch link · qty · running balance · who) | ✅ Done | `inventory-records/show` |
| **Reverse link**: production page lists each consumed lot (linked) with qty, unit cost and batch material cost | ✅ Done | `productions/show` |

Verified with a 13-assertion smoke test: distinct lots, per-lot intake/consumption logging,
multi-lot consumption from one batch, running balance reconciling to on-hand, lot→batches
traceback, and edit-reversal trail.

**Phase 4 decision (locked):** accrual / FIFO-COGS model — purchases are an inventory asset + supplier
payable and become expense (COGS) when consumed; the Expenses module will be re-scoped to operating
expenses only, with a separate Purchases summary. *(Phase 4 not yet built.)*

**2026-06-14 — Phases 2, 3, 4 + fresh-install fix shipped:**

| Item | Status | Where |
|------|--------|-------|
| **Fresh-install fix** — "ensure columns" migration dated before the index migration so `unit_cost`/`total_amount`/`payment_status`/etc. exist on clean installs (no-op on existing DBs) | ✅ Done | `2026_06_10_000000_ensure_inventory_records_columns` |
| **Phase 3** — soft deletes + `created_by` on inventory; delete reserved for `delete-records`; **consumed lots cannot be deleted** (protects FIFO history); recorder shown on detail page | ✅ Done | `InventoryRecord` (SoftDeletes, `hasBeenConsumed()`), `InventoryRecordController@destroy` |
| **Phase 2** — `lot_number`, `expiry_date`, `reorder_level` on intakes; **Stock-on-Hand by item** view (open lots, FIFO order, value, low-stock & near-expiry/expired flags) | ✅ Done | `inventory-records/stock`, `stockOnHand()`, expiry accessors |
| **Phase 4** — net profit now subtracts **operating expenses only** (COGS comes from FIFO, no double count); `purchases_total` memo added; expense add-form restricted to operating categories with banner + server-side guard | ✅ Done | `AdminDashboardService`, `reports/index`, `expenses/_form`, `StoreExpenseRequest` |

Verified with a 13-assertion traceability test (Phase 1) and an 8-assertion test for Phases 2/3/4
(lot lifecycle, near-expiry flag, consumed-lot delete block, soft delete, operating-only profit,
purchases excluding soft-deleted lots). All Blade views compile.

**Fresh-install ordering bug — fixed.** The `inventory_records` add-column migrations were dated
*before* the create-table migration, so clean installs were missing columns; the new ensure-columns
migration (dated `2026_06_10`, before the reporting-index migration) backstops them. Existing/production
databases are unaffected (no-op).

*All four phases of the inventory plan are now complete.*
