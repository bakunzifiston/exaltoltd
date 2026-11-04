# Browser QA log — admin CRUD and financial statements

Driven headlessly against a live app (`php artisan serve`) on the real development database
(MariaDB, 172 sales / 290 sale lines / 277 inventory records / 104 customers). No unit
tests were used for this pass; every finding below came from loading real pages and
submitting real forms.

All test data created during the run was removed afterwards. Original data verified intact:
3 financial reports, 4 users, 104 customers, 6 suppliers, 5 products, 172 sales,
277 inventory records, 33 expenses.

---

## Errors caught and their status

| # | Error | Where | Cause | Status |
|---|---|---|---|---|
| 1 | `Undefined variable $totalRevenuePaid` — dashboard 500 on every load | `AdminDashboardService.php:254` | Missed one reference when renaming to `$recognisedRevenue`. Unit tests passed because the fixture had **zero employees**, so `$headcount > 0` short-circuited past the line. Real data has 10. | **FIXED** |
| 2 | `SQLSTATE[42000] 1064` — profitability page 500 | `ProductProfitabilityService` | `COUNT(*) as lines` — `lines` is a reserved word in MariaDB. | **FIXED** — aliased to `line_count` |
| 3 | `SQLSTATE[42000] 1055` — profitability page 500 | `ProductProfitabilityService` | `ONLY_FULL_GROUP_BY`: `products.name` used inside `COALESCE` but absent from `GROUP BY`. | **FIXED** — added to both group-bys |
| 4 | Migrations could not run: `errno 150 foreign key constraint is incorrectly formed` | Whole schema | **All 32 existing tables were MyISAM**, which supports neither foreign keys nor transactions. New tables default to InnoDB, and InnoDB cannot reference MyISAM. | **FIXED** — backup taken, all 32 tables converted to InnoDB |
| 5 | `Undefined variable $request` — every product deletion threw a 500 *after* deleting the row | `ProductController::destroy()` | Method signature is `destroy(Product $product)` but the body calls `$request->filled(...)`. Pre-existing; not introduced by this work. | **FIXED** — added `Request $request` parameter |
| 6 | Cost of goods sold was **0.00** on every real statement | `IncomeStatementDefinition` | Costing consumed FIFO *purchase layers of the finished product*. Shaky manufactures: all 277 inventory records are raw materials with no `product_id`, so no finished-goods layer exists to consume. Revenue was recognised with no cost against it, overstating profit and equity. | **FIXED** — see "Costing rebuilt" below |
| 7 | Balance sheet did not balance on real data (out by ~3.17M) | `BalanceSheetService` | Consequence of #6, plus inventory counting only raw materials — so every production batch looked like value being destroyed. | **PARTLY FIXED** — see "Remaining imbalance" |
| 8 | `Undefined property: stdClass::$quantity_sold` | `ProductionCostingService` | `pluck()` with a raw `SUM()` expression has no column to key on. | **FIXED** — explicit `select` with an alias |
| 9 | Statements were wrong unless someone pressed "Recalculate costs" first | `IncomeStatementDefinition` | Income statement read frozen sale-line costs, but nothing populated them. | **FIXED** — the snapshot now costs any uncosted line in the window itself; costing is idempotent, so it is a no-op afterwards |
| 10 | 99.1% gross margin presented as fact | Profitability report | Legacy placeholder batches claim thousands of units produced from two material lots, making goods look nearly free. | **FIXED (surfaced, not silenced)** — implausible batches are now listed with their cost as a share of price |
| 11 | No delete control anywhere in the UI for customers or suppliers | `customers/index`, `suppliers/index` | `customers.destroy` and `suppliers.destroy` routes exist and work, but no view renders a button for them. | **OPEN** — product decision, see below |
| 12 | HTTP 403 on every `/storage/*` image | Environment | `public/storage` symlink missing. | **OPEN** — environment, fix with `php artisan storage:link` |

Three further "failures" during the run were **faults in my own test harness**, not the app,
and are recorded here so the results are not misread:

- A generic delete routine scanned `div` elements and matched a wrapper containing the
  header's **logout** form, ending the session mid-run.
- An edit-link scan matched the header's **"Edit Profile"** link, so it edited the logged-in
  user's own profile and overwrote their email. Both scans are now scoped to `tr` only.
- Employee creation "failed" because `district` is a dependent dropdown that must match the
  selected `province`; the generic filler chose each independently.

---

## Costing rebuilt (finding #6)

Shaky is a manufacturer, not a reseller. The correct chain already existed in the data and
is now what the system uses:

```
raw-material lot cost  ->  batch cost  ->  cost per unit produced  ->  cost of a sale line
```

`sale_items.production_id` names the batch each sale came from, so this is exact per batch
rather than an average. FIFO over purchase layers is retained as a fallback for genuinely
resold goods. A line neither path can price stays `null`, never `0` — a zero would let it
contribute revenue with no cost and quietly overstate margin.

New: `ProductionCostingService`. Result on real data: **158 of 286 delivered lines costed
from production batches**, up from 0.

Inventory on the balance sheet is now raw materials *plus* finished goods (units produced
less units sold, at batch cost), so production moves value between two assets instead of
appearing to destroy it.

---

## Remaining imbalance (finding #7) — data, not arithmetic

The balance check is still out by roughly RWF 2.5M in early months. The cause is
data completeness, and the statement now names it in its own notes rather than leaving the
preparer to guess:

1. **128 of 286 delivered sale lines carry no cost** (RWF 7,382,100 of revenue). They belong
   to 3 of 42 production batches that record no usable raw materials. Those sales add profit
   with nothing against them, and their stock carries no value.
2. **The two inventory subledgers disagree.** Production records consume 3,780.45 units of
   raw material; the inventory lots show 9,727.67 units issued — a gap of 5,947.22 units.

Both need a business decision and a data fix, not a code change. Until then the balance
check line reports the difference in red and blocks nothing silently. That line is doing
exactly the job it was added for: the discrepancy existed before, it was simply invisible.

---

## CRUD coverage

| Area | Create | Read | Update | Delete | Notes |
|---|---|---|---|---|---|
| Customers | pass | pass | pass | **no UI** | destroy route exists, unreachable |
| Suppliers | pass | pass | pass | **no UI** | destroy route exists, unreachable |
| Employees | pass | pass | pass | pass | province/district dependency verified |
| Products | pass | pass | pass | pass | after fixing #5 |
| Expenses | pass | pass | pass | pass | delete lives on the all-records tab |
| Inventory records | pass | pass | pass | pass | `total_amount` correctly derived server-side (50 × 1,200 = 60,000); client value ignored |
| Sales | form loads | — | — | — | empty submit validated, no crash |
| Productions | form loads | — | — | — | empty submit validated, no crash |
| Cash accounts | pass | pass | — | pass | |
| Opening balances | pass | pass | pass | pass | unbalanced set correctly **rejected**; balanced set accepted |
| Supplier payments | pass | pass | — | pass | settles the originating purchase |
| Fixed assets | pass | pass | — | pass | depreciation exact: 41,666.67/mo, 9 months = 375,000.03 |
| Loans + repayments | pass | pass | — | pass | rejects split ≠ total, and principal > outstanding |
| Equity movements | pass | pass | — | pass | drawings correctly signed negative |
| Financial statements | pass ×3 | pass | pass | pass | income statement, balance sheet, cash flow |

Every admin page (29 routes) was swept for 500s and console errors. After the fixes above,
the only remaining failed requests are the `/storage/*` 403s in finding #12.

**Exports verified as genuine files**, not HTML error pages:

| Statement | PDF | XLSX |
|---|---|---|
| Income statement | `%PDF`, 38,608 bytes | `PK`, 10,758 bytes |
| Balance sheet | `%PDF`, 42,127 bytes | `PK`, 12,212 bytes |
| Cash flow | `%PDF`, 43,176 bytes | `PK`, 12,242 bytes |

---

## Recommended next actions

1. `php artisan storage:link` — restores product and employee images (#12).
2. Decide whether customers and suppliers should be deletable. The routes are already
   there; either render the control or remove the routes so the gap is not misleading (#11).
3. Complete the raw materials on the 3 uncostable production batches, then reconcile
   `inventory_records.quantity_out` against what productions record as consumed. Both are
   needed before the balance sheet can be relied on.
4. Consider whether `XLS-LEGACY-STOCK-*` batches should carry a real opening cost, or be
   replaced by an opening-balance entry for finished goods.


---

## Follow-up pass: table pagination

**Reported:** tables showed only Prev/Next, with no page numbers and no way to change how
many rows are displayed.

**Root cause (#13).** `resources/css/admin/app.css` sources only `resources/views/**` and
`resources/js/**`. It never scans Laravel's vendor pagination Blade view, so the utility
classes that view relies on were never generated for the admin bundle and its numbered
section stayed `display:none`. Laravel was rendering the numbers all along; the admin CSS
simply had no rules to show them. **FIXED** — replaced with a self-contained
`<x-admin.table-pagination>` component styled from the project's own stylesheet, so it no
longer depends on vendor view scanning at all.

**What every table now has**

- Numbered pages with first/prev/next/last, the current page highlighted, and a `…` gap
  marker so a long table never renders a hundred links.
- A row-count picker: **10, 25, 50, 100, All** (`?per_page=`), defaulting to each screen's
  previous size.
- A "Showing 31–40 of 104 customers" line, and a proper empty state.
- Filters, search and sort survive both page links and a change of page size
  (`withQueryString()` plus carrying the query into the picker's option URLs).

**Guards**

- `All` is capped at 2,000 rows. Unbounded would take the page down on a large table rather
  than merely being slow; past the cap the view says so instead of silently truncating.
- An out-of-range or hand-edited `per_page` falls back to the screen's default.
- The expenses "All records" footer still totals **the whole year**, not the visible page.
  Paginating that table would otherwise have quietly changed what the Total row meant.

**Coverage:** 27 controllers, 33 views. Two screens that build their rows in PHP rather than
by query — stock-on-hand and the expenses all-records tab — got a collection paginator so
they behave identically.

Deliberately left unpaginated: financial statements, the profitability and team-performance
reports, data-health, detail/show panels and small settings tables. Paginating a balance
sheet or a per-record detail panel would be wrong, not merely unnecessary.

**Verified in the browser across 26 pages** — every one returned HTTP 200 with the picker,
the count line and a visible numbered nav; `per_page` 10/50/100/all returned exactly
10/50/100/104 rows on the 104-row customers table; page 3 honoured the chosen size; and
filters survived both paging and resizing.

One more latent bug was caught during this pass: several `index()` methods had no `Request`
parameter, so wiring the page size into them would have produced the same
`Undefined variable $request` fatal as finding #5. All were given the parameter before
anything shipped.
