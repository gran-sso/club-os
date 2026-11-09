# Malabar Club — application development specification

Prepared for Skysys Infosolutions OPC Pvt. Ltd. • Version 1.0 • 17 October 2026

## 1. Goal and confirmed facts

Develop one integrated, staff-operated management and billing application for Malabar Club, Vadakkencherry, Kerala. The system must support approximately 460 current members, five rooms, one badminton court, two to three billing counters, and five staff users, with growth through configuration. These numbers describe the starting operation; do not hardcode them as software limits.

The club operates the facilities directly, with one specific exception: food preparation is contracted externally. The club receives food payments and remits the qualifying collected amount to the contractor on the following day. Records must separate food sales, payment allocations, settlement amounts, payments, and balances owed to the contractor.

Required facilities are membership, bar, card room, accommodation, gym, and badminton. Gym and badminton have monthly and annual plans plus daily guest access. Guests may be sponsored by either an eligible club member or an eligible affiliated-club member.

The club forbids credit to all facility users. Guests using the bar/card room must register and pay applicable entry charges before admission, and must settle all consumption. Members also pay immediately or use confirmed prepaid funds. Cash and digital payments coexist with member identification/prepaid cards. Facial and/or fingerprint entry integration is required wherever supported by selected hardware.

Central reports cover sales, collections, expenses, stock, contractor settlements, outstanding exceptions/payables, reconciliation, and department/overall profitability for daily, weekly, monthly, yearly, and custom periods. Tally integration is required.

## 2. Decisions that allow development to start

These are implementation defaults, not additional claims made by the club. Build them as configuration where indicated and record future changes in `DECISIONS.md`.

| Topic | Development decision |
| --- | --- |
| Organisation | One club and one operating entity; no multi-tenant subscription platform |
| Architecture | One Laravel application and authoritative MySQL database, divided into domains |
| Initial deployment | Design for a cloud Linux VPS with reliable club connectivity; keep deployment portable to a club LAN server |
| Offline money | Disabled. A disconnected counter cannot spend prepaid funds from a cached balance |
| Currency/time | INR; local display and business dates in Asia/Kolkata; store instants consistently in UTC |
| Fiscal reporting | Configurable financial-year start, initially 1 April; weekly reports Monday–Sunday; business-day cutoff initially local midnight |
| Payment policy | Confirmed prepayment/immediate settlement, including accommodation before check-in; no discretionary credit override |
| Cards | Server-linked identifiers; balances are not authoritative on the card. Cardholder confirmation is required |
| Entry | Membership/facility entitlement and paid guest pass determine admission; a low wallet balance alone does not cancel otherwise valid membership access |
| Guest wallet | Guest registration and direct payment are mandatory capabilities. Temporary guest prepaid accounts/cards are configurable; sponsorship alone never authorises spending the sponsor's money |
| Contractor | No retained club commission assumed. Track 100% of configured payable food allocations, subject to explicit tax/accounting mapping |
| Costing | Perpetual moving weighted-average inventory costing, including opening cost; use one documented policy consistently |
| UI | English initially, full Unicode data support, desktop/tablet-first staff screens, mobile-friendly layouts |
| Member portal | Optional extension after all confirmed staff operations; not a first-release dependency |
| Missing hardware | Continue using tested adapter contracts and labelled development simulators; track live qualification separately |

Do not invent real membership fees, tariffs, tax rates, opening balances, bank details, licence rules, contractor deductions, member records, or device credentials. Fictional development fixtures may contain clearly labelled example rates. Production configuration must explicitly supply required financial mappings instead of silently falling back to zero.

## 3. Technology and project organisation

Use PHP 8.4, Laravel 13, MySQL 8.4 LTS/InnoDB, Filament 5, Livewire 4, Blade, Tailwind CSS 4.1+, and Alpine.js. Use Laravel session authentication, policies and compatible Spatie Laravel Permission for granular authorisation. Resolve stable compatible package versions, commit Composer/npm lockfiles, and verify current APIs in official documentation. Node.js is used for asset builds and development tooling.

Use Laravel queues and scheduler. The database queue is sufficient initially; Redis is an optional operational enhancement. Queued work includes integration delivery, statements, notifications, exports, report snapshots, and approved recognition schedules. Workers and schedules need documented production supervision.

Keep the standard Laravel directory structure. Suggested application domains are Membership, Access, Payments, Billing, Inventory, ContractorSettlements, Accommodation, Activities, Finance, and Integrations. Put reusable application actions/services behind thin controllers/Livewire components/Filament resources. Do not add microservices or a separate frontend deployment.

Use MySQL foreign keys, unique indexes, check constraints where applicable, exact types, and explicit transactional boundaries. Give financial events immutable identifiers and source references. Use optimistic version checks for editable settings and draft orders; use row locks for wallets, stock balances, and resource reservations.

### 3.1 Core data model

Use this as an entity catalogue, not permission to generate every screen before its workflow exists. Finalise fields and migrations during the relevant stage.

| Domain | Principal records |
| --- | --- |
| Identity/security | Staff users, roles/permissions, persons, members, membership categories, dependent relationships, affiliated clubs/members, issued cards |
| Configuration | Departments, counters, shifts, facilities/resources, effective-dated tariff/tax rules, approval permissions, numbering settings |
| Guests/access | Visits, guest registrations, sponsor links, paid access passes, device identity mappings, permission sync attempts, access events |
| Billing/payments | Draft orders/lines, posted bills/lines, payment intents/attempts, verified receipts, tender allocations, refund/reversal records |
| Prepaid | Wallet accounts, immutable wallet entries, reservations/releases, recharge receipts, reconciliation exceptions |
| Inventory | Stock items/base units, sellable-serving mappings, suppliers, purchase receipts/returns, locations, stock movements, cost snapshots, counts/adjustments |
| Contractor | Agreements, food-payable allocations linked to bill lines, settlement batches/lines, approved adjustments, confirmed contractor payments |
| Accommodation | Rooms/types, bookings/occupants, room-night/payment allocations, maintenance blocks, housekeeping tasks/status changes |
| Activities | Plans, subscriptions/entitlements, court reservations/participants, attendance, daily passes, recognition schedules |
| Finance | Chart of accounts, journal headers/lines, expenses/payables, payment allocations, shared-cost rules, period closures, opening-balance batches |
| Integration/audit | Transactional outbox, delivery attempts/acknowledgements, external ID mappings, import batches, audit events |

Important constraints include unique membership/card references; unique financial idempotency key plus stored request hash; unique source-event journal posting; unique provider event/receipt references within their provider/account scope; and one settlement allocation per eligible food event. A single receipt may allocate to several lines/bills only up to its confirmed amount. Reversal and refund links must prevent over-refund.

Do not implement booking overlap protection as a simple unique start-time column. All booking writers must lock the same resource row, validate the whole requested interval and maintenance blocks, and commit under the same protocol. Document deterministic lock ordering for transactions touching multiple wallets/resources/stock items.

## 4. Roles and staff workflow

Roles are configurable and may be combined for five actual employees. Initial role templates: Administrator, Manager, Cashier, Reception/Facilities, Accountant, and read-only Auditor. Do not create a separate employee for every role automatically.

Permissions cover member administration, guest registration, recharge receipts, sales, refunds, stock purchases, stock adjustments, expenses, contractor settlement preparation/payment, financial closing, tariff editing, reports, and integration configuration. A cashier cannot grant themselves refund/adjustment permissions. Shared staff logins are not the default.

Each cashier opens a shift on an assigned counter with an opening cash float. Record transactions by employee, counter, department, tender and business date. On closing, compare expected cash with counted cash, digital receipts with provider/terminal records, and wallet tenders with ledger debits. Floats and cash transfers are not revenue. Record discrepancy reason and manager resolution; close with an immutable snapshot.

## 5. Membership, families, affiliates, and migration

Maintain membership number, category, photo, contact information, joining date, status, validity, renewal rules, optional documents, dependents, cards, and transaction history. Separate the person from their membership/entitlements and from the staff authentication account. A sponsor, payer, room occupant, and member may be different people.

Base club membership and facility plans have separate validity. Payment of a gym plan does not renew base membership automatically. Life membership can have separate paid facility subscriptions. Draft renewal requests are not an authorisation for credit or access.

Household/dependent access and use of a primary member's prepaid account require explicit permissions. Provide both individual transaction attribution and an optional authorised household statement. Do not merge every family member into a single anonymous identity.

Maintain affiliated clubs, affiliated-member identities, verification/validity, and sponsoring relationships. Club-member and affiliated-member sponsorship must both work. Guest rules may vary by facility, with clear reasons for denied entry.

Provide a CSV import template, dry-run preview, duplicate detection by membership number, row-specific errors, and approved import batches. Preserve club-supplied membership numbers. Import opening prepaid balances only with a reconciled opening journal and source batch. Legacy debts, if encountered, are separately flagged historical exceptions and do not enable post-go-live credit.

## 6. Guest registration and facility access

Reception registers guest name, appropriate contact/identity information, sponsor type and identifier, visit date, permitted facilities, payer, and relevant room stay. Store documents privately only when required by the club's configured process. Retain an audit trail without copying unnecessary sensitive data into logs.

For bar and card room admission, create a guest pass only after the required fee is settled. Link each pass to its bill line/payment and guest. Give passes an explicit validity window, facilities, status, and permitted entry count/re-entry policy. A refund/void or cancellation revokes the related unused entitlement consistently. Record denied entry with a reason.

The initial paid-guest-entry policy does not allow staff to bypass the fee by a complimentary issue or a 100% fee discount. General stock-adjustment permissions do not grant guest access. Any later free-entry category would require an explicit change to the club's business policy, not a hidden cashier workaround.

Ordinary membership access does not require an arbitrary minimum wallet balance. Check applicable membership/facility validity and required entry fees. Top-up alone does not create a gym plan or entry pass. A sponsor relationship alone does not charge the sponsor's wallet.

Access decisions control admission. Checkout staff can see unresolved payment exceptions, but payment rules must not become a mechanism that prevents emergency egress.

## 7. Cards and prepaid ledger

A card has an internal identifier, token/reference appropriate to the chosen reader, owner, status, issuance/replacement history, and last-use metadata. The card does not hold the authoritative monetary balance. Support keyboard-input/manual lookup for development and a defined reader adapter for qualified hardware. A simple readable UID alone is not proof of cardholder authority; support staff photo verification and a configurable PIN/approved second check for spending.

Provide confirmed recharges through cash or verified digital receipts, balance enquiries, statements, spend, approved refund/reversal, card block/replacement, and access expiry independent of wallet history. An old blocked card cannot operate after replacement. Show the same ledger after a card is replaced.

Store money as signed BIGINT paise in posted ledgers. Use exact decimal arithmetic for prices/quantities before the documented rounding step. Maintain an immutable wallet ledger and a transactionally updated balance snapshot that is reconciled to that ledger. Available balance excludes active reservations. A debit locks the account row and cannot exceed available funds.

Recharges are funding receipts backed by an appropriate advance/liability account. Wallet redemption settles a bill and reduces that liability; it is not another external cash receipt. Do not offer peer-to-peer wallet transfers, public payment acceptance, or arbitrary manual balance editing.

If a confirmed external payment is later reversed/charged back after funds were spent, record the real exception, restrict further affected use according to policy, and send it for resolution. Never conceal it with a fake recharge or silently grant new credit. The no-credit rule blocks new authorised credit; it does not justify erasing genuine payment exceptions.

Reverse unused funding where available; classify any already-consumed amount in the appropriate reconciliation/recovery account under approved accounting policy. Keep available spend at zero and further affected spending blocked until resolved.

## 8. Billing, payments, and financial posting

Support cash, bank-card terminal receipts, UPI/other configured digital receipts, and prepaid tenders. Keep member prepaid cards distinct from bank debit/credit cards. A manual digital confirmation requires an authorised employee, unique transaction reference, amount, time, and audit record; a screenshot or browser redirect alone is not automatic payment verification.

Provide draft orders, customer/guest selection, department attribution, immutable item/rate/tax snapshots, discounts with approval, tender allocations, receipt printing, refunds, and reversals. Require an idempotency key on financial actions and provider callbacks. Enforce exact total allocation; mixed tenders must not partially authorise fulfilment. Implement an initial single-tender workflow first, then add split tenders only with corresponding failure/retry tests.

Use separate state models for drafts, payment attempts, confirmed allocations, posted bills, refunds, and fulfilment. Do not issue stock, activate a paid entitlement, or finalise service delivery against an unpaid bill. A statement displays charges and settled payments; it must not create another tax invoice or rebill settled sales.

Within one database commit, consistently post applicable bill, wallet debit, payment allocations, journal, stock issue, contractor allocation, and outbox event. External gateway capture cannot be rolled back by a database transaction. Implement explicit recovery for captured-but-unposted money, timeouts, duplicate confirmations, and refunds in progress. Do not call an external provider while holding long-running wallet/stock locks.

Assign bill numbers atomically with unique constraints. Reprints keep the original number and are identifiable as copies. Allow items from multiple departments to share a bill while retaining line-wise accounting and stock attribution. If tax or invoice-series requirements require separate documents, preserve one checkout/payment reference across the linked documents.

The initial fulfilment rule is pay before issue/service. An optional running-order workflow must reserve enough prepaid value before fulfilment and settle/release those reservations atomically. Unfunded bar tabs are out of scope.

Taxes, discounts, rounding, fee inclusion, and invoice formats are configurable and effective-dated. Do not hardcode a common tax treatment across liquor, food, accommodation, subscriptions, and entry fees. Snapshot the rules used so later changes do not alter old bills.

## 9. Bar, card room, purchasing, and stock

The bar POS provides member/card search, guest validation, category/item buttons, quantity and serving selection, quick tender, balance display, and clear payment errors. Provide full-bottle, can/unit, and measured-serving items with explicit base-unit conversions. Link each sellable serving to the inventory item and required quantity. Track unopened and opened stock sufficiently to reconcile both quantity and value.

Support suppliers, purchases/receiving, purchase returns, opening stock, store-to-bar transfers, stock issues, approved wastage/breakage/complimentary issues if authorised, physical counts, adjustments, reorder levels, and item-wise movement history. Complimentary issues require explicit zero-price permission and a reason; they cannot be used as an unrestricted no-payment bypass.

Use moving weighted-average cost consistently. Stock consumption/COGS and sales returns must use traceable costs. A cancelled unfulfilled order is different from a paid drink already poured: a financial refund does not automatically put consumed liquor back into stock. Record physical return or wastage explicitly. Do not overwrite historic sale costs when a new purchase changes average cost.

The card room is a distinct department with configurable day/session/table usage fees as required. Track charged consumables where the club actually stocks them. The word 'card room' does not introduce gambling balances, betting, winnings, or payouts; these are not requested.

Food ingredients held by the contractor are not automatically club-owned inventory. Track ownership explicitly if a later club arrangement requires club supplies to be issued to the contractor.

## 10. Food contractor settlement

Maintain the contractor, agreement dates, eligible food items, settlement schedule/cutoff, accounting mapping, and authorised payment details. Initially assume no retained commission. Configure tax/pass-through allocation explicitly with the accountant before real posting.

For every settled food line, record gross bill value, discounts, refunds, tax/accounting components, qualifying contractor payable, settlement batch, and payment/reversal references. Funding tender can be cash, confirmed digital, or prepaid redemption. Unused wallet recharge never creates a contractor payable.

Prepare the previous business day's eligible transactions for payment on the next day. Preview the line list and totals; prevent a line from entering two settlement batches. Keep preparation, approval where configured, payment confirmation, and reconciliation distinct. A payment-in-progress is not a completed contractor payment.

Track opening contractor payable, new allocations, adjustments, confirmed transfers, and closing payable. Partial/failed transfers leave a real residual payable. Refunds after a batch was paid create a linked adjustment for a subsequent settlement or contractor recovery; do not rewrite a paid batch.

Provide accountant-configurable gross-sales/contractor-cost versus collection-on-behalf mappings. The real agreement determines presentation; software must not assume all food receipts are club income or profit. Operational gross food billing remains visible whichever accounting mapping is selected.

## 11. Five-room accommodation

Maintain room number/type, capacity, tariff plans, occupancy, and housekeeping/maintenance status. Provide a visual availability calendar, member/guest reservation, sponsor and payer, date changes, cancellation/no-show, advances, check-in, checkout, and additional paid services.

Use half-open time intervals and a documented nightly charging rule. Reservation availability and physical room readiness are separate. A room may be reserved but awaiting cleaning. Prevent confirmed overlapping bookings, including concurrent submissions, by locking the shared room/resource row while checking and committing reservations. Maintenance blocks participate in the same availability rules.

Full accommodation charges must be covered before check-in under the default no-credit policy. Reservation deposits can reserve future availability but do not authorise an unfunded stay. Additional nights require payment before extension. Paid extras settle immediately or through confirmed prepaid allocation; checkout reconciles charges, deposits, and approved refunds without double charging.

Check-in/check-out times, maximum advance booking, cancellation terms, and member/guest tariffs are settings. Initial development examples may use 14:00/11:00, explicitly identified as defaults. Guest identity and sponsor are not the same as the bill-to party.

## 12. Gym and badminton

Provide monthly and annual plans, eligible holder categories, effective dates, renewal, payment, suspension/extension with approval, attendance, and daily guest access. Separate entitlement sale from facility usage. A valid plan can cover usage charges while a separate guest entry fee may still apply according to configuration.

Monthly/annual validity uses calendar periods with documented month-end/leap-year handling, not an assumed 30/365 days. Store precise exclusive end boundaries. The same entitlement service is used by reception, booking, and access-control decisions.

Maintain the badminton court, operating hours, configurable slots initially 60 minutes, booking window, participants, guest counts, cancellation/no-show rules, maintenance/coaching blocks, and usage charges. The default booking payer is the booking person, with separate guest fees where applicable. Reserve any required capacity atomically; do not charge every player the full court fee by accident. Plan-included bookings must still respect availability and booking limits.

Membership/subscription income and cash receipts are reported separately. For longer service periods, support accountant-approved deferred-revenue/recognition schedules so management P&L can allocate income to the service period. The demo policy may use daily straight-line allocation with a deterministic final-day rounding adjustment; actual policy remains configurable.

## 13. Expenses, finance, reconciliation, and reports

Maintain one operational chart of accounts and balanced journal headers/lines tied to source transactions. Include cash/bank/clearing, prepaid advances, contractor payables, tax components, department income, inventory/COGS, expenses, and approved adjustment accounts. Journal posting is append-only; reversals link to the original entry. Prevent a source from posting twice.

Capture expense categories, supplier/payee, document, period, department, payment method, approval, and payment reference. Support outstanding supplier/contractor payables without enabling customer credit. Store authorised accruals and shared-expense allocations where needed for profitability. Opening balances must form a balanced, reconciled batch.

Provide daily, weekly, monthly, yearly and custom reports with common filters for department, counter, employee, payment method, member category, and business date where relevant. All reports need drill-down to source records and CSV export; receipts/statements need print-friendly layouts.

Required reports: department/counter sales; external collections and tender allocation; cashier shifts; cash/digital reconciliation and exceptions; member/prepaid statements; prepaid liability reconciliation; guest fees/access; bar/card-room sales and stock; stock valuation/COGS/wastage; food sales and contractor settlements/payables; room occupancy/revenue/advances; gym/badminton plan sales, usage and recognised income; expenses; income/expenditure; cash flow; department and overall management P&L; integration exceptions.

P&L must distinguish operational billed values from accounting revenue, use consumed inventory costs, reflect approved revenue schedules/expenses, and disclose shared-cost allocations. It must reconcile to the same posted ledger used for Tally mapping. Tally-side year-end or other approved adjustments must be imported or included through an explicit reconciliation process if the club app's P&L is expected to match final books.

An outstanding report can show renewal requests due, supplier/contractor payables, payment reversals and reconciliation exceptions. It cannot introduce an approved debtor-credit option for members or guests.

## 14. Tally, hardware, payments, and printing

Create interfaces for `AccountingConnector`, `AccessControlProvider`, `CardReader`, `PaymentProvider`, and `ReceiptPrinter`. Define input/output DTOs, external references, idempotency, acknowledgement, timeout/error semantics, and test fixtures before integration implementation. Avoid letting a vendor's device schema become the main membership schema.

### Tally

Implement a Tally XML serializer, mapped masters/vouchers, export preview, import result parser, a durable outbox, duplicate prevention, retries, and reconciliation status. Start with file-based XML export plus the connector contract, then implement an authenticated local connector for automated delivery to the actual installation. The real Tally product/release determines final supported transport and features. Use a test company for validation.

The local connector can receive/pull signed, authorised jobs over HTTPS and call Tally on its trusted local network. Select its runtime after confirming the device/SDK environment; a Windows service may be suitable where required. Do not expose Tally's local HTTP port to the public internet. Finalise posting ownership so users do not enter the same transaction independently in both systems. Handle master mappings, debit/credit signs, departments/cost centres, voucher references, and corrections explicitly.

Tally being closed/offline creates queued integration work, not permission for credit and not failure of otherwise valid local application posting. Statuses include not configured, queued, delivered, acknowledged, failed, and reconciled. 'Export generated' is not 'Tally accepted'. Do not promise native cost-centre final statements for versions/features that do not provide them.

### Biometrics/access

Use manufacturer-supported terminals/controllers and their matching engine. Map approved device user identifiers to the club person and access profile. Implement enrolment association, permission validity updates, guest expiry/revocation, entry events, rejection reasons, and acknowledged synchronisation status. Recognition and physical access enforcement depend on qualified hardware capabilities.

Use authenticated service-to-service communication, least-privilege credentials, protected device networks, and minimal sensitive logging. Keep biometric templates in the manufacturer's managed environment where possible; no raw fingerprint/face images in club application fixtures or logs. Record necessary notice/consent and retention metadata according to the club's approved process; do not invent statutory periods.

Simulator access decisions demonstrate application logic only. They cannot prove remote real-time payment enforcement, anti-passback, biometric accuracy, or door behaviour. Track these as hardware qualification items until exercised on the selected equipment. Temporary network loss follows an explicitly configured controller policy; do not claim every terminal supports instant revocation.

### Digital receipts/cards/printing

Implement manual verified cash/terminal/UPI receipts first; add the selected payment provider through signed callbacks and server verification. Sandbox behaviour is development-only. Gateway failures and Tally/access outages have independent statuses.

Provide browser print layouts for A4 documents and 80 mm receipts. Automatic/silent or network printer control is a printer-adapter/local-bridge capability to qualify on the actual equipment. A browser print view does not prove unattended bar-order printing.

## 15. Interface and operating standards

Use compact, readable screens with clear member photos/status, department labels, current available balance, and obvious success/failure feedback. Provide search by membership number, name, phone, and card reference with appropriate permission masking. Counter tasks should be usable with keyboard and touch. Use a neutral light theme with restrained deep teal accents; this is an operating tool, not a marketing website.

Provide a manager dashboard based on real queries: today's department sales, external collections, pending contractor settlement, prepaid liability, room occupancy, expiring plans, stock alerts, and integration exceptions. Avoid decorative charts or hardcoded metrics. Every displayed action/menu route must work for the current delivered stage; hide undelivered modules.

Provide empty, loading, validation, permission-denied, and retry states. Avoid hiding important financial failures in temporary notifications. Prevent repeated tapping and also enforce server idempotency. Keep print copies consistent with transaction snapshots.

## 16. Delivery boundaries and operation

The core includes all confirmed staff modules. Optional later additions: member self-service, automated WhatsApp/SMS messaging, silent printing bridges, advanced coaching/events, or a dedicated mobile app. Excluded from initial scope: multi-tenant SaaS billing, public e-commerce, payroll, custom biometric AI, offline prepaid synchronisation, wagering, and unrestricted two-way edits of accounting transactions.

Local development uses an isolated MySQL database, synthetic data, mail log/test transport, simulator access, and sandbox or manual-test payment adapters. Maintain separate production credentials and an explicit production onboarding checklist for real tariffs/accounting, imported balances, payment setup, hardware qualification, and backup restoration.

Deploy with HTTPS, `APP_DEBUG=false`, supervised queues, scheduler, restricted database access, private file storage, error monitoring, and encrypted/off-server backups. Restore into a clean environment and reconcile cash/wallet/journal/stock balances as part of release verification. Backup frequency and acceptable recovery windows are configured with the actual operating setup; do not promise zero data loss from daily backup alone.

No module is complete merely because its screens render. Each stage must pass the relevant scenarios in `ACCEPTANCE_TESTS.md`, and integrations must keep separate code-complete, fixture-tested, sandbox-tested, device-tested, and live-qualified states.

## 17. Official implementation references

Checked 17 October 2026. Consult the installed package's version-specific documentation during implementation. These sources support framework/integration capabilities; the club-specific business design above is the proposed implementation specification.

- Laravel release notes: https://laravel.com/framework/docs/13.x/releases
- Laravel deployment: https://laravel.com/framework/docs/13.x/deployment
- Filament 5 installation: https://filamentphp.com/docs/5.x/introduction/installation
- Livewire 4: https://livewire.laravel.com/docs/4.x/installation
- Spatie permissions compatibility: https://spatie.be/docs/laravel-permission/v8/prerequisites
- MySQL 8.4: https://dev.mysql.com/doc/refman/8.4/en/mysql-releases.html
- Tally XML integration: https://help.tallysolutions.com/xml-integration/
- Tally cost-centre allocation: https://help.tallysolutions.com/cost-centre-or-profit-centre-tally/
- Tally cost-centre FAQ: https://help.tallysolutions.com/tally-prime/financial-controlling/cost-centres-in-tallyprime-faq/
- Antigravity project rules: https://antigravity.google/docs/rules/
