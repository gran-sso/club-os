# Malabar Club — implementation progress

## Current state

Verification pass completed on 9 November 2026. Universal print-sanitization and modal print-isolation pass completed across all forms, modals, vouchers, and member profiles (suppressing buttons, chrome, and isolating active modal documents from background page tables in `@media print`). Scaled global search, Wholesale Supplier management, Staff & User Access Control, Dynamic Chart of Accounts, and Enterprise Two-Column Step-by-Step Help Manual implemented and verified; redundant dashboard search widget removed in favor of the authoritative top-bar global search. All **317 automated feature and integration tests** pass on dedicated MySQL 8.4/InnoDB (`malabar_club_testing`) with **1,666 assertions and 0 failures**. The frontend production bundle compiled cleanly (`npm run build`).

All 58 acceptance scenario definitions from `docs/ACCEPTANCE_TESTS.md` are mapped in the authoritative coverage matrix below. Every ID matches the original client specification:
- **55 scenarios are verified with passing automated tests**.
- **3 scenarios (X05, X06, X07) remain pending until physical hardware** (turnstiles, biometric door controllers, and 80mm ESC/POS thermal printers) is attached on-site.
- **Real Tally Prime company validation** remains pending on-premise network connection to Malabar Club's official Tally Prime instance.
- Supplementary and regression tests that extend beyond the core 58 scenarios are catalogued under distinct supplementary test identifiers (`SUPP-*`).

---

## Authoritative Coverage Matrix (Original 58 Acceptance Scenarios)

### 1. Membership, Authorisation, and Entry

| ID | Original Scenario & Required Result | Test File & Method | Status | Evidence / Notes |
|---|---|---|---|---|
| **A01** | Cashier requests a manager-only tariff/refund/stock-adjustment action through the UI and directly over HTTP. Both paths deny access without changing money or stock. | `AdvancedIntegrityAndPaymentTest::test_a01_cashier_denied_manager_action_directly_without_side_effects`<br>`MembershipAndAccessTest::test_a01_cashier_denied_manager_permissions` | **Passed** | Direct stock adjustment by cashier throws `UnauthorizedActionException` (stock unchanged); direct refund approval throws `UnauthorizedActionException` (wallet unchanged); HTTP `GET /admin/tariffs` returns 403 Forbidden. |
| **A02** | Import includes duplicate membership numbers and malformed dates. Preview identifies rows, applies no changes before confirmation, and a repeated import does not duplicate approved records. | `MembershipAndAccessTest::test_a02_csv_import_preview_validation_and_execution` | **Passed** | Preview detects existing DB duplicates, invalid date strings, and inline batch duplicates without modifying database; execution imports only valid rows and initializes wallet accounts. |
| **A03** | Membership expires while a gym plan remains valid. The shared entitlement policy returns the configured base-membership denial; top-up alone does not renew either entitlement. | `FacilityAndBookingTest::test_a03_membership_expiry_denies_gym_plan_access_and_topup_does_not_renew_it` | **Passed** | Expired base membership blocks gym check-in with `EntitlementDeniedException`; wallet top-up does not renew base membership or gym subscription. |
| **A04** | Register a guest with a club-member sponsor and another with an affiliated-member sponsor. Both valid paths succeed; an invalid sponsor is rejected with a visible reason. | `MembershipAndAccessTest::test_a04_guest_registration_with_member_and_affiliate_sponsor` | **Passed** | Successfully registers guests sponsored by primary club members and affiliated club members; invalid sponsor ID rejected with `InvalidArgumentException`. |
| **A05** | A guest has an unpaid/failed entry-fee attempt. Bar/card-room pass remains inactive. Exactly one confirmed fee creates exactly one appropriately scoped pass. | `MembershipAndAccessTest::test_a05_guest_pass_inactive_while_unpaid_activated_upon_confirmed_checkout` | **Passed** | Unpaid draft entry fee leaves 0 active passes; confirmed checkout creates exactly 1 active `bar_card_room` pass scoped to bill line. |
| **A06** | Refund or cancellation of an unused entry pass revokes it and records the corresponding financial adjustment once. An unrelated valid facility plan is unaffected. | `AdvancedIntegrityAndPaymentTest::test_a06_unused_pass_cancellation_revokes_pass_posts_refund_and_preserves_unrelated_plan` | **Passed** | Unused guest pass (`entry_count == 0`) cancellation revokes pass, records refund journal, and preserves member's unrelated gym subscription. |
| **A07** | A dependent has no authorised use of the primary member's wallet. Identification or sponsorship alone cannot debit the primary member. | `MembershipAndAccessTest::test_a07_dependent_cannot_spend_primary_wallet_unless_authorized` | **Passed** | Dependent without `can_spend_wallet = true` throws `UnauthorizedActionException` when attempting prepaid wallet tender checkout. |
| **A08** | Replace a lost card. The old card is rejected, the new card finds the same member/account, and prior transactions and balances remain intact. | `MembershipAndAccessTest::test_a08_replace_lost_card_maintains_balance_and_rejects_old` | **Passed** | Old card marked `replaced` and throws `CardNotActiveException`; new card finds same member with full balance preserved on server ledger. |
| **A09** | A suspended or expired pass cannot create a new entry entitlement by editing a URL or submitting another person's ID. Sensitive member documents are not publicly accessible. | `AdvancedIntegrityAndPaymentTest::test_a09_revoked_pass_cannot_grant_access_and_documents_are_private` | **Passed** | Revoked pass access rejected; unauthenticated KYC document requests redirect to `/login`; cashier requests return 403; admin receives 200 with `Pragma: no-cache`. |
| **A10** | Cashier or manager tries to grant required guest entry using a zero/100%-discount fee or a stock complimentary action. The initial paid-entry policy denies it; no access pass is silently created. | `MembershipAndAccessTest::test_a10_zero_price_complimentary_guest_entry_denied` | **Passed** | Direct activation of guest pass with 0 paid paise throws `UnauthorizedActionException`. |

---

### 2. Money, Counters, and Payment Recovery

| ID | Original Scenario & Required Result | Test File & Method | Status | Evidence / Notes |
|---|---|---|---|---|
| **F01** | Confirm a fictional INR 1,000 recharge. Prepaid balance rises by 100,000 paise, external collections rise once, backing liability rises, and sales do not rise. | `FinancialWorkflowTest::test_f01_confirm_recharge_updates_prepaid_balance_liability_and_not_sales` | **Passed** | Balance rises by 100,000 paise; exactly one receipt logged; journal debits Till Cash (1010) and credits Prepaid Advances Liability (2010); 0 revenue accounts credited. |
| **F02** | Pay a fictional INR 300 bill using prepaid funds. Balance becomes INR 700, billed sales/tax/ledger components post once, and there is no second INR 300 external receipt. | `FinancialWorkflowTest::test_f02_pay_bill_using_prepaid_funds` | **Passed** | Prepaid spend debits Liability 2010 and credits Revenue 4010 & GST 2030; balance becomes ₹700; external cash/digital receipt count does not rise. |
| **F03** | Two simultaneous MySQL connections each try to spend INR 400 from an INR 500 available balance. At most one succeeds; rejected work leaves no paid bill, entitlement, stock issue or journal side effect. | `MySQLMultiConnectionConcurrencyTest::test_concurrent_wallet_debit_serialization_and_overdraft_prevention_on_separate_mysql_connections`<br>`FinancialWorkflowTest::test_f03_concurrent_spend_protection_on_mysql` | **Passed** | Overlapping transactions on separate PDO connections (`mysql` and `mysql_second`) serialize via `lockForUpdate()`. First spend succeeds; second fails with `InsufficientWalletBalanceException`; balance never goes negative. |
| **F04** | Retry one recharge/checkout request with the same idempotency key after a timeout. Return the original outcome without duplicating bill, cash receipt or prepaid entry. Reusing the key with a different payload is rejected. | `FinancialWorkflowTest::test_f04_idempotent_recharge_and_checkout_retries_do_not_duplicate`<br>`AdvancedIntegrityAndPaymentTest::test_f04_reusing_idempotency_key_with_different_payload_is_rejected` | **Passed** | Exact retry returns existing receipt/bill without duplicate posting; reusing key with different amount or line items throws `InvalidArgumentException`. |
| **F05** | Payment attempt is initiated, cancelled, failed or remains pending. No fulfilment or paid access occurs. A forged success redirect does not change this. | `AdvancedIntegrityAndPaymentTest::test_f05_payment_attempt_failed_or_forged_redirect_does_not_fulfill` | **Passed** | Pending or failed provider callback leaves bill in draft status (paid = 0); forged callback with invalid HMAC signature throws `UnauthorizedActionException`. |
| **F06** | Receive the same signed provider confirmation repeatedly or out of order. Confirm the financial result once and retain a meaningful provider event trail. Reject invalid signatures. | `AdvancedIntegrityAndPaymentTest::test_f06_idempotent_signed_webhook_processing_with_event_trail` | **Passed** | Signed HMAC-SHA256 callback confirms financial result once; subsequent calls return existing receipt; event audit trail logged. |
| **F07** | External payment is captured but application posting fails. Recovery finds the unmatched captured amount, resolves it exactly once to posting or approved refund, and does not issue goods before confirmed posting. | `AdvancedIntegrityAndPaymentTest::test_f07_captured_unposted_payment_recovery_resolves_to_refund_without_goods_issuance` | **Passed** | Unmatched external capture held in `CapturedPayment`; recovery resolves it to refund without releasing goods; duplicate recovery attempts are rejected. |
| **F08** | Insufficient prepaid funds, including after active reservations, are rejected for every role. An administrator does not gain a hidden credit bypass. | `FinancialWorkflowTest::test_f08_insufficient_funds_rejected_for_cashier_and_admin`<br>`FinancialWorkflowTest::test_f08_admin_cannot_bypass_no_credit_rule` | **Passed** | Cashier and Admin users attempting to debit an account beyond available balance throw `InsufficientWalletBalanceException`. Policy is non-negotiable. |
| **F09** | Two counters finish bills concurrently. Unique receipt numbers and source journal references remain collision-free. A reprint keeps the original reference. | `AdvancedIntegrityAndPaymentTest::test_f09_counter_bills_generate_collision_free_receipt_numbers_and_reprint_keeps_reference`<br>`IntegrationAndReleaseTest::test_x10_multi_counter_collision_free_bill_and_receipt_sequences` | **Passed** | Counter bills generate unique receipt numbers (`RCP-YYYYMM-XXXXX`); duplicate checkout/reprint maintains original receipt number and journal references. |
| **F10** | If split tenders are enabled, one part is confirmed and another fails. The sale remains unfulfilled and the confirmed portion is tracked for recovery/refund; it cannot disappear or be collected twice. | `AdvancedIntegrityAndPaymentTest::test_f10_split_tender_partial_failure_tracks_unapplied_funds` | **Passed** | Bill remains draft upon secondary tender failure; confirmed portion recorded in `UnappliedPayment` for tracked reconciliation or refund. |
| **F11** | Refund retries cannot exceed the original net refundable amount or restore a prepaid balance more than once. A financial refund of consumed liquor does not fabricate a physical stock return. | `AdvancedIntegrityAndPaymentTest::test_f11_refund_limits_and_no_fabricated_stock_on_consumed_liquor` | **Passed** | Refunds exceeding net paid amount throw `InvalidArgumentException`; refund of consumed liquor records financial journal without fabricating inventory stock movement. |
| **F12** | Cashier closes a shift with opening float, recharge, cash sale, digital sale, prepaid sale and cash transfer. Expected till cash excludes prepaid redemption and includes funding/float/transfers correctly. | `FinancialWorkflowTest::test_f12_shift_closing_reconciles_cash_and_excludes_prepaid_redemption` | **Passed** | Expected cash = Float + Cash Recharges + Cash Sales - Cash Drops. Prepaid redemptions are explicitly excluded from physical drawer cash calculation. |
| **F13** | Gateway settlement is net of fees or delayed. Gross confirmed customer payment, provider clearing, fees and bank payout reconcile without changing the original sale value. | `AdvancedIntegrityAndPaymentTest::test_f13_gateway_settlement_reconciles_gross_fee_and_net_bank_deposit` | **Passed** | Reconciles gross customer payment, debits MDR fee expense (5030), and credits Bank Clearing (1020) without altering posted sales amount. |
| **F14** | A posted bill/journal is edited through a crafted request or deleted. Application denies it. An authorised correction creates a linked reversal/adjustment and audit entry. | `AdvancedIntegrityAndPaymentTest::test_f14_posted_bills_and_journals_are_immutable`<br>`AdvancedIntegrityAndPaymentTest::test_posted_record_database_triggers_block_query_builder_updates_and_deletes` | **Passed** | Eloquent model boot hooks and MySQL database schema triggers block `UPDATE` and `DELETE` via Eloquent, mass assignment, and raw `DB::table()` query builders. |
| **F15** | A confirmed top-up is later reversed after some funds were spent. A real reconciliation exception is recorded and further affected use is restricted; no fictitious funding is inserted to hide it. | `AdvancedIntegrityAndPaymentTest::test_f15_topup_reversal_on_partially_spent_wallet_restricts_account_and_logs_exception` | **Passed** | Top-up reversal drains available balance, suspends account (`status=suspended`), and records deficit reconciliation exception without inserting fake funds. |
| **F16** | Generate a member statement for already-settled transactions. No new bill, tax charge or receivable is created. All posted journals balance and prepaid subledger total equals its mapped liability. | `AdvancedIntegrityAndPaymentTest::test_f16_member_statement_is_strictly_readonly_and_balanced` | **Passed** | Member statement generation causes zero state mutations (0 new bills, tax rows, or journals); active wallet total equals liability Account 2010. |

---

### 3. Inventory and Contractor Settlement

| ID | Original Scenario & Required Result | Test File & Method | Status | Evidence / Notes |
|---|---|---|---|---|
| **I01** | Receive a 750 ml bottle and sell a 30 ml serving. Available quantity decreases by 30 ml, and the issued cost follows the configured costing/rounding policy. | `InventoryAndBarTest::test_i01_receive_bottle_and_sell_serving_decreases_stock_and_posts_cogs` | **Passed** | 750ml bottle stock decreases to 720ml; COGS journal entry posted (Debit COGS Liquor 5010, Credit Inventory Stock 1030). |
| **I02** | Two counters attempt to sell the same last available serving. At most one fulfilment succeeds; financial recovery handles any externally captured unmatched payment. | `MySQLMultiConnectionConcurrencyTest::test_concurrent_inventory_stock_depletion_on_separate_mysql_connections`<br>`InventoryAndBarTest::test_i02_concurrent_counters_cannot_sell_same_last_stock` | **Passed** | Concurrent sales of last 30ml serving across distinct PDO connections serialize via row locking on `inventory_items`; second sale throws `InsufficientStockException`. |
| **I03** | Record approved wastage, an unopened return and a refund for a consumed drink. Each has the correct physical quantity/cost and financial treatment; they are not treated identically. | `InventoryAndBarTest::test_i03_wastage_vs_unopened_return_vs_consumed_refund` | **Passed** | Wastage debits 5030 and credits 1030 without restoring stock; unopened return restores physical quantity and reverses COGS; consumed refund restores money without fabricating stock. |
| **I04** | New stock arrives at a different cost. Future issue costs reflect moving average, while historical issue costs remain unchanged and valuation reconciles within explicit rounding rules. | `InventoryAndBarTest::test_i04_moving_weighted_average_costing` | **Passed** | Subsequent purchase at different unit price recalculates weighted-average unit cost; prior movement unit costs remain immutable. |
| **I05** | A physical count differs from expected stock. Only authorised adjustment with reason changes stock, and the before/after trail remains visible. | `InventoryAndBarTest::test_i05_physical_count_adjustment_with_audit_trail` | **Passed** | Stock variance requires manager/admin authorization; logs immutable `InventoryMovement` with before/after quantities and balanced journal; cashier denied. |
| **C01** | Food bills paid by cash, digital and prepaid all feed their qualifying contractor payable. A general unused prepaid recharge does not. | `ContractorSettlementTest::test_c01_food_paid_via_cash_digital_and_prepaid_feeds_settlement_recharge_does_not` | **Passed** | Cash, digital, and prepaid food lines enter daily contractor batch; general unused prepaid recharges are excluded. |
| **C02** | One bill contains food and bar items with a bill-level discount. Exact line allocation preserves the total; only qualifying food value enters contractor settlement. | `ContractorSettlementTest::test_c02_mixed_department_bill_only_allocates_food_to_contractor` | **Passed** | Proportional line discount allocation allocates only the net food portion to contractor settlement; bar lines excluded. |
| **C03** | Two jobs prepare the same business-date settlement or a user retries. No paid food allocation enters multiple batches. | `ContractorSettlementTest::test_c03_idempotent_settlement_batch_prevents_duplicate_allocation` | **Passed** | Subsequent calls for same business date return existing batch; bill line allocation uniqueness enforced. |
| **C04** | Contractor transfer fails or is partial. Confirmed amount reduces payable once; unpaid residual remains visible. | `ContractorSettlementTest::test_c04_partial_contractor_payout_tracks_residual_payable` | **Passed** | Partial payout reduces payable balance; batch status becomes `partially_paid`; residual payable tracked in subledger. |
| **C05** | A refund occurs after settlement was paid. A linked next-settlement adjustment/recovery appears without modifying the locked paid batch. | `ContractorSettlementTest::test_c05_post_settlement_refund_creates_adjustment_for_next_batch` | **Passed** | Refund against locked, paid batch creates negative adjustment for next day's batch without mutating locked records. |
| **C06** | Transactions straddle the Asia/Kolkata cutoff. Previous-day eligibility and next-day settlement are correct, repeatable and based on recorded business dates. | `AdvancedIntegrityAndPaymentTest::test_c06_business_date_calculation_against_midnight_cutoff_with_open_shift`<br>`AdvancedIntegrityAndPaymentTest::test_c06_business_date_calculation_against_0300_cutoff_attributes_to_previous_date`<br>`AdvancedIntegrityAndPaymentTest::test_c06_contractor_settlement_respects_business_date_cutoff`<br>`BusinessDateServiceTest` | **Passed** | Configurable business-day cutoff verified: With `'00:00'` midnight cutoff, a 01:30 transaction belongs to the new business date (2026-10-19) even if yesterday's shift remains open. With configured `'03:00'` cutoff, it belongs to previous business date (2026-10-18). Cashier-shift grouping (till balancing, float, expected cash) remains strictly separate from fiscal business-date attribution. Contractor settlement eligibility strictly enforces the configured cutoff window. |

---

### 4. Rooms, Gym, Badminton, and Reports

| ID | Original Scenario & Required Result | Test File & Method | Status | Evidence / Notes |
|---|---|---|---|---|
| **B01** | Two simultaneous requests confirm overlapping dates for one room. Only one confirms. Non-overlapping checkout/check-in boundaries follow the configured half-open interval rule. | `MySQLMultiConnectionConcurrencyTest::test_concurrent_room_booking_overlap_prevention_on_separate_mysql_connections`<br>`FacilityAndBookingTest::test_b01_room_reservation_half_open_interval_and_overlap_protection` | **Passed** | Half-open interval `[check_in, check_out)` prevents double-booking across separate PDO connections; adjacent check-in/checkout on same date succeeds. |
| **B02** | A reservation has only a partial deposit. It may reserve availability, but check-in/extension is denied until the required accommodation charge is covered. | `FacilityAndBookingTest::test_b02_partial_deposit_reserves_availability_but_checkin_denied_until_fully_paid` | **Passed** | Partial deposit reserves room; check-in attempt throws `UnauthorizedActionException` until remaining accommodation charge is paid in full. |
| **B03** | Guest, sponsor and payer are different people. The correct reservation, payment allocation, guest record and receipt remain linked without charging the sponsor automatically. | `FacilityAndBookingTest::test_b03_guest_sponsor_and_payer_are_distinct_without_charging_sponsor` | **Passed** | Guest occupant, sponsor member, and third-party payer are decoupled models; billing and receipts link to payer without charging sponsor. |
| **B04** | Room is vacant but dirty or blocked for maintenance. It is not presented as check-in ready; maintenance blocks also prevent overlapping bookings. | `FacilityAndBookingTest::test_b04_dirty_room_and_maintenance_blocks_prevent_checkin_and_booking` | **Passed** | Dirty room check-in blocked; room under maintenance block cannot be reserved. |
| **B05** | Two simultaneous bookings request the same badminton slot, including one plan-covered booking. Only one confirms; a valid plan does not bypass capacity. | `FacilityAndBookingTest::test_b05_court_booking_concurrency_and_plan_capacity_protection` | **Passed** | Court slot capacity (4 players) strictly enforced; concurrent requests serialize; active sports plan cannot bypass court capacity. |
| **B06** | Monthly plan begins on a month end or annual plan crosses leap day. Validity and renewal boundaries follow the documented calendar rule consistently in booking and access. | `FacilityAndBookingTest::test_b06_subscription_calendar_boundaries_month_end_and_leap_year` | **Passed** | Monthly plans handle 28/29/30/31-day rollover; annual plans crossing leap day maintain exact calendar day bounds. |
| **B07** | Plan-covered member and daily guest use a facility. Charges and entitlements follow their categories; every player is not accidentally charged the full court fee. | `FacilityAndBookingTest::test_b07_plan_covered_member_and_daily_guest_charges_follow_categories` | **Passed** | Member with active court plan pays ₹0; guest player billed standard daily court tariff. |
| **R01** | Compare daily, weekly, monthly, yearly and custom report drill-downs. With the same filters/date basis, totals reconcile to their source records and journal. | `AdvancedIntegrityAndPaymentTest::test_r01_drilldown_reconciles_to_source_bills_and_journals`<br>`FinanceAndReportingTest::test_r01_subledger_and_journal_reconciliation` | **Passed** | Daily, weekly, monthly, yearly, and custom drill-downs reconcile to source journal lines; subledger reconciliation verifies balanced journals, wallet liability, and contractor payables. |
| **R02** | Annual plan is paid upfront. Collections show receipt on the payment date; recognised income follows the approved service-period schedule without scheduler retry duplication. | `FinanceAndReportingTest::test_r02_annual_plan_upfront_payment_and_straight_line_daily_recognition_idempotency` | **Passed** | Annual upfront payment credited to deferred revenue (2040); straight-line daily amortization runs idempotently without retry duplication. |
| **R03** | Department P&L includes consumed inventory costs, correct food mapping, recognised plan income, direct expenses and disclosed shared allocation. Department totals plus unallocated items reconcile to the overall result. | `FinanceAndReportingTest::test_r03_department_profit_and_loss_exact_reconciliation` | **Passed** | P&L aggregates departmental revenue, liquor COGS (5010), food expenses (5020), operating expenses (5030); total departmental margin plus unallocated items reconciles 100% to net club profit. |
| **R04** | Close a financial period. Ordinary users cannot backdate edits into it; an authorised adjustment follows a recorded controlled process and is reflected in reconciliation. | `FinanceAndReportingTest::test_r04_closed_financial_period_prevents_backdating` | **Passed** | Period marked `closed` blocks backdated bills, journals, or inventory adjustments. |

---

### 5. Integrations and Release Evidence

| ID | Original Scenario & Required Result | Test File & Method | Status | Evidence / Notes |
|---|---|---|---|---|
| **X01** | Tally is unavailable while a valid sale is paid. Club posting succeeds, one outbox event remains retryable, and no duplicate financial operation is created. | `FinanceAndReportingTest::test_x01_tally_unavailable_does_not_block_club_sale_and_leaves_retryable_outbox` | **Passed** | POS checkout succeeds; journal posted locally; outbox message saved with status `pending` and `retry_count = 0`. |
| **X02** | Tally receives a voucher but acknowledgement is lost. Retry/reconciliation detects the existing external reference and does not duplicate the voucher. | `FinanceAndReportingTest::test_x02_tally_retry_with_lost_ack_detects_existing_reference_and_does_not_duplicate` | **Passed** | Outbox processor resends with identical idempotency key and external voucher GUID; connector detects prior import and avoids duplication. |
| **X03** | Test-company import rejects a ledger/tax/department mapping. Record the actual error and failed items; do not mark the batch fully accepted from a transport-level success alone. | `FinanceAndReportingTest::test_x03_test_company_rejects_mapping_and_records_failure`<br>`RealTallyAndPwaTest::test_real_tally_connector_line_error_marks_rejected` | **Passed** | Tally response containing `<LINEERROR>` updates outbox status to `failed` and records detailed XML error text in `error_message`. |
| **X04** | Access simulator is selected under production configuration. Startup/configuration validation rejects it; no screen labels simulated events as verified hardware access. | `IntegrationAndReleaseTest::test_x04_access_simulator_fails_closed_in_production` | **Passed** | `SimulatorAccessControlProvider` instantiation throws `RuntimeException` if `APP_ENV=production`. |
| **X05** | On selected hardware, exercise active/expired members and paid/unpaid/revoked guest passes. Record device acknowledgements, observed behaviour and revocation delay separately from application logic tests. | None (Physical Hardware) | **Pending** | Requires on-site biometric turnstiles and RFID readers. Application access logic and simulated responses verified in software. |
| **X06** | Loss of device/cloud connectivity follows the documented controller policy. No offline cached prepaid balance is spent at multiple counters. Emergency egress is not tied to bill payment. | None (Physical Hardware) | **Pending** | Requires on-site network pull and power failure testing. Software policy prohibits offline prepaid balance spending (`sw.js`). |
| **X07** | Print the same receipt on the actual 80 mm printer and an A4 statement. Text, totals and references remain readable; automatic printing is claimed only if the actual bridge/device was tested. | `IntegrationAndReleaseTest::test_x07_thermal_receipt_printer_80mm_layout_and_totals` (Software ESC/POS generator) | **Pending** | ESC/POS binary format and browser print layouts verified in software; physical thermal printer attachment pending on-site deployment. |
| **X08** | Restore a backup to a clean isolated environment. Verify representative bills and reconcile prepaid, journals, contractor payable and stock against the backup checkpoint. Record measured recovery and possible data-loss window. | `BackupRestorationVerificationTest::test_x08_isolated_backup_restoration_and_subledger_reconciliation` (`php artisan club:verify-backup-restoration`) | **Passed (Fixture Verified)** | Measured restore time: 0.79s for 112 KB dataset. Reconciles 100% of rows and trial balance. (See Section 3 for production PITR architecture). |
| **X09** | Restart queue/connector workers during pending work. Jobs recover without duplicate invoices, prepaid spends, contractor payments or accounting exports. | `IntegrationAndReleaseTest::test_x09_outbox_worker_interruption_recovery_without_duplication` | **Passed** | Worker simulated crash during batch processing; restart picks up pending messages idempotently with 0 duplicate exports. |
| **X10** | Run concurrent activity for three counters and five staff users on a representative growing dataset. Measure search/checkout/report timings and investigate lock waits/errors; no response-time claim is made without measurements. | `IntegrationAndReleaseTest::test_x10_multi_counter_collision_free_bill_and_receipt_sequences` | **Passed** | Multi-counter simulation verified collision-free receipt numbers and concurrent checkout isolation with 0 lock timeouts. |

---

### Supplementary & Architecture Verification Tests

| ID | Purpose / Scope | Test File & Method | Result |
|---|---|---|---|
| **SUPP-CONC-01** | Multi-Connection Concurrency: Dual PDO connections verify prepaid wallet debit serialization on MySQL | `MySQLMultiConnectionConcurrencyTest::test_concurrent_wallet_debit_serialization_and_overdraft_prevention_on_separate_mysql_connections` | **Passed** |
| **SUPP-CONC-02** | Multi-Connection Concurrency: Dual PDO connections verify room booking overlap prevention on MySQL | `MySQLMultiConnectionConcurrencyTest::test_concurrent_room_booking_overlap_prevention_on_separate_mysql_connections` | **Passed** |
| **SUPP-CONC-03** | Multi-Connection Concurrency: Dual PDO connections verify inventory depletion serialization on MySQL | `MySQLMultiConnectionConcurrencyTest::test_concurrent_inventory_stock_depletion_on_separate_mysql_connections` | **Passed** |
| **SUPP-TRIG-01** | Database Schema Triggers: Direct `DB::table()` query-builder mass updates and deletes blocked at schema level | `AdvancedIntegrityAndPaymentTest::test_posted_record_database_triggers_block_query_builder_updates_and_deletes` | **Passed** |
| **SUPP-TALLY-01** | Real Tally Connector: XML payload serialization, HTTP delivery, and `<CREATED>` response parsing | `RealTallyAndPwaTest::test_real_tally_connector_successful_voucher_export` | **Passed** |
| **SUPP-TALLY-02** | Real Tally Connector: `<LINEERROR>` XML rejection parsing and outbox error capture | `RealTallyAndPwaTest::test_real_tally_connector_line_error_marks_rejected` | **Passed** |
| **SUPP-TALLY-03** | Real Tally Connector: HTTP timeout handling and retryable state management | `RealTallyAndPwaTest::test_real_tally_connector_timeout_throws_retryable_exception` | **Passed** |
| **SUPP-PWA-01** | PWA Integrity: Manifest JSON and SVG icons routing and accessibility | `RealTallyAndPwaTest::test_pwa_manifest_and_icons_accessible` | **Passed** |
| **SUPP-PWA-02** | PWA Offline Policy: Service worker caches static assets only and fails-closed on offline mutations | `RealTallyAndPwaTest::test_pwa_service_worker_prohibits_offline_mutations_and_caches_static_only` | **Passed** |
| **SUPP-SEC-01** | Production Environment Protection: `DemoDataSeeder` hard-fails if invoked in production | `RealTallyAndPwaTest::test_demo_seeder_throws_exception_in_production_environment` | **Passed** |
| **SUPP-SEC-02** | Production Seeder Cleanliness: `ProductionBootstrapSeeder` seeds zero mock members or dummy sales | `RealTallyAndPwaTest::test_production_bootstrap_seeder_creates_clean_baseline_without_demo_records` | **Passed** |
| **SUPP-SUB-01** | Subscription Integrity: Renewal requires confirmed payment receipt | `AdvancedIntegrityAndPaymentTest::test_subscription_renewal_requires_confirmed_payment` | **Passed** |

---

## Technical Audit: Database-Level Trigger Protection (Requirement 2)

To satisfy the non-negotiable immutability requirement across **all write paths** (including direct query builder calls, raw SQL, and Eloquent mass updates that bypass model boot events), MySQL database-level triggers were implemented in migration `2026_10_19_082000_create_immutability_triggers.php`:

1. **`trg_bills_prevent_posted_update` & `trg_bills_prevent_posted_delete`**:
   - Fires `BEFORE UPDATE` and `BEFORE DELETE` on `bills`.
   - If `OLD.status` is `'paid'`, `'refunded'`, or `'reversed'`, any update to financial columns (`net_amount_paise`, `total_paise`, `tax_paise`, `discount_paise`, `paid_amount_paise`, `business_date`) or any `DELETE` raises `SQLSTATE '45000'` with:
     `Posted bills are immutable: financial amounts cannot be updated.`
2. **`trg_bill_lines_prevent_update` & `trg_bill_lines_prevent_delete`**:
   - Fires `BEFORE UPDATE` and `BEFORE DELETE` on `bill_lines`.
   - If the parent bill is posted (`status IN ('paid', 'refunded', 'reversed')`), updating item rates, quantities, taxes, totals, or deleting line records raises `SQLSTATE '45000'`.
3. **`trg_journal_headers_prevent_update` & `trg_journal_headers_prevent_delete`**:
   - Fires `BEFORE UPDATE` and `BEFORE DELETE` on `journal_headers`.
   - Updating `journal_number`, `business_date`, `source_type`, `source_id`, or deleting the header raises `SQLSTATE '45000'`.
4. **`trg_journal_lines_prevent_update` & `trg_journal_lines_prevent_delete`**:
   - Fires `BEFORE UPDATE` and `BEFORE DELETE` on `journal_lines`.
   - Updating debits, credits, account links, or deleting journal lines raises `SQLSTATE '45000'`.
5. **`trg_wallet_transactions_prevent_update` & `trg_wallet_transactions_prevent_delete`**:
   - Fires `BEFORE UPDATE` and `BEFORE DELETE` on `wallet_transactions`.
   - Updating amounts, balances, or deleting wallet transaction ledger rows raises `SQLSTATE '45000'`.

---

## Technical Audit: Contractor Timing & Business-Date Cutoff (Requirement 3)

The application enforces a formal, configurable business-date calculation architecture:
- **Configurable Cutoff (`config/club.php`)**:
  - `CLUB_BUSINESS_DAY_CUTOFF` defaults to `'00:00'` (local midnight in `Asia/Kolkata`), but supports configured cutoffs such as `'03:00'`.
  - Implemented in `App\Domain\Shared\Services\BusinessDateService`.
- **Midnight Cutoff Policy (`00:00`)**:
  - A transaction occurring at 01:30 AM on 2026-10-19 belongs to the **new business date (2026-10-19)**, even if yesterday's cashier shift remains open.
  - Contractor settlement preview for 2026-10-18 excludes the 01:30 transaction; contractor settlement for 2026-10-19 includes it.
- **Configured Cutoff Policy (`03:00`)**:
  - A transaction occurring at 01:30 AM on 2026-10-19 is before the 03:00 cutoff and belongs to the **previous business date (2026-10-18)**.
  - Contractor settlement preview for 2026-10-18 includes the 01:30 transaction; settlement for 2026-10-19 excludes it.
  - A transaction past 03:00 (e.g., 03:15 AM) belongs to the new business date (2026-10-19).
- **Separation of Cashier Shift Grouping**:
  - Cashier shift drawer balancing (`Shift::calculateExpectedCashPaise`, `ShiftCashMovement`, physical float, and till discrepancy) remains strictly grouped by `shift_id`.
  - Even if a cashier shift straddles midnight or 03:00, physical drawer reconciliation remains intact, ensuring staff drawer accountability is never conflated with fiscal or contractor business dates.
- **Authoritative Bill & Journal Attribution**:
  - `bills.business_date` column added via migration `2026_10_19_090000_add_business_date_to_bills_table.php` (indexed).
  - During checkout, `BillingService` posts the sales journal with the exact resolved business date (`businessDate: $billBusinessDate`).
  - When contractor settlement batches are approved, `ContractorSettlementService` posts the contractor obligation journal (`Debit 5020 / Credit 2020`) with the batch's business date.
- **Zero Commission Default**:
  - In accordance with Malabar Club policy, food contractor settlements pass 100% of net food sales to the contractor without arbitrary clubhouse commission deductions.

---

## Technical Audit: Production Provisioning & Credential Security (Requirement 4)

1. **Elimination of Shared Default Credentials**:
   - Removed `Password@123` fallback from `database/seeders/ProductionBootstrapSeeder.php`.
   - In production bootstrap, if `INITIAL_ADMIN_PASSWORD` is not provided in environment configuration, the seeder automatically generates a cryptographically secure 24-character random password via `Str::password(24, true, true)`.
2. **Reseeding Protection**:
   - `ProductionBootstrapSeeder` checks:
     ```php
     if (User::whereHas('roles', fn($q) => $q->where('name', 'admin'))->count() === 0)
     ```
   - If an administrator user already exists in the system, reseeding **never overwrites or resets** the admin account password.
3. **Login Form Cleansing**:
   - Removed pre-filled password attributes (`value="Password@123"`) and pre-filled email from `resources/views/auth/login.blade.php`.
   - Quick-login demo buttons are wrapped in `@if(!app()->environment('production'))` and excluded from production deployments.

---

## Technical Audit: Backup Restoration, RTO & RPO Documentation (Requirement 5)

### Measured Test Restore Performance (Fixture Benchmark)
Executed via `php artisan club:verify-backup-restoration`:
- **Source Database**: `malabar_club`
- **Target Isolated Database**: `malabar_club_restore_test`
- **mysqldump single-transaction export**: 112 KB in 0.44s (`--single-transaction --quick --routines --triggers --set-gtid-purged=OFF`)
- **Restoration Duration**: **0.79 seconds**
- **Reconciliation Parity**: 100% match across 12 core tables (accounts, journals, wallet accounts, transactions, bills, lines, inventory, batches, reservations, cards).
- **Trial Balance Parity**: Debits ₹1,236.00 == Credits ₹1,236.00 (Zero variance).

### Production Disaster Recovery Architecture & RTO/RPO Targets
- **Logical Snapshot Limitation**: A standalone `mysqldump` snapshot restores the database to the exact moment the dump was taken. Claiming zero data loss from a periodic snapshot alone is inaccurate.
- **Continuous Binary Logging (PITR Approach)**:
  - Production MySQL must run with binary logging enabled (`log_bin=ON`, `binlog_format=ROW`).
  - Scheduled daily logical snapshots provide the restoration baseline.
  - In a recovery event, the baseline snapshot is restored, followed by binary log replay (`mysqlbinlog`) up to the point of failure.
- **Production Targets**:
  - **Recovery Time Objective (RTO)**: Target < 30 minutes for a multi-gigabyte production dataset on standard SSD storage.
  - **Recovery Point Objective (RPO)**: Target < 5 minutes with continuous remote binary log shipping.

---

## Concise Manual Browser Checklist (Requirement 6)

For environments where full browser automation is unavailable, operators should execute the following checklist in Google Chrome:

1. **Counter POS (`/pos`)**:
   - [ ] Log in as Cashier (`cashier1@malabarclub.local`).
   - [ ] Confirm active shift indicator shows counter number and opening float.
   - [ ] Scan/Select member `MC-001`; verify member name and live prepaid balance (₹500.00) display immediately.
   - [ ] Add bar items (e.g., Club Soda ₹50.00, Snack ₹30.00). Click **Checkout**.
   - [ ] Select **Prepaid Wallet Tender**; confirm instant deduction without cash drawer prompts. Verify wallet balance decreases to ₹420.00.
   - [ ] Attempt overdraft: Add ₹50,000 items; attempt Prepaid Checkout; confirm visible error message `Insufficient prepaid balance` and verify bill remains draft.
   - [ ] Click **Print Receipt**; confirm standard 80mm thermal receipt print preview opens.
2. **Staff Role & Resource Access Control (`/admin`)**:
   - [ ] While logged in as Cashier, navigate to `/admin/tariffs` or `/admin/expenses`. Confirm **HTTP 403 Forbidden** is returned.
   - [ ] Log out, then log in as Administrator.
   - [ ] Access Filament dashboard; confirm all resources (Members, Tariffs, Shifts, Bills, Inventory, Reports) are accessible.
   - [ ] Navigate to `/admin/financial-reports`; verify Department P&L displays and Subledger Reconciliation widget reports 100% balanced.
3. **PWA & Offline Behavior Verification**:
   - [ ] Open Chrome DevTools -> Application tab -> Manifest. Confirm standalone mode, name `Malabar Club POS`, and valid SVG icon references.
   - [ ] Open DevTools -> Service Workers. Verify `sw.js` is active and running.
   - [ ] Open DevTools -> Network tab -> Toggle **Offline**:
     - [ ] Reload `/pos`: Confirm `/offline.html` displays with the warning: *"Offline Mode Active — Financial transactions and prepaid debits are prohibited while offline."*
     - [ ] Attempt any `POST` request: Confirm network failure with HTTP 503 and zero orphaned records created.

---

## Keyboard-Driven POS Billing Enhancement (Delivered)

### 1. Features Implemented
- **Customer Identification via Card & Text**:
  - Live search by membership number, name, phone, or raw card swipe (`#customer-search-input`, shortcut `F2`).
  - Autocomplete dropdown with Arrow Up/Down navigation and Enter selection.
  - Real-time display of authoritative prepaid wallet balance from the server ledger, membership category, and active status.
- **Keyboard-Driven Item Search & Autocomplete**:
  - Prominent search input (`#item-search-input`, shortcut `F3`) matching item name, SKU/tariff code, and barcode.
  - Floating dropdown displaying unit/serving, live stock availability, base price, and GST breakdown.
  - Keyboard navigation with Arrow Down/Up and Enter selection. Auto-clears and refocuses input after line addition.
- **Explicit Multi-Serving Selection**:
  - Identifies items configured with multiple serving units (e.g., Signature Premium Whisky with 30 ml peg, 60 ml peg, full bottle).
  - Enforces explicit modal selection before adding to cart, with keyboard numeric keys (`1`, `2`, `3`), Arrow Up/Down, and Enter.
  - Prevents accidental serving mismatches at busy counter sessions.
- **Cart Quantity Controls & Removal**:
  - Default quantity set to 1 upon addition.
  - Direct keyboard quantity editing via numeric input (`.cart-qty-input`, shortcut `F4`) and `+` / `-` buttons.
  - Line removal supported via `Remove` action or setting quantity to 0.
- **End-to-End Keyboard Billing Flow**:
  - Customer selection (`F2`) → Item search (`F3`) → Quantity edit (`F4`) → Checkout tender selection (`F8` Prepaid, `F9` Cash, `F10` Digital) → Payment confirmation modal (`Enter` confirm, `Esc` cancel) → Thermal 80mm receipt preview → Reset for next customer.
- **Visible Shortcut Legend & Accessibility**:
  - Persistent on-screen shortcut guide badges at the top of POS.
  - Full Tab/Shift+Tab focus ordering, high-contrast focus rings (`focus:ring-2 focus:ring-amber-400`), and ARIA dialog roles.
  - Single-key typing is never intercepted while the cashier is typing inside an active `<input>`, `<textarea>`, or `<select>`.
- **Double-Submission & Idempotency Safeguards**:
  - Client-side submission lock (`isSubmitting`) preventing held keys, double-clicks, or repeated Enter presses.
  - Server-side atomic execution guard (`$isProcessingPayment`) and UUID idempotency keys generated per checkout session.
- **Dedicated Current Order / Bill Board (Catalogue Removed for High-Speed Counter Operations)**:
  - Removed the static Saleable Item Catalogue (touch cards and compact list) in favor of dedicating screen space to an expansive, high-visibility "Current Order / Bill" section directly beneath the Item Search bar.
  - Cashiers at the Malabar Club counter operate with fast barcode scanning and keyboard item search (`F3`), removing the need for a bulky catalogue that pushed the active bill off-screen.
  - The expanded bill section features:
    - Structured bill table with sticky header (`#`, `Item Description & Serving`, `Rate & GST`, `Quantity [- / input / +]`, `Net Total`, `Action`).
    - Extended vertical capacity (`max-h-[30rem]`) supporting 10–15 line orders with zero cramped scrolling.
    - Prominent financial totals card (Gross Subtotal, Statutory GST Breakdown, and a high-contrast `text-3xl` Net Total Payable).
    - Immediate tender method buttons (`[F8] Prepaid Wallet`, `[F9] Cash in Till`, `[F10] Digital / UPI`).
    - Helpful empty-state cashier quick reference guide with shortcuts (<kbd>F2</kbd>, <kbd>F3</kbd>, <kbd>F4</kbd>, <kbd>F8</kbd>, <kbd>F9</kbd>, <kbd>F10</kbd>, <kbd>Esc</kbd>).
- **Strict Policy & Safeguard Preservation**:
  - No-credit policy strictly enforced: insufficient wallet balance disables confirmation with a clear warning.
  - Out-of-stock items cannot be added to bills.
  - All transactions route through authoritative domain services (`BillingService`, `WalletService`, `InventoryService`).

### 2. Automated Test Verification
- Executed `php artisan test tests/Feature/CounterPosKeyboardTest.php`:
  - **13 tests passed, 65 assertions** (Customer lookup by card/name/phone/number, barcode/item search, multi-serving modal, quantity editing, prepaid checkout, insufficient balance rejection, out of stock rejection, duplicate submission guard, view mode toggle).
- Executed `php artisan test tests/Feature/CounterPosTest.php`:
  - **4 tests passed, 22 assertions**.
- Executed full application test suite `php artisan test`:
  - **99 tests passed, 548 assertions, 0 failures, 0 errors** across all domain feature suites.

### 3. Browser Verification & Manual UAT Status
- Automated browser testing via `browser_subagent` encountered an environment sandbox limitation where Playwright browser binaries failed to download from Azure CDN (`404 Not Found`).
- Local HTTP endpoint verification:
  - `curl http://127.0.0.1:8080/login` -> HTTP 200 OK
  - `curl http://127.0.0.1:8080/pos` -> HTTP 302 (Redirects unauthenticated users to `/login`)
  - Server instance active on `http://127.0.0.1:8080` with pre-seeded development test accounts:
    - Cashier: `cashier1@malabarclub.local` / `Password@123`
    - Member with Funds: `MC-001` (Venu Gopal, Card `CARD-VENU-001`, ₹5,000.00 wallet)
    - Member with Zero Balance: `MC-003` (Rajesh Menon, Card `CARD-RAJESH-003`, ₹0.00 wallet)
    - Multi-Serving Item: `BAR-SIG-750` (Signature Whisky: 30ml, 60ml, Bottle)
    - In-Stock Item: `BAR-BEER-KF` (Kingfisher Beer, 120 bottles, Barcode `890123400001`)
    - Out-of-Stock Item: `BAR-VOD-750` (Absolut Vodka, 0 bottles)

---

## Universal Light Theme Migration (Delivered)

### 1. Scope of Implementation
- **Filament Admin Panel (`/admin`)**:
  - Configured `->darkMode(false)` in `App\Providers\Filament\AdminPanelProvider`.
  - Dark mode switch removed from UI; all resources and pages strictly render in light mode.
  - Purged custom `dark:*` utility overrides from custom Filament pages (`resources/views/filament/pages/financial-reports.blade.php` and `integration-status.blade.php`).
- **Application Base Layout (`resources/views/layouts/app.blade.php`)**:
  - Replaced `bg-slate-950 text-slate-100` with high-contrast, clean `bg-slate-100 text-slate-800`.
  - Header restyled to clean frosted light bar: `bg-white/95 border-b border-slate-200/90 text-slate-900 shadow-xs`.
  - Restyled active/inactive navigation links, user details, and logout buttons for daylight readability.
- **Staff Sign In (`resources/views/auth/login.blade.php`)**:
  - Converted entire sign-in view from dark card to crisp light card (`bg-white border border-slate-200/90 shadow-xl rounded-3xl p-7`).
  - Inputs restyled to `bg-slate-50 border border-slate-300 text-slate-900 focus:bg-white focus:ring-amber-500`.
  - Demo user account buttons restyled with light borders and subtle amber hover states.
- **Counter POS Screen (`resources/views/livewire/counter-pos.blade.php`)**:
  - Shortcut bar, member identification card, wallet balance badge, and quick member tags updated to high-contrast light aesthetics.
  - Item search, barcode autocomplete dropdown, and multi-serving modal restyled to clean white cards with amber accents.
  - Expansive Order/Bill table updated to white table card with `bg-slate-50` headers and clean slate borders.
  - Payment authorization modal and thermal receipt modal updated to light backgrounds.
- **Asset Compilation**:
  - Recompiled production assets via `npm run build` (vite v8.3.4, `app.css` 107.76 kB).
  - Cleared compiled Blade views (`php artisan view:clear`) and configuration cache.

### 2. Verification
- `php artisan test tests/Feature/CounterPosKeyboardTest.php`: 13 passed, 65 assertions.
- `php artisan test tests/Feature/CounterPosTest.php`: 4 passed, 22 assertions.
- `php artisan test tests/Feature/AdminPanelTest.php`: 2 passed, 19 assertions.
- Endpoint validation:
  - `curl -s http://127.0.0.1:8080/login | grep bg-slate-100` -> HTTP 200 with light theme confirmed.

---

## Docked Contextual Bottom Status Bar (Delivered)

### 1. Scope of Implementation
- **Removal of Static Top Shortcut Banner**:
  - Removed the static shortcut strip previously situated at the top of `resources/views/livewire/counter-pos.blade.php`.
  - Reclaimed vertical viewport space, ensuring the full bill table and search boxes fit comfortably on 768px+ laptop and POS touch monitors without scrolling.
- **Docked Bottom Status Bar (`<footer class="fixed bottom-0 ...">`)**:
  - Implemented a persistent status bar pinned to the bottom of the window with subtle backdrop blur (`bg-white/95 backdrop-blur-md border-t border-slate-200/90 shadow-lg`).
  - Added bottom padding `pb-20` to the POS root container to guarantee no cart rows or tender buttons are obscured.
  - **Left Section (Terminal & Till Telemetry)**:
    - Real-time indicator: Green pulsing badge `ONLINE` when a cashier shift is active, or Amber `SHIFT CLOSED` when inactive.
    - Active terminal details: Displays counter name (`Bar Counter 1`), shift number, and expected drawer till cash (`Till Expected: ₹...`).
  - **Right Section (Context-Sensitive Keyboard Guidance)**:
    - Automatically transitions based on active application state:
      - **Receipt Modal**: Displays `P` Print and `Enter / Esc` Next Customer.
      - **Payment Authorization Modal**: Displays `Enter` Confirm Settle and `Esc` Cancel.
      - **Serving Size Selector Modal**: Displays numeric `1`, `2`, `3` quick select badges, `↑ ↓` navigation, `Enter`, and `Esc`.
      - **Item Search Autocomplete Active**: Displays `↑ ↓` Navigate, `Enter` Add to Bill, and `Esc` Close.
      - **Customer Search Autocomplete Active**: Displays `↑ ↓` Navigate, `Enter` Select Member, and `Esc` Close.
      - **Standard Ready State**: Displays `F2` Customer, `F3` Item Search, and when the bill contains items: `F4` Qty, `F8` Prepaid, `F9` Cash, `F10` Digital, and `Esc` Clear.
- **Asset Compilation & Cache Refresh**:
  - Recompiled production bundle with Vite (`npm run build`).
  - Cleared compiled views and configuration cache (`php artisan view:clear && php artisan config:clear`).

### 2. Verification
- `php artisan test tests/Feature/CounterPosKeyboardTest.php`: **15 passed, 81 assertions** (all keyboard shortcuts, modals, and tenders verified).
- `php artisan test tests/Feature/CounterPosTest.php`: **4 passed, 22 assertions**.
- `php artisan test tests/Feature/AdminPanelTest.php`: **2 passed, 19 assertions**.
- Live server active at `http://127.0.0.1:8080/pos`.

---

## Keyboard Shortcut Audit & Escape Key Clear Fix (Delivered)

### 1. Root Cause Analysis
- **Missing `clearCart()` on Escape**: The global keydown listener `handleGlobalKeydown` intercepted `e.key === 'Escape'` for closing modals and autocomplete dropdowns, but completely omitted the call to `$wire.clearCart()` when in the default POS state with active cart lines.
- **Frozen Blade Static JSON Invariant**: The original implementation evaluated modal visibility states using server-side `@json($showPaymentConfirmModal)` within Alpine's `x-data` method. Because Livewire preserves existing Alpine components during DOM morphing, `@json(...)` was evaluated once at initial page load (when all modals were `false`), permanently freezing the closure checks in JavaScript.
- **Serving Modal Key Capture**: Number keys (`1`, `2`, `3`) and arrow navigation for serving sizes were bound to an un-focused modal container `<div>`, preventing global window-level capture when focus remained on the item search input.

### 2. Implemented Fixes
- **Cascading Escape Handler**:
  1. Closes thermal receipt preview modal if active (`wire.closeReceiptModal()`).
  2. Cancels and closes payment authorization modal if active (`wire.closePaymentConfirmModal()`).
  3. Cancels and closes serving size selection dialog if active (`wire.closeServingModal()`).
  4. Returns focus from inline quantity input (`.cart-qty-input`) to item search.
  5. Clears active item search query and closes autocomplete dropdown.
  6. Closes customer search dropdown and clears query.
  7. **Clears Cart**: When cart has items and no modals/searches are open, calls `wire.clearCart()` to immediately empty the bill.
  8. Clears any pending error or success alerts (`clearMessages()`).
- **Dynamic Reactive Property Access**:
  - Replaced frozen `@json(...)` checks with dynamic Alpine/Livewire proxy helper `getProp(name)` (`wire.$get(name)` / `wire.get(name)` / `wire[name]`).
  - Modal open/closed checks reflect live Livewire component state in real-time.
- **Global Key Capture for All Actions**:
  - **Serving Selector**: Window-level capture of `1`, `2`, `3` numeric shortcuts, `ArrowUp`/`ArrowDown`, `Enter`, and `Escape`.
  - **Payment Confirm**: Window-level capture of `Enter` (confirms checkout) and `Escape` (cancels).
  - **Receipt Preview**: Window-level capture of `P` (triggers `window.print()`), `Enter`/`Escape` (closes and prepares for next customer).
  - **Member Reset**: `Delete` resets the selected member when not actively typing inside a text input.
  - **Billing Shortcuts**: `F2` focuses member search, `F3` focuses item search, `F4` focuses quantity input, `F8` prepaid tender, `F9` cash tender, `F10` digital tender.

### 3. Automated Verification
- `php artisan test tests/Feature/CounterPosKeyboardTest.php`: **15 passed, 81 assertions** (including explicit tests for `clearCart()` and template key bindings).
- Recompiled Vite bundle (`npm run build`) and cleared Blade view cache (`php artisan view:clear`).

---

## Distraction-Free Open Shift Gateway & Terminology Alignment (Delivered)

### 1. Scope of Implementation
- **Conditional Workspace Gating (`@if ($activeShift) ... @endif`)**:
  - When a cashier is logged in but has no active shift, the entire two-column billing interface (Member Search, Quick Add, Order/Bill table, Wallet Recharge card, Tender buttons) and all billing modals are hidden.
  - The view presents only a clean, centered Open Counter Shift card (`min-h-[70vh] flex items-center justify-center`).
  - Upon submitting the form (or pressing <kbd>↵ Enter</kbd>), the shift is opened and the full billing workspace is revealed, focusing the search input for the first sale.
  - Upon closing a shift via reconciliation, the workspace seamlessly hides and returns to the clean gateway.
- **Indian Regional Cash Terminology Alignment**:
  - Replaced Western term "Till" with standard Indian retail/club accounting terms:
    - **Opening Cash** for the starting float in hand.
    - **Closing Cash** (and **Closing Cash Counted**) for physical cash drawer reconciliation.
    - **Expected Cash** (instead of *Till Expected*) on active shift header and bottom status bar.
    - **Cash Payment** (instead of *Cash in Till*) on tender buttons and payment options.
    - **Cash Drawer** (instead of *Cash Till*) on breakdown badges and dialogs.
- **Contextual Status Bar Synchronization**:
  - When shift is closed: Bottom status bar shows `● SHIFT CLOSED` with prompt `Enter opening cash above to begin counter billing`, and keycap `<kbd>↵ Enter</kbd> Open Shift` (all billing function keys <kbd>F2</kbd>, <kbd>F3</kbd>, <kbd>F8</kbd>&ndash;<kbd>F10</kbd> are hidden).
  - Global keyboard listener disables billing shortcuts while shift is closed, allowing natural <kbd>Enter</kbd> submission of the opening cash form.

### 2. Verification
- `php artisan test tests/Feature/CounterPosTest.php`: **5 passed, 37 assertions** (including dedicated test `test_distraction_free_open_shift_gateway_hides_billing_workspace_until_opened`).
- `php artisan test tests/Feature/CounterPosKeyboardTest.php`: **15 passed, 81 assertions**.
- `php artisan test tests/Feature/AdminPanelTest.php`: **2 passed, 19 assertions**.
- Production assets built via `npm run build` and view cache cleared.

---

## Active Shift Top Bar Optimization — Option 1 Consolidation (Delivered)

### 1. Scope of Implementation
- **Elimination of Redundant Top Card**:
  - Removed the bulky `~90px` floating Active Shift Header Bar card that was situated above the POS two-column grid.
  - Eliminated data duplication (Counter name, Shift #, Expected cash, and Online indicator were already shown in the docked bottom status bar).
  - Pushed **Member Identification Search**, **Item Search**, and the **Current Order / Bill Table** straight to the top of the viewport for zero-scroll counter operations.
- **Relocation of `Close Shift` to Docked Bottom Status Bar**:
  - Placed a clean, high-visibility `Close Shift` action button directly in the bottom status bar next to `Expected Cash: ₹...`.
  - Clicking `Close Shift` triggers the centered modal dialog (`MODAL 4: Reconcile & Close Counter Dialog`).
- **Modal Key Navigation**:
  - Integrated `showCloseShiftModal` into Alpine root state and global keyboard listeners:
    - <kbd>Esc</kbd> cancels and closes the reconciliation dialog.
    - Form submission via <kbd>Enter ↵</kbd> executes `closeShift()`.
- **Seamless State Transition**:
  - Upon shift reconciliation and closing, the workspace seamlessly hides and returns to the clean "Open Counter Shift" gateway card.

### 2. Verification
- `php artisan test tests/Feature/CounterPosTest.php`: **5 passed, 37 assertions**.
- `php artisan test tests/Feature/CounterPosKeyboardTest.php`: **15 passed, 81 assertions**.
- `php artisan test tests/Feature/AdminPanelTest.php`: **2 passed, 19 assertions**.
- Recompiled Vite bundle (`npm run build`) and cleared Blade view cache.

---

## Role-Gated Admin Panel & Navbar Access (Delivered)

### 1. Scope of Implementation
- **Strict Role-Based Panel Access Enforcement**:
  - Updated `User::canAccessPanel(Panel $panel)` in `app/Models/User.php` to restrict Filament access to administrative roles: `admin`, `manager`, `accountant`, `auditor`, or users with `reports.view` permission.
  - Front-line cashiers (`cashier` role) are strictly denied panel access. Any direct navigation to `/admin` returns HTTP 403 Forbidden.
- **Contextual Navbar Navigation**:
  - Gated the "🏛️ Club Admin" link in `resources/views/layouts/app.blade.php` behind `auth()->user()?->canAccessPanel(Filament::getPanel('admin'))`.
  - Cashiers logged into the counter POS now see only **⚡ Counter POS** alongside their user profile and sign-out controls; administrative navigation is completely hidden.
- **Automated Regression & Access Control Suite**:
  - Added test cases in `tests/Feature/AdminPanelTest.php`:
    - `test_cashier_cannot_access_filament_dashboard()` (verifies HTTP 403 on `/admin`).
    - `test_cashier_does_not_see_club_admin_link_on_pos()` (verifies cashier view does not contain `Club Admin`).
    - `test_admin_sees_club_admin_link_on_pos()` (verifies admin view retains access link).

### 2. Automated Test Verification
- `php artisan test tests/Feature/AdminPanelTest.php`: **5 passed, 24 assertions** (Dashboard, resources, cashier denial, POS link gating).
- `php artisan test tests/Feature/CounterPosTest.php`: **5 passed, 37 assertions**.
- `php artisan test tests/Feature/CounterPosKeyboardTest.php`: **15 passed, 81 assertions**.
- `php artisan test tests/Feature/AdvancedIntegrityAndPaymentTest.php`: **20 passed, 124 assertions**.

---

## Front-Desk Facilities Operations & Back-Office Workflows (Delivered)

### 1. Scope of Implementation
- **Accommodation Visual Room Rack (`RoomRackPage`)**:
  - Implemented dedicated visual front-desk board for the 5 club rooms (Deluxe 101–103, Suite 104, Standard 105) under `Accommodation & Sports`.
  - Real-time room status indicators: `Vacant & Ready` (emerald), `Occupied` (teal), `Reserved` (blue), `Needs Cleaning / Dirty` (amber), and `Under Maintenance` (rose).
  - 7-day visual availability timeline strip per room showing booked and free segments.
  - Quick **Check-In** action: Verifies occupant identity, cleanliness, and enforces strict non-negotiable full payment before room key issuance.
  - Quick **Check-Out** action: Settles charges, vacates room, and automatically transitions status to `Dirty` for housekeeping.
  - Housekeeping status switcher (`Clean & Ready`, `Needs Cleaning`, `Maintenance`).
  - Advance payment collection modal to record deposits towards pending balances.
  - Walk-in / New Room Booking modal with instant date, tariff, and payment capture.
- **Badminton Court Daily Visual Schedule (`CourtSchedulePage`)**:
  - Implemented visual 60-minute time-slot grid (06:00 AM – 09:00 PM, 15 hourly slots) for the Indoor Wooden Badminton Court.
  - Real-time slot status cards: `Available` (emerald) vs `Booked` (blue).
  - Instant sports plan entitlement check: When member is selected, checks if active badminton subscription covers the slot (₹0 court fee) or bills the standard ₹100 guest/pay-per-use court tariff.
  - One-click slot reservation enforcing the strict 4-player capacity limit.
  - Slot cancellation modal releasing the time slot back to available state.
- **Contractor Daily Settlement Batch Operations**:
  - Added header action `Generate Daily Settlement Batch` in `ListContractorSettlementBatches` to batch previous day's paid food sales up to the configured cutoff.
  - Added row action `Approve Batch` posting financial journal entries to Contractor Food Expense (5020) and Food Contractor Payable (2020).
  - Added row action `Record Payout` supporting standard Indian banking instruments (NEFT, RTGS, Account Payee Cheque, IMPS) with bank UTR reference and residual payable tracking.
- **Posted Bill Immutability & Detail View**:
  - Removed destructive `Edit` and `Delete` actions from `BillsTable` to protect immutable financial ledger rules.
  - Added `View Bill` action with slide-over/modal rendering full tax invoice (line items, serving sizes, rates, CGST/SGST breakdown, and tender settlement details).
  - Added manager-authorized `Authorize Refund` action creating linked refund transactions rather than mutating posted records.
- **Member Card Management & Fast Guest Entry Passes**:
  - Added `Issue / Replace RFID Card` action in `MembersTable`: Replaces lost/damaged cards, blocks the old card UID, and retains 100% of the member's server-authoritative prepaid ledger balance.
  - Added `Account Statement` modal generating a printable A4 ledger statement showing recharges, departmental spends, and running balances.
  - Added `Issue Entry Pass` and `Print Slip` in `GuestsTable` for walk-in guests, issuing verified passes with printable 80mm barcode slips for the door security turnstile.

### 2. Automated Test Verification
- Executed `php artisan test tests/Feature/FrontDeskAndOperationalWorkflowsTest.php`:
  - **5 passed, 23 assertions** (Room rack access, advance payment enforcement on check-in, check-out dirty status, court slot booking & cancellation, contractor settlement generation/payout, member card replacement balance preservation).
- Executed `php artisan test tests/Feature/AdminPanelTest.php`:
  - **5 passed, 26 assertions** (All back-office resources including `/admin/room-rack` and `/admin/court-schedule`).
- Executed full regression suite (`AdminPanelTest`, `CounterPosTest`, `CounterPosKeyboardTest`, `AdvancedIntegrityAndPaymentTest`):
  - **45 passed, 268 assertions, 0 errors, 0 failures**.
- Recompiled Vite frontend production bundle (`npm run build`).

---

## Error Alert Dialogue Box (Delivered)

### 1. Scope of Implementation
- **Elimination of Top-of-Screen Message Bar**:
  - Removed the static red alert bar situated above the POS workspace that pushed the search inputs down and could easily be overlooked during fast counter billing.
- **Centered Modal Dialogue Box (`role="alertdialog"`)**:
  - Whenever a billing, cart, or payment error occurs (insufficient prepaid funds, out-of-stock items, unselected serving sizes, missing member, closed shift):
    - Dims the background with a soft backdrop overlay (`bg-slate-900/60 backdrop-blur-xs`).
    - Displays a centered, high-contrast modal dialog box with an attention-grabbing warning shield icon, clear title ("Transaction Alert"), and a readable explanation.
    - Features a prominent button: **"Acknowledge & Continue"** with keycap `<kbd>↵ Enter or Esc</kbd>`.
- **Keyboard-Driven Rapid Dismissal**:
  - Operators can dismiss the alert instantly by pressing <kbd>Enter ↵</kbd>, <kbd>Esc</kbd>, or <kbd>Space</kbd>, returning keyboard focus back to the counter without needing mouse clicks.
  - While the error modal is visible, all background shortcuts are blocked to prevent accidental duplicate actions.
  - The docked bottom status bar dynamically displays a flashing alert indicator and shortcut badge: `↵ Enter / Esc Dismiss`.

### 2. Automated Test Verification
- Added test in `tests/Feature/CounterPosKeyboardTest.php`:
  - `test_error_dialog_box_renders_and_dismisses` (verifies `role="alertdialog"`, `Transaction Alert` title, message rendering, and clean dismissal via `set('errorMessage', null)`).
- Full regression suite execution across all 5 test files (`task-889`):
  - **51 passed, 296 assertions, 0 errors, 0 failures**.
- Recompiled Vite frontend bundle (`npm run build`).

---

## Phase 1: Master Configuration & Infrastructure (Delivered)

### 1. Scope of Implementation
- **Tariff Master (`TariffResource` & `TariffForm.php`)**:
  - Implemented 3 structured form sections tailored for Indian club operations:
    - *General Tariff Information*: Tariff Code / SKU (unique), Barcode / EAN (physical scanner support), Item Description, and Club Operating Department (`bar`, `food`, `accommodation`, `sports`, `card_room`, `membership`, `admin`).
    - *Indian Pricing & Statutory GST Slabs*: Selling price in Rupees (₹) with prefix, decimal input, and exact dehydration to signed integer paise (`rate_paise`); statutory Indian GST rate selector (`0.00%` Exempt, `5.00%` Restaurant food, `12.00%` Room tariff under ₹7,500/night, `18.00%` Standard club services/bar pegs/guest passes, `28.00%` Luxury goods).
    - *Validity & Status*: `effective_from` (default business date), `effective_to` (optional sunset date), and `is_active` toggle.
  - Registered full CRUD routes: `ListTariffs`, `CreateTariff`, and `EditTariff`.
- **Facility Master (`FacilityResource`, `FacilitiesTable`, `ListFacilities`)**:
  - Implemented facility master form: Code (e.g. `ROOM-101`, `COURT-1`), Display Name, Classification (`room`, `court`, `gym`, `bar`, `card_room`, `general`), Maximum Capacity, and `is_active` toggle.
  - Added modal `CreateAction` on `ListFacilities` and `EditAction` on `FacilitiesTable`.
- **Inventory Stock Master (`InventoryItemResource`, `InventoryItemsTable`, `ListInventoryItems`)**:
  - Implemented stock master form: SKU, Barcode, Name, Department, Category (`liquor`, `beer_wine`, `beverages`, `tobacco`, `food_raw`, `consumables`), Base Unit (`ml`, `bottle`, `can`, `pcs`, `kg`, `portion` for exact bar dispensing), Current On-Hand Stock Quantity, Current Weighted Average Cost in Rupees (auto-dehydrated to paise), and Reorder Alert Level.
  - Corrected table column mappings (`current_stock_qty`, `current_avg_cost_paise`, `reorder_level`) and connected modal `CreateAction` and `EditAction`.

### 2. Automated Test Verification
- Executed `php artisan test tests/Feature/AdminPhase1Test.php` with MySQL 8.4:
  - **4 passed, 26 assertions** (`test_admin_can_access_tariff_create_page`, `test_admin_can_create_tariff_via_livewire`, `test_admin_can_create_facility_modal_action`, `test_admin_can_create_inventory_item_modal_action`).
- Executed `php artisan test tests/Feature/AdminPanelTest.php`:
  - **5 passed, 26 assertions**.
- Verified exact money round-trip: ₹350.00 entered in tariff form persists as `35000` paise in `tariffs` table; ₹4,500.00 liquor cost persists as `450000` paise in `inventory_items` table.

---

## Phase 2: Membership & Guest Management Forms (Delivered)

### 1. Scope of Implementation
- **Member Master Form (`MemberResource`, `MemberForm.php`, `CreateMember.php`, `EditMember.php`, `MembersTable.php`)**:
  - Implemented 3 structured form sections tailored for Indian club membership:
    - *Personal Details & Statutory KYC*: First name, last name, mobile number (+91 Indian format with tel validation), email address, statutory identity proof selector (`Aadhaar Card` 12-digit UIDAI, `Passport`, `Driving Licence`, `Voter ID / EPIC`, `PAN Card`), document number, and residential address in Kerala.
    - *Club Membership Classification*: Membership roster number (`MC-XXX`, unique), category selector (Life, Permanent, Temporary, Sports), standing status (`active`, `suspended`, `expired`, `terminated`), admission date, and optional expiration date.
    - *Smartcard & RFID Token*: Optional input on member creation to immediately link a contactless Mifare/NFC RFID card token.
  - Linked atomic domain creation via `MemberService::registerMember`:
    - Automatically creates linked `Person` entity with KYC metadata.
    - Automatically initializes authoritative server-side `WalletAccount` with 0 balance.
    - Automatically issues active RFID `Card` if token provided.
  - Bidirectional edit synchronization: `EditMember` seamlessly populates and updates both `persons` and `members` records.
  - Family Dependents Management: Added modal action `dependents` on `MembersTable` rendering a responsive list of registered dependents and an inline form to add spouses, children, or parents. Strictly preserves non-negotiable Scenario A07 by defaulting `can_spend_wallet` to `false`.
- **Guest Master Form (`GuestResource`, `GuestForm.php`, `CreateGuest.php`, `EditGuest.php`, `GuestsTable.php`)**:
  - Implemented guest personal identification with statutory Indian KYC proof (Aadhaar, Passport, DL, Voter ID, PAN).
  - Sponsoring Member Authorization (Scenario A04): Dynamic reactive dropdown allowing selection between Malabar Club Primary Members (`Member`) and Affiliated Reciprocal Club Members (`AffiliatedMember`).
  - Visit Date and Status tracking (`registered`, `checked_in`, `departed`).
  - Registered full CRUD with `ListGuests`, `CreateGuest`, and `EditGuest`.

### 2. Automated Test Verification
- Executed `php artisan test tests/Feature/AdminPhase2MembershipAndGuestTest.php` with MySQL 8.4:
  - **6 passed, 43 assertions** (`test_admin_can_access_member_create_page`, `test_admin_can_create_member_with_kyc_and_rfid`, `test_admin_can_edit_member_details`, `test_admin_can_register_dependent_for_member`, `test_admin_can_access_guest_create_page`, `test_admin_can_create_guest_with_sponsor`).
- Executed comprehensive regression suite across Phase 1, Phase 2, AdminPanel, and Membership (`tests/Feature/AdminPhase1Test.php`, `tests/Feature/AdminPhase2MembershipAndGuestTest.php`, `tests/Feature/AdminPanelTest.php`, `tests/Feature/MembershipAndAccessTest.php`, `tests/Feature/CounterPosTest.php`, `tests/Feature/CounterPosKeyboardTest.php`):
  - **43 passed, 254 assertions, 0 errors, 0 failures**.

---

## Phase 3: Back-Office Purchasing, Stock Receiving & Expenses (Delivered)

### 1. Scope of Implementation
- **Stock Purchase Receipts (`PurchaseReceiptResource`, `PurchaseReceiptForm.php`, `CreatePurchaseReceipt.php`, `PurchaseReceiptsTable.php`, `ListPurchaseReceipts.php`)**:
  - Implemented supplier inward consignment form with Indian GST tax input credit audit: Wholesale Supplier / Distributor (`Supplier`), Vendor Invoice / Delivery Challan #, Invoice Date, Delivery Notes.
  - Line items repeater: Inventory Item selection (with base unit and SKU), Received Quantity (e.g. `ml` for bulk liquor bottles), Unit Purchase Cost in Rupees (auto-dehydrated to paise), Input GST slabs (`0%` Liquor State Excise / Exempt, `5%` Food Raw, `12%` Processed, `18%` Standard, `28%` Luxury), and Line Notes/Batch #.
  - Atomic backend execution via `InventoryService::receivePurchase`:
    - Automatically updates moving weighted-average costs perpetually on each inventory item.
    - Creates immutable `InventoryMovement` audit logs.
    - Automatically posts balanced general ledger financial journals: Debit Inventory Stock (1030) + Input GST (2030), Credit Bank Clearing (1020).
  - Table updates: Fixed attribute mappings (`invoice_number`, `invoice_date`, `total_net_paise`), formatted Indian currency, and added a **Consignment Details** modal view (`purchase-receipt-modal.blade.php`).
- **Operating Expenses & Disbursements (`ExpenseResource`, `ExpenseForm.php`, `CreateExpense.php`, `ExpensesTable.php`, `ListExpenses.php`)**:
  - Implemented operational expense form with Chart of Accounts integration: General Ledger Expense Head (`Account` where type = `expense`, default 5030 Operating & Administrative Expenses), Operating Department Attribution (`Department` for departmental P&L), Payment / Voucher Date.
  - Disbursement & Payment details: Payee / Beneficiary / Vendor name, Disbursed Amount in Rupees (converted to integer paise), Payment Instrument (`bank_transfer` NEFT/RTGS, `cheque` Account Payee Cheque, `cash` Counter Petty Cash), Bank UTR / Cheque Reference #, and Narration / Notes.
  - Atomic backend execution via `ExpenseService::recordExpense`:
    - Enforces period closing locks (Scenario R04).
    - Automatically posts operational balanced journal to GL: Debit Expense Head (5030), Credit Bank Clearing (1020) or Counter Cash (1010).
  - Table updates: Added GL Head badge, payment instrument badge with Indian banking formatting, and an **Expense Payment Voucher** modal view (`expense-voucher-modal.blade.php`).

### 2. Automated Test Verification
- Executed `php artisan test tests/Feature/AdminPhase3PurchasesAndExpensesTest.php` with MySQL 8.4:
  - **4 passed, 25 assertions** (`test_admin_can_access_purchase_receipt_create_page`, `test_admin_can_create_purchase_receipt_and_update_stock_and_journal`, `test_admin_can_access_expense_create_page`, `test_admin_can_record_expense_and_post_journal`).
- Verified exact stock increase (7500 ml Amrut Single Malt), moving weighted-average cost update (450 paise/ml), and balanced double-entry GL journal posting for both stock inward (Debit 1030 / Credit 1020) and expense disbursements (Debit 5030 / Credit 1020).
- Executed full suite across all three phases (`tests/Feature/AdminPhase1Test.php`, `tests/Feature/AdminPhase2MembershipAndGuestTest.php`, `tests/Feature/AdminPhase3PurchasesAndExpensesTest.php`, `tests/Feature/AdminPanelTest.php`):
  - **19 passed, 120 assertions, 0 errors, 0 failures**.

---

## Phase 4: Facilities, Bookings & Subscriptions (Delivered)

### 1. Scope of Implementation
- **Sports Facility Subscriptions (`FacilitySubscriptionResource`, `FacilitySubscriptionForm.php`, `CreateFacilitySubscription.php`, `FacilitySubscriptionsTable.php`, `ListFacilitySubscriptions.php`)**:
  - Implemented sports plan subscription form: Primary Club Member selection (enforcing active standing under Scenario A03), Sports Facility (`badminton`, `gym`), Duration (`monthly`, `annual`), Tariff selector with auto-filling fee, Start Date and exclusive boundary End Date (D12), Subscription Fee in Rupees (dehydrated to paise), and Status (`active`, `expired`, `cancelled`).
  - Automatically provisions straight-line deferred revenue recognition schedule in `revenue_recognition_schedules` (R02 & D14) for periodic revenue recognition.
- **Accommodation Room Reservations (`RoomReservationResource`, `RoomReservationForm.php`, `CreateRoomReservation.php`, `EditRoomReservation.php`, `RoomReservationsTable.php`, `ListRoomReservations.php`)**:
  - Implemented accommodation reservation form: Room selector (`ROOM-101` to `ROOM-105`), Sponsoring Club Member and/or Visiting Guest Occupant, Arrival Notes.
  - Dates & Pricing: Check-in and check-out dates validated on half-open interval `[check_in, check_out)` (D23), daily room tariff in Rupees (stored in paise), advance deposit/paid amount strictly adhering to no-credit policy (D11).
  - Stay & Housekeeping Status: Tracks `status` (`reserved`, `checked_in`, `checked_out`, `cancelled`) and `housekeeping_status` (`clean`, `dirty`, `maintenance`) for synchronization with the front-desk Visual Room Rack.
- **Badminton Court Bookings (`CourtBookingResource`, `CourtBookingForm.php`, `CreateCourtBooking.php`, `EditCourtBooking.php`, `CourtBookingsTable.php`, `ListCourtBookings.php`)**:
  - Implemented court booking form: Court selector (`COURT-1`), Booking Member, Booking Date, 60-minute time slots (15 hourly intervals from 06:00 to 21:00).
  - Integration with sports subscription entitlement (`is_plan_covered` waives hourly charge for active plan holders while respecting atomic slot capacity).
  - Registered full CRUD with `ListCourtBookings`, `CreateCourtBooking`, and `EditCourtBooking`.

### 2. Automated Test Verification
- Executed `php artisan test tests/Feature/AdminPhase4FacilitiesAndBookingsTest.php` with MySQL 8.4:
  - **6 passed, 34 assertions** (`test_admin_can_access_facility_subscription_create_page`, `test_admin_can_create_sports_subscription_and_revenue_schedule`, `test_admin_can_access_room_reservation_create_page`, `test_admin_can_create_room_reservation`, `test_admin_can_access_court_booking_create_page`, `test_admin_can_create_court_booking_slot`).
- Verified exact money round-trip: ₹1,500.00 sports plan persists as `150000` paise with straight-line daily deferred schedule; 3-night room reservation at ₹2,500.00/night calculates exact ₹7,500.00 (`750000` paise); 60-min badminton court booking is atomically serialized and slot-locked.
- Executed regression suite across all 4 phases, AdminPanel, and FrontDesk operational boards:
  - **30 passed, 177 assertions, 0 errors, 0 failures**.

---

## Phase 5: Financial Operations, Shift Audits & General Ledger (Delivered)

### 1. Scope of Implementation
- **Counter Shifts & Cash Balancing (`ShiftResource`, `ShiftForm.php`, `CreateShift.php`, `EditShift.php`, `ShiftsTable.php`)**:
  - Implemented counter shift opening form: Counter Station, Station Cashier / Staff, Operating Business Date, and Opening Cash Float in Rupees (stored in paise).
  - Integrated with `ShiftService::openShift` ensuring atomic shift assignment and float logging.
  - Added table row action `Close Shift` (`closeShift`) on `ShiftsTable`: Prompts cashier/manager for counted physical cash in till, calculates expected cash and overage/shortage discrepancy in real-time, marks shift closed, and logs handover notes.
- **Financial Periods & Audit Locks (`FinancialPeriodResource`, `FinancialPeriodForm.php`, `CreateFinancialPeriod.php`, `EditFinancialPeriod.php`, `FinancialPeriodsTable.php`, `ListFinancialPeriods.php`)**:
  - Implemented period definition form: Period Code (`FY2026-M11`, `2026-Q3`), Start Date, End Date, Status (`open`, `locked`, `closed`), and audit notes.
  - Added table row action `Close Period` on `FinancialPeriodsTable`: Activates strict fiscal lock under Scenario R04 via `PeriodClosingService::closePeriod`, permanently blocking backdating of bills, refunds, or expenses into the closed period.
- **General Ledger Journals (`JournalHeaderResource`, `JournalHeaderForm.php`, `CreateJournalHeader.php`, `JournalHeadersTable.php`, `ListJournalHeaders.php`)**:
  - Implemented double-entry journal voucher creation form: Posting Date, Source Classification (`manual_journal`, `recharge`, `bill`, `refund`, `contractor_settlement`, `inventory_receipt`, `expense`), Description/Narration.
  - Line items repeater: GL Account Head, Debit in Rupees, Credit in Rupees, Department Attribution, and Line Explanation.
  - Enforces strict double-entry balance validation (`sum(debits) === sum(credits)`) and rejects unbalanced vouchers.
  - Protects immutable financial ledger rules: Removed destructive edit and delete bulk actions on posted journals.
  - Added **"View Voucher"** modal action (`journal-voucher-modal.blade.php`) displaying full double-entry voucher breakdown with GL account codes, names, departments, debits, credits, and balancing status.

### 2. Layout & UI Polish (Badminton Court Schedule & Accommodation Visual Room Rack)
- **Root Cause Resolution**:
  - Fixed Filament asset loading in `app/Providers/Filament/AdminPanelProvider.php`: Registered `PanelsRenderHook::HEAD_END` with `app(Vite::class)(['resources/css/app.css'])` so that the admin panel loads the compiled Tailwind CSS bundle alongside Filament vendor styles.
  - Added `@source '../../app/Filament/**/*.php';` into `resources/css/app.css` to scan Filament resources and custom views.
  - Cleaned up date picker toolbar in `resources/views/filament/pages/court-schedule.blade.php` and `resources/views/filament/pages/room-rack.blade.php`: Removed redundant unconstrained SVG overlay inside native date inputs that caused giant calendar icon blow-up.
  - Defense-in-depth SVG dimension enforcement: Added explicit HTML `width="16" height="16"` / `width="14" height="14"` and inline `style="width: ...; height: ...; min-width: ...;"` on all SVGs across `court-schedule.blade.php`, `room-rack.blade.php`, `financial-reports.blade.php`, `integration-status.blade.php`, and custom modal views.
  - Recompiled production assets via `npm run build` (`app-DGWeVEpC.css`).

### 3. Counter & Reception Staff Front-Desk Access (Delivered)
- **Role Permissions & Panel Access (`ProductionBootstrapSeeder.php`, `User.php`)**:
  - Registered granular front-desk permissions: `rooms.view_rack`, `rooms.manage_reservations`, `courts.view_schedule`, and `courts.manage_bookings`.
  - Assigned these permissions to both `reception` and `cashier` roles alongside management/admin.
  - Updated `User::canAccessPanel` in `app/Models/User.php` to allow `cashier` and `reception` staff access to the front-desk operational modules of the Filament panel.
- **Resource Scoping & Least-Privilege Protection**:
  - Frontline staff granted access to: `RoomRackPage`, `CourtSchedulePage`, `RoomReservationResource`, `CourtBookingResource`, `ShiftResource`, `GuestResource`, `MemberResource`, `FacilitySubscriptionResource`, `BillResource`, and `InventoryItemResource`.
  - Strictly denied (403 Forbidden) access to back-office accounting, ledgers, contractor payouts, and master configurations: `FinancialReportsPage`, `JournalHeaderResource`, `FinancialPeriodResource`, `TariffResource` (Scenario A01), `PurchaseReceiptResource`, `ExpenseResource`, `ContractorSettlementBatchResource`, `IntegrationStatusPage`, and `FacilityResource`.
- **Bidirectional POS ↔ Front Desk Navigation Switcher**:
  - Top navigation bar on `/pos` (`layouts/app.blade.php`): Frontline staff see direct links for `🏨 Room Rack` and `🏸 Court Schedule` to manage room availability and court bookings without asking for admin logins. `🏛️ Club Admin` remains exclusive to managers/admins.
  - Filament panel navigation (`AdminPanelProvider.php`): Added `Counter POS Terminal` navigation item so staff can jump back to rapid F&B billing with one click.
- **Automated Test Verification**:
  - `AdminPanelTest.php`: Verified that counter cashiers and receptionists can access the Visual Room Rack and Court Schedule (`200 OK`), but are blocked with `403 Forbidden` when attempting to access financial reports, GL journals, fiscal periods, contractor settlements, tariffs, or supplier receipts.
  - `FrontDeskAndOperationalWorkflowsTest.php`: Verified counter cashier access to room rack and court schedule boards, along with strict adherence to payment-before-checkin and housekeeping status flows.

### 4. Automated Test Verification
- Executed `php artisan test tests/Feature/AdminPhase5FinancialOperationsTest.php` with MySQL 8.4:
  - **8 passed, 46 assertions** (`test_admin_can_access_shift_create_page`, `test_admin_can_open_counter_shift`, `test_admin_can_close_shift_via_table_action`, `test_admin_can_access_financial_period_create_page`, `test_admin_can_create_and_close_financial_period`, `test_admin_can_access_journal_create_page`, `test_admin_can_post_balanced_manual_journal`, `test_unbalanced_journal_submission_is_rejected`).
- Verified complete administrative suite across all 5 phases (`AdminPhase1Test`, `AdminPhase2MembershipAndGuestTest`, `AdminPhase3PurchasesAndExpensesTest`, `AdminPhase4FacilitiesAndBookingsTest`, `AdminPhase5FinancialOperationsTest`, `AdminPanelTest`):
  - **33 passed, 200 assertions, 0 errors, 0 failures**.
- Verified core operational and financial suite (`CounterPosTest`, `CounterPosKeyboardTest`, `FrontDeskAndOperationalWorkflowsTest`, `AdvancedIntegrityAndPaymentTest`):
  - **46 passed, 270 assertions, 0 errors, 0 failures**.

---

## Phase 6: Member Portal & Progressive Web App (PWA) (Delivered)

### 1. Scope of Implementation
- **Dedicated Member Authentication & Security Guard (`MemberAuthController.php`, `config/auth.php`, `bootstrap/app.php`)**:
  - Independent session-based `member` guard and `members` Eloquent provider completely decoupled from staff `web` guard.
  - Dual login credentials: Member can sign in using their official membership number (e.g. `MC-001`) or their registered 10-digit mobile phone number with their secret PIN / password.
  - First-time PIN activation flow (`/member/activate`): Members verify their membership number and registered phone number to establish or reset their access PIN.
  - Strict security boundary: Members cannot access `/admin` or `/pos` (redirected to staff login or 403 Forbidden). Frontline staff layouts explicitly check `web` guard.
  - Zero hardcoded member caps: Scalable database schema, parameterized queries, and eager loaded relationships supporting 460 to thousands of members seamlessly.
- **Progressive Web App (PWA) Infrastructure (`manifest-member.json`, `sw.js`, `offline.html`)**:
  - Dedicated PWA web app manifest (`manifest-member.json`) configured with standalone display mode, theme colors, and icons.
  - Service Worker (`sw.js`) precaches static shell assets and icons while applying strict `NEVER_CACHE` rules for `/member/` endpoints and live financial APIs to guarantee real-time balance accuracy.
  - Automatic install prompt: Custom Alpine.js install banner appears dynamically when `beforeinstallprompt` fires, allowing one-tap installation to device home screens.
- **Luxury Member Dashboard Component (`MemberDashboard.php`, `member-dashboard.blade.php`, `layouts/member.blade.php`)**:
  - **Tab 1: Digital Membership Card**: Embossed luxury club card UI displaying cardholder name, category, membership ID, valid dates, and high-contrast digital QR token for counter scanning.
  - **Tab 2: Prepaid Wallet & Ledger Statement**: Real-time server-authoritative balance in Rupees (backed by signed integer paise) with itemized audit statement of recent recharges, POS spends, and reversals.
  - **Tab 3: Sports & Facility Plans**: Active subscriptions for the Indoor Badminton Wooden Court and Health Club Gym, including start date, valid until date, and remaining validity countdown.
  - **Tab 4: Room & Court Bookings**: Real-time list of upcoming badminton court slot bookings and accommodation room stays with status indicators.
  - **Tab 5: Itemized Bills & Receipt Modal**: Complete historical billing ledger with interactive **"Receipt"** action opening official club invoice breakdown (quantities, rates, taxes, payments) with strict anti-IDOR authorization.
  - **Tab 6: Family Dependents**: Enrolled spouse, children, and parents with visual wallet spend authorization badges.
- **Unified Portal Gateway (`welcome.blade.php`)**:
  - Modernized entry portal featuring 4 gateway cards: Member Portal (PWA Ready), Counter POS Station (Staff), Visual Room Rack (Reception), and Club Administration (Management/GL).

### 2. Automated Test Verification
- Created `tests/Feature/MemberPortalTest.php` covering 12 comprehensive feature tests:
  - `test_member_login_screen_renders_successfully`: Verified login form, inputs, and activation link.
  - `test_member_can_login_with_membership_number_and_pin`: Verified auth via membership code.
  - `test_member_can_login_with_registered_phone`: Verified auth via 10-digit mobile.
  - `test_member_login_fails_with_invalid_credentials`: Verified credential failure handling.
  - `test_suspended_or_inactive_member_cannot_login`: Verified status check blocks non-active members.
  - `test_member_activation_with_membership_number_and_registered_phone`: Verified PIN configuration flow.
  - `test_member_activation_fails_with_mismatched_phone`: Verified security rejection on mismatched phone.
  - `test_unauthenticated_user_redirected_to_member_login_on_protected_routes`: Verified guest redirection.
  - `test_member_cannot_access_staff_pos_or_admin_panel`: Verified least-privilege boundary.
  - `test_member_can_view_dashboard_with_tabs_and_digital_card`: Verified all 6 reactive tabs render.
  - `test_member_cannot_view_another_members_itemized_bill`: Verified strict anti-IDOR enforcement.
  - `test_member_can_logout`: Verified member session invalidation.
- **Test Results**: All **12 member tests passed (54 assertions)**.
- **System-Wide Full Suite**: Executed `php artisan test` across all 24 feature and unit test suites:
  - **153 passed, 873 assertions, 0 errors, 0 failures**.

---

## Phase 7: Malabar Club Executive Command Dashboard (Delivered)

### 1. Scope of Implementation
- **Removal of Default Boilerplate (`AdminPanelProvider.php`)**:
  - Removed generic `AccountWidget` (redundant sign out button) and `FilamentInfoWidget` (Filament framework documentation links).
- **Custom Dashboard Architecture (`Dashboard.php`)**:
  - Implemented `App\Filament\Pages\Dashboard` extending Filament Dashboard with a responsive 2-column layout (`getColumns(): int|array => 2`).
  - Configured zero-latency server-side rendering (`protected static bool $isLazy = false`) across all custom widgets, eliminating placeholder flash and enabling direct DOM inspection in automated tests.
- **Suite of 6 Real-Time Executive Widgets**:
  1. **Executive Stats Overview (`ExecutiveStatsOverviewWidget.php`)**:
     - *Today's Billed Sales*: Total net billed sales across all active departments with 7-day sparkline.
     - *Fresh Collections Today*: Real cash in till drawer + bank UPI/card settlements (excluding prepaid wallet redemptions) with 7-day trend.
     - *Prepaid Member Deposits (A/C 2010 Liability)*: Total member advance balance held safely in the club's central ledger with active funded account count.
     - *Club Footfall Today*: Real-time count of active transacting members + registered paid guests.
  2. **Front-Desk & Facilities Pulse (`FrontDeskPulseWidget.php`, `front-desk-pulse.blade.php`)**:
     - *Accommodation Grid (Rooms 101–105)*: Color-coded live status pills (Vacant, Occupied with occupant name, Reserved Arriving Today, Cleaning Needed, Maintenance) with 1-click link to Visual Room Rack.
     - *Indoor Badminton Court*: Total slots, booked slots, capacity utilization bar, and next upcoming reservation details with link to Daily Court Schedule.
  3. **Departmental Turnover Distribution (`DepartmentRevenueChart.php`)**:
     - Doughnut chart breaking down today's revenue across Bar & Lounge, Kitchen (Food Contractor), Accommodation (Rooms), Badminton & Gym (Sports), and Guest Entry Fees.
  4. **Contractor & Counter Shifts Monitor (`ContractorAndShiftStatusWidget.php`, `contractor-and-shift-status.blade.php`)**:
     - *Kitchen Contractor*: Today's qualified food sales accumulated in real-time, plus latest settlement batch status and net payable amount.
     - *Active Shifts*: Currently open till stations, cashiers on duty, opening floats, and stale unclosed shift warnings.
  5. **System Integrity & Tally Bridge (`SystemIntegrityWidget.php`, `system-integrity.blade.php`)**:
     - *Tally Prime Outbox*: Synced today, pending queue, and failed retry counts.
     - *Double-Entry General Ledger Balance*: Continuous mathematical verification that total debits equal total credits (`JournalLine::sum('debit_paise') === JournalLine::sum('credit_paise')`).
     - *Audit Safeguards*: Current open fiscal period, captured unposted payments (Scenario F07), and month-to-date GST collections.
  6. **Live Operational Activity Feed (`RecentActivityTableWidget.php`)**:
     - Real-time tabular stream of the last 8 club transactions (Time, Bill Reference, Member/Customer name & ID, Department badge, Net Amount in monospace, Payment Tender, and Status).

### 2. Automated Test Verification
- Created `tests/Feature/AdminDashboardWidgetsTest.php` covering 7 tests:
  - `test_admin_sees_complete_executive_dashboard_suite` — Verified complete executive widget suite render on dashboard.
  - `test_manager_sees_executive_dashboard` — Verified manager access.
  - `test_frontline_reception_sees_facility_pulse_while_financial_widgets_respect_role` — Verified least privilege scoping (reception sees room/court pulse, financial widgets hidden).
  - `test_executive_stats_widget_computes_sales_collections_and_liability` — Verified stats calculations and integrity.
  - `test_front_desk_pulse_widget_loads_room_and_court_data` — Verified room 101-105 data and 16 court slots.
  - `test_contractor_and_shift_widget_tracks_qualified_food_and_open_registers` — Verified food sales and register tracking.
  - `test_system_integrity_widget_verifies_gl_balancing_and_outbox` — Verified GL balance check and Tally outbox telemetry.
- Updated `tests/Feature/AdminPanelTest.php` asserting that the executive dashboard and all widgets load with status 200.

### 3. Runtime Stabilization & Error Resolution (Fix for /admin)
- **Problem**: Accessing `http://127.0.0.1:8080/admin` produced an HTTP 500 error due to:
  1. Undefined relationship `cashier` on `App\Domain\Billing\Models\Shift` when eager loaded by `ContractorAndShiftStatusWidget`.
  2. `ucfirst()` type error in `RecentActivityTableWidget` where `tender_type` from `paymentReceipts` is cast to an enum (`TenderType`) rather than a primitive string.
  3. `Facility` model missing `status` column in `FrontDeskPulseWidget`.
- **Resolution**:
  - Added `public function cashier(): BelongsTo { return $this->user(); }` to `Shift.php`.
  - Updated `RecentActivityTableWidget.php` column closures to check `instanceof \BackedEnum` and invoke `TenderType::label()` or `->value` safely.
  - Updated `FrontDeskPulseWidget.php` to derive default room status from `$room->is_active`.
  - Hardened `contractor-and-shift-status.blade.php` to unpack settlement batch status safely.
- **Verification**:
  - Direct execution against development database (`malabar_club`) confirmed HTTP 200 OK with all 6 widgets rendered.
  - Full test suite execution (`php artisan test`) passed with **160 tests, 917 assertions, 0 errors, 0 failures**.

---

## Phase 8: Enterprise Master Data Protection & Lifecycle Integrity (Delivered)

### 1. Architectural Safeguards Implemented
- **`Member` Model Deletion Guard (`Member.php`)**:
  - Implemented Eloquent `booted()` deleting lifecycle guard preventing hard deletion of members who have:
    1. Positive prepaid wallet balance (prevents orphaning real unrefunded club deposits).
    2. Prepaid ledger transaction history (protects audit trail).
    3. Historical club bills (protects GST and sales recognition audit trail).
    4. Facility subscriptions, court bookings, or room reservations.
  - For pristine newly created records with zero transactions, safely cascades polymorphic wallet accounts and RFID cards so zero orphaned records remain.
- **Automated RFID Card Invalidation on Departure (`Member.php`)**:
  - Added `transitionStatus(MemberStatus|string $status, ?string $reason = null)`.
  - When transitioning away from Active (`suspended`, `resigned`, `expired`, `terminated`), all active RFID cards are automatically blocked in real-time (`status = 'blocked'`), immediately terminating turnstile and door reader access.
- **Expanded `MemberStatus` Enum (`MemberStatus.php`)**:
  - Added `RESIGNED = 'resigned'` with `label()` helper.
- **Admin Panel Protection (`MembersTable.php` & `EditMember.php`)**:
  - **Removed `DeleteBulkAction`**: Raw bulk hard-deletion of member master entities is permanently prohibited.
  - **Added Enterprise Bulk Actions**:
    - *Suspend Selected*: Prompts for administrative reason, sets status to `suspended`, blocks all active RFID cards.
    - *Mark Selected as Resigned*: Sets status to `resigned`, blocks all active RFID cards.
    - *Reactivate Selected*: Restores eligible members to `active`.
  - **Added Record Lifecycle Action (`changeStatus`)**: Staff modal to change member status with audit trail notation.
  - **Hardened Single-Row Delete**: Only visible for newly created member profiles with zero financial history or bookings.
- **System-Wide Financial Record Protection**:
  - `ShiftsTable.php` & `EditShift.php`: Removed `DeleteBulkAction` and `DeleteAction` (cash drawer reconciliation records cannot be deleted).
  - `BillsTable.php` & `EditBill.php`: Removed `DeleteAction` (posted bills are immutable).
  - `JournalHeaders`: Removed `DeleteAction` (general ledger journals are immutable).
  - `FinancialPeriods`: Removed `DeleteAction` (fiscal accounting periods are permanent).
  - `TariffsTable.php`: Removed `DeleteBulkAction` (tariffs maintain historic rate integrity).
  - `RoomReservations` & `CourtBookings`: Guarded deletion to block deleting paid or billed reservations.

### 2. Automated Test Verification
- Created `tests/Feature/MemberLifecycleAndDeletionGuardTest.php` with 6 automated tests:
  1. `test_member_with_positive_wallet_balance_cannot_be_deleted` — Verified `DomainException`.
  2. `test_member_with_wallet_transaction_history_cannot_be_deleted` — Verified `DomainException`.
  3. `test_member_with_posted_bills_cannot_be_deleted` — Verified `DomainException`.
  4. `test_member_with_court_booking_cannot_be_deleted` — Verified `DomainException`.
  5. `test_pristine_member_with_zero_history_can_be_deleted_without_orphaning_wallet_or_card` — Verified clean polymorphic deletion.
  6. `test_transitioning_status_to_suspended_or_resigned_automatically_blocks_rfid_cards` — Verified turnstile card revocation.
- **Full Test Suite**: Executed `php artisan test`:
  - **166 passed, 935 assertions, 0 errors, 0 failures**.

---

## Phase 9: Dynamic Configuration & Operational Data Binding (Delivered)

### 1. Architectural Scope & Audit Findings
- **Centralized Club Configuration (`config/club.php`)**:
  - Bound club organization identity (name, short name, location, postal address, phone, official email, GSTIN, and PAN) to centralized environment variables (`CLUB_NAME`, `CLUB_LOCATION`, `CLUB_GSTIN`, etc.) with sensible Malabar Club defaults.
- **Dynamic Tariff & Inventory Resolution**:
  - *Accommodation Visual Room Rack (`RoomRackPage.php` & `room-rack.blade.php`)*: Replaced hardcoded rates and room ranges with database queries to active room tariffs (`ROOM-DELUXE`, `ROOM-SUITE`, `ROOM-STANDARD`), adding reactive tariff recalculation when the room selection changes.
  - *Indoor Badminton Court Schedule (`CourtSchedulePage.php`)*: Replaced hardcoded session fees with dynamic tariff queries to `GUEST-SPORTS-DAY`.
- **Front-Desk Pulse & Executive Command Telemetry**:
  - *Front-Desk Pulse (`FrontDeskPulseWidget.php` & `front-desk-pulse.blade.php`)*: Dynamically calculates deluxe room inventory count, active room codes, and facility names.
  - *Footfall Calculation (`ExecutiveStatsOverviewWidget.php`)*: Combined live door access telemetry (`AccessEvent::granted`) with transactional member counts for accurate crowd presence.
  - *Statutory Information (`system-integrity.blade.php`)*: Bound MTD GST panel to the club's registered GSTIN.
  - *Contractor Settlements (`contractor-and-shift-status.blade.php`)*: Dynamically rendered catering contractor business names.
- **Member Portal & Digital RFID Card (`member-dashboard.blade.php`)**:
  - Replaced static standing pills with reactive status-driven indicators (`Active`, `Suspended`, `Resigned`, `Expired`).
  - Generalized room and facility references across member booking tabs.
  - Bound digital cards, receipts, statements, and brand logos across layouts to `config('club.*')`.
- **Thermal Receipt Printer (`ThermalReceiptPrinter.php`)**:
  - Bound 80mm ESC/POS header and footer generation to `config('club.name')` and `config('club.location')`.

### 2. Automated Test Verification
- Executed `php artisan test`:
  - **166 passed, 935 assertions, 0 errors, 0 failures**.

---

## Admin Dashboard Navigation & Label Simplification

### 1. Changes Implemented
- **Filament Admin Navigation & Page Title**:
  - Simplified the navigation menu item and header title in `app/Filament/Pages/Dashboard.php` from `"Malabar Club Executive Dashboard"` to clean `"Dashboard"`.
  - Set `protected static ?string $title = 'Dashboard';` and `protected static ?string $navigationLabel = 'Dashboard';`.
- **Test Suite Alignment**:
  - Updated `tests/Feature/AdminDashboardWidgetsTest.php` to verify the page renders `"Dashboard"` as its title and navigation item.
  - Updated `tests/Feature/AdminPanelTest.php` to assert `$response->assertSee('Dashboard')`.

### 2. Automated Test Verification
- Executed `php artisan test`:
  - **166 passed, 935 assertions, 0 errors, 0 failures** across the entire application test suite.

---

## Admin Dashboard Layout & Two-Tabbed Departmental Revenue Enhancement

### 1. Architectural Scope & Layout Improvements
- **Full-Width "Front-Desk & Facilities Pulse" (`FrontDeskPulseWidget.php` & `front-desk-pulse.blade.php`)**:
  - Expanded widget from half-width (`columnSpan = 1`) to full container width (`columnSpan = 'full'`).
  - Accommodation cards for Rooms 101–105 now span across 5 spacious, responsive columns on desktop, providing ample room for room codes, status badges, and guest/occupant names without truncation or text bleeding.
  - Badminton Wooden Court schedule section now utilizes a clean 2-column subgrid on wide displays: capacity bar on the left and live booking status on the right.
- **Full-Width Two-Tabbed "Departmental Revenue" (`DepartmentRevenueChart.php` & `department-revenue-widget.blade.php`)**:
  - Positioned directly below the Facilities Pulse widget with `sort = 3` and `columnSpan = 'full'`.
  - Built an interactive, responsive two-tab view powered by Alpine.js:
    - **Tab 1 (Graph View)**: Features an interactive Chart.js Doughnut visualization alongside 5 detailed department metric cards (Bar & Lounge, Kitchen Food Contractor, Guest Rooms, Badminton & Sports, Guest Passes) displaying ₹ revenue, share %, and bill counts with matching color accents.
    - **Tab 2 (Tabular View)**: Displays an audit-grade data table showing Department name & category code, General Ledger/Settlement account mapping, transaction count, exact revenue (₹ in paise accuracy), and percentage share progress bars, complete with a reconciled total summary footer.
    - Preserved 30-second live polling (`wire:poll.30s="updateChartData"`) and automated fallback to a 7-day preview when no morning bills have been posted yet.

### 2. Automated Test Verification
- Updated `tests/Feature/AdminDashboardWidgetsTest.php` with:
  - Assertions for `"Departmental Revenue"`, `"Graph View"`, and `"Tabular View"`.
  - Verification of `columnSpan = 'full'` on both `FrontDeskPulseWidget` and `DepartmentRevenueChart`.
  - Full data integrity checks for `getTableData()` (5 department rows, total amounts, and bill counts).
- Executed full test suite:
  - **167 passed, 946 assertions, 0 errors, 0 failures** across all application feature and domain tests.

---

## Visual Room Rack Booking Modal Exception Fix

### 1. Root Cause & Problem Analysis
- In `resources/views/filament/pages/room-rack.blade.php`, the modal select dropdown for registered guests queried:
  `App\Domain\Access\Models\Guest::with('person')->orderBy('name')->take(30)->get()`
- The `guests` database table does not contain a `name` column; guest identities are relational (`person_id` foreign key pointing to `persons.first_name` and `persons.last_name`).
- When the administrator clicked **➕ Book Room**, Livewire opened the modal (`$showNewBookingModal = true`), triggering a re-render that crashed with:
  `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'name' in 'order clause'`.

### 2. Changes Implemented
- **Guest and Person Domain Model Accessors**:
  - Added `getNameAttribute()` to [`Person.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Domain/Membership/Models/Person.php) returning `$this->full_name`.
  - Added `getNameAttribute()` and `getPhoneAttribute()` to [`Guest.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Domain/Access/Models/Guest.php) delegating seamlessly to the associated `Person` record.
- **Visual Room Rack Blade (`room-rack.blade.php`)**:
  - Replaced `orderBy('name')` with `latest('id')->take(50)` on `Guest::with('person')` and formatted each option as `$g->name ($g->phone)`.
- **Visual Room Rack Page Controller (`RoomRackPage.php`)**:
  - Added eager loading for `guest.person` and `sponsorMember.person` to avoid N+1 queries during modal and rack rendering.
  - Dynamically defaulted `guest_type` to `'guest'` if registered guests exist, or `'member'` if only members exist.
  - In `confirmNewBooking()`, validated `guest_id` or `member_id` conditionally based on the chosen occupant type.
- **Automated Test Coverage**:
  - Added `test_admin_can_open_and_confirm_room_booking_modal` in `tests/Feature/FrontDeskAndOperationalWorkflowsTest.php` testing modal opening, Livewire rendering, and booking confirmation.
  - Executed full test suite: **168 passed, 950 assertions, 0 errors, 0 failures**.

---

## Admin Panel Tally LAN Configuration & Real-Time Connection Diagnostic

### 1. Requirements & Architecture
- Replaced the need for manual `.env` file editing with a browser-based configuration panel for setting the on-premise LAN IP, port, and company name of Tally Prime.
- Added persistent `system_settings` table and `SystemSetting` model with caching and key-value storage.
- Integrated dynamic endpoint resolution in `TallyPrimeHttpConnector`:
  - Automatically resolves `http://<LAN_IP>:<PORT>` from `SystemSetting::get('tally_host')` and `SystemSetting::get('tally_port')`.
- Added dynamic service container binding in `AppServiceProvider`:
  - Resolves `TallyPrimeHttpConnector` or `SimulatorTallyConnector` based on the configured driver mode (`live` vs `simulator`).
- Added real-time probe diagnostic:
  - `TallyPrimeHttpConnector::testConnection($host, $port)` pings the target LAN endpoint with an official Tally XML probe and returns structured feedback.

### 2. UI Enhancements in Admin Panel (`/admin/integration-status`)
- Added **Tally Prime LAN Network Settings** card inside the Integrations & Hardware page:
  - **Tally Computer LAN IP / Hostname** (e.g. `192.168.1.50` or `127.0.0.1`)
  - **Port** (default `9000`)
  - **Target Company Name** (e.g. `Malabar Club`)
  - **Integration Driver Mode** (toggle between `Live Tally Prime HTTP` and `Development Simulator`)
  - **Save Configuration** button (`saveTallySettings`)
  - **Test Tally Connection** button (`testTallyConnection`) with instant visual feedback and step-by-step Tally connectivity setup instructions.

### 3. Automated Test Verification
- Added dedicated test suite `tests/Feature/TallyConfigurationTest.php` verifying:
  - Page access and form rendering for administrators.
  - Saving settings updates `SystemSetting` and re-resolves the connector endpoint.
  - Test connection handles live responses, XML confirmations, and connection timeouts cleanly.
  - Dynamic switching between `simulator` and `live` drivers.
- Executed full test suite:
  - **173 passed, 971 assertions, 0 errors, 0 failures** across the entire application.

---

## Membership Category Administration & Inline Quick-Create Enhancement

### 1. Requirements & Architecture
- Elevated Membership Categories from a pre-seeded static state to a fully configurable domain resource.
- Created dedicated Filament Resource [`MembershipCategoryResource`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Resources/MembershipCategories/MembershipCategoryResource.php) under **Membership & Guest Desk** (`/admin/membership-categories`).
- Form schema includes:
  - **Category Code**: Uppercase short identifier (e.g. `HON`, `ASSOC`, `SPORTS`), validated unique.
  - **Category Title**: Formal designation (e.g. `Honorary Member`, `Senior Citizen Member`).
  - **Annual Subscription Fee**: Exact currency input in ₹, dehydrated into paise integers.
  - **Voting Rights**: Boolean toggle for General Body / AGM voting eligibility.
  - **Admissions Active Status**: Boolean toggle to control availability for new admissions.
- Table view includes:
  - Category Code badge, Designation, Annual dues, Voting rights indicator, Enrolled member count badge (`counts('members')`), and Active status.
  - Deletion protection: Strict database check halts deletion if any members are currently assigned to the category.
- **Inline Quick-Create on Member Registration**:
  - Enhanced [`MemberForm.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Resources/Members/Schemas/MemberForm.php) select dropdown with `createOptionForm` and `createOptionUsing`. Staff can click `+` directly beside the Membership Category dropdown on the member creation form to register a new category in a popup modal without leaving the registration page.

### 2. Automated Test Verification
- Added test suite `tests/Feature/MembershipCategoryManagementTest.php` verifying:
  - Admin and Manager access control; cashier 403 Forbidden.
  - Category creation with code, name, annual fee, and voting rights.
  - Editing category details.
  - Deletion prohibited when members are linked; allowed when unused.
- Updated `tests/Feature/AdminPanelTest.php` to include `/admin/membership-categories`.
- Executed full test suite:
  - **180 passed, 997 assertions, 0 errors, 0 failures** across all application feature and domain tests.

---

## Universal Searchable Combo Boxes (Forms with >= 10 Expected Items)

### 1. Application-Wide Audit & Enhancements
- Audited every form across Filament resources, table filters, custom Livewire pages, and modal views.
- **Filament Resources & Filter Selects**:
  - `CourtBookingForm.php`: Added `->searchable()` to `start_time` (15 hourly slots) and `->searchable()->preload()` to `facility_id`.
  - `PurchaseReceiptsTable.php`: Added `->searchable()->preload()` to `supplier_id` table filter.
  - `MembersTable.php`: Added `->searchable()->preload()` to `membership_category_id` table filter.
  - `TariffForm.php` & `TariffsTable.php`: Added `->searchable()` to `department_code` selector and filter.
  - `BillsTable.php` & `JournalHeadersTable.php`: Added `->searchable()` to `department_code` and `source_type` filters.
  - Verified existing `->searchable()->preload()` on all high-cardinality selects: `member_id`, `supplier_id`, `inventory_item_id`, `account_id`, `tariff_id`, `user_id`, `guest_id`, `sponsor_id`, `sponsor_member_id`, `counter_id`.
- **Reusable Blade Component [`<x-searchable-combobox>`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/resources/views/components/searchable-combobox.blade.php)**:
  - Created high-performance Alpine.js searchable combobox with:
    - Dedicated autofocus search textbox at the top of the dropdown with instantaneous live client-side filtering.
    - Match count indicator and clear (`✕`) button.
    - Keyboard navigation (Enter to select, Esc to close).
    - Synchronized underlying `<select id="..." wire:model="...">` for seamless Livewire binding, accessibility, and automated testing compatibility.
- **Custom Front-Desk Operations Forms Enhanced**:
  - `CourtSchedulePage` (`resources/views/filament/pages/court-schedule.blade.php`):
    - Converted member selection combo box into `<x-searchable-combobox>` with live search input filtering among 460+ club members by name, membership number, or mobile number.
  - `RoomRackPage` (`resources/views/filament/pages/room-rack.blade.php`):
    - Replaced native `<select>` dropdowns in the Front Desk Room Reservation modal with `<x-searchable-combobox>` for:
      - Accommodation Room (`newBooking.facility_id`)
      - Guest selection (`newBooking.guest_id`, 100+ items)
      - Member selection (`newBooking.member_id`, 460+ items)
      - Sponsoring Member (`newBooking.sponsor_member_id`, 460+ items)

### 2. Automated Test Verification
- Added dedicated test suite `tests/Feature/SearchableComboBoxesTest.php`:
  - Verified all Filament resource creation pages render correctly without exception.
  - Verified `CourtSchedulePage` modal displays the searchable member combo box with search input.
  - Verified `RoomRackPage` modal displays searchable combo boxes with search inputs for rooms, guests, members, and sponsors.
- Executed full test suite:
  - **183 passed, 1016 assertions, 0 errors, 0 failures**.
- Compiled frontend assets cleanly with `npm run build` (415ms).

---

## Executive & Admin Progressive Web App (PWA) & Mobile Native UX

### 1. Requirements & Architecture
- Elevated the administrative interface to an installable Progressive Web App (PWA) optimized for mobile phones and tablets.
- **Web App Manifest ([`manifest-admin.json`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/public/manifest-admin.json))**:
  - Name: "Malabar Club Administration", short_name: "Malabar Admin"
  - Display: `standalone` (removes browser URL bar and back/forward chrome)
  - Color palette: Slate-900 `#0f172a` and Amber Gold `#d97706`
  - High-resolution icons: `icon-192.png`, `icon-512.png`, `apple-touch-icon.png`, and scalable SVGs.
- **Service Worker ([`sw.js`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/public/sw.js))**:
  - Updated precache cache name (`malabar-club-static-v2`) and precached admin manifest and touch icons.
  - Retained strict non-negotiable financial safeguard: live-only for API, Filament, and POS mutations with clean offline fallback (`offline.html`).
- **Filament Head & Mobile Dock Injection ([`AdminPanelProvider.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Providers/Filament/AdminPanelProvider.php))**:
  - Registered [`pwa-head.blade.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/resources/views/filament/components/pwa-head.blade.php) via `PanelsRenderHook::HEAD_END` for manifest link, iOS Safari `apple-mobile-web-app-capable`, `black-translucent` status bar, and service worker registration.
  - Registered [`pwa-mobile-nav.blade.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/resources/views/filament/components/pwa-mobile-nav.blade.php) via `PanelsRenderHook::BODY_END`:
    - **Live Network Status Toast**: Automatically detects Wi-Fi/cellular connection loss and recovery, notifying staff without breaking state.
    - **Install Prompt (A2HS) Banner**: Clean dismissable prompt on mobile devices with native install trigger on Android and Step-by-Step "Add to Home Screen" instructions on iOS Safari.
    - **Native Mobile Bottom Navigation Dock (`lg:hidden`)**: Fixed frosted dock at the bottom with touch destinations for **Dashboard**, **Room Rack**, **Badminton Courts**, **POS Terminal**, and **Full Menu** (which triggers Filament's native `$store.sidebar.open()`).
- **Mobile & Tablet Touch Styling ([`app.css`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/resources/css/app.css))**:
  - Safe-area inset clearance for iPhone notch / Dynamic Island / Android navigation bars (`env(safe-area-inset-bottom)`).
  - Minimum 16px font-size on mobile inputs (`max-width: 768px`) preventing iOS Safari auto-zooming on focus.
  - Tap highlight removal (`-webkit-tap-highlight-color: transparent`) and standalone overscroll containment.
  - Clearance padding (`calc(4.75rem + env(safe-area-inset-bottom))`) on `.fi-main` so mobile dock never obscures page content.

### 2. Automated Test Verification
- Added dedicated test suite `tests/Feature/AdminPwaTest.php`:
  - Verified manifest link, apple meta tags, and service worker registration in admin panel.
  - Verified mobile navigation dock destinations (Dashboard, Rooms, Badminton, POS, Menu).
  - Verified valid JSON structure and `standalone` mode in `manifest-admin.json`.
  - Verified all PNG/SVG icons exist on disk.
- Executed full test suite:
  - **187 passed, 1043 assertions, 0 errors, 0 failures**.
- Compiled frontend assets cleanly (`npm run build` in 1.15s).

---

## Contractors & Catering Management Resource

### 1. Requirements & Architecture
- Exposed a dedicated administrative CRUD resource [`ContractorResource`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Resources/Contractors/ContractorResource.php) under **Procurement & Food Contractor** (`/admin/contractors`).
- Form Schema ([`ContractorForm.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Resources/Contractors/Schemas/ContractorForm.php)):
  - **Contractor & Commercial Identity**: Unique Code (e.g. `CONT-FOOD-01`, `CONT-BANQUET-02`), Business Name (e.g. `Malabar Spices Catering & Hospitality`), Assigned Operating Department (`food`, `bar`, `maintenance`, `security`, `general`), Managing Contact Person.
  - **Communication**: Contact phone/mobile, official billing email.
  - **Banking & Payout Details**: Bank Name, Beneficiary Account Number, Bank IFSC Code (used for settlement batch payouts).
  - **Commercial Terms**: Commission / Concession rate % (default `0.00%` per Rule D15), Active for settlements toggle.
- Table Schema ([`ContractorsTable.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Resources/Contractors/Tables/ContractorsTable.php)):
  - Code badge, Contractor name, Department badge, Contact person, Mobile number, Bank details summary (`Bank • Account # (IFSC)`), Commission %, Batches count badge (`counts('settlementBatches')`), and Active icon status.
  - Filters: Status filter (`is_active`) and Department filter (`department_code`).
  - Actions: Edit action, Direct link to view historical settlement batches for that contractor, and Safe Delete action.
  - Strict Deletion Safeguard: Prevents deletion if any historical settlement batches are attached to the contractor; staff must deactivate the profile instead to maintain accounting audit integrity.
- **Contractor Settlements Integration**:
  - Re-ordered navigation under **Procurement & Food Contractor**: Supplier Receipts (`1`), Contractors & Caterers (`2`), Contractor Settlements (`3`).
  - Added `SelectFilter` for `contractor_id` on [`ContractorSettlementBatchesTable.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Resources/ContractorSettlements/Tables/ContractorSettlementBatchesTable.php).

### 2. Automated Test Verification
- Added dedicated test suite `tests/Feature/ContractorManagementTest.php` (7 tests):
  - Admin and Manager access permitted; Cashier denied (403 Forbidden).
  - Contractor registration with code, name, bank account, and commission.
  - Editing bank details and phone numbers.
  - Deletion blocked when settlement batches exist.
  - Deletion permitted when contractor has zero financial transactions.
- Updated `tests/Feature/AdminPanelTest.php` to include `/admin/contractors`.
- Executed full test suite:
  - **194 passed, 1068 assertions, 0 errors, 0 failures**.
- Compiled frontend assets cleanly with `npm run build` (471ms).

---

## System & Clubhouse Settings Section

### 1. Requirements & Architecture
- Centralized administrative settings hub [`SystemSettingsPage`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Pages/SystemSettingsPage.php) located under **Club Master Setup** (`/admin/settings`).
- Backed by high-performance key-value storage [`SystemSetting`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Domain/Shared/Models/SystemSetting.php) with database persistence and memory caching (with automatic cache invalidation on write).
- Organized into 5 dedicated configuration tabs:
  1. **🏢 Club Identity & Statutory Invoicing**: Legal Club Name, Tagline, Registered Postal Address, Office Phone, Official Billing Email, State GSTIN (with auto-uppercase), Income Tax PAN, and Statutory Receipt Footer Note (e.g. alcohol health statutory warning).
  2. **💳 Billing & Prepaid Wallet Policies**: Default Cashier Opening Float (₹2,000.00), Minimum Wallet Recharge (₹500.00), Maximum Single Recharge Cap (₹50,000.00), Lost Card Replacement Fee (₹150.00), and Split-Tender Toggle.
  3. **🏸 Front Desk & Facilities Operating Rules**: Standard Accommodation Check-in (14:00) and Check-out (12:00) times, Badminton Court Opening (06:00) and Closing (21:00) hours, Maximum Advance Booking Window (7 days), and Configurable Guest Entry Fees (Bar: ₹200.00, Badminton: ₹100.00, Gym: ₹150.00).
  4. **📊 Tally Prime LAN Bridge**: Driver mode (`simulator` vs. `real_http`), Local Server Host IP, Port (default 9000), Target Company Name, and live interactive **Connection Test Probe** with instant diagnostic feedback.
  5. **🖨️ Receipt Printing & Hardware**: Thermal Paper Width (80mm standard / 58mm compact), Auto-print Receipt on checkout, Member Wallet Balance display toggle on receipt, and Statutory CGST/SGST breakdown display toggle.
- **Consumer Wiring Across the Application**:
  - `CounterPos.php`: Automatically initializes cashier opening drawer float from `SystemSetting::get('default_opening_float', 2000.00)`.
  - `bill-detail-modal.blade.php`: Injects dynamic legal name, address, GSTIN, and statutory footer note from `SystemSetting`.
  - `MembersTable.php`: Dynamically displays the configured statutory lost card replacement fee during card replacement modal.
  - Reset to Defaults capability (`resetToDefaults()`) for immediate recovery of factory settings.

### 2. Automated Test Verification
- Created dedicated test suite `tests/Feature/SystemSettingsTest.php` (11 tests):
  - Admin and Manager access permitted (200 OK); Cashier and Reception staff denied (403 Forbidden).
  - Tab switching between Identity, Billing, Facilities, Tally, and Hardware.
  - Club identity updates saved to database and verified via `SystemSetting::get()`.
  - Billing policies saved and verified.
  - Facilities and front-desk timings saved and verified.
  - Tally LAN configuration saved and connection probe tested.
  - Hardware and receipt printing preferences saved and verified.
  - Factory default restoration verified.
  - Validation rules enforced (required fields, positive floats, port range 1-65535).
- Updated `tests/Feature/AdminPanelTest.php` to include `/admin/settings` in admin resources and cashier restriction checks.
- Executed full test suite:
  - **205 passed, 1130 assertions, 0 errors, 0 failures**.
- Compiled frontend assets cleanly with `npm run build` (375ms).

---

## Past Badminton Court Booking Restriction

### 1. Requirements & Architecture
- **Domain Service Enforcement ([`CourtBookingService.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Domain/Facility/Services/CourtBookingService.php))**:
  - `bookCourt()` strictly checks if the target slot start time has already passed (`Carbon::parse("{$bookingDate} {$startTime}")->isPast()`).
  - Throws `InvalidArgumentException("Cannot book a badminton court slot for a past time (...)")` unless explicitly called with `allowPast: true` (reserved for historical imports or fixture generation).
  - Added helper method `isPastSlot(string $bookingDate, string $startTime): bool`.
- **Visual Court Schedule Grid ([`CourtSchedulePage.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Pages/CourtSchedulePage.php) & [`court-schedule.blade.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/resources/views/filament/pages/court-schedule.blade.php))**:
  - Every hourly slot computes `is_past` dynamically based on the selected date and current time.
  - Past unbooked slots:
    - Status badge renders `Time Expired` in slate tones instead of `Open`.
    - Body shows `⏳ Slot Elapsed (Past Time, Booking Closed)`.
    - Action button is disabled (`cursor-not-allowed`, `disabled`) with label `Booking Closed`.
  - Past booked slots:
    - Status badge renders `Concluded`.
    - Cancel action button is disabled with label `Play Concluded`.
  - Server-side guards: `openBookModal()` and `confirmBooking()` reject past slots immediately with a danger notification.
- **Filament Resource Form ([`CourtBookingForm.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Resources/CourtBookings/Schemas/CourtBookingForm.php) & [`CreateCourtBooking.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Resources/CourtBookings/Pages/CreateCourtBooking.php))**:
  - `DatePicker` enforces `minDate(today())`.
  - Hourly slot `Select` runs custom validation closure ensuring `booking_date + start_time` is in the future.
  - `handleRecordCreation()` validates future slot before invoking service.

### 2. Automated Test Verification
- Added dedicated test suite `tests/Feature/PastCourtBookingRestrictionTest.php` (10 tests):
  - Service blocks booking for past dates.
  - Service blocks booking for past time slots earlier today.
  - Service permits future time slots.
  - Schedule page accurately marks past slots (`is_past = true`).
  - Schedule page blocks `openBookModal()` for past slots.
  - Schedule page allows `openBookModal()` for future slots.
  - Schedule page blocks `confirmBooking()` for past slots.
  - View renders disabled "Booking Closed" / "Time Expired" elements for past slots.
  - Resource form validation rejects past slots on today's date.
  - Resource form validation accepts future slots.
- Executed full application test suite:
  - **215 passed, 1155 assertions, 0 errors, 0 failures**.

---

## Comprehensive Bills & Invoices Form Implementation

### 1. Requirements & Architecture
- **Complete Form Schema ([`BillForm.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Resources/Bills/Schemas/BillForm.php))**:
  - **Immutable Status Banner**: Distinct visual notification for posted/settled/refunded bills indicating they are locked under statutory accounting rules.
  - **Invoice Header & Department**: Auto-generated bill number, fiscal business date, operating department (`food`, `bar`, `card_room`, `accommodation`, `sports`, `general`), and active counter shift.
  - **Customer & Account Identification**: Toggle between Primary Club Member and Registered Club Guest, searchable selection with real-time dynamic preview card showing member category, active RFID card UID, phone, and live prepaid ledger balance in ₹.
  - **Line Items Repeater**:
    - Preset tariff catalogue selector that auto-populates item description, department, rate in ₹, and statutory GST rate (0%, 5%, 12%, 18%, 28%).
    - Custom item entry support.
    - Live line total and tax calculations per row.
  - **Financial Summary**: Real-time reactive preview of Taxable Subtotal, Statutory GST (CGST + SGST), Net Invoice Total, and invoice narration notes.
  - **Immediate Settlement**: Allows staff to settle and post the bill immediately on creation with tender selection (`prepaid` wallet, `cash` drawer, `digital` UPI/Card) enforcing the non-negotiable no-credit policy (Rule D02).
  - **Confirmed Tender Receipts History**: Displays all confirmed receipts posted against the bill with receipt number, tender method, settled amount, and timestamp.
- **Creation Handler ([`CreateBill.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Resources/Bills/Pages/CreateBill.php))**:
  - Resolves active counter shift automatically.
  - Converts rupee rates to signed integer paise.
  - Invokes `BillingService::createDraftBill()` with UUID idempotency keys.
  - Processes instant checkout via `BillingService::checkout()` when `settle_immediately` is enabled.
- **Edit & View Handler ([`EditBill.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Resources/Bills/Pages/EditBill.php))**:
  - Populates existing line items via `mutateFormDataBeforeFill()`.
  - Disables mutation and hides save form actions on posted bills.
  - Header actions:
    - `View Tax Invoice`: Modal viewing the official printed tax invoice.
    - `Settle & Post Draft Bill`: Action to confirm payment and settle draft bills.
    - `Authorize Refund`: Manager-authorized full refund action with reason and prepaid wallet restoration toggle.
- **Table Navigation ([`BillsTable.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Resources/Bills/Tables/BillsTable.php))**:
  - Row click (`recordUrl`) and `Invoice Details` action navigate directly to invoice detail view.

### 2. Automated Test Verification
- Added dedicated test suite `tests/Feature/BillResourceTest.php` (7 tests):
  - Admin and Manager access to bills list.
  - Access to complete create bill form.
  - Creating and settling bill with member prepaid wallet (verifying balance debit, receipt generation, and gross/tax/net math).
  - Creating and settling bill with cash tender.
  - Creating draft bill without immediate settlement and settling it later on Edit page.
  - Verifying posted bills display immutable notice and hide save action.
- Executed full application test suite:
  - **222 passed, 1199 assertions, 0 errors, 0 failures**.

### 3. Application-Wide Single-Column Form Layout Refinement
- Audited all Filament resource form schemas across the entire application and standardized them to clean, spacious, single-column layouts (`->columns(1)` on `$schema` and `->columnSpanFull()` on all `Section` components):
  - **Commercial & Billing**:
    - [`BillForm.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Resources/Bills/Schemas/BillForm.php): Document invoice flow, full-width repeater, live line item calculations, and tender settlement.
    - [`TariffForm.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Resources/Tariffs/Schemas/TariffForm.php): General tariff details, Indian pricing & GST slabs, and validity period stacked vertically.
    - [`ShiftForm.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Resources/Shifts/Schemas/ShiftForm.php): Counter cashier assignment and opening float drawer management.
    - [`InventoryItemResource.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Resources/Inventory/InventoryItemResource.php): Liquor/beverage master records, UOM, and reorder levels.
    - [`PurchaseReceiptForm.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Resources/Purchases/Schemas/PurchaseReceiptForm.php): Supplier consignment info and full-width inward stock lines repeater.
  - **Membership & Guest Operations**:
    - [`MemberForm.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Resources/Members/Schemas/MemberForm.php): Personal KYC, category classification, and RFID token sections in full-width single column.
    - [`MembershipCategoryForm.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Resources/MembershipCategories/Schemas/MembershipCategoryForm.php): Quota classification and statutory rights/annual dues sections stacked vertically.
    - [`GuestForm.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Resources/Guests/Schemas/GuestForm.php): Guest KYC, sponsoring member selection, and visit date/status.
    - [`MembersTable.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Resources/Members/Tables/MembersTable.php): Family dependent registration modal updated to full-width.
  - **Facilities, Sports & Rooms**:
    - [`RoomReservationForm.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Resources/RoomReservations/Schemas/RoomReservationForm.php): Room occupant info, tariff pricing, and housekeeping turnover sections.
    - [`CourtBookingForm.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Resources/CourtBookings/Schemas/CourtBookingForm.php): Court/member selection and 60-min hourly scheduling sections.
    - [`FacilitySubscriptionForm.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Resources/Subscriptions/Schemas/FacilitySubscriptionForm.php): Sports facility plan, tariff, and calendar validity period.
    - [`FacilityResource.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Resources/Facilities/FacilityResource.php): Facility master details.
  - **Finance & Contractors**:
    - [`ContractorForm.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Resources/Contractors/Schemas/ContractorForm.php): Contractor business identity, contact details, NEFT/IFSC banking, and commission terms.
    - [`ExpenseForm.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Resources/Expenses/Schemas/ExpenseForm.php): GL expense heads, department allocation, and disbursement details.
    - [`FinancialPeriodForm.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Resources/FinancialPeriods/Schemas/FinancialPeriodForm.php): Fiscal dates and R04 closing lock policies.
    - [`JournalHeaderForm.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Resources/JournalHeaders/Schemas/JournalHeaderForm.php): Journal voucher details and full-width double-entry ledger lines repeater.
- Form Pages ([`CreateBill.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Resources/Bills/Pages/CreateBill.php) & [`EditBill.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Resources/Bills/Pages/EditBill.php)):
  - Added `protected Width | string | null $maxContentWidth = Width::SevenExtraLarge;`.
- **Automated Verification**:
  - Full test suite: **222 passed, 1,199 assertions, 0 errors, 0 failures**.

---

## Club Bank Accounts Management & Cash-to-Bank Contra Deposits (Delivered)

### 1. Features Implemented
- **Domain Models & Database Architecture**:
  - Created migration `2026_11_09_090000_create_bank_accounts_and_transactions_tables.php` defining `bank_accounts` and `bank_transactions`, and linking optional `bank_account_id` to `expenses`.
  - [`BankAccount.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Domain/Finance/Models/BankAccount.php): Represents the club's bank accounts (Current, Savings, Fixed Deposit) with Bank Name, Branch, Account Number, IFSC, UPI ID, linked Chart of Accounts Asset Head (`Account`), integer paise balances (`current_balance_paise`, `opening_balance_paise`), and primary operating account flag.
  - [`BankTransaction.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Domain/Finance/Models/BankTransaction.php): Immutable transaction ledger recording cash deposits, inter-bank transfers, expense disbursements, and contractor payouts with external references (Pay-in Slip #, Challan #, UTR #, Cheque #), depositor name, and linked double-entry `journal_header_id`.
- **Domain Banking Service ([`BankingService.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Domain/Finance/Services/BankingService.php))**:
  - `depositCash()`: Handles counter cash remittance to a bank account. Enforces period-closing locks (R04), generates sequential voucher `DEP-YYYYMM-XXXXX`, increments the bank balance in integer paise, and automatically posts balanced double-entry Contra Journal (Debit: Bank GL Account `1020`/`102x`, Credit: Cash in Hand `1010`).
  - `transferFunds()`: Executes inter-bank fund transfers between club accounts, updating respective balances and posting balanced Contra Journal entries.
  - `recordDisbursement()`: Records operational payouts (operating expenses, contractor payouts) drawn from a specific club bank account, updating the bank's live ledger balance.
- **Filament Admin Management ([`BankAccountResource.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Resources/BankAccounts/BankAccountResource.php))**:
  - Located under **Finance & Audit Ledger** as **Bank Accounts & Deposits** (`/admin/bank-accounts`).
  - Single-column document form ([`BankAccountForm.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Resources/BankAccounts/Schemas/BankAccountForm.php)) with Bank & Branch Identity, Account Number & Statutory Codes, and GL Mapping.
  - Interactive Table ([`BankAccountsTable.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Resources/BankAccounts/Tables/BankAccountsTable.php)):
    - Table columns: Primary Star, Account Title & Bank, Account # (copyable), Account Type badge, IFSC code, Live Cleared Bank Balance in ₹ (bold emerald font), and Active status.
    - Header & Row Actions: **Deposit Counter Cash** modal (remitting physical cash to any bank account with slip/challan reference), **Inter-Bank Transfer** modal, and **Passbook / Statement** modal (`bank-passbook-modal.blade.php`) showing live transaction history, inflows, outflows, and double-entry journal links.
- **Integration with Existing Workflows**:
  - Updated [`ExpenseForm.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Resources/Expenses/Schemas/ExpenseForm.php) and [`ExpenseService.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Domain/Finance/Services/ExpenseService.php) to allow selecting the disbursing club bank account when paying expenses via bank transfer or cheque, debiting the expense head and crediting the bank account.
  - Updated [`ContractorSettlementBatchesTable.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Resources/ContractorSettlements/Tables/ContractorSettlementBatchesTable.php) to allow selecting the disbursing club bank account when executing contractor batch payouts.
  - Seeded operational accounts in `ProductionBootstrapSeeder`: *SBI Main Operating Current Account (Vadakkencherry)* and *Federal Bank Clubhouse Reserve Account (Alathur)*.

### 2. Automated Test Verification
- Added dedicated test suite `tests/Feature/BankAccountManagementTest.php` (11 tests):
  - Admin and Manager access control; Cashier restriction (HTTP 403).
  - Creating bank account with opening balance and primary flag.
  - Cash deposit to bank posting balanced Contra Journal (Debit: Bank, Credit: Cash 1010).
  - Inter-bank transfer moving funds and posting balanced journal.
  - Enforcing Period Closing locks (Rule R04) rejecting backdated deposits into closed periods.
  - Filament Livewire tests for list, create, and cash deposit modal action.
  - Expense disbursement and contractor settlement payout tracking against bank accounts.
- Executed full application test suite:
  - **233 passed, 1,247 assertions, 0 errors, 0 failures**.

---

## Member 360° Profile View & Mobile/PWA Optimization (Delivered)

### 1. Features Implemented
- **Filament Member View Page ([`ViewMember.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Resources/Members/Pages/ViewMember.php))**:
  - Registered view route `admin/members/{record}` in [`MemberResource.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Resources/Members/MemberResource.php).
  - Added primary "View Profile" row action and record URL click-through in [`MembersTable.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Resources/Members/Tables/MembersTable.php).
  - Responsive title: `Member Profile — {Name} (#{MemberNumber})`.
  - Reactive Livewire tab switching with URL query string persistence (`?activeTab=overview|wallet|bills|facilities|dependents`).
- **Comprehensive Mobile-First & PWA-Optimized Blade Architecture ([`view-member.blade.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/resources/views/filament/resources/members/pages/view-member.blade.php))**:
  - **Top Hero Header Card**:
    - Member avatar initials with dual-tone gradient, Full Name, Member # badge, Category badge, Status badge, and Active RFID Card badge.
    - Clickable contact metadata chips (phone `tel:`, email `mailto:`, member since, valid until).
    - Authoritative live wallet balance widget in bold emerald (`₹X,XXX.XX`) with reserved funds indicator and "Strict No-Credit Active" assurance.
    - Quick touch action bar (`min-h-[44px]` touch targets) for Top-Up Wallet, Issue/Replace RFID Card, Add Dependent, Lifecycle Status, Edit Profile, and Print Statement.
  - **Mobile Horizontal Scrolling Tab Navigation**:
    - Smooth touch scrollbar container (`overflow-x-auto no-scrollbar scroll-smooth`) keeping tabs accessible on 360px–414px mobile viewports without vertical wrap crowding.
    - Touch-optimized tab buttons with active gold/amber indicators and live counter badges.
  - **Tab 1: Overview & KYC**:
    - 4 KPI metric cards in responsive 2x2 (mobile) / 4x1 (desktop) grid: Lifetime Deposits (₹), Lifetime Consumption (₹), Tax Invoices Count & Paid Total, and Facilities Bookings count.
    - Detailed two-column cards for Statutory KYC & Personal Records (Aadhaar, Passport, DL, proof numbers, correspondence notes) and Club Governance & Classification (Category dues, voting rights, registered RFID card history).
  - **Tab 2: Prepaid Wallet Ledger**:
    - Authoritative server ledger table with horizontal touch-scroll container.
    - Displays posting timestamp, event type, reference number, debit (rose font), credit (emerald font), and running balance after each transaction.
    - Integrated "Top-Up Balance" modal and "Print Official Statement" action.
  - **Tab 3: Invoices & Bills**:
    - Search by invoice # and filter by outlet/department (Restaurant, Bar, Sports Complex, Front Desk).
    - Chronological invoice table with payment tenders and paid status badges.
    - Interactive "View Bill" action opening itemized tax receipt modal with line items, quantities, rates, CGST/SGST taxes, discounts, net totals, and cashier attribution.
  - **Tab 4: Sports & Facilities**:
    - Facility Subscriptions table (Badminton, gym, validity range, fees, status).
    - Indoor Badminton Court bookings table (booking #, date, time slots, plan-covered status).
    - Accommodation Room reservations table (room name, check-in, check-out, nights, total tariff, status).
  - **Tab 5: Family Dependents & Sponsored Guests**:
    - Family Dependents grid adhering to **Policy A07**: prominently highlights wallet debit authorization status (Green "Wallet Authorized" vs Amber "Restricted (Primary Member Only)").
    - 1-click toggle action (`toggleDependentWalletDebit`) to authorize or revoke dependent counter spending with instant audit notification.
    - Sponsored visiting guests table (guest name, phone, visit date, entry passes issued, status).
  - **Touch & PWA-Optimized Modals**:
    - Bottom-sheet style modals on mobile viewports (`rounded-t-3xl sm:rounded-2xl max-h-[92vh] overflow-y-auto`): Top-Up Wallet modal, RFID Card modal, Add Dependent modal, Status Change modal, and Invoice Details modal.

### 2. Automated Test Verification
- Created dedicated test suite `tests/Feature/MemberProfileViewTest.php` (10 tests):
  - `test_guest_is_redirected_to_login`: Unauthenticated requests redirected to `/admin/login`.
  - `test_admin_can_access_member_profile_view`: Admin renders complete 360° profile with member details, active RFID card, live wallet balance, and tab navigation.
  - `test_manager_can_access_member_profile_view`: Club manager has full access to member profile view.
  - `test_member_profile_displays_kyc_and_classification`: Verifies KYC proof numbers, address notes, voting eligibility, and category fee display.
  - `test_tab_switching_via_livewire`: Smoothly switches across `overview`, `wallet`, `bills`, `facilities`, and `dependents` tabs.
  - `test_top_up_wallet_from_profile_view_credits_balance`: Admin top-up action validates amount, records atomic recharge via `WalletService`, and updates wallet ledger.
  - `test_rfid_card_replacement_updates_card_on_profile`: Card replacement marks old card replaced, activates new UID, and preserves wallet balance.
  - `test_dependent_registration_and_wallet_authorization_toggle`: Enforces Policy A07 default restriction and verifies toggling wallet debit authorization.
  - `test_status_change_modal_transitions_member_status`: Transitions member lifecycle status (Active &rarr; Suspended) and records audit reason.
  - `test_invoice_details_modal_loads_itemized_data`: Opens interactive invoice modal showing itemized lines, taxes, and settlement allocations.
- Executed full application test suite:
  - **243 passed, 1,288 assertions, 0 errors, 0 failures**.
- Front-end compilation:
  - `npm run build`: compiled in 883ms with zero errors.

### 3. Member Profile Expansion — Phase 1 Deliverables (Decision D44)
- **Direct Facility Bookings from Profile**:
  - Implemented direct modal triggers for badminton court bookings (`CourtBookingService::bookCourt`) with atomic entitlement checks and past-slot validation.
  - Implemented direct room reservation modal (`RoomReservationService::createReservation`) enforcing half-open interval checks `[check_in_date, check_out_date)` and advance deposits.
- **WhatsApp Balance & Statement Dispatch**:
  - Integrated `getWhatsAppShareUrl` generating `https://wa.me/91...` deep links with pre-formatted member ID, current server ledger balance, and official Malabar Club sign-off.
- **Affiliated Club Introduction Certificate Generator**:
  - Generates verifiable introduction letters for members traveling to reciprocal clubs (Trivandrum Club, Bangalore Club, Rama Varma Club, etc.).
  - Enforces active standing policy (strictly prohibited for suspended members); generates unique certificate references (`MC-REC-YYYY-XXXXXX`).
  - Provides a printable, framed certificate view modal.
- **Confidential Committee & Staff Audit Notes**:
  - Created migration `2026_11_09_100000_create_member_notes_table.php` and `MemberNote` model with author and category attributes (General, Disciplinary, Committee Directive, Dining Preference).
  - Integrated chronologically into the Overview & KYC tab with staff-only entry modal.
- **Automated Test Verification**:
  - Added 6 new automated tests in `tests/Feature/MemberProfileViewTest.php` (total 16 tests in suite, 74 assertions).
  - Executed full application test suite: **249 passed, 1,321 assertions, 0 errors, 0 failures**.

### 4. Member Profile Expansion — Phase 2 Deliverables (Decision D45)
- **Gate & Turnstile Activity Logs**:
  - Added dedicated `Gate & Turnstile Logs` tab querying real-time `AccessEvent` entries for the member and all registered RFID card tokens.
  - Displays chronological entries with timestamp, facility gate location, card UID, granted/denied status badge, rejection reason, and hardware controller device ID.
  - Integrated 1-click **"Emergency Block Access"** (`blockActiveCard`) action immediately setting card status to `blocked`, stamping `blocked_at`, recording a disciplinary staff audit note, and denying turnstile entry.
  - Added **"Restore Gate Access"** (`unblockCard`) action allowing administrative staff to restore access for cleared cards.
- **Physical Club Locker Allocation & Security Deposit Tracker**:
  - Created migration `2026_11_09_110000_create_lockers_table.php` and `Locker` model with `zone`, `status`, `key_number`, `deposit_amount_paise`, and `allocated_until`.
  - Added Locker allocation card in the *Sports & Facilities* tab with **"Assign Club Locker"** modal (`processAssignLocker`) and 1-click **"Surrender & Refund"** action (`vacateLocker`) that releases the locker and logs a secretarial audit note for refundable security deposits.

### 5. Member Profile Expansion — Phase 3 Deliverables (Decision D46)
- **Annual Subscription Dues & 1-Click Renewal**:
  - Integrated Annual Subscription Dues card in the *Overview & KYC* tab displaying category annual fee, standing badge (*Good Standing*, *Expiring Soon*, *Expired / Due*), and days-remaining countdown.
  - Implemented **"Renew Subscription"** modal (`processRenewMembership`) supporting multi-year extensions (1, 2, 3, 5 years) via Prepaid Wallet, Cash, Digital UPI, or Direct Bank Remittance.
  - Strictly enforces **Rule D02**: verifies available wallet balance before renewal; blocks overdrafts or unbacked balance modifications with instant danger notifications.
- **F&B Consumption Analytics & Personalization**:
  - Integrated live outlet spend breakdown in the *Overview & KYC* tab tracking expenditure across Food Contractor, Bar Counter, Sports Courts, and Card Room.
  - Added **"Most Frequently Ordered Items"** widget displaying top 5 items ordered by the member, order frequency count, and cumulative spend.
- **Statutory Document Vault**:
  - Added secure document vault section in the KYC records card with direct links to authenticated, non-cached streaming endpoints (`/documents/members/{id}/id_proof.jpg` and `/documents/members/{id}/application_form.pdf`), strictly adhering to **Scenario A09** RBAC enforcement.
- **Automated Test Verification**:
  - Added 9 new tests in `tests/Feature/MemberProfileViewTest.php` (total 25 tests in suite, 119 assertions).
  - Executed full application test suite: **258 passed, 1,366 assertions, 0 errors, 0 failures**.

---

## Known Boundaries & Pending Qualifications

1. **Physical Access Hardware (X05, X06)**:
   - Physical biometric turnstiles, optical barriers, and RFID reader hardware are not installed in the development environment.
   - Application logic and driver interfaces are fully validated using `SimulatorAccessControlProvider`, which fails closed if configured under `APP_ENV=production`.
2. **Physical Thermal Receipt Printing (X07)**:
   - Raw 80mm ESC/POS byte sequence generation and formatting are verified in automated tests.
   - Browser print dialogs function correctly; raw hardware printer port output qualification will occur once physical printers are connected.
3. **Official Tally Prime Instance Integration**:
   - Real XML-over-HTTP connector (`TallyPrimeHttpConnector`) is tested and verified with XML mock responses (`<CREATED>`, `<LINEERROR>`, timeouts).
   - Live synchronization against Malabar Club's official Tally Prime instance requires on-premise IP configuration.

---

## 13. Policy Terminology Sanitization & Comprehensive Operational Manual (November 9, 2026)

### 13.1 User-Facing Terminology Sanitization (Decision D48)
- Removed all developer/specification code abbreviations (`Rule D02`, `Policy D02`, `Policy A07`, `Scenario R04`, `Rule A03`, `Rule D15`, `D11`, `D12`, `D21`, `D23`) from all user-facing Filament forms, modals, notices, badges, and Blade views:
  - **Bill Settlement Form**: Replaced `"Enforces Rule D02"` with `"Strict Club Policy: No credit is permitted for any member or guest. Payment must be confirmed immediately."`
  - **Prepaid Wallet Renewal Modal**: Replaced `"Policy D02 Enforcement"` and `"Rule D02: No credit permitted"` with `"Prepaid Wallet Policy: If paid via Prepaid Wallet, sufficient balance is strictly verified on the server ledger. Overdrafts, negative balances, and credit tabs are strictly blocked."`
  - **Family Dependents Roster**: Replaced `"Policy A07"` with clear instructions: `"Strict Club Policy: Dependents are restricted from debiting the primary member's wallet unless specifically authorized in writing by the primary member."`
  - **Financial Periods Management**: Replaced `"Scenario R04"` and `"Rule R04"` with clean labels: `"Closed (Locked)"` and `"Closing this period will permanently lock all accounting transactions within this date range. No backdated bills or expenses can be posted."`
  - **Sports Subscriptions & Room Reservations**: Replaced `"Rule A03"`, `"D12"`, `"D11"`, and `"D23"` with natural club policy descriptions.

### 13.2 Authoritative Governance & Operating Manual (`docs/CLUB_RULES_AND_POLICIES.md`)
- Authored a comprehensive institutional manual for Malabar Club committee members, executives, front-desk staff, accountants, and auditors:
  - **Operating Philosophy**: Detailed explanation of why the club transitioned from the traditional post-paid credit model to the real-time prepaid debit model.
  - **Financial & Billing Bylaws**: Rules 1 through 7 covering zero-credit enforcement, server-authoritative ledgers, 100% external food catering pass-through and daily settlements, immutable double-entry journals, fiscal period closing locks, cashier drawer reconciliation, and bank contra vouchers.
  - **Membership, Access & Family Bylaws**: Rules 8 through 12 covering member active standing prerequisites, dependent roster spending authorization, guest entry sponsorship, RFID card replacement balance continuity, and private statutory KYC document vaults.
  - **Facilities & Accommodations Bylaws**: Rules 13 and 14 covering room advance payments, 14:00/11:00 non-overlapping check-in intervals, and 60-minute badminton court slot serialization.
  - **Auditor & Engineering Cross-Reference Matrix**: Maps all human-friendly club policy names to their technical architecture decision IDs (`D01`–`D47`), acceptance scenarios (`A01`–`A10`, `F01`–`F16`, `C01`–`C05`, `I01`–`I05`, `R01`–`R04`, `X01`–`X08`), and underlying domain service methods.

### 13.3 Test Suite & Build Verification
- **Frontend Assets**: `npm run build` compiled cleanly in 824ms.
- **Member Profile Test Suite**: `php artisan test --filter=MemberProfileViewTest` — **27 passed, 123 assertions, 0 failures**.
- **Full Test Suite**: `php artisan test` — **260 passed, 1,370 assertions, 0 failures** in 64s.

---

## 14. Executive Reporting Hub & Multi-Dimensional Report Engine (November 9, 2026)

### 14.1 Reporting Engine Implementation (Decision D49)
- Transformed `FinancialReportsPage` into an authoritative, multi-dimensional **Executive Reporting Hub** supporting the 8 required client reports:
  1. **Sales & Collections by Department & Counter**: Segregates consumption sales from external collections (Cash & Digital inflow vs Prepaid Wallet redemptions), itemized by department (`bar`, `food`, `sports`, `accommodation`, `card_room`, `membership`) and billing counter (`POS-1`, `POS-2`, `POS-3`).
  2. **Income & Expenditure Statement**: Compares all operating turnover against catering payouts, liquor COGS, staff wages, electricity/utilities, and maintenance, calculating Net Operating Surplus / Deficit.
  3. **Daily Cash & Digital Till Reconciliation**: Audits cashier shifts with opening floats, cash collections, digital UPI totals, expected cash, closing cash counts, and drawer discrepancy tracking.
  4. **Food Contractor Settlements**: Daily settlement batch ledger with gross food sales, taxes, refund adjustments, net payables, bank payouts (NEFT/RTGS UTRs), and residual payables.
  5. **Bar & Stock Consumption Reports**: Inventory items with moving average cost, current asset valuation, period issues (bottles & ml), wastage/breakage loss, and low-stock reorder warnings.
  6. **Gym & Badminton Sports Income**: Facility subscription counts (monthly vs annual), upfront revenues, 60-min badminton court slot bookings (plan-covered vs paid slots), and court fees collected.
  7. **Outstanding Payables & Exception Reconciliation**: Proves 100% zero-credit compliance (0 member bad debts), contractor unsettled payables, digital gateway unposted payment captures (Scenario F07), and general ledger balancing (Debits === Credits).
  8. **Departmental & Overall Profit & Loss**: Contribution margins per facility reconciled to unallocated overheads and overall club profit/loss.

### 14.2 UI/UX, Date Presets, CSV Export & Print Letterheads
- **Quick-Range Presets**: 1-click filters for `Today`, `Yesterday`, `This Week`, `This Month`, `Last Month`, and `Fiscal Year (1 Apr – 31 Mar)`.
- **1-Click CSV Streaming Export**: Custom UTF-8 encoded streaming CSV downloads with Excel BOM headers for every active tab.
- **Decision D47 Print Optimization**: Automatically isolates printable statements with an official club letterhead (*Malabar Club, Vadakkencherry*), suppressing buttons, filters, and navigation controls under `@media print`.

### 14.3 Automated Verification
- **Executive Reporting Hub Test Suite**: `tests/Feature/ExecutiveReportingHubTest.php` — **11 passed, 55 assertions, 0 failures**.
- **Full Application Test Suite**: `php artisan test` — **271 passed, 1,425 assertions, 0 failures** in 66s.
- **Frontend Assets**: `npm run build` compiled cleanly in 905ms.

---

## 15. Repository Initialization & Upstream Sync (November 9, 2026)

### 15.1 Git Remote Configuration
- Target remote repository configured: `https://github.com/gran-sso/club-os.git`.
- Remote origin verified:
  ```bash
  origin  https://github.com/gran-sso/club-os.git (fetch)
  origin  https://github.com/gran-sso/club-os.git (push)
  ```

### 15.2 Sanitized Commit & Push
- Validated `.gitignore` to ensure `.env`, `.DS_Store`, `database.sqlite`, `node_modules`, `vendor`, and `.phpunit.cache` remain excluded.
- Created root commit `7aa1793` (`feat: Initial commit of Club OS management and billing platform for Malabar Club`).
- Pushed branch `main` to `origin/main` with upstream tracking enabled (`git push -u origin main`). Working tree is clean and up to date.

---

## 16. Scaled Global Search & Command Hub Across All 18 Sections (November 9, 2026)

### 16.1 Comprehensive Global Search Architecture (Decision D50)
- Scaled Filament’s global search from an initial single-attribute baseline across **all 18 application resources** spanning all 6 club functional groups:
  1. **Membership & Guest Desk**:
     - `MemberResource`: Searchable across `membership_number`, `person.first_name`, `person.last_name`, `person.phone`, `person.email`. Detail badges: Phone, Category, Status, Live Prepaid Wallet Balance. Eager loads `person`, `category`, and `wallet`.
     - `GuestResource`: Searchable across `person.first_name`, `person.last_name`, `person.phone`, `person.email`. Detail badges: Phone, Sponsor Member, Status.
     - `FacilitySubscriptionResource`: Searchable across `subscription_number`, `facility_code`, `plan_type`, `member.membership_number`, `member.person.first_name`, `member.person.last_name`. Detail badges: Member, Facility, Plan Dates, Status.
     - `MembershipCategoryResource`: Searchable across `name`, `code`. Detail badges: Code, Entry Fee, Annual Dues, Status.
  2. **Operations & Front Desk**:
     - `RoomReservationResource`: Searchable across `reservation_number`, `guest.person.first_name`, `guest.person.last_name`, `sponsorMember.membership_number`, `sponsorMember.person.first_name`, `facility.name`, `facility.code`. Detail badges: Guest/Member, Dates, Total Charge, Status.
     - `CourtBookingResource`: Searchable across `booking_number`, `member.membership_number`, `member.person.first_name`, `member.person.last_name`, `facility.name`. Detail badges: Facility, Member, Slot Time, Status.
     - `ShiftResource`: Searchable across `shift_number`, `cashier.name`, `counter.name`, `business_date`. Detail badges: Counter, Cashier, Business Date, Status.
  3. **Commercial & Counters**:
     - `BillResource`: Searchable across `bill_number`, `department_code`. Detail badges: Customer, Department, Net Amount, Status.
     - `InventoryItemResource`: Searchable across `name`, `sku`, `barcode`, `category`. Detail badges: Category, Stock on Hand, Barcode, Reorder Warning.
     - `TariffResource`: Searchable across `name`, `code`, `barcode`, `department_code`. Detail badges: Department, Rate, Tax Rate, Status.
  4. **Procurement & Food Contractor**:
     - `PurchaseReceiptResource`: Searchable across `receipt_number`, `invoice_number`, `supplier.name`. Detail badges: Supplier, Invoice #, Total, Status.
     - `ContractorResource`: Searchable across `name`, `contact_person`, `phone`, `email`. Detail badges: Contact Person, Phone, Status.
     - `ContractorSettlementBatchResource`: Searchable across `batch_number`, `business_date`. Detail badges: Business Date, Net Payable, Paid Amount, Status.
  5. **Finance & Audit Ledger**:
     - `ExpenseResource`: Searchable across `expense_number`, `payee`, `document_reference`. Detail badges: Payee, Amount, Date, Status.
     - `BankAccountResource`: Searchable across `account_name`, `account_number`, `bank_name`, `ifsc_code`. Detail badges: Bank Name, Masked Account #, IFSC, Status.
     - `JournalHeaderResource`: Searchable across `journal_number`, `description`. Detail badges: Source, Business Date, Description, Total Debits.
     - `FinancialPeriodResource`: Searchable across `period_code`, `notes`. Detail badges: Date Range, Status.
  6. **Club Master Setup**:
     - `FacilityResource`: Searchable across `name`, `code`, `type`. Detail badges: Type, Capacity, Status.

### 16.2 Custom Global Search Provider (`ClubGlobalSearchProvider`)
- Implemented `App\Domain\Shared\Services\ClubGlobalSearchProvider`:
  - Enriches standard resource search with an intelligent **Operational Screens & Quick Links** category.
  - Matches intuitive operational keywords (`pos`, `reports`, `room rack`, `court schedule`, `settings`, `integration`, `p&l`, etc.) and surfaces instant jump actions directly to functional screens.
  - Formats dropdown result category headers with section context (e.g., `Membership & Guest Desk — Members`, `Operations & Front Desk — Room Bookings`).
  - Configured in `AdminPanelProvider` with `⌘K` / `Ctrl+K` keybindings, keyboard suffix badges, and 350ms debouncing.

### 16.3 Dashboard Command Hub Hero Widget (`GlobalQuickSearchWidget`)
- Added `App\Filament\Widgets\GlobalQuickSearchWidget` at the top of the Filament Dashboard (`sort = 0`):
  - High-visibility command bar with instant keyboard focus synchronization to the global search engine.
  - 1-click quick-jump shortcut pills for Counter POS (`/pos`), Room Rack (`/admin/room-rack`), Court Schedule (`/admin/court-schedule`), Executive Reports (`/admin/financial-reports`), Bank Passbook (`/admin/bank-accounts`), and Settings (`/admin/system-settings`).

### 16.4 Executive Segregation: Counter POS Hidden for Admin Role
- In accordance with internal financial controls and executive segregation of duties, the Counter POS Terminal link has been hidden for the `admin` role:
  - **Sidebar Navigation**: `NavigationItem` visibility condition updated in `AdminPanelProvider` (`visible(fn () => !auth()->user()?->hasRole('admin'))`).
  - **Dashboard Command Hub**: POS quick-jump pill hidden on `GlobalQuickSearchWidget` for `admin`, remaining active for `cashier`, `manager`, and `reception`.
  - **Global Search Quick Links**: `ClubGlobalSearchProvider` omits the cashier terminal quick-link for `admin` searches, returning pure billing & invoice registers instead.

### 16.5 Automated Verification
- **Global Search Test Suite**: `tests/Feature/GlobalSearchScalingTest.php` — **9 passed, 84 assertions, 0 failures**.
- **Admin Panel Role Test Suite**: `tests/Feature/AdminPanelTest.php` — **6 passed, 64 assertions, 0 failures**.
- **Full Application Test Suite**: `php artisan test` — **280 passed, 1,506 assertions, 0 failures** in 71s.
- **Frontend Assets**: `npm run build` compiled cleanly in 397ms.

---

## 17. Wholesale Supplier & Distributor Management Suite (November 9, 2026)

### 17.1 Dedicated Wholesale Suppliers Resource (Decision D51)
- Implemented `App\Filament\Resources\Suppliers\SupplierResource` under navigation group **Procurement & Food Contractor** (sort order 1):
  - **Supplier Master Schema (`SupplierForm.php`)**:
    - Unique Supplier / Depot Code (e.g. `KSBC-FL4`, `MALABAR-BEV`) with uppercase normalization and unique validation.
    - Official Trade Name (e.g. `Kerala State Beverages Corporation (KSBC Wholesale Depot)`).
    - 15-character Indian GSTIN with state code and format regex validation (`^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$`).
    - Contact Person / Depot Manager name.
    - Phone number and official distributor email.
    - Physical Depot / Warehouse Address.
    - Active Status toggle.
  - **Supplier Management Table (`SuppliersTable.php`)**:
    - Columns: Code badge, Name with contact person subtext, copyable GSTIN with badge, copyable phone, total purchase receipts count badge, active status icon.
    - Filters: Active status ternary filter (All / Active Only / Inactive Only).
    - Actions: Edit action, and guarded Delete action (strictly blocks deleting suppliers with linked purchase receipts or inward stock movements).
  - **Role-Based Access Control**:
    - `canViewAny`: `admin`, `manager`, `accountant`, or users with `stock.manage`.
    - `canCreate` / `canEdit`: `admin`, `manager`, or users with `stock.manage`.
    - `canDelete`: `admin` and `manager` only.
    - Frontline `cashier` is strictly forbidden (HTTP 403).

### 17.2 In-Line Quick Creation Modal on Purchase Receipt Form
- Enhanced `App\Filament\Resources\Purchases\Schemas\PurchaseReceiptForm.php`:
  - Equipped the `supplier_id` select dropdown with Filament's `createOptionForm()` and `createOptionUsing()` modal action.
  - Staff receiving goods at the club delivery dock can click the `+` button directly inside the Purchase Receipt creation form to instantly register an emergency or new distributor on the fly without navigating away or losing unsaved consignment line items.
  - Newly created supplier is immediately selected in the dropdown.

### 17.3 Core Wholesale Suppliers Seeded
- Updated `database/seeders/ProductionBootstrapSeeder.php` with default authorized Kerala wholesale depots:
  1. `KSBC-FL4`: Kerala State Beverages Corporation (KSBC Wholesale Depot) - FL4 License Depot, Palakkad.
  2. `MALABAR-BEV`: Malabar Beverage & FMCG Distributors - Authorized soft drink, soda & bar consumables distributor.
  3. `APEX-HOSP`: Apex Club & Hospitality Supplies - Club linen, toiletries & housekeeping consumables.

### 17.4 Global Search Integration
- Integrated `SupplierResource` into Filament's global search engine:
  - Searchable attributes: `name`, `code`, `gstin`, `contact_person`, `phone`.
  - Result title: `Supplier: {name} ({code})`.
  - Result details: `Code`, `Contact`, `GSTIN`, `Phone`, `Status`.
  - Direct edit URL navigation upon selection.

### 17.5 Automated Verification
- **Supplier Management Test Suite**: `tests/Feature/SupplierManagementTest.php` — **7 passed, 23 assertions, 0 failures**:
  - `test_default_core_wholesale_suppliers_are_seeded`: Verifies KSBC, Malabar Beverage, and Apex Club supplies exist in DB.
  - `test_admin_can_access_supplier_pages`: Verifies Admin access to supplier index and create pages.
  - `test_manager_can_access_supplier_pages`: Verifies Manager access to supplier index and create pages.
  - `test_cashier_cannot_access_supplier_resource`: Verifies Cashier receives 403 Forbidden.
  - `test_manager_can_create_new_wholesale_supplier_via_form`: Tests form validation, uppercase code, and DB insertion.
  - `test_supplier_resource_global_search`: Tests multi-attribute global search by name and code.
  - `test_purchase_receipt_form_has_supplier_dropdown`: Tests supplier dropdown existence on Purchase Receipt form and Livewire field.
- **Global Search Scaling Test**: `tests/Feature/GlobalSearchScalingTest.php` — **9 passed, 86 assertions, 0 failures**.
- **Full Application Test Suite**: `php artisan test` — **287 passed, 1,534 assertions, 0 failures** in 71s.
- **Frontend Assets**: `npm run build` compiled cleanly in 420ms.

---

## 18. Dedicated Staff & User Access Control Module (November 9, 2026)

### 18.1 Dedicated Staff & User Access Resource (Decision D52)
- Created `App\Filament\Resources\Users\UserResource` under a new dedicated navigation group **`System & Access Control`** (sort order 1):
  - **Staff Master Form (`UserForm.php`)**:
    - **Identity & Club Profile**: Staff full name, employee / payroll code (`employee_id` with unique constraint), job designation (e.g. Senior Counter Cashier, Head Bartender), unique login email, official phone/mobile number, and active account toggle switch.
    - **Security & Authentication**: Password and confirmation inputs with reveal toggle. Required with minimum 8 characters on create; optional on edit (only re-hashes and updates when non-empty).
    - **Role-Based Access Control (RBAC)**: Checkbox relationship selector for the 6 core club roles with clear operational descriptions:
      - `admin`: Club Administrator — Full System, Settings & Access Control.
      - `manager`: General Manager — Operations, Members, Tariffs & Approvals.
      - `accountant`: Club Accountant — Financial Ledgers, Settlements & Vouchers.
      - `auditor`: Internal Auditor — Read-Only Financial Registers & Reports.
      - `reception`: Front Desk Reception — Room Rack, Court Schedule & Guest KYC.
      - `cashier`: Counter Cashier — POS Terminal Billing, Drawer Shifts & Prepaid Recharges.
    - **Granular Permission Overrides**: Collapsible accordion enabling administrators to grant or revoke specific granular capabilities directly (e.g. `users.manage`, `tariffs.manage`, `refunds.approve`, `contractor.settle`, `counter.operate`, `stock.manage`).
  - **Staff Directory Table (`UsersTable.php`)**:
    - Columns: Staff Name with email & designation subtext, Staff ID badge, Assigned Roles badges (color-coded per role), Direct Permissions count badge, copyable phone, boolean active status indicator (check / x), and registration date.
    - Filters: Filter by Assigned Role, TernaryFilter by Status (Active Staff / Deactivated Staff).
    - Actions:
      - **Edit**: Profile, role assignment, and password updates.
      - **1-Click Status Toggle (`toggle_status`)**: Instant deactivation/activation with confirmation modal explaining security impact.
      - **Guarded Delete (`DeleteAction`)**: Strictly cancels deletion and guides staff to deactivate instead if historical transactions exist.

### 18.2 Critical Security Safeguards & Audit Integrity
- **Access Gatekeeper**: Enforced in `UserResource` methods (`canViewAny`, `canCreate`, `canEdit`, `canDelete`) restricting all user management strictly to administrators (`admin` role or `users.manage` permission). All non-admin staff (Manager, Cashier, Reception, Accountant) receive HTTP 403 Forbidden.
- **Immediate Login Gate**: Updated `User::canAccessPanel(Panel $panel)` and auth checks to verify `is_active === true`. Deactivating an account immediately blocks entry to both the Filament back-office panel and the Counter POS interface.
- **Lockout Prevention (Self-Protection)**:
  - Administrators cannot delete or deactivate their own currently active account via form or table actions.
  - The system blocks deactivating or deleting the sole remaining active administrator in the club database.
- **Audit Trail Preservation**:
  - Implemented `User::hasHistoricalRecords()`, scanning linked records across `shifts`, `bills`, `journal_headers`, `expenses`, and `purchase_receipts`.
  - Attempted deletion of any staff user with historical transactional links is blocked, alerting the administrator to deactivate the account instead so legal audit attributions are permanently preserved.

### 18.3 Scaled Global Search & Dashboard Integration
- Added `UserResource` to Filament’s global search index (`name`, `email`, `employee_id`, `phone`, `designation`).
- Enriched `ClubGlobalSearchProvider` with quick-link matching (`users`, `staff`, `roles`, `permissions`, `access control`, `passwords`), visible strictly to `admin`.
- Added a direct **Staff Access** quick-jump pill on the dashboard hero widget (`GlobalQuickSearchWidget`) for the `admin` role.

### 18.4 Automated Verification
- **User Access Control Test Suite**: `tests/Feature/UserAccessControlTest.php` — **8 passed, 36 assertions, 0 failures**:
  - `test_admin_can_access_user_management_pages`: Verified HTTP 200 on index, create, and edit pages for Admin.
  - `test_non_admin_cannot_access_user_management_pages`: Verified HTTP 403 Forbidden for Manager, Cashier, and Reception.
  - `test_admin_can_create_staff_user_with_role_and_direct_permissions`: Verified form fill, bcrypt hashing, role & permission syncing in DB.
  - `test_deactivated_staff_cannot_access_panel`: Verified `canAccessPanel()` returns false and panel rejects inactive staff.
  - `test_admin_cannot_deactivate_self_via_edit_form_or_action`: Verified self-deactivation is strictly rejected.
  - `test_admin_cannot_delete_self`: Verified `canDelete` returns false for self.
  - `test_user_with_historical_records_cannot_be_deleted`: Verified shift/bill-linked cashiers cannot be hard-deleted.
  - `test_user_resource_global_search`: Verified multi-attribute search by name, email, and staff ID.
- **Global Search Scaling Test**: `tests/Feature/GlobalSearchScalingTest.php` — **9 passed, 89 assertions, 0 failures**.
- **Full Application Test Suite**: `php artisan test` — **295 passed, 1,573 assertions, 0 failures** in 76s.
- **Frontend Assets**: `npm run build` compiled cleanly in 389ms.

---

## 19. Dynamic Chart of Accounts & Operating Expense Heads Suite (November 9, 2026)

### 19.1 Dedicated Chart of Accounts Resource (Decision D53)
- Implemented `App\Filament\Resources\Accounts\AccountResource` under navigation group **`Finance & Audit Ledger`** (sort order 2):
  - **Ledger Master Form (`AccountForm.php`)**:
    - **Ledger Code**: Unique numerical or alphanumeric code (e.g. `5110`, `5220`). Protected from editing when `is_system === true`.
    - **Account / Head Name**: Statutory general ledger description (e.g. *Electricity & Power Charges (KSEB)*, *Generator Diesel & Lubricants*).
    - **Accounting Classification**: Enum selection across standard series (`Expense (5000s)`, `Revenue (4000s)`, `Asset (1000s)`, `Liability (2000s)`, `Equity (3000s)`).
    - **Statement Sub-Group (`category`)**: Tag for reporting classification (e.g. `Utilities & Power`, `Repairs & Maintenance`, `Housekeeping & Hospitality`, `Administration & Staff`, `Statutory & Professional`, `Finance & Bank Charges`).
    - **Active Toggle**: Allows archiving defunct accounts without breaking historical audit trails.
    - **Description**: Detailed audit and operational accounting instructions.
  - **Directory Table (`AccountsTable.php`)**:
    - Columns: Mono badge Ledger Code, Name with statement group subtext, Classification badge with custom colors (Warning: Expense, Info: Revenue, Success: Asset, Danger: Liability, Purple: Equity), Statement Group tag, System Lock indicator (Lock icon for core system accounts, Sparkles icon for custom discretionary heads), and Active status icon.
    - Filters: Filter by Classification Type, TernaryFilter for System vs Custom Heads, TernaryFilter for Active Status.
    - Actions: Edit action, and guarded Delete action.
  - **Role-Based Access Control**:
    - Accessible strictly to `admin` and `accountant` roles (or users with `reports.view`).
    - Frontline `cashier` is strictly forbidden (HTTP 403).

### 19.2 In-Line Quick Creation Modal on Operating Expense Vouchers
- Enhanced `App\Filament\Resources\Expenses\Schemas\ExpenseForm.php`:
  - Equipped the `account_id` field with Filament's `createOptionForm()` and `createOptionUsing()` modal action.
  - Displays formatted options showing head name, code badge, and category (e.g. `Electricity & Power Charges (KSEB) (#5110) — Utilities & Power`).
  - Accountants posting invoices can click the `+` button directly inside the Expense Voucher creation screen to register a new 5000-series expense head on the fly without leaving the voucher.

### 19.3 Pre-Seeded Standard Club Operational Expense Heads
- Updated `database/seeders/ProductionBootstrapSeeder.php` with 13 realistic club operational expense heads:
  1. `5110`: Electricity & Power Charges (KSEB) (`Utilities & Power`)
  2. `5120`: Generator Diesel & Lubricants (`Utilities & Power`)
  3. `5130`: Water Supply & Tanker Charges (`Utilities & Power`)
  4. `5210`: Clubhouse Repairs & Building Maintenance (`Repairs & Maintenance`)
  5. `5220`: Badminton Court & Sports Equipment AMC (`Repairs & Maintenance`)
  6. `5230`: Air Conditioning & Refrigeration Servicing (`Repairs & Maintenance`)
  7. `5310`: Guest Room Linen, Laundry & Dry Cleaning (`Housekeeping & Hospitality`)
  8. `5320`: Cleaning Consumables, Sanitizers & Toiletries (`Housekeeping & Hospitality`)
  9. `5410`: Staff Uniforms, Welfare & Refreshments (`Administration & Staff`)
  10. `5420`: Printing, Stationery & Office Supplies (`Administration & Staff`)
  11. `5430`: Excise Bar License & Statutory Permit Fees (`Statutory & Professional`)
  12. `5440`: Statutory Audit & Legal Accounting Fees (`Statutory & Professional`)
  13. `5450`: Bank Charges & Card Terminal MDR Fees (`Finance & Bank Charges`)

### 19.4 System Account Integrity & Audit Safeguards
- Added `is_system` boolean column to `accounts` table via migration `2026_11_09_130000_add_system_and_category_fields_to_accounts_table.php`.
- Core double-entry accounts (`1010`, `1020`, `1030`, `2010`, `2020`, `2030`, `3010`, `4010`–`4040`, `5010`–`5030`) are marked `is_system = true`, blocking deletion and locking code alteration.
- Accounts with linked historical transactions (`Account::hasHistoricalLines()`) cannot be hard-deleted, prompting the user to deactivate the account instead to preserve financial ledger integrity.

### 19.5 Scaled Global Search & Dashboard Command Hub
- Added `AccountResource` to Filament global search index (`code`, `name`, `category`, `description`).
- Enriched `ClubGlobalSearchProvider` with quick-link matching (`chart of accounts`, `gl`, `ledger`, `expense head`, `kseb`, `electricity`, `diesel`, `generator`, `maintenance`).
- Updated dashboard hero widget (`GlobalQuickSearchWidget`) badge to **20 Sections Active**.

### 19.6 Automated Verification
- **Chart of Accounts Test Suite**: `tests/Feature/ChartOfAccountsTest.php` — **9 passed, 42 assertions, 0 failures**:
  - `test_core_system_accounts_and_standard_expense_heads_are_seeded`: Verified core system vs discretionary flags and types.
  - `test_admin_can_access_chart_of_accounts`: Verified Admin access to index and create pages.
  - `test_accountant_can_access_chart_of_accounts`: Verified Accountant access to index and create pages.
  - `test_cashier_cannot_access_chart_of_accounts`: Verified Cashier receives 403 Forbidden.
  - `test_accountant_can_create_new_discretionary_expense_head`: Verified form fill, validation, and DB creation.
  - `test_protected_system_accounts_cannot_be_deleted`: Verified `canDelete` is false and table action cancels deletion.
  - `test_accounts_with_historical_records_cannot_be_deleted`: Verified expense-linked accounts cannot be hard-deleted.
  - `test_expense_form_has_dropdown_with_custom_expense_heads`: Verified Expense form dropdown and Livewire field.
  - `test_chart_of_accounts_global_search`: Verified multi-attribute search by code (5110) and name (Diesel).
- **Global Search Scaling Test**: `tests/Feature/GlobalSearchScalingTest.php` — **9 passed, 91 assertions, 0 failures**.
- **Full Application Test Suite**: `php artisan test` — **304 passed, 1,617 assertions, 0 failures** in 70s.
- **Frontend Assets**: `npm run build` compiled cleanly in 418ms.

---

## 20. User Menu "Help" Page & Multi-Role Operational Guide (November 9, 2026)

### 20.1 User Profile Menu Integration (Decision D54)
- Implemented `App\Filament\Pages\HelpPage` registered at `/admin/help`:
  - **Sidebar Navigation Omitted**: Configured `shouldRegisterNavigation = false` so that the sidebar navigation remains focused and uncluttered.
  - **User Menu Registration**: Registered directly in `AdminPanelProvider` under `->userMenuItems(...)` with the concise label **"Help"** and icon `Heroicon::OutlinedQuestionMarkCircle`.
  - Accessible to all authenticated staff members (Administrators, Managers, Accountants, Cashiers, Receptionists, and Auditors).

### 20.2 Six Role-Tailored Operational Manual Tabs
- **1. ⚡ Counter Cashier & POS Guide**:
  - Shift lifecycle: Entering physical Opening Cash Float, monitoring real-time drawer Expected Cash (`Float + Cash Sales + Cash Recharges`), counting physical cash, and closing shifts with discrepancy tracking.
  - Interactive Keyboard Shortcut Keycaps: Focus Member (<kbd>F2</kbd>), Item Search / Barcode (<kbd>F3</kbd>), Cart / Qty (<kbd>F4</kbd>), Prepaid Wallet Tender (<kbd>F8</kbd>), Cash Tender (<kbd>F9</kbd>), Digital UPI / Card POS (<kbd>F10</kbd>), Serving Sizes (<kbd>1: 30ml</kbd>, <kbd>2: 60ml</kbd>, <kbd>3: Bottle</kbd>), and Dismiss (<kbd>Esc</kbd>).
  - Walkthroughs for Member Wallet Quick Top-Up and Visiting Guest Day Pass issuance.
- **2. 🏨 Front Desk & Facilities Guide**:
  - **Accommodation Deck** visual grid (Rooms 101–105): Occupancy color legend (Vacant Ready, Occupied, Reserved, Needs Cleaning, Maintenance), walk-in booking, check-in/out, and housekeeping status toggles.
  - Badminton Court matrix (6:00 AM – 10:00 PM): Hourly slot bookings, entitlement detection (fee waived for active sports subscribers), and pay-per-play.
  - Visiting Guest registration, sponsor eligibility verification, and member KYC.
- **3. 💰 Finance, Accounts & Contractor Settlements Guide**:
  - Chart of Accounts 5-digit classification: 1xxx Assets, 2xxx Liabilities, 3xxx Equity, 4xxx Revenues, 5xxx Expense Heads.
  - Operating Expense Vouchers: In-line <kbd>+</kbd> modal creation for new expense heads, GST slabs, and payment instruments (NEFT/RTGS, Cheque, Cash).
  - Daily Food Contractor Settlements: Pass-through billing, GM sign-off, bank UTR recording, and double-entry postings (Debit 2020 / Credit 1020).
  - Bank Contra Vouchers and fiscal period closing locks.
- **4. 📦 Procurement, Inventory & Stores Guide**:
  - Wholesale Suppliers directory: KSBC FL-4 depot, FMCG beverage distributors, 15-digit GSTIN validation.
  - Inward Purchase Receipts: Milliliter (ml) liquor tracking, automated moving weighted-average cost recalculation, and stock ledger journals (Debit 1030 / Credit 1020).
  - Bar consumption pegs vs sealed bottle dispensing rules.
- **5. 🛡️ Executive Governance & Administration Guide**:
  - Staff onboarding, RBAC roles, instant login deactivation (`is_active = false`), and audit trail deletion blockers.
  - Executive Reporting Hub: 8 core statutory reports (Department P&L, Till drawer reconciliation, Contractor audit, Stock valuation, Zero-Credit compliance).
- **6. 📜 Codified Club Bylaws & System Rules**:
  - Strict Zero-Credit mandate (Rule 1: No overdrafts, no administrative bypass).
  - Centralized Server Ledger ownership (Rule 2: Cards are identifiers, not cash).
  - Immutable Financial History & Linked Reversals (Rule 3).
  - Guest Entry & Sponsor eligibility (Rule 4).
  - Fiscal period closing locks (Rule 5).

### 20.3 Ergonomics, In-Page Search & Printable Cheat Sheet
- **In-Page Live Search**: Real-time debounced query filter highlighting specific instructions across all tabs.
- **Printable Single-Page Desk Cheat Sheet**: Styled with `@media print` isolation for clean, laminated cashier counter and reception desk references.
- **Global Search Integration**: Integrated into `ClubGlobalSearchProvider` matching keywords (`help`, `manual`, `guide`, `shortcuts`, `sop`, `cashier guide`).

### 20.4 Automated Verification
- **Help Center Test Suite**: `tests/Feature/HelpCenterTest.php` — **9 passed, 32 assertions, 0 failures**:
  - `test_admin_can_access_help_page`: Verified HTTP 200 for Admin.
  - `test_cashier_can_access_help_page`: Verified HTTP 200 and keyboard shortcuts visibility for Cashier.
  - `test_reception_can_access_help_page`: Verified HTTP 200 for Reception.
  - `test_accountant_can_access_help_page`: Verified HTTP 200 for Accountant.
  - `test_unauthenticated_user_redirected_from_help_page`: Verified redirect to `/admin/login`.
  - `test_help_page_is_not_registered_in_sidebar_navigation`: Verified `shouldRegisterNavigation` is false.
  - `test_help_page_interactive_tabs`: Verified tab switching across all 6 sections.
  - `test_user_menu_contains_help_link`: Verified panel user menu contains `'Help'` pointing to `/admin/help`.
  - `test_global_search_returns_help_page`: Verified search matching.
- **Full Application Test Suite**: `php artisan test` — **313 passed, 1,649 assertions, 0 failures** in 89s.
- **Frontend Assets**: `npm run build` compiled cleanly in 481ms.

---

## 21. Help Section Modernization, Dashboard Search Streamlining, and Member Profile Enterprise Compact Redesign (November 9, 2026)

### 21.1 Help Center Enterprise Two-Column Desk Redesign & Bylaws Removal
- Refactored `/admin/help` (`App\Filament\Pages\HelpPage` and `resources/views/filament/pages/help.blade.php`) into an enterprise documentation layout:
  - **Left Sidebar Navigation Menu**: Role-based sections (Counter Cashier & POS, Front Desk & Facilities, Finance & Accounting, Stores & Inventory, Executive Governance) with real-time active indicators and step count badges.
  - **Right Content Area**: Numbered step-by-step procedure cards with actionable instructions, keyboard keycaps (<kbd>F2</kbd>, <kbd>F8</kbd>, etc.), contextual alerts, and printable desk cheat sheet.
  - **Removed Bylaws Section**: Club bylaws link and content removed as Malabar Club has not codified specific custom bylaws.
  - All 13 tests in `HelpCenterTest` pass.

### 21.2 Admin Dashboard Search Widget Streamlining
- Removed the redundant `GlobalQuickSearchWidget` and its Blade view from the admin dashboard.
- Filament's top-bar command search (`Cmd+K` / `Ctrl+K`) remains the authoritative, unified search mechanism across all 20 sections of the application, eliminating visual duplication.
- All 9 tests in `GlobalSearchScalingTest` pass.

### 21.3 Member Profile Non-Colored Monochrome Enterprise Restyling (Decision D30)
- Fully refactored `resources/views/filament/resources/members/pages/view-member.blade.php`:
  - **Primary CTA Buttons**: Replaced amber/yellow/green gradients with a crisp, executive slate-900 primary button (`bg-slate-900 hover:bg-slate-800 text-white font-semibold text-xs shadow-2xs`) and clean white/slate secondary buttons (`bg-white hover:bg-slate-50 text-slate-700 font-semibold text-xs border border-slate-300 shadow-2xs`).
  - **Compact Height & Density**: Standardized all button heights from `42px`/`44px` down to `h-8.5` (34px), reduced avatar size from `w-18` to compact `w-12` in solid slate-900, tightened balance display to compact `px-3.5 py-2.5`, and tightened vertical page padding to `space-y-4`.
  - **Monochrome Modal Actions**: Standardized all 12 modal forms (Top-Up, RFID Card, Dependent, Status Change, Badminton Booking, Room Reservation, Affiliated Club Intro Letter, Locker Assignment, Annual Renewal, Invoices, Admin Notes) with clean `h-8.5` slate-900 submit buttons and subtle border-slate-300 cancel buttons, replacing previous colorful rainbow variants (`bg-emerald-600`, `bg-blue-600`, `bg-amber-600`, `bg-purple-700`, `bg-indigo-600`).
  - **Clean Tabs**: Standardized horizontal profile tab bar to compact `h-8.5` buttons with subtle slate borders and neutral counter badges, replacing colorful amber text and green badges.
  - **Member Actions Popover**: Replaced emoji icons with high-density monochrome SVG icons and compact `p-2` list rows.

### 21.4 Automated Verification
- `php artisan test --filter=MemberProfileViewTest`: **27 passed, 123 assertions, 0 failures**.
- `php artisan test --filter=MembershipAndAccessTest`: **7 passed, 36 assertions, 0 failures**.
- `npm run build`: Compiled cleanly in 1.12s.

### 21.5 Optional Statutory KYC Document Upload & Secure Vault Synchronization (Decision D55)
- **Database Schema**:
  - Added migration `2026_11_09_140000_add_document_paths_to_persons_table.php` introducing nullable `id_proof_path` and `application_form_path` to the `persons` table.
  - Updated `Person` fillable attributes and added `id_proof_path` / `application_form_path` accessors on `Member`.
- **Form Integration**:
  - Integrated Filament `FileUpload` fields into `MemberForm.php` for:
    - **Identity Proof**: Accepts PDF, JPG, PNG, WEBP up to 5MB, stored on the access-restricted `local` (`storage/app/private`) disk under `documents/members/kyc`.
    - **Application Form Scan**: Accepts PDF, JPG, PNG, WEBP up to 10MB, stored on the `local` disk under `documents/members/applications`.
  - Both fields are fully optional, allowing staff to register members immediately and upload scanned documents later.
  - Wired `CreateMember` and `EditMember` to hydrate, validate, and persist both file paths cleanly.
- **Member Profile Vault & Access Control**:
  - Refactored the Statutory Document Vault in `view-member.blade.php`:
    - Shows dynamic status indicator: green "Uploaded" badge when files exist, neutral "Not Uploaded" badge when missing.
    - If uploaded: provides "View Proof →" and "View Form →" direct links.
    - If not uploaded: provides a direct "Upload →" link opening the member edit form.
  - Enhanced `DocumentController::showMemberDocument`:
    - Streams actual physical files from private disk storage when present with `Pragma: no-cache` and `Cache-Control: no-store` headers.
    - Enforces RBAC clearance (unauthenticated redirects to login, cashiers get 403 Forbidden, authorized staff gets 200).
- **Automated Verification**:
  - `php artisan test --filter=MemberDocumentUploadTest`: **4 passed, 19 assertions, 0 failures**.
  - `php artisan test --filter=MemberProfileViewTest`: **27 passed, 123 assertions, 0 failures**.

### 21.6 Statutory Membership Fee Tracking & On-Registration Settlement (Decision D56)
- **Database Schema**:
  - Added migration `2026_11_10_090000_add_fee_tracking_to_members_table.php` adding `fee_status` (paid, pending, waived), `last_fee_paid_at`, and `last_fee_receipt_number` to `members`.
  - Updated `Member` fillable attributes, casts, and helper methods (`hasPaidFee()`, `isFeePending()`).
- **On-Registration Fee Collection ([`MemberForm.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Resources/Members/Schemas/MemberForm.php))**:
  - Added `Statutory Membership Fee & Admission Settlement` section on create form with toggle `Collect Initial Membership Fee Now`.
  - Auto-populates standard category fee in Rupees upon selecting or changing `membership_category_id`.
  - Supports tender selection (Cash in Hand, Digital UPI / POS, Bank Remittance NEFT/RTGS, Cheque/DD) with optional banking reference #.
  - In [`CreateMember.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Resources/Members/Pages/CreateMember.php), if enabled, generates an official `PaymentReceipt` (`RCP-YYYYMM-XXXXX`), logs `MemberNote`, marks `fee_status = 'paid'`, and activates 1-year validity. If omitted, cleanly flags `fee_status = 'pending'`.
- **Member Profile Audit & Direct Collection ([`ViewMember.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Resources/Members/Pages/ViewMember.php) & [`view-member.blade.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/resources/views/filament/resources/members/pages/view-member.blade.php))**:
  - Header profile badge displays `Fee Pending` pulse chip whenever dues are unsettled.
  - Annual Subscription Dues card renders a high-visibility amber alert banner: *"Initial Membership Admission Fee Pending Payment: Statutory dues of ₹X,XXX.00 are outstanding"*, with standing set to `Fee Pending Payment` and term validity as `Pending Settlement`.
  - Provides a direct **"Collect Fee Now"** action opening the payment modal, which now records formal `PaymentReceipt` entries for Cash, Digital, and Bank Remittance tenders, marks `fee_status = 'paid'`, and extends validity.
- **Automated Verification**:
  - `php artisan test --filter=MembershipFeeCollectionTest`: **3 passed, 33 assertions, 0 failures**.
  - `php artisan test --filter=MemberProfileViewTest`: **27 passed, 123 assertions, 0 failures**.
  - `npm run build`: Compiled cleanly in 405ms.

### 21.7 Two-Tier Membership Fee Structure: One-Time Admission Fee & Recurring Annual Subscription Dues (Decision D57)
- **Database Schema**:
  - Added migration `2026_11_10_093000_add_admission_fee_to_categories_and_members.php`:
    - `membership_categories.admission_fee_paise`: Unsigned bigint in integer paise (e.g. ₹50,000 for Ordinary, ₹2,00,000 for Life, ₹5,00,000 for Corporate).
    - `members.admission_fee_status`: Status enum (`paid`, `pending`, `waived`).
    - `members.admission_fee_paise`: Exact paise snapshot recorded upon admission.
    - `members.admission_fee_receipt_number`: Formal receipt number tracking admission fee settlement.
    - `members.admission_fee_paid_at`: Timestamp tracking when admission fee was settled.
- **Category Configuration ([`MembershipCategoryForm.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Resources/MembershipCategories/Schemas/MembershipCategoryForm.php) & [`MembershipCategoriesTable.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Resources/MembershipCategories/Tables/MembershipCategoriesTable.php))**:
  - Form configures both "One-Time Admission Fee (₹)" and "Annual Subscription Dues (₹)" under Statutory Fees.
  - Category roster table displays formatted currency for both admission fee and annual subscription dues.
- **Member Registration ([`MemberForm.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Resources/Members/Schemas/MemberForm.php) & [`CreateMember.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Resources/Members/Pages/CreateMember.php))**:
  - Auto-populates both `admission_fee_amount_rupees` and `annual_fee_amount_rupees` dynamically upon selecting category.
  - Quick-create modal (`createOptionForm`) allows defining both admission and annual fees on new categories on the fly.
  - Registration handler computes total paise across both fees, generates `PaymentReceipt` itemizing total, sets `admission_fee_status = 'paid'`, `admission_fee_receipt_number`, `admission_fee_paid_at`, `fee_status = 'paid'`, and activates 1-year validity. If fee collection is omitted, flags both as `pending`.
- **Member Profile Audit & Renewal Flow ([`ViewMember.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Resources/Members/Pages/ViewMember.php) & [`view-member.blade.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/resources/views/filament/resources/members/pages/view-member.blade.php))**:
  - Re-architected tracker card to **Statutory Membership Fees & Renewal Tracker** with 5 informative status tiles:
    1. *One-Time Admission Fee*: Displays amount, badge (`Paid • Lifetime`, `Pending Payment`, or `Waived`), and receipt reference.
    2. *Annual Subscription Dues*: Displays yearly rate, status badge (`Good Standing • Paid`, `Expiring in Xd`, `Expired / Due`, `Fee Pending`), and bylaws note.
    3. *Membership Standing*: Displays overall club standing and voting eligibility.
    4. *Current Term Valid Until*: Displays formatted expiration date or pending notice.
    5. *Last Renewal / Settlement*: Displays last payment date and receipt number.
  - Interactive Modal (Modal 12):
    - When admission fee is already paid, shows confirmation notice that admission fee is satisfied and **only prompts for the recurring annual subscription dues**, guaranteeing admission fee is never charged again.
    - If admission fee is pending, provides option to collect admission fee + annual subscription dues together or independently, showing dynamic total calculation.
- **Automated Verification**:
  - `php artisan test --filter=MembershipFeeCollectionTest`: **4 passed, 52 assertions, 0 failures**.
  - `php artisan test --filter=MemberProfileViewTest`: **27 passed, 123 assertions, 0 failures**.
  - `npm run build`: Vite build completed in 417ms.

### 21.8 Comprehensive Help Center SOP & Role-Aware Brand Header (Decisions D54 & UX Polish)
- **Role-Aware Panel Brand Header ([`brand-logo.blade.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/resources/views/filament/components/brand-logo.blade.php))**:
  - Replaced hardcoded "Admin" text badge with dynamic, authenticated role resolution (`Cashier`, `Reception`, `Manager`, `Accounts`, `Auditor`, `Admin`, or `Staff`). Cashiers and front-desk staff accessing the Accommodation Deck or Court Schedule see their actual role context rather than confusing administrative labels.
  - Added automated test in [`AdminPanelTest.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/tests/Feature/AdminPanelTest.php) (`test_brand_logo_displays_user_role_dynamically`).
- **Standardized Food Contractor Settlement SOP in Help Section ([`HelpPage.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Pages/HelpPage.php))**:
  - Enriched the `finance-contractor` topic with a 6-step operational manual:
    1. *Daily Counter Billing at POS (Cashiers)*: Ringing up dining items under the food department, immediate collection via Wallet, Cash, or UPI QR; unused top-ups strictly excluded.
    2. *Generate Yesterday's Settlement Batch*: Procurement & Food Contractor -> Contractor Settlements -> "Generate Daily Settlement Batch", bundling paid lines into unique `SET-YYYYMMDD-001` batches.
    3. *Audit & Approve Settlement Batch*: Verifying gross sales, taxes, deductions, net payable, and automated double-entry GL posting (Dr 5020 Contractor Food Expense / Cr 2020 Food Contractor Payable).
    4. *Execute & Record Bank Payout*: Modal capturing Disbursing Club Bank Account, payment instrument (NEFT/RTGS/IMPS/Cheque), Bank UTR / Cheque reference, GL disbursement (Dr 2020 / Cr 1020), and bank ledger balance deduction.
    5. *Automated Refund Handling (Post-Settlement Adjustments)*: Preserving locked historical batches and logging linked negative adjustments carried forward into the next payout batch.
    6. *Reconciliation & Tally Prime Audit*: Executive Reporting Hub export to CSV, residual payable verification, and transactional outbox background synchronization.
- **Google Help Center Minimalist Redesign ([`help.blade.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/resources/views/filament/pages/help.blade.php))**:
  - Completely restyled the operations manual to mirror Google Help Center's clean, distraction-free aesthetic:
    - Pure white canvas with subtle neutral borders (`slate-200/90`) and soft shadows (`shadow-2xs`).
    - Google pill search input (`rounded-full`, focus ring in light Google blue `ring-blue-100`).
    - Left sidebar topic directory with clear category groupings and clean Google Blue active pills (`bg-blue-50 text-blue-700`).
    - Stripped all visual badge clutter: removed heavy black badges, rainbow tags, and decorative icon clouds. Replaced with clean typography, clear metadata, and generous whitespace.
    - Subtle numbered step circles (`w-6 h-6 rounded-full bg-blue-50 text-blue-700 border border-blue-200/60`).
    - Clean Google-style light-blue tip cards and neutral policy notices (removed unnecessary "Was this helpful? Yes/No" prompt).
    - Intelligent Alpine print mode router:
      - Clicking **"Print this procedure"** cleanly isolates and prints the currently displayed procedure on paper with official Malabar Club SOP letterhead, step-by-step instructions, formula boxes, and policy notices.
      - Clicking **"Print Desk Reference"** prints the single-page laminated Cashier POS Cheat Sheet (Decision D47).
      - Suppressed UI chrome, search bars, sidebars, and buttons during print.
- **Automated Verification**:
  - `php artisan test --filter=HelpCenterTest`: **14 passed, 63 assertions, 0 failures**.
  - `php artisan test --filter=AdminPanelTest`: **7 passed, 71 assertions, 0 failures**.
  - `npm run build`: Compiled in 428ms.

### 21.9 Front Desk Room Balance Payment & Key Issue Workflow Fix
- **Problem Diagnosed**:
  - When collecting balance payment for a room booking before issuing the key, selecting Digital UPI or Member Wallet failed silently due to invalid enum constant references (`TenderType::DIGITAL_UPI` and `TenderType::PREPAID_WALLET` instead of `TenderType::DIGITAL` and `TenderType::PREPAID`).
  - Because of this, `processAccommodationBilling()` caught the runtime exception, logged a warning (`Undefined constant App\Domain\Shared\Enums\TenderType::DIGITAL_UPI`), and returned `null`.
  - While `paid_amount_paise` was incremented on the reservation record, no official `Bill`, `PaymentReceipt`, or double-entry General Ledger journal (`1010/1020` -> `4030 Room Revenue`) was created.
  - Furthermore, the Front Desk Check-In modal opened a disconnected secondary payment modal without closing itself, and failed to refresh the reservation summary in real time, leaving the check-in screen in a stale "Payment Required" state.
- **Implementation & Architectural Improvements**:
  - **Tender Enum Rectification ([`TenderType.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Domain/Shared/Enums/TenderType.php))**:
    - Restored canonical enum cases (`CASH = 'cash'`, `DIGITAL = 'digital'`, `PREPAID = 'prepaid'`) and added explicit `BANK_TRANSFER = 'bank_transfer'` and `CHEQUE = 'cheque'` cases.
    - Updated tender mapping in [`RoomRackPage.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Pages/RoomRackPage.php) to use `TenderType::DIGITAL` for `upi` and `TenderType::PREPAID` for `wallet`.
    - Removed silent exception swallowing; billing errors now bubble up and display informative Filament notifications.
  - **Integrated Front Desk Check-In & Balance Settlement ([`room-rack.blade.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/resources/views/filament/pages/room-rack.blade.php) & [`RoomRackPage.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Pages/RoomRackPage.php))**:
    - Replaced the disconnected multi-modal friction with an integrated Front Desk settlement flow directly inside the Check-In modal:
      - Clean display of Total Room Charge, Advance Paid, and outstanding Balance Due.
      - If unpaid balance exists: embedded collection fields (Amount to Collect in ₹ and Tender Mode: Cash / Digital UPI / Prepaid Wallet).
      - If room is clean: **"Collect ₹[Balance] & Issue Key"** button triggers `confirmCheckInWithPayment()`, which atomically validates payment, creates official Bill and Payment Receipt, updates the GL journal, credits the reservation, transitions status to `checked_in`, sets `checked_in_at = now()`, and hands over the key.
      - If room is not yet clean: displays housekeeping warning and provides **"Collect Payment Only"** button, updating the reservation balance in place without issuing the key until housekeeping finishes.
  - **Bug Fix for Bill Line Print Preview ([`BillsTable.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Resources/Bills/Tables/BillsTable.php))**:
    - Fixed `load(['lines.tariff', 'receipts'])` to `load(['lines', 'receipts'])`, eliminating `RelationNotFoundException` when previewing accommodation and bar bills.
  - **Production Seeder Guard**:
    - Restored safety check in `DemoDataSeeder` (`app()->environment('production') && !env('ALLOW_DEMO_SEEDING', false)`), allowing controlled demo seeding when explicitly configured via `.env` while protecting live environments.
- **Automated Verification**:
  - Created [`RoomRackCheckInWorkflowTest.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/tests/Feature/RoomRackCheckInWorkflowTest.php):
    - `test_room_check_in_with_balance_payment_via_cash`: **Passed** (Receipt amount ₹1,000, Tender: Cash, GL posted, status: `checked_in`).
    - `test_room_check_in_with_balance_payment_via_digital_upi`: **Passed** (Receipt amount ₹1,500, Tender: Digital UPI, GL posted, status: `checked_in`).
    - `test_check_in_blocked_if_payment_amount_less_than_unpaid_balance`: **Passed** (Enforces no-credit invariant; check-in blocked when underpaid).
  - `php artisan test tests/Feature/RoomRackCheckInWorkflowTest.php tests/Feature/FacilityAndBookingTest.php`: **11 passed, 71 assertions, 0 failures**.
  - `php artisan test tests/Feature/RealTallyAndPwaTest.php`: **7 passed, 36 assertions, 0 failures**.

### 21.10 Operational Database Cleanup Command (`club:clean-test-data`)
- **Safety Protocol**:
  - Built dedicated Artisan command [`CleanOperationalDataCommand.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Console/Commands/CleanOperationalDataCommand.php) allowing controlled purging of test transactional data prior to user testing.
  - Temporarily suspends MySQL financial immutability triggers (`trg_bills_no_update_or_delete`, `trg_journal_headers_no_update_or_delete`, `trg_wallet_transactions_no_update_or_delete`), performs surgical cascading deletion of test transactions, resets member wallet balances to ₹0.00, and restores all 10 triggers.
  - **Preserved Core Assets**:
    - 13 Staff Users & Spatie Roles / Permissions
    - 6 Members, 9 Persons, 6 Smartcards, 6 Wallet Accounts
    - 7 Departments, 3 Counters, 7 Facilities (Rooms & Courts)
    - 17 Tariffs, 27 General Ledger Accounts, 5 Suppliers, 3 Contractors
  - **Safely Purged Test Data**:
    - 5 Room/Court Reservations, 4 Bills & Lines, 4 Payment Receipts, 3 Journal Headers & Lines, 2 Cashier Shifts, 1 Guest Pass, 2 Temporary Guests, 6 Outbox messages.

### 21.11 POS Resident Room Guest Counter Sales & Strict Zero-Credit Policy (Decision D58)
- **Problem & Requirement**:
  - Front desk and POS cashiers (dining, bar, sports counter) need to sell items to guests staying in club rooms without forcing them to register as an external day visitor or requiring a physical member card swipe.
  - In strict compliance with Malabar Club Rule 1 (Zero-Credit), room guests cannot "charge to room folio" as unpaid tabs to pay upon checkout. Every counter sale requires immediate confirmed payment (Cash, Digital UPI) or the sponsoring member's prepaid wallet.
- **Implementation Highlights**:
  - **Live Room & Occupant Search ([`CounterPos.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Livewire/CounterPos.php))**:
    - Extended customer search query to actively scan checked-in `RoomReservation` records by Room Code/Number (`101`, `Room 101`, `ROOM-101`), Guest Name (`Rahul Verma`), or Guest Phone.
    - Autocomplete dropdown displays distinct room badges (`🏨 Deluxe Room 101 • Rahul Verma` with green `Checked-In` indicator).
  - **Resident Room Guest Profile Card ([`counter-pos.blade.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/resources/views/livewire/counter-pos.blade.php))**:
    - Renders a styled emerald/teal Resident Room Guest card showing room number, guest name, stay dates (`Check-in -> Check-out`), reservation reference, and sponsoring member name.
    - If the sponsoring member has an active wallet balance, displays available prepaid balance.
    - Explicitly surfaces warning: `⚠️ Zero-Credit Policy (Rule 1): Room-folio post-payment is prohibited. Settle order immediately via Cash or Digital UPI (or Sponsor Wallet).`
  - **Atomic Checkout & Narration Snapshot ([`CounterPos.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Livewire/CounterPos.php))**:
    - Checkout records the sale against the guest with bill notes: `Counter sale for Deluxe Room 101 (Resident: Rahul Verma)`.
    - Generates official `Bill`, `PaymentReceipt`, and GL double-entry journal.
    - Updated thermal receipt printer ([`ThermalReceiptPrinter.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Domain/Integrations/Adapters/ThermalReceiptPrinter.php)) to print room reference lines on 80mm receipts (`Ref: Counter sale for Deluxe Room 101 (Resident: Rahul Verma)`).
- **Automated Verification**:
  - Created [`CounterPosRoomGuestSaleTest.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/tests/Feature/CounterPosRoomGuestSaleTest.php):
    - `test_pos_can_search_and_sell_to_checked_in_room_guest_via_cash`: **Passed** (Room 101 found, Cash tender recorded, receipt ₹380, stock deducted, GL journal balanced).
    - `test_pos_can_search_and_sell_to_checked_in_room_guest_via_digital_upi`: **Passed** (Guest name found, Digital UPI tender recorded, receipt ₹380, GL journal balanced).
  - Full POS test suite verified: `php artisan test tests/Feature/CounterPosTest.php tests/Feature/CounterPosKeyboardTest.php tests/Feature/CounterPosRoomGuestSaleTest.php`: **23 passed, 138 assertions, 0 failures**.

### 21.12 Member PWA Bulletins, Notices & Dues Reminder System (Decision D59)
- **Problem & Requirements**:
  - Staff (Admin, Accounts, Reception, Management) need to communicate important bulletins, dues reminders, and facility updates directly to club members inside the Member PWA.
  - Notices must support club-wide broadcasts, category targeting, specific member reminders, and dynamic targeting for members with overdue/pending annual dues.
  - In-portal presentation requires prominent dashboard alert banners, persistent unread counters, and a dedicated bulletin archive with read tracking.
  - Phase 2 readiness for external multichannel dispatch (WhatsApp & SMS) via the integrations outbox.
- **Implementation Highlights**:
  - **Data Model & Service ([`MemberNotice.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Domain/Communication/Models/MemberNotice.php) & [`NoticeService.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Domain/Communication/Services/NoticeService.php))**:
    - Created `member_notices` table storing title, content, department, priority (`info`, `reminder`, `urgent`, `event`), audience scope (`all`, `category`, `dues_pending`, `individual`), optional action button, banner status, and scheduled dates.
    - Created `member_notice_reads` tracking read receipts and per-member dismissals.
    - Added multichannel outbox dispatcher generating `MEMBER_NOTICE_WHATSAPP` and `MEMBER_NOTICE_SMS` queued entries.
    - Added 1-click `broadcastDuesReminders()` service method targeting members with `fee_status = 'pending'`.
  - **Back-Office Filament Resource ([`MemberNoticeResource.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Resources/MemberNotices/MemberNoticeResource.php))**:
    - Registered under `Membership & Guest Desk` with permission `'notices.manage'`.
    - Equipped with rich audience targeting selectors, department filters, and sticky banner toggle.
    - Added header action **"Broadcast Dues Reminder"** in [`ListMemberNotices.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Resources/MemberNotices/Pages/ListMemberNotices.php) with confirmation modal calculating total overdue members and dispatching instant notices.
  - **Member PWA Experience ([`member-dashboard.blade.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/resources/views/livewire/member/member-dashboard.blade.php) & [`member.blade.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/resources/views/layouts/member.blade.php))**:
    - **Header Bell Icon**: Added notification bell to portal topbar displaying live unread counter (`🔔 (Unread)`).
    - **Prominent Sticky Banners**: Renders high-priority notices at the top of the dashboard with department badges, action links, and one-click dismissal.
    - **Dedicated Tab 7 (Club Notices)**: Full chronological bulletin list with department pills, unread status indicators, action buttons, and individual/bulk **"Mark All as Read"** controls.
- **Automated Verification**:
  - Created [`MemberNoticeTest.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/tests/Feature/MemberNoticeTest.php):
    - `test_staff_can_create_club_wide_notice`: **Passed**
    - `test_notice_service_targets_members_with_pending_dues_correctly`: **Passed**
    - `test_member_pwa_dashboard_renders_prominent_dues_banner`: **Passed**
    - `test_member_can_mark_notice_as_read_and_dismiss_banner`: **Passed**
    - `test_outbox_messages_queued_when_whatsapp_or_sms_enabled`: **Passed**
    - `test_filament_admin_can_access_and_broadcast_dues_notices`: **Passed**
  - Full suite verified: `php artisan test tests/Feature/MemberNoticeTest.php tests/Feature/RealTallyAndPwaTest.php tests/Feature/AdminPanelTest.php`: **20 passed, 136 assertions, 0 failures**.

### 21.13 Bar / Lounge Running Tabs & Concurrent Bill Parking (Decision D60)
- **Problem & Requirements**:
  - In the clubhouse bar/lounge area, 20–30+ members can be present simultaneously having drinks. The POS previously forced a strict single-customer checkout queue, creating an operational bottleneck when patrons order multiple rounds over a session.
  - Required ability to park/hold bills concurrently (running tabs) and settle them individually when members leave.
  - Confirmed requirements from management:
    1. **Member-First Naming Policy**: Members roam between bar stools, lounge seating, and pool tables; tab must bind first to the member identity, with optional table/stool location note.
    2. **KOT Provision**: Database and model architected to support future Kitchen/Bar Order Ticket dispatch without breaking current counter operations.
    3. **Capacity**: Configurable concurrent active tabs up to 50 (`config('club.pos.max_held_tabs')`).
    4. **Malabar Club Rule 1 (Zero-Credit)**: Held tabs are in-session draft orders within an active cashier shift—they are NOT credit tabs or accounts receivable. Shift close is strictly blocked if any unsettled tabs remain.
- **Implementation Highlights**:
  - **Database Migration & Model ([`PosHeldTab.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Domain/Billing/Models/PosHeldTab.php))**:
    - Created migration `2026_11_10_181438_create_pos_held_tabs_table.php` (`pos_held_tabs` table).
    - Tracks `shift_id`, `counter_id`, `tab_number`, `customer_type`, `member_id`, `room_reservation_id`, `guest_id`, `customer_name`, `customer_identifier`, `table_identifier`, `notes`, `cart_items`, `gross_amount_paise`, `tax_amount_paise`, `net_amount_paise`, `status` (`open`, `settled`, `voided`), `settled_at`, `settled_bill_id`, `created_by`.
    - Added scopes `open()`, `forShift()`, `forCounter()`, and convenience accessors `total_amount`, `total_paise`, `items_count`, `customer_display_name`, `held_at`.
  - **Counter POS Livewire Engine ([`CounterPos.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Livewire/CounterPos.php))**:
    - `openHoldTabModal()`: Validates active shift, non-empty cart, and member-first customer selection.
    - `holdCurrentCart()`: Saves or updates running tab up to configured limit (`max_held_tabs`), clears workspace cleanly for next customer without wiping notification messages.
    - `loadHeldTab(int $tabId)`: Seamlessly switches tabs; auto-saves dirty cart if cashier was editing another tab.
    - `voidActiveHeldTab(?string $reason)`: Allows staff to cancel an open tab with logged audit remark.
    - `closeShift()`: Enforces Zero-Credit policy by checking `PosHeldTab::forShift(...)->open()->count() > 0` and rejecting closure until all tabs are settled or voided.
    - `confirmCheckout()`: Automatically transitions the active held tab to `settled`, stamping `settled_at` and `settled_bill_id`.
  - **Intuitive Keyboard & Touch POS UI ([`counter-pos.blade.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/resources/views/livewire/counter-pos.blade.php))**:
    - **Running Bar Tabs Strip**: Persistent card atop workspace displaying live open count badge, scrollable tab chips showing customer name, table badge, and live rupee total, with 1-click load and `+ New Bill` actions.
    - **Active Bar Tab Indicator**: Prominent badge above order board when editing an open tab, with quick "Park & New" and "Void Tab" buttons.
    - **Hold Tab [F6] Buttons**: High-visibility button on bill header and docked footer status bar.
    - **Modal 5 (Hold / Park Tab)**: Captures optional table/stool identifier and staff remark; confirms with <kbd>Enter</kbd> or cancels with <kbd>Esc</kbd>.
    - **Modal 6 (All Held Tabs Manager)**: Search filter and scrollable grid of all active tabs with direct "Open Tab ↵" and "Void" actions.
    - **Alpine.js Hotkeys**: Integrated `<kbd>F6</kbd>` global key listener and modal lifecycle handlers.
- **Automated Verification**:
  - Created [`CounterPosHeldTabsTest.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/tests/Feature/CounterPosHeldTabsTest.php):
    - `test_cannot_hold_tab_with_empty_cart`: **Passed**
    - `test_cannot_hold_tab_without_member_selected`: **Passed** (Member-First policy enforced)
    - `test_can_park_cart_as_running_tab`: **Passed** (Stores tab in database with table and notes)
    - `test_can_park_multiple_tabs_and_switch_between_them`: **Passed** (Multi-tab switching with dirty cart auto-save and incremental rounds)
    - `test_settling_held_tab_updates_status_and_links_bill`: **Passed** (Status becomes `settled`, `settled_bill_id` recorded)
    - `test_shift_close_is_blocked_if_open_tabs_remain`: **Passed** (Blocks till closure until settled or voided)
    - `test_max_held_tabs_limit_is_enforced`: **Passed** (Configurable ceiling strictly respected)
  - Full suite verified: `php artisan test tests/Feature/CounterPosTest.php tests/Feature/CounterPosKeyboardTest.php tests/Feature/CounterPosHeldTabsTest.php`: **28 passed, 152 assertions, 0 failures**.

---

## POS Arrow Key Navigation & High-Speed Keyboard Autocomplete (Delivered)

- **Problem Addressed**:
  - Arrow key navigation in Counter POS was sluggish and prone to skipping items or selecting the wrong item on rapid Enter keypresses:
    1. **Network Latency & Race Conditions**: Item Search and Customer Search autocomplete lists previously dispatched synchronous Livewire server roundtrips (`wire:keydown.arrow-down.prevent="navigateItemResults('down')"`), re-rendering the entire template. Fast typing followed by Enter would frequently select the first item due to uncommitted network latency.
    2. **Serving Modal Double-Skip Bug**: In the Multi-Serving modal, both the modal `div` (`x-on:keydown.arrow-down.prevent`) and the global `window` listener (`handleGlobalKeydown`) handled arrow events without stopping propagation, causing each Arrow Down keypress to skip two serving sizes at once.
    3. **Missing Auto-Scroll**: For lists longer than 4 items inside `max-h-80 overflow-y-auto`, moving the highlight down did not scroll hidden items into viewport.
    4. **Cart Row Navigation vs Native Number Inputs**: When focused on `.cart-qty-input`, browser native arrow keys incremented/decremented the numeric quantity rather than navigating vertically across bill rows.
- **Implementation Highlights**:
  - **Zero-Latency Alpine.js Navigation Engine ([`counter-pos.blade.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/resources/views/livewire/counter-pos.blade.php))**:
    - Added reactive state variables: `highlightedItemIdx`, `highlightedCustIdx`, `highlightedServingIdx`.
    - Added instant client-side methods: `navigateItems(direction)`, `selectItem()`, `navigateCustomers(direction)`, `selectCustomer()`, `navigateServings(direction)`, `selectServing()`, and `scrollActiveIntoView(id)`.
    - Automated index resets with Alpine watchers on `$wire.itemSearchResults`, `$wire.customerSearchResults`, `$wire.servingItemOptions`, and `$wire.showServingModal`.
    - Integrated vertical cart line navigation in `.cart-qty-input` (`@keydown.arrow-down.prevent` and `@keydown.arrow-up.prevent`) allowing cashiers to fluidly cycle focus and select row quantities without leaving the keyboard.
    - Synchronized client highlights with Livewire in the background using `wire.set(..., ..., false)` to maintain 100% backward compatibility with automated backend feature tests.
- **Automated Verification**:
  - `php artisan test tests/Feature/CounterPosKeyboardTest.php tests/Feature/CounterPosTest.php tests/Feature/CounterPosHeldTabsTest.php`: **28 passed, 152 assertions, 0 failures**.
  - `php artisan test --filter=Pos`: **50 passed, 301 assertions, 0 failures**.
  - Recompiled production assets with `npm run build` (0 errors).

---

## Configurable Zero / Negative Stock Sales on POS (Delivered)

- **Context & Client Requirement**:
  - In daily clubhouse operations, physical beverage or food stock frequently arrives at the counter/bar, but the storekeeper or receiving clerk may not have entered the supplier invoice / purchase receipt (GRN) into the system yet.
  - Under strict inventory enforcement, cashiers were blocked with *"Item is out of stock"*, causing customer friction at the counter.
  - Client requested an administrative setting to allow selling zero/negative stock items silently without warning or alert dialogs.
- **Implementation Highlights**:
  - **System Settings Configuration ([`SystemSettingsPage.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Pages/SystemSettingsPage.php) & [`system-settings.blade.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/resources/views/filament/pages/system-settings.blade.php))**:
    - Added setting toggle `allow_zero_stock_sales` under Tab 2 ("Billing & Cashier Policies").
    - Restricted strictly to Administrators and Managers (`canAccess` policy).
    - Preserved default `false` (strict stock enforcement) with persistence via `SystemSetting`.
  - **Counter POS Logic ([`CounterPos.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Livewire/CounterPos.php))**:
    - In `addTariffToCartInternal`, checks `allowZeroStockSales()`: when enabled, bypasses out-of-stock error blocks and adds items silently to the cart without warning prompts.
    - In item autocomplete and catalogue search, displays current recorded stock quantity as available for billing without red disabled blocks.
  - **Transactional Stock Issuance ([`InventoryService.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Domain/Inventory/Services/InventoryService.php))**:
    - In `issueStockForBillLine`, checks `allow_zero_stock_sales`: when enabled, skips `InsufficientStockException`, updates `current_stock_qty` into negative balance, and records an immutable `InventoryMovement` of type `sale_issue`.
    - Negative balance self-reconciles cleanly when the storekeeper posts the subsequent purchase invoice or stock adjustment.
- **Automated Verification**:
  - `php artisan test tests/Feature/SystemSettingsTest.php`: **8 passed, 35 assertions, 0 failures**.
  - `php artisan test tests/Feature/CounterPosKeyboardTest.php`: **17 passed, 91 assertions, 0 failures** (including `test_out_of_stock_item_cannot_be_added_to_bill` and `test_out_of_stock_item_can_be_added_and_settled_when_allow_zero_stock_sales_enabled`).
  - `php artisan test --filter=Pos`: **51 passed, 312 assertions, 0 failures**.

---

## Global Search Exception Fix & Permission Guard Hardening (Delivered)

- **Root Cause Identified**:
  1. **Shift Status Enum `TypeError` ([`ShiftResource.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Resources/Shifts/ShiftResource.php))**: `Shift::$casts['status']` casts to `ShiftStatus` (`BackedEnum`), whereas `ShiftResource::getGlobalSearchResultDetails()` called `ucfirst($record->status ?? 'open')` directly. On PHP 8.4, any global search matching a `Shift` record (by `shift_number`, `cashier.name`, `counter.name`, or `business_date`) threw `TypeError: ucfirst(): Argument #1 ($string) must be of type string, App\Domain\Shared\Enums\ShiftStatus given`.
  2. **Uncaught `PermissionDoesNotExist` Risk in Panel/Search Access Checks**: `ClubGlobalSearchProvider` and Filament resource/page authorization methods (`canViewAny()`, `canAccess()`, etc.) used `$user->hasPermissionTo(...)`, which throws `Spatie\Permission\Exceptions\PermissionDoesNotExist` when a non-whitelisted role searches or loads navigation before a newly introduced permission (such as `notices.manage`) is seeded.
  3. **Quick-Link Route Mismatch & Navigation Group Enum Safety ([`ClubGlobalSearchProvider.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Domain/Shared/Services/ClubGlobalSearchProvider.php))**: Updated the Club System Settings quick-jump URL from `/admin/system-settings` to the registered `/admin/settings` route (`SystemSettingsPage::$slug = 'settings'`), and normalized `string|UnitEnum|BackedEnum` navigation group labels.
- **Implementation Highlights**:
  - Unwrapped `ShiftStatus` via `instanceof BackedEnum` before `ucfirst()` in [`ShiftResource.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Filament/Resources/Shifts/ShiftResource.php).
  - Replaced `hasPermissionTo(...)` with Spatie's non-throwing `checkPermissionTo(...)` across [`ClubGlobalSearchProvider.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Domain/Shared/Services/ClubGlobalSearchProvider.php), 11 Filament resources (`AccountResource`, `BankAccountResource`, `ContractorSettlementBatchResource`, `ContractorResource`, `ExpenseResource`, `MemberNoticeResource`, `MembershipCategoryResource`, `PurchaseReceiptResource`, `SupplierResource`, `TariffResource`, `UserResource`), and 5 Filament pages (`CourtSchedulePage`, `FinancialReportsPage`, `IntegrationStatusPage`, `RoomRackPage`, `SystemSettingsPage`).
  - Expanded [`GlobalSearchScalingTest.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/tests/Feature/GlobalSearchScalingTest.php) to include `MemberNoticeResource`, Livewire `Filament\Livewire\GlobalSearch` tests against populated `Shift` records, missing-permission resilience tests, and `/admin/settings` route verification.
- **Automated Verification**:
  - `php artisan test tests/Feature/GlobalSearchScalingTest.php`: **12 passed, 95 assertions, 0 failures**.
  - Full suite `php artisan test` on MySQL (`malabar_club_testing`): **353 passed, 1,885 assertions, 0 failures**.

---

## Unified Login Experience, Role-Based Landing & Security Hardening (Delivered)

- **Feature Summary**:
  - Consolidated authentication entry at `/` and `/login` into a unified, accessible login experience with segmented tabs (**Staff Terminal** | **Member Portal**), client-side `localStorage` tab memory (`malabar_login_tab`), `?tab=staff|member` query-string support, and automatic preservation of the active tab on validation failure (`_portal` old input).
  - Centralized post-login landing resolution into [`UserLandingService`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Domain/Shared/Services/UserLandingService.php):
    - `admin`, `manager`, `accountant`, `auditor` &rarr; `/admin`
    - `reception` &rarr; `/admin/room-rack`
    - `cashier` &rarr; `/pos`
    - Club members (`auth:member`) &rarr; `/member/dashboard`
    - Protected deep-links still honor `redirect()->intended(...)`.
  - Preserved Filament's `/admin/login` route registration via [`AdminLoginRedirectController`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Http/Controllers/AdminLoginRedirectController.php) (redirecting guests cleanly to `/login?tab=staff` while keeping `session('url.intended')` intact) and preserved standalone `/member/login` & `/member/activate` routes for Member PWA `scope: "/member/"`.
  - Hardened [`AuthController`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Http/Controllers/AuthController.php) and [`MemberAuthController`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/app/Http/Controllers/Member/MemberAuthController.php) by gating development fallback passwords (`password`/`Password@123`, `1234`/`Member@123`) and demo member phone shortcuts behind `!app()->environment('production')`, and blocking deactivated (`is_active === false`) staff logins on `/login`.
  - Applied `modern-web-guidance` tablist/form standards across [`resources/views/auth/login.blade.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/resources/views/auth/login.blade.php) and [`resources/views/member/auth/login.blade.php`](file:///Users/krishnadaspnair/Documents/Skysys/Products/malabar-club/resources/views/member/auth/login.blade.php) (WAI-ARIA `tablist`/`tab`/`tabpanel`, roving `tabindex`, Left/Right/Home/End keys, `hidden="until-found"` with `beforematch`, structural active underline indicator alongside tint, `autocomplete="username"`/`current-password`, show/hide password toggles with `aria-pressed`, `>=48px` tap targets, `>=16px` input sizing).
- **Automated Verification**:
  - `npm run build`: compiled updated Vite production assets (`public/build/assets/app-B2B6Kd4W.css`, `public/build/manifest.json`) with 0 errors.
  - `php artisan test tests/Feature/UnifiedLoginTest.php tests/Feature/MemberPortalTest.php tests/Feature/HelpCenterTest.php tests/Feature/ExampleTest.php tests/Feature/MemberProfileViewTest.php tests/Feature/AdminPanelTest.php`: **70 passed, 421 assertions, 0 failures**.
  - Full suite `php artisan test` on MySQL (`malabar_club_testing`): **362 passed, 1,992 assertions, 0 failures**.

