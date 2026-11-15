# POC workbook source notes

The selected source is `docs/List of POC Products and Materials.xlsx` from `origin/main` commit `23fd5e2211f43e0d405578c269e627444edc5070`. It was inspected from the repository copy; the workbook is not copied into the demo seeder assets.

The workbook has 2,651 data rows and many repeated product rows. It includes part number, product name, raw-material description, plan quantity, consumption weight, and material-used fields. The selected rows below are stored as product examples and material descriptions for thesis demonstration. They are not customer-released specifications.

| Product family | Workbook part number | Workbook component | Workbook material text | Recorded consumption weight |
| --- | --- | --- | --- | ---: |
| Raw-material reference | `24979-252-01` | Terminal Unit (Black) | PBT DX 515HR EF2001 NC (J) + SK-P0499DC (0.40%) | 1.9095 |
| Washer System | `A2070-479-00` | Joint Washer | PP IDEMITSU J-762HP + BC4C 10MB (2.5%) | 0.95305 |
| Fan Motor | `M41-2-2578-101-00-3` | Fan Case | ABS PA-777D (FBP 0CA537 Black) | 61.0 |
| Fan Motor | `M41-2-2568-109-00-3` | Fan | PP IDEMITSU J-762HP + BC4C 10MB (2.5%) | 34.75 |
| Power Window Motor | `34995-935-10-002` | WM04B101A Cover Motor Upper | PP T120XHF (K) | 72.819 |
| Power Window Motor | `34995-936-11-009` | WM04B101A Cover Motor Lower | PP T120XHF (K) | 119.879 |
| Harness Protector | `53904-405-00` | Clip Wire Harness (Old Mold) | PA66 A3W NATURAL | 0.77 |
| Raw-material reference | `33949-402-00` | Bush RR | POM DURACON M90-44 CF2001 NAT + POM DN GH-25 CF3500 NC | 1.2 |

**Workbook limitations:** plan quantity cells are blank in these examples. The weight column has no unit label, and the material-used values for the selected rows are zero. The demo records the numeric values with an explicit “unit not stated” note and does not use them as BOM quantities, stock balances, costs, or purchasing prices.

The inventory catalog keeps its current application item codes, base UOMs, BOM quantities, costs, reorder points, lead times, and opening balances. Selected grade strings have been added to the relevant resin and colorant item descriptions. Raw-material supplier listings continue to use the six user-specified suppliers according to their described scopes. Supplier pricing and other planning values remain illustrative application demo values, not values from the workbook.

The source catalog is too large to import wholesale. Other product rows and material grades remain available in the workbook but were deliberately not turned into demo SKUs so the seeded catalog and existing BOM/ledger graph stay coherent.
