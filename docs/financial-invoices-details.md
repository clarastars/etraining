# Invoice details report (تقرير الفواتير — تفاصيل)

## Overview

The invoice details report is an editable working sheet on the finance page. It lists one row per invoice for a company and an invoice period, lets finance fill the start date, end date, and four monthly amounts, and calculates day count and amounts due with the same rules as the Excel `DAYS360` sheet.

The Masdr start date is shown for reference. It is not used in the day-count formula. The manual start date is.

Edits are stored per invoice and come back on the next visit. The same sheet can be downloaded as Excel (with live formulas) or PDF.

## How to open it

1. Go to **Finance** (`/back/finance`).
2. Open **تقرير الفواتير (تفاصيل)**. The card is visible only with the permission `view-financial-invoices-details`.
3. Choose a company and an invoice period (`date from` / `date to`), then search.

The period filters `invoices.from_date`. Every invoice status is included: unpaid, paid, audit, receipt rejected, finance audit, under review, and archived.

## What you can edit

Each cell saves on change.

| Column | Saved? | Source |
|---|---|---|
| Trainee, identity, subtotal, tax, grand total, status, invoice date | No | The invoice and its trainee |
| تاريخ المباشرة (مصدر) | No | Cached Masdr data for this company |
| تاريخ المباشرة (manual) | Yes | Empty until someone enters it |
| تاريخ الانتهاء | Yes | Empty until someone enters it |
| Full salary, full reward, full fees, full refund | Yes | See suggestions below |
| Day count, daily rate, amount due | No | Calculated |

### Suggestions on the first visit

Until a row is saved, the sheet suggests:

- **Full salary** from the company’s latest contract `trainee_salary` (latest `contract_starts_at`).
- **Full reward** from the trainee’s `platform_reward`.
- **Full fees** and **full refund** stay empty.

The first save copies those two suggestions onto the row, then applies the cell you edited. After that, an empty cell stays empty. Changing a date does not refill salary or reward.

Clearing a saved amount stores null. It does not fall back to the suggestion.

## Formulas

Day count uses Excel `DAYS360` (US / NASD), plus one day so both the start and the end count.

```
day count = DAYS360(manual start, end) + 1
daily     = full amount / 30
due       = daily × day count
```

Day count, daily, and due stay blank until both dates exist. Daily and due also stay blank when the full amount is empty.

NASD adjustments, matching Excel:

- If the start day is 31, it is treated as 30.
- If the start is the last day of February, it is treated as 30.
- If the end day is 31 and the start day is not 30, the end becomes the 1st of the next month. Otherwise the end day becomes 30.

Example: 14 May 2026 through 30 May 2026 is 17 days. A full salary of 2,750 gives a daily rate of 2,750 / 30 and a salary due of that rate × 17.

The same four blocks are salary, reward, training fees, and refund.

### Totals

The last row sums every numeric column: invoice subtotal, tax, grand total, day count, and each full amount, daily rate, and amount due. Name, identity, status, and dates are not totaled. The on-screen total updates as cells are saved. Excel uses `SUM` formulas. The PDF prints the same sums.

## Masdr date

تاريخ المباشرة (مصدر) reads cached `gosi_employee_data` only. The report does not call Masdr.

Matching:

1. Compare the company `cr_number` to each `employmentStatusInfo.commercialRegistrationNumber`, digits only.
2. Prefer the employment whose status contains نشيط or `active`.
3. Use `dateOfJoining`. If that is missing, use `salaryStartingDate`.
4. If nothing matches, the cell is an em dash.

Hijri years below 1700 are converted with the Islamic civil calendar when PHP `IntlCalendar` is available. Years 1700 and above are treated as Gregorian.

## Downloads

Both exports use the current company and date filters.

| Format | File | Notes |
|---|---|---|
| Excel | `invoice-details-report.xlsx` | RTL. Identity is text. Dates are Excel dates. Day count and the daily / due columns are formulas (`DAYS360`, `/30`, `× days`), so editing the sheet in Excel recalculates them. The totals row is `SUM`. |
| PDF | `invoice-details-report.pdf` | A3 landscape. Calculated values are already computed, to two decimals. |

## Permission

`view-financial-invoices-details`

The migration creates the permission and grants it to each team’s `{teamId}_admins` role. Other finance roles need it assigned. New teams receive it through `RolesService` when roles are seeded.

The finance hub itself still uses `view-backoffice-reports`. The details card and every details route use the new permission.

## Routes

All of these sit under `/back/finance` and require `view-financial-invoices-details`.

| Method | Path | Name |
|---|---|---|
| GET | `/back/finance/invoices/details` | `back.finance.invoices.details` |
| PATCH | `/back/finance/invoices/details/{invoice}` | `back.finance.invoices.details.update` |
| GET | `/back/finance/invoices/details/excel` | `back.finance.invoices.details.excel` |
| GET | `/back/finance/invoices/details/pdf` | `back.finance.invoices.details.pdf` |

`details` is registered before the invoices resource so it is not captured as an invoice id.

## Data

Saved inputs live in `invoice_detail_report_lines`, one row per invoice (`invoice_id` unique, cascade delete).

| Column | Meaning |
|---|---|
| `manual_start_date` | The start date used by `DAYS360` |
| `end_date` | The end date used by `DAYS360` |
| `full_salary`, `full_reward`, `full_fees`, `full_refund` | Monthly amounts. Null means blank |

Calculated columns are not stored.

## Code

| Piece | Location |
|---|---|
| Page | `resources/js/Pages/Back/Finance/Invoices/Details.vue` |
| Finance card | `resources/js/Pages/Back/Finance/Index.vue` |
| Controller | `app/Http/Controllers/Back/InvoiceDetailReportController.php` |
| Sheet logic | `app/Services/InvoiceDetailReportService.php` |
| Day count | `app/Support/ExcelDays360.php` |
| Excel | `app/Exports/InvoiceDetailsSheetExport.php` |
| PDF | `resources/views/pdf/finance/invoice-details.blade.php` |
| Model | `app/Models/Back/InvoiceDetailReportLine.php` |
| Tests | `tests/Feature/InvoiceDetailReportTest.php`, `tests/Unit/ExcelDays360Test.php` |
