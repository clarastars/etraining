# Companies without payroll (شركات لم تصدر رواتب)

## Overview

Lists companies that have trainees with a تاريخ مباشرة, but no invoices whose `from_date` falls in the selected period. That means they have not issued payroll for that period.

تاريخ المباشرة comes from the cached `trainee_documentation_dates` table (Drive sheet تاريخ التوثيق), the same source used by تقرير الفواتير (تفاصيل).

## How to open it

1. Go to **Finance** (`/back/finance`).
2. Open **شركات لم تصدر رواتب**.
3. Choose `date from` / `date to`, then search.

## Inclusion rules

A company appears when all of these are true:

1. It has at least one trainee (current team) whose identity exists in `trainee_documentation_dates`.
2. That trainee’s `documented_on` is on or before `date_to`.
3. The company has **no** invoices with `from_date` between `date_from` and `date_to` (any status).

Companies that already have any invoice in the period are excluded, even if some trainees are missing invoices. Use the trainees-without-invoices report for that case.

## Screen

- One row per company: name, code, trainee count, earliest start date.
- Expand a row to see trainee name, identity, and start date.
- Excel exports one line per trainee under those companies.

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
