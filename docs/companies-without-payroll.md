# Companies without payroll (شركات لم تصدر رواتب)

## Overview

Lists companies that have trainees with a تاريخ مباشرة from the documentation Google Sheet, and are missing invoices for at least one **owed** calendar month in the selected range.

Under each company name is a credit-style month strip:

| Color | Meaning |
|---|---|
| Gray | Not owed yet (before eligibility starts) |
| Green | Invoice `from_date` exists in that month |
| Red | Owed, but no invoice |

## Eligibility

1. تاريخ المباشرة = `trainee_documentation_dates.documented_on` (Drive sheet تاريخ التوثيق).
2. For a company, use the **earliest** start date among its current trainees.
3. First owed month = the **month after** that start date’s month. Joining on the 1st still starts the following month.
4. Example: start `2026-10-12` (or `2026-10-01`) → first owed month = `2026-11`.

## Inclusion rules

A company appears when:

1. It has at least one trainee (current team) with a documentation date.
2. At least one calendar month in `[date_from, date_to]` is owed and has no invoice with `from_date` in that month.

If every owed month in the range is covered, or no month in the range is owed yet, the company is omitted.

## Ordering

Worst → best:

1. Most missing owed months first
2. Then oldest last invoice `created_at` (never invoiced first)
3. Then company name

## Documentation sheet sync

`php artisan invoice-details:sync-documentation-dates` (hourly):

- Upserts `documented_on` from the sheet (updates when the date differs).
- Newer year tabs win over older tabs even if the date value is earlier.
- Also updates `trainees.linked_date` when it differs from the sheet date.

## Permission

`view-backoffice-reports`

## Routes

| Method | Path | Name |
|---|---|---|
| GET | `/back/finance/companies-without-payroll` | `back.finance.companies-without-payroll` |
| GET | `/back/finance/companies-without-payroll/excel` | `back.finance.companies-without-payroll.excel` |
