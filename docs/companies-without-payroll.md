# Companies without payroll (شركات لم تصدر رواتب)

## Overview

Lists companies that have trainees with a تاريخ مباشرة and are missing invoices for at least one calendar month in the selected range. Under each company name is a credit-style month strip: green when an invoice `from_date` exists in that month, red when it does not.

تاريخ المباشرة comes from the cached `trainee_documentation_dates` table (Drive sheet تاريخ التوثيق), the same source used by تقرير الفواتير (تفاصيل).

## How to open it

1. Go to **Finance** (`/back/finance`).
2. Open **شركات لم تصدر رواتب**.
3. Choose `date from` / `date to`, then search.

## Inclusion rules

A company appears when all of these are true:

1. It has at least one trainee (current team) whose identity exists in `trainee_documentation_dates`.
2. That trainee’s `documented_on` is on or before `date_to`.
3. At least one calendar month between `date_from` and `date_to` has **no** invoice with `from_date` in that month.

Companies that issued invoices for every month in the range are excluded.

## Ordering

Worst → best:

1. Most missing months first
2. Then oldest last invoice `created_at` (never invoiced first)
3. Then company name

## Screen

- One row per company: name (with link to the company page), month circles (`mm/yyyy`), code, trainee count, earliest start date, last invoice `created_at`.
- Expand a row to see trainee name (with link to the trainee profile), identity, and start date.
- Excel exports one line per trainee under those companies, including missing-month count and month coverage.

## Permission

`view-backoffice-reports`

## Routes

| Method | Path | Name |
|---|---|---|
| GET | `/back/finance/companies-without-payroll` | `back.finance.companies-without-payroll` |
| GET | `/back/finance/companies-without-payroll/excel` | `back.finance.companies-without-payroll.excel` |

## Code

| Piece | Location |
|---|---|
| Page | `resources/js/Pages/Back/Finance/CompaniesWithoutPayroll.vue` |
| Finance card | `resources/js/Pages/Back/Finance/Index.vue` |
| Controller | `app/Http/Controllers/Back/CompaniesWithoutPayrollController.php` |
| Logic | `app/Services/CompaniesWithoutPayrollReportService.php` |
| Excel | `app/Exports/CompaniesWithoutPayrollExport.php` |
| Tests | `tests/Feature/CompaniesWithoutPayrollReportTest.php` |
