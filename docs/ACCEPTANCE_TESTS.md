# Malabar Club — acceptance and integrity scenarios

Convert relevant scenarios into executable tests as their modules are built. These describe observable business outcomes; they are not evidence that the application has already passed. Use synthetic fixtures and a dedicated MySQL database. Device tests require actual selected equipment or a documented vendor test environment.

## Membership, authorisation, and entry

| ID | Scenario and required result |
| --- | --- |
| A01 | Cashier requests a manager-only tariff/refund/stock-adjustment action through the UI and directly over HTTP. Both paths deny access without changing money or stock. |
| A02 | Import includes duplicate membership numbers and malformed dates. Preview identifies rows, applies no changes before confirmation, and a repeated import does not duplicate approved records. |
| A03 | Membership expires while a gym plan remains valid. The shared entitlement policy returns the configured base-membership denial; top-up alone does not renew either entitlement. |
| A04 | Register a guest with a club-member sponsor and another with an affiliated-member sponsor. Both valid paths succeed; an invalid sponsor is rejected with a visible reason. |
| A05 | A guest has an unpaid/failed entry-fee attempt. Bar/card-room pass remains inactive. Exactly one confirmed fee creates exactly one appropriately scoped pass. |
| A06 | Refund or cancellation of an unused entry pass revokes it and records the corresponding financial adjustment once. An unrelated valid facility plan is unaffected. |
| A07 | A dependent has no authorised use of the primary member's wallet. Identification or sponsorship alone cannot debit the primary member. |
| A08 | Replace a lost card. The old card is rejected, the new card finds the same member/account, and prior transactions and balances remain intact. |
| A09 | A suspended or expired pass cannot create a new entry entitlement by editing a URL or submitting another person's ID. Sensitive member documents are not publicly accessible. |
| A10 | Cashier or manager tries to grant required guest entry using a zero/100%-discount fee or a stock complimentary action. The initial paid-entry policy denies it; no access pass is silently created. |

## Money, counters, and payment recovery

| ID | Scenario and required result |
| --- | --- |
| F01 | Confirm a fictional INR 1,000 recharge. Prepaid balance rises by 100,000 paise, external collections rise once, backing liability rises, and sales do not rise. |
| F02 | Pay a fictional INR 300 bill using prepaid funds. Balance becomes INR 700, billed sales/tax/ledger components post once, and there is no second INR 300 external receipt. |
| F03 | Two simultaneous MySQL connections each try to spend INR 400 from an INR 500 available balance. At most one succeeds; rejected work leaves no paid bill, entitlement, stock issue or journal side effect. |
| F04 | Retry one recharge/checkout request with the same idempotency key after a timeout. Return the original outcome without duplicating bill, cash receipt or prepaid entry. Reusing the key with a different payload is rejected. |
| F05 | Payment attempt is initiated, cancelled, failed or remains pending. No fulfilment or paid access occurs. A forged success redirect does not change this. |
| F06 | Receive the same signed provider confirmation repeatedly or out of order. Confirm the financial result once and retain a meaningful provider event trail. Reject invalid signatures. |
| F07 | External payment is captured but application posting fails. Recovery finds the unmatched captured amount, resolves it exactly once to posting or approved refund, and does not issue goods before confirmed posting. |
| F08 | Insufficient prepaid funds, including after active reservations, are rejected for every role. An administrator does not gain a hidden credit bypass. |
| F09 | Two counters finish bills concurrently. Unique receipt numbers and source journal references remain collision-free. A reprint keeps the original reference. |
| F10 | If split tenders are enabled, one part is confirmed and another fails. The sale remains unfulfilled and the confirmed portion is tracked for recovery/refund; it cannot disappear or be collected twice. |
| F11 | Refund retries cannot exceed the original net refundable amount or restore a prepaid balance more than once. A financial refund of consumed liquor does not fabricate a physical stock return. |
| F12 | Cashier closes a shift with opening float, recharge, cash sale, digital sale, prepaid sale and cash transfer. Expected till cash excludes prepaid redemption and includes funding/float/transfers correctly. |
| F13 | Gateway settlement is net of fees or delayed. Gross confirmed customer payment, provider clearing, fees and bank payout reconcile without changing the original sale value. |
| F14 | A posted bill/journal is edited through a crafted request or deleted. Application denies it. An authorised correction creates a linked reversal/adjustment and audit entry. |
| F15 | A confirmed top-up is later reversed after some funds were spent. A real reconciliation exception is recorded and further affected use is restricted; no fictitious funding is inserted to hide it. |
| F16 | Generate a member statement for already-settled transactions. No new bill, tax charge or receivable is created. All posted journals balance and prepaid subledger total equals its mapped liability. |

## Inventory and contractor settlement

| ID | Scenario and required result |
| --- | --- |
| I01 | Receive a 750 ml bottle and sell a 30 ml serving. Available quantity decreases by 30 ml, and the issued cost follows the configured costing/rounding policy. |
| I02 | Two counters attempt to sell the same last available serving. At most one fulfilment succeeds; financial recovery handles any externally captured unmatched payment. |
| I03 | Record approved wastage, an unopened return and a refund for a consumed drink. Each has the correct physical quantity/cost and financial treatment; they are not treated identically. |
| I04 | New stock arrives at a different cost. Future issue costs reflect moving average, while historical issue costs remain unchanged and valuation reconciles within explicit rounding rules. |
| I05 | A physical count differs from expected stock. Only authorised adjustment with reason changes stock, and the before/after trail remains visible. |
| C01 | Food bills paid by cash, digital and prepaid all feed their qualifying contractor payable. A general unused prepaid recharge does not. |
| C02 | One bill contains food and bar items with a bill-level discount. Exact line allocation preserves the total; only qualifying food value enters contractor settlement. |
| C03 | Two jobs prepare the same business-date settlement or a user retries. No paid food allocation enters multiple batches. |
| C04 | Contractor transfer fails or is partial. Confirmed amount reduces payable once; unpaid residual remains visible. |
| C05 | A refund occurs after settlement was paid. A linked next-settlement adjustment/recovery appears without modifying the locked paid batch. |
| C06 | Transactions straddle the Asia/Kolkata cutoff. Previous-day eligibility and next-day settlement are correct, repeatable and based on recorded business dates. |

## Rooms, gym, badminton, and reports

| ID | Scenario and required result |
| --- | --- |
| B01 | Two simultaneous requests confirm overlapping dates for one room. Only one confirms. Non-overlapping checkout/check-in boundaries follow the configured half-open interval rule. |
| B02 | A reservation has only a partial deposit. It may reserve availability, but check-in/extension is denied until the required accommodation charge is covered. |
| B03 | Guest, sponsor and payer are different people. The correct reservation, payment allocation, guest record and receipt remain linked without charging the sponsor automatically. |
| B04 | Room is vacant but dirty or blocked for maintenance. It is not presented as check-in ready; maintenance blocks also prevent overlapping bookings. |
| B05 | Two simultaneous bookings request the same badminton slot, including one plan-covered booking. Only one confirms; a valid plan does not bypass capacity. |
| B06 | Monthly plan begins on a month end or annual plan crosses leap day. Validity and renewal boundaries follow the documented calendar rule consistently in booking and access. |
| B07 | Plan-covered member and daily guest use a facility. Charges and entitlements follow their categories; every player is not accidentally charged the full court fee. |
| R01 | Compare daily, weekly, monthly, yearly and custom report drill-downs. With the same filters/date basis, totals reconcile to their source records and journal. |
| R02 | Annual plan is paid upfront. Collections show receipt on the payment date; recognised income follows the approved service-period schedule without scheduler retry duplication. |
| R03 | Department P&L includes consumed inventory costs, correct food mapping, recognised plan income, direct expenses and disclosed shared allocation. Department totals plus unallocated items reconcile to the overall result. |
| R04 | Close a financial period. Ordinary users cannot backdate edits into it; an authorised adjustment follows a recorded controlled process and is reflected in reconciliation. |

## Integrations and release evidence

| ID | Scenario and required result |
| --- | --- |
| X01 | Tally is unavailable while a valid sale is paid. Club posting succeeds, one outbox event remains retryable, and no duplicate financial operation is created. |
| X02 | Tally receives a voucher but acknowledgement is lost. Retry/reconciliation detects the existing external reference and does not duplicate the voucher. |
| X03 | Test-company import rejects a ledger/tax/department mapping. Record the actual error and failed items; do not mark the batch fully accepted from a transport-level success alone. |
| X04 | Access simulator is selected under production configuration. Startup/configuration validation rejects it; no screen labels simulated events as verified hardware access. |
| X05 | On selected hardware, exercise active/expired members and paid/unpaid/revoked guest passes. Record device acknowledgements, observed behaviour and revocation delay separately from application logic tests. |
| X06 | Loss of device/cloud connectivity follows the documented controller policy. No offline cached prepaid balance is spent at multiple counters. Emergency egress is not tied to bill payment. |
| X07 | Print the same receipt on the actual 80 mm printer and an A4 statement. Text, totals and references remain readable; automatic printing is claimed only if the actual bridge/device was tested. |
| X08 | Restore a backup to a clean isolated environment. Verify representative bills and reconcile prepaid, journals, contractor payable and stock against the backup checkpoint. Record measured recovery and possible data-loss window. |
| X09 | Restart queue/connector workers during pending work. Jobs recover without duplicate invoices, prepaid spends, contractor payments or accounting exports. |
| X10 | Run concurrent activity for three counters and five staff users on a representative growing dataset. Measure search/checkout/report timings and investigate lock waits/errors; no response-time claim is made without measurements. |

Release requires passing applicable implemented business cases, documented unresolved integration qualification, tested backup restoration, and reconciliation of real opening data. A device-dependent test remains pending until it actually runs; do not replace its result with a simulator pass.
