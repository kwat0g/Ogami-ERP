# Government contribution schedule import drafts

These CSV files are prepared for **Administration → Gov Tables** (`/ad/gov-tables`). Select the agency tab, open **Import or review schedule versions**, enter the source title and HTTPS URL below, and choose the matching CSV. Imports create drafts. A different authorized reviewer must verify and publish each schedule before payroll can use it.

## Schedule files and source metadata

| Agency | File | Source title | Official source URL |
| --- | --- | --- | --- |
| SSS | `sss-2025-01-01.csv` | SSS Circular No. 2024-006 — Schedule of SSS Contributions effective January 2025 (business employers and employees) | <https://www.sss.gov.ph/wp-content/uploads/2024/12/CI-2024-006-Publication.pdf> |
| PhilHealth | `philhealth-2025-01-01.csv` | PhilHealth Advisory No. 2026-0042 — Reimbursement Claims Abroad and Premium Contributions (confirms the 5% rate effective January 2025) | <https://www.philhealth.gov.ph/advisories/2026/PA2026-0042.pdf> |
| Pag-IBIG | `pagibig-2024-02-01.csv` | Pag-IBIG Circular No. 460 — Guidelines on the increase in Maximum Fund Salary effective February 2024 | <https://www.pagibigfund.gov.ph/document/pdf/circulars/provident/Circular%20No.%20460%20-%20Guidelines%20on%20the%20Pag-IBIG%20Fund's%20Implementation%20of%20Increase%20in%20the%20MFS%20Effective%20February%202024.pdf> |
| BIR | `bir-semi-monthly-2023-01-01.csv` | BIR RR No. 11-2018, Annex E — Revised semi-monthly withholding tax table effective January 1, 2023 onwards | <https://bir-cdn.bir.gov.ph/local/pdf/Annex%20E%20RR%2011-2018.pdf> |

## Review notes

- **SSS:** The file contains 61 business employee/employer compensation bands effective 2025-01-01. `ee_amount` is the employee SS+MPF share; `er_amount` is the employer SS+MPF share, excluding EC; `ec_amount` is the employer-only Employees' Compensation amount (₱10 through MSC ₱14,500; ₱30 from MSC ₱15,000). Confirm employment coverage and the latest schedule before publishing.
- **PhilHealth:** The official July 2026 Advisory No. 2026-0042 confirms a 5% premium effective January 2025 and gives examples from ₱10,000 monthly income/₱500 premium through ₱100,000/₱5,000. Advisory No. 2025-0002 states the ₱10,000 floor and ₱100,000 ceiling. The CSV is effective 2025-01-01; the reviewer should confirm these remain applicable to the payroll date before publishing.
- **Pag-IBIG:** `ee_amount` and `er_amount` are rates. The app separately caps the contribution salary at ₱10,000. The CSV encodes 1%/2% below or at ₱1,500 and 2%/2% above.
- **BIR:** `ee_amount` stores fixed tax and `er_amount` stores the rate on excess for the semi-monthly table. The source is effective 2023-01-01 onwards; confirm there is no superseding rule for the payroll date.
- All schedules are unapproved import drafts. Source data and payroll rules are subject to the app's independent reviewer and payroll/compliance approval workflow. Do not treat these files as already published or as legal advice.

## CSV format

The shared columns are `bracket_min,bracket_max,ee_amount,er_amount,effective_date`. SSS also requires `ec_amount`. The amounts are flat pesos for SSS, rates for PhilHealth/Pag-IBIG, and fixed tax/rate-on-excess for BIR.
