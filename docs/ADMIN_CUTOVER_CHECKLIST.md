# Admin Admin Panel Checklist

## Goal
Operate fully on custom Blade admin safely.

## Phase A - Pre-cutover
- [ ] Confirm all custom modules function on real staging data:
  - Users, Employees, Products, Inventory Records, Productions, Sales, Dashboard
- [ ] Validate parity of calculations:
  - Sales totals and item totals
  - Production stock deduction behavior
  - Inventory payment status auto-calculation
  - FIFO stock value, COGS, gross profit
- [ ] Validate role/auth behavior for `/admin-app/*`
- [ ] Confirm login works on `/admin-app/login`

## Phase B - Cutover
- [ ] Clear cached config/routes:
  - `php artisan optimize:clear`
- [ ] Verify `/` redirects to `/admin-app`
- [ ] Run smoke checks on top workflows:
  - create sale
  - create production with material consumption
  - create/update inventory payment

## Phase C - Soak period
- [ ] Monitor logs for validation/query/auth errors
- [ ] Compare key daily KPIs between old and new dashboards

## Phase D - Maintenance
- [ ] Re-run full regression and smoke tests

## Rollback
- [ ] Restore previous release artifact
- [ ] `php artisan optimize:clear`
- [ ] Confirm `/` redirects to `/admin-app`
