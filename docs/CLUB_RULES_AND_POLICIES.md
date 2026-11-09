# Malabar Club — Operational Bylaws, Financial Rules & System Integrity Manual

**Malabar Club, Vadakkencherry, Palakkad, Kerala**  
*Comprehensive Policy Manual for Committee Members, Management, Front-Desk Staff, Accountants & Auditors*  
*Version 1.0 • Formally Approved & Codified*

---

## 1. Executive Summary & Operating Philosophy

Malabar Club operates as a private, staff-managed hospitality and recreational institution. To guarantee long-term financial stability, transparent governance, and seamless member service, the club operates under a **Real-Time Prepaid and Immediate Settlement Model**.

### The Traditional Club Problem
In conventional private clubs, members run monthly "credit tabs" for food, bar drinks, room stays, and sports. This traditional approach creates critical financial liabilities:
1. **Unpaid Arrears & Bad Debts**: Significant working capital is tied up in member dues, requiring uncomfortable collection follow-ups.
2. **Reconciliation Disputes**: Members dispute bill items on monthly statements weeks after consumption.
3. **Contractor Settlement Strain**: Third-party catering contractors and liquor suppliers cannot be paid on time if member payments remain uncollected.

### The Malabar Club Solution
Malabar Club eliminates credit risks entirely:
* **Prepaid Smartcards / Wallets**: Members top up their club accounts in advance using Cash, UPI, or Card.
* **Immediate Counter Billing**: Every meal, drink, court booking, and guest pass is verified against available ledger funds or settled on the spot.
* **Guaranteed Solvency**: Club bank accounts and cash drawers hold collected money *before* services are delivered, ensuring food contractors and suppliers are paid reliably.

---

## 2. Financial & Billing Bylaws

### Rule 1: Strict Zero-Credit Policy (No Overdrafts)
*(Internal Reference: Decision D02, Scenario F08)*

* **Core Mandate**: No member, dependent, visiting guest, or affiliated reciprocal club member may receive goods, liquor, food, sports access, or room stays on credit or with a negative account balance.
* **No Administrative Override**: No staff user, cashier, front-desk executive, manager, or club administrator possesses the authority or software bypass to issue a bill against an overdrawn wallet.
* **Prepaid Ledger Verification**: When paying via Prepaid Wallet, the server ledger checks that `Available Balance ≥ Bill Amount`. If the balance is insufficient, the transaction is rejected immediately.
* **Alternative Settlement**: If wallet balance is insufficient, the transaction can be fulfilled instantly using **Cash Drawer** or **Digital UPI / Card POS**.

---

### Rule 2: Centralized Authoritative Server Ledger
*(Internal Reference: Decisions D06 & D09, Scenario F01 & F02)*

* **Cards are Tokens, Not Cash**: A member's RFID contactless smartcard does not store money. It stores a secure, unique identifier that links to the club's central database ledger.
* **Atomic Balance Locks**: To prevent concurrent spending (e.g., using a physical card at the bar while a court is booked at reception), the system applies pessimistic database row locks during transaction processing.
* **Offline Spending Disabled**: If local counter connectivity to the central server is interrupted, prepaid wallet spending is automatically disabled until online connection is restored, preventing untraceable negative balances.
* **Paise Precision**: All monetary values are recorded as exact 64-bit signed integers in Indian Paise (₹1.00 = 100 paise), preventing floating-point rounding discrepancies.

---

### Rule 3: External Food Catering Pass-Through & Daily Settlement
*(Internal Reference: Decisions D03 & D15, Scenarios C01–C05)*

* **Contractor Segregation**: Dining kitchen operations are managed by an independent third-party catering contractor.
* **100% Net Food Revenue Pass-Through**: All food bill items—whether settled via Cash, UPI, or Member Prepaid Wallet—feed into the contractor's qualifying settlement ledger.
* **Prepaid Recharges are Not Food Sales**: General member wallet top-ups are liabilities held in trust by the club. They are **never** remitted to the food contractor until the member actually orders and consumes food items.
* **Next-Day Settlement Batches**: Every morning, the system compiles the preceding business day's food sales into an immutable Settlement Batch.
* **Management Sign-Off & Banking Payout**: Settlement batches require General Manager approval before being disbursed through Indian banking instruments (NEFT, RTGS, or Cheque) with recorded transaction reference numbers.
* **Post-Settlement Adjustments**: If a food item is refunded after its settlement batch has already been paid out to the contractor, the negative adjustment is carried forward and automatically deducted from the subsequent day's batch.

---

### Rule 4: Immutable Ledgers, Anti-Tamper Records & Adjustments
*(Internal Reference: Decisions D08 & D41, Scenario F14)*

* **No Deletion of Financial History**: Once an official tax invoice, wallet recharge, stock receipt, or journal voucher is posted, it is permanently locked in the database.
* **Destructive Edits Prohibited**: Posted records cannot be updated, edited, or deleted under any circumstance.
* **Linked Reversals**: To correct an erroneous transaction, an authorized manager must issue a linked credit note, refund voucher, or reversing journal. Both the original entry and the corrective entry remain permanently visible in the audit trail.
* **External Accounting Integrity**: Every financial transaction generates balanced double-entry journals (Debits === Credits) synchronized to the club's Tally Prime accounting system.

---

### Rule 5: Financial Period Locks & Backdating Controls
*(Internal Reference: Decision D41, Scenario R04)*

* **Fiscal Period Auditing**: The club's accounting periods (monthly and annual) are formally audited and locked.
* **Strict Backdating Block**: Once an accounting period is marked as "Closed", the system strictly forbids posting backdated bills, refunds, purchase receipts, or expense vouchers into that date range.
* **Audit Trail Protection**: Any retrospective financial adjustment must be posted in the currently open fiscal period with explanatory audit notes.

---

### Rule 6: Cashier Shift Reconciliation & Indian Drawer Float Management
*(Internal Reference: Decisions D27, D31, D32, D33, Scenario F12)*

* **Opening Cash Float**: Cashiers must record their verified drawer float before beginning a counter shift.
* **Distraction-Free Shift Gateway**: If a cashier has no active shift, the billing screen is locked, presenting only the Open Shift dialogue.
* **Expected Drawer Calculation**: The system continuously computes expected cash:  
  `Expected Cash = Opening Float + Cash Recharges + Cash Bills - Cash Transfers`  
  *(Non-cash tenders like Prepaid Wallet debits and Digital UPI payments are tracked separately and excluded from drawer cash).*
* **Shift Handover & Blind Balancing**: Upon closing a shift, the cashier enters the physical cash count. Any discrepancy (overage or shortage) is recorded in the permanent audit log.

---

### Rule 7: Club Treasury & Cash-to-Bank Contra Remittances
*(Internal Reference: Decision D42)*

* **Segregated Chart of Accounts**: Cash collected at counter tills resides in account `1010 (Cash in Hand)`. Club bank accounts reside in `1020 (Bank Operating Account)`.
* **Contra Vouchers**: Transferring physical cash from the clubhouse safe to the club's bank account (SBI, Federal Bank, etc.) requires an approved Contra Voucher, incrementing the verified bank ledger balance.

---

## 3. Membership, Access Control & Family Bylaws

### Rule 8: Primary Member Standing Prerequisite
*(Internal Reference: Decision D44, Scenario A03)*

* **Active Standing Required**: A member must possess an active, non-expired, non-suspended club membership to access facilities, book courts, reserve rooms, or sponsor guests.
* **Dual Expiration Protection**: If a member's primary club membership expires, sports subscriptions (gym or badminton) are automatically paused at the gate, even if the sports plan duration has remaining days.
* **Annual Dues Tracking**: Member profiles display real-time statutory dues countdowns with 1-click renewal options (via Prepaid Wallet, Cash, Digital UPI, or Bank Remittance).

---

### Rule 9: Family Dependents & Spending Safeguards
*(Internal Reference: Decisions D38 & D43, Scenario A07)*

* **Default Restriction**: Dependents (spouses, sons, daughters, parents) registered under a primary member's roster have **no default access** to debit the primary member's prepaid wallet.
* **Written Authorization Required**: Counter debit permissions are disabled by default. Management will only toggle `Authorize Counter Wallet Debits` upon receiving a signed written authorization request from the primary member.
* **Independent Access**: Dependents can identify themselves for facility access, but cannot deplete family funds without documented consent.

---

### Rule 10: Guest Entry Passes & Sponsorship Responsibility
*(Internal Reference: Decisions D04 & D38, Scenarios A04–A06, A10)*

* **Mandatory Sponsorship**: No guest may enter the bar, card room, or sports facilities without an active primary member or affiliated reciprocal club member as an identified sponsor.
* **Paid Entry Passes**: Guests must pay applicable statutory entry fees before entering regulated zones.
* **No Unauthorised Free Entry**: Cashiers and managers cannot issue zero-fee complimentary guest passes without committee authorization.
* **Unused Pass Refund**: If a guest pass is cancelled before entry, the fee is refunded and the pass is revoked atomically.

---

### Rule 11: RFID Smartcard Replacement & Balance Continuity
*(Internal Reference: Decision D38, Scenario A08)*

* **Lost / Damaged Cards**: When a member reports a lost, stolen, or damaged card, management issues a replacement card.
* **Instant Deactivation**: The previous card UID is permanently revoked, preventing unauthorized entry or spending.
* **Balance Preservation**: 100% of the member's wallet balance, active sports plans, room bookings, and historical receipts remain attached to the member's permanent ledger account.

---

### Rule 12: Statutory KYC Document Vault & Confidentiality
*(Internal Reference: Decisions D38 & D43, Scenario A09)*

* **Mandatory KYC**: Indian statutory norms require valid identification (Aadhaar, Passport, Driving Licence, Voter ID, or PAN) on file for all members.
* **Private Encrypted Storage**: Identity documents are stored in a private directory outside the public web root.
* **Role-Based Access Control**: Front-line staff cannot view private files. Document viewing routes require authenticated administrative permissions with strict non-cached HTTP headers.

---

## 4. Facilities, Sports & Accommodations Bylaws

### Rule 13: Accommodation Advance Payment & Interval Boundaries
*(Internal Reference: Decisions D11, D21, D23)*

* **100% Advance Payment**: Guest rooms must be fully paid (via Cash, Digital, or Prepaid) before key handover. Discretionary post-stay billing is prohibited.
* **Standard Clubhouse Timings**: Check-in is at **14:00**; Check-out is at **11:00**.
* **Half-Open Interval Scheduling**: Room reservations are scheduled on half-open boundaries `[check_in, check_out)`. This allows a departing guest to check out at 11:00 and an arriving guest to check in on the same calendar date at 14:00 without booking collisions.

---

### Rule 14: Badminton Court & Gym Subscription Boundaries
*(Internal Reference: Decisions D05, D12, D40)*

* **60-Minute Court Slots**: Badminton court play is scheduled in strict 60-minute serialized intervals to prevent scheduling disputes.
* **Automatic Plan Coverage**: If a member holds an active Badminton Subscription, the system automatically detects entitlement and waives the hourly court booking fee.
* **Exclusive Expiration Boundaries**: Monthly and annual sports plans expire cleanly at 23:59:59 on the final covered day.

---

## 5. Technical Architecture & Compliance Cross-Reference Index

For engineers, external auditors, and technical personnel, this matrix maps the friendly club policies to internal specification codes and codebase service modules:

| Friendly Club Policy Name | Decision ID | Test Scenario | Primary Codebase Service |
| :--- | :--- | :--- | :--- |
| **Strict Zero-Credit Policy** | `D02` | `F08`, `F03` | `WalletService::debit`, `BillingService::settleBill` |
| **Authoritative Server Ledger** | `D06`, `D09` | `F01`, `F02`, `F04` | `WalletService::recharge`, `CardService::issueCard` |
| **Food Contractor Pass-Through** | `D03`, `D15` | `C01`–`C05` | `ContractorSettlementService::createDailyBatch` |
| **Contractor Bank Payouts** | `D35`, `D42` | `C04` | `ContractorSettlementService::confirmSettlementPayment` |
| **Immutable Accounting Journals** | `D08`, `D41` | `F14` | `AccountingService::postJournal`, `Bill::boot` triggers |
| **Fiscal Period Closing Locks** | `D41` | `R04` | `PeriodClosingService::closePeriod` |
| **Cash Float & Shift Auditing** | `D27`, `D31`–`D33`| `F12` | `ShiftService::openShift`, `ShiftService::closeShift` |
| **Club Bank Accounts & Contras** | `D42` | `F01`, `R04` | `BankingService::depositCash`, `BankAccountResource` |
| **Member Active Standing Rule** | `D44` | `A03` | `SubscriptionService::createSubscription`, `ViewMember` |
| **Dependent Spending Restriction** | `D38`, `D43` | `A07` | `MemberService::registerDependent`, `ViewMember::toggleDependentWalletDebit` |
| **Guest Sponsoring & Paid Entry** | `D04`, `D38` | `A04`–`A06`, `A10` | `GuestService::registerGuest`, `GuestService::issuePass` |
| **RFID Card Balance Continuity** | `D38` | `A08` | `CardService::replaceCard`, `MembersTable::actions` |
| **Statutory KYC Document Vault** | `D38`, `D43` | `A09` | `DocumentController::streamDocument` |
| **Room Booking Half-Open Interval**| `D11`, `D23` | `B01`–`B03` | `AccommodationService::createReservation`, `RoomRackPage` |
| **Badminton Slot Serialization** | `D05`, `D40` | `B06`, `B07` | `BookingService::bookCourt`, `CourtSchedulePage` |
| **Tally Prime Outbox Resilience** | `D26` | `X01`–`X03` | `TallySyncService::dispatchOutbox`, `TallyPrimeHttpConnector` |

---
*Document maintained by Skysys Infosolutions for Malabar Club, Vadakkencherry.*

