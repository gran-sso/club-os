# Malabar Club — Codified Operational Bylaws & System Rules Manual

**Malabar Club, Vadakkencherry, Palakkad District, Kerala — 678683**  
*Official Policy Manual for the Managing Committee, Executive Officers, Management & Internal Auditors*  
*Prepared by Skysys Infosolutions • Version 2.0 • Formally Adopted for Club OS Deployment*

---

## Executive Overview: The Operating Philosophy

Traditional private social and sports clubs across India face three persistent administrative and financial liabilities:
1. **Unpaid Arrears & Bad Debts**: Allowing members, dependents, and guests to run monthly "credit tabs" for dining, bar, rooms, and sports ties up millions in working capital and leads to painful recovery follow-ups.
2. **Billing Disputes**: Members frequently dispute individual food, liquor, or guest charges on monthly statements received weeks after consumption.
3. **Vendor & Contractor Strain**: When member collections lag, the club struggles to pay third-party food caterers, beverage distributors, and staff on time.

### The Malabar Club Solution: Real-Time Prepaid & Guaranteed Solvency
Malabar Club eliminates these risks entirely through a **Prepaid and Immediate Settlement Model**:
* **Prepaid Member Wallets**: Members fund their club accounts in advance via Cash, UPI QR, or Bank Transfer.
* **Instant Verification**: Every meal, drink, court booking, room stay, and guest pass is debited against confirmed ledger funds or paid on the spot.
* **Guaranteed Liquidity**: Collected funds sit safely in club bank accounts and cashier tills *before* goods or services are dispensed, ensuring total institutional solvency.

```mermaid
flowchart LR
    A["Member / Guest"] -->|"Funds Account (Cash / UPI / Bank)"| B["Club Vault & Bank (1010/1020)"]
    B -->|"Prepaid Wallet Balance (2010)"| C["Authoritative Server Ledger"]
    C -->|"Instant POS / Deck Verification"| D["Fulfilment: Bar, Dining, Rooms, Sports"]
    D -->|"Daily Next-Day Settlement"| E["Food Contractor & Suppliers (100% On-Time)"]
```

---

## Section I: Financial & Revenue Integrity Bylaws

### Rule 1: Strict Zero-Credit Policy (No Overdrafts or Credit Tabs)
* **The Mandate**: No member, dependent, visiting guest, reciprocal club member, or staff member may receive goods, liquor, food, room accommodation, or sports access on credit or with a negative account balance.
* **Zero Administrative Override**: Neither the Cashier, Front Desk Executive, General Manager, nor Club Administrator possesses the authority or software bypass to approve an unbacked transaction.
* **Tender Fallback**: If a member’s prepaid balance is insufficient, the transaction must be settled immediately using physical **Cash** or **Digital UPI**.

### Rule 2: Centralized Authoritative Server Ledger
* **Smartcards are Tokens, Not Cash**: A member’s contactless RFID smartcard holds only an encrypted identifier linked to the club’s central server ledger. No monetary balance is stored on the card chip.
* **Pessimistic Concurrency Locks**: When a transaction is submitted, the server applies immediate database row-level locking. If two simultaneous spends are attempted (e.g., at the bar and the front desk), they are serialized atomically. Overdrafts are mathematically impossible.
* **Offline Spending Disabled**: Prepaid debits require live communication with the central ledger. If clubhouse internet drops, offline prepaid spending is blocked to prevent untraceable debit accumulation.
* **Paise Precision**: All accounting is calculated in signed 64-bit integer paise (₹1.00 = 100 paise). Binary floating-point arithmetic is prohibited to prevent fractional rounding drift.

### Rule 3: External Food Catering Pass-Through & Daily Settlement Batches
* **Contractor Operational Segregation**: Kitchen and food preparation are managed by an external catering contractor.
* **100% Net Food Revenue Pass-Through**: All food bill lines—whether settled via Cash, UPI, or Member Wallet—feed the contractor's qualifying settlement ledger.
* **Prepaid Recharges Are Not Food Revenue**: General wallet top-ups are held in trust as a club liability (`Account 2010`). They are **never** remitted to the food contractor until food items are ordered and consumed.
* **Next-Day Settlement Batches**: Every morning, yesterday’s food sales are compiled into an immutable Settlement Batch requiring Manager sign-off before electronic banking payout (NEFT/RTGS/Cheque).
* **Post-Settlement Adjustments**: If a food item is refunded after its settlement batch was already disbursed, a negative adjustment is carried forward and automatically deducted from the subsequent day’s batch.

### Rule 4: Immutable Accounting Ledgers & Linked Reversals (No Deletions)
* **Permanent Record Retention**: Once a tax invoice, receipt, wallet top-up, or stock movement is posted, it is permanently locked in the database.
* **Destructive Edits Prohibited**: Posted records cannot be updated, altered, or deleted under any circumstance.
* **Linked Reversals**: Mistakes are corrected solely by authorized managers issuing a linked credit note, refund voucher, or reversing journal. Both the original entry and the corrective entry remain permanently visible in the audit trail.
* **Balanced General Ledger**: Every transaction automatically generates balanced double-entry journals ($\text{Debits} = \text{Credits}$) mapped to the club’s Chart of Accounts.

### Rule 5: Fiscal Period Closing Locks & Backdating Controls
* **Audit Period Closure**: Monthly and annual accounting periods are formally reviewed by the Treasurer/Auditor and marked as "Closed".
* **Backdating Prohibited**: The system strictly forbids posting backdated invoices, receipts, expenses, or inventory adjustments into a closed fiscal period.
* **Audit Transparency**: Any retrospective financial adjustment must be posted in the currently active fiscal period with explicit audit justification notes.

### Rule 6: Cashier Shift Accountability & Drawer Float Management
* **Cashier Shift Gateway**: Cashiers and front-desk operators cannot access billing screens without opening an active shift on an assigned counter with a verified opening cash float.
* **Live Drawer Tracking**: The system continuously tracks expected drawer cash:
  $$\text{Expected Cash} = \text{Opening Float} + \text{Cash Bills} + \text{Cash Recharges} - \text{Cash Drops}$$
* **Exclusion of Non-Cash Tenders**: Member wallet debits and UPI digital payments are tracked independently and never mixed into the physical cash drawer calculation.
* **Blind Balancing at Shift Close**: Upon closing a shift, the cashier enters their physical cash count. Any variance (overage or shortage) is permanently logged for manager audit.

### Rule 7: Club Treasury Management & Cash-to-Bank Contra Vouchers
* **Separation of Cash & Bank**: Cash collected at counter tills sits in `Account 1010 (Cash in Hand)`. Club bank accounts sit in `Account 1020 (Bank Operating Accounts)`.
* **Contra Vouchers**: Remitting physical clubhouse cash to the club’s bank account (SBI, Federal Bank, etc.) requires an approved Contra Voucher, ensuring the physical drawer decrements and the bank ledger increments simultaneously.

---

## Section II: Membership, Access Control & Family Bylaws

### Rule 8: Primary Member Standing Prerequisite (Dual Expiry Protection)
* **Good Standing Required**: A member must hold an active, non-suspended, non-expired membership to access the clubhouse, reserve rooms, book courts, or sponsor visiting guests.
* **Dual Expiration Protection**: If a member’s base club membership expires, secondary sports subscriptions (gym or badminton) are automatically paused at turnstiles/gates, even if the sports plan duration has remaining validity.

### Rule 9: Two-Tier Statutory Membership Fee Structure
* **Entrance Fee vs. Annual Dues**: Membership categories govern two distinct financial components:
  1. **One-Time Admission / Entrance Fee**: Capital reserve receipt, non-recurring, charged once in a member’s lifetime upon joining.
  2. **Annual Subscription Dues**: Operational revenue charge recurring every 12 months for club maintenance.
* **Lifetime Admission Protection**: Subsequent renewals charge *only* the recurring annual dues, guaranteeing that existing members are never billed entrance admission twice.

### Rule 10: Family Dependents & Spending Safeguards
* **Default Spending Block**: Dependents (spouses, children, parents) registered under a primary member's roster have **zero debit access** to the primary member's prepaid wallet by default.
* **Explicit Written Consent**: Counter wallet debit access is enabled *only* upon documented authorization from the primary member.
* **Independent Identity**: Dependents carry their own identification and access privileges, but cannot deplete family balances without explicit consent.

### Rule 11: Mandatory Sponsoring for Visiting Guests & Regulated Entry
* **Mandatory Sponsorship**: No guest may enter the bar, card room, dining hall, or sports courts without an active club member or reciprocal affiliated member as an identified sponsor.
* **Paid Access Passes**: Guests must settle statutory guest entry fees prior to entering regulated zones.
* **Zero Complimentary Entry**: Cashiers and managers cannot issue zero-fee complimentary guest passes without Executive Committee authorization.
* **Unused Pass Refund**: If an entry pass is cancelled before physical admission, the fee is refunded and the pass is revoked atomically.

### Rule 12: RFID Smartcard Tokens & Balance Continuity
* **Lost Card Protocol**: When a member reports a lost or damaged card, management issues a replacement card immediately.
* **Instant Deactivation**: The lost card UID is permanently revoked, preventing unauthorized entry or spending.
* **Complete Asset Continuity**: 100% of the member’s wallet balance, sports plans, room bookings, and invoice history remain intact on their central account.

### Rule 13: Statutory KYC Vault & Document Confidentiality
* **Mandatory Government ID**: In compliance with Indian hospitality and association laws, verified government identification (Aadhaar, Passport, Driving Licence, Voter ID, PAN) is maintained on file.
* **Private Encrypted Storage**: Identity documents are stored outside the public web root.
* **Restricted Access**: Counter cashiers cannot browse member documents. Private file streaming requires manager/admin credentials and enforces strict non-cached HTTP headers.

---

## Section III: Hospitality, Facilities & Operational Deck Rules

### Rule 14: Accommodation 100% Advance Funding
* **Pre-Stay Full Payment**: All room stays must be fully funded (via Cash, UPI, or Member Wallet) prior to key handover and check-in. Post-stay discretionary billing or credit checkout is strictly prohibited.
* **Standard Timings**: Check-in is at **14:00**; Check-out is at **11:00**.
* **Half-Open Interval Scheduling**: Room reservations are scheduled on half-open boundaries $[T_{\text{check-in}}, T_{\text{check-out}})$. This guarantees that a departing guest checking out at 11:00 and an arriving guest checking in at 14:00 on the same calendar date never collide.

### Rule 15: Front-Desk Accommodation Billing & Cash Reconciliation
* **Unified Lifecycle**: The Front Desk Operator / Receptionist manages the full accommodation lifecycle from the Accommodation Deck (`/admin/room-rack`): Room Reservation $\rightarrow$ Advance Collection $\rightarrow$ Check-In $\rightarrow$ Key Handover $\rightarrow$ Check-Out $\rightarrow$ Housekeeping (`Dirty`/`Clean`).
* **Direct Revenue Posting**: Whenever stay charges or advance payments are collected on the Accommodation Deck, the system records the money directly against the Reception Cashier Shift and credits General Ledger `Account 4030 (Room Accommodation Revenue)`.

### Rule 16: Badminton Court & Gym Subscription Boundaries
* **Serialized 60-Minute Slots**: Badminton court play is scheduled in strict 60-minute blocks to eliminate court hogging and timing disputes.
* **Automatic Plan Waiver**: If a member holds an active Badminton Subscription, the system automatically detects entitlement and waives the hourly court booking fee.
* **Precise Expiry Boundaries**: Monthly and annual sports plans expire cleanly at 23:59:59 on the final covered day.

### Rule 17: Wholesale Beverage Procurement & Moving-Average Costing
* **Verified Wholesale Suppliers**: Bulk purchases (e.g. KSBC Wholesale Depot, beverage distributors) require valid trade names and 15-character statutory GSTINs.
* **Weighted-Average Costing**: Inventory uses moving weighted-average valuation. Liquor dispensing is tracked down to the exact milliliter (ml).
* **No Fabricated Stock Returns**: If a drink or food item is refunded after consumption for service recovery, the financial refund is posted without fabricating a physical return to inventory.

---

## Section IV: Governance, Security & System Safeguards

### Rule 18: Segregation of Duties & Role-Based Access Control (RBAC)
* **Front-Line Cashier Isolation**: Cashiers operate exclusively in the Counter POS interface (`/pos`) and cannot access executive back-office ledgers, member KYC vaults, or contractor agreements.
* **Administrative Separation**: Administrative personnel cannot operate a cashier drawer without opening a formal cashier shift.
* **Auditor Role**: Internal auditors have read-only access across all general ledger vouchers, shift balancing logs, and contractor remittance audits.

### Rule 19: Offline Outbox & Tally Prime Integration Resilience
* **Durable Outbox Architecture**: Financial journals and vouchers destined for Tally Prime are staged in a retryable database outbox with unique idempotency keys.
* **Non-Blocking Operations**: If Tally Prime or the network bridge is temporarily unreachable, counter billing and room check-ins proceed without interruption. Queued vouchers synchronize automatically once connectivity resumes.

### Rule 20: Tamper-Proof Audit Trail & Action Logs
* **Comprehensive Event Logging**: Critical actions—card replacements, manager refund approvals, shift discrepancy overrides, gate unblocks, and committee notes—are permanently recorded with timestamp, operator ID, and client IP address.

---

## Executive Summary Matrix: Club Rules Quick Reference

| # | Policy Name | Key Mandate | Staff Authority | System Enforcement |
|:---:|:---|:---|:---|:---|
| **1** | **Strict Zero-Credit Policy** | No credit tabs, overdrafts, or negative balances for any person | No override permitted for any role | Blocked by server ledger validation |
| **2** | **Central Server Ledger** | RFID cards are identifiers; balance stays on central server | Cannot manually edit card balances | Pessimistic DB locking & integer paise |
| **3** | **Food Contractor Pass-Through** | 100% net food sales remitted next-day; top-ups excluded | Manager approval on daily batch | Batch-allocated GL remittances |
| **4** | **Immutable Financial Records** | Posted bills, receipts, and journals cannot be edited/deleted | Reversals via linked credit notes only | Read-only vouchers & delete blockers |
| **5** | **Fiscal Period Locks** | Closed accounting periods cannot receive backdated entries | Treasurer/Auditor period closing | DB rejection on closed date ranges |
| **6** | **Cashier Shift Balancing** | All cash must belong to an open shift; blind reconciliation | Cashier opens/closes with float count | POS gateway locked without shift |
| **7** | **Treasury Contra Remittances** | Cash-to-bank deposits require approved Contra Vouchers | Accountant & Manager verification | Dual-leg journal (1010 $\rightarrow$ 1020) |
| **8** | **Member Standing Prerequisite** | Active base membership required for all facilities and guests | Reception verifies standing | Gate & turnstile automatic lockout |
| **9** | **Two-Tier Membership Fees** | Lifetime admission fee charged once; annual dues recurring | Staff collects via registration/profile | Lifetime flag prevents duplicate entrance fee |
| **10** | **Dependent Spending Safeguards**| Dependents cannot debit member wallet without written consent | Manager toggles upon written letter | Default false on wallet spending |
| **11** | **Mandatory Guest Sponsorship** | All guests require an active member sponsor & paid entry pass | Staff registers sponsor & collects fee | Unpaid passes cannot open gates |
| **12** | **Smartcard Replacement** | Lost card UID revoked; 100% balance preserved on member | Staff issues replacement token | Instant old UID blacklist |
| **13** | **Statutory KYC Vault** | Government IDs kept in private vault outside public web | Admin/Manager role only | Private streaming with no-cache headers |
| **14** | **100% Advance Room Funding** | Accommodation fully paid before check-in; 14:00/11:00 slots | Front desk verifies settlement | Check-in blocked if unpaid |
| **15** | **Deck Room Billing** | Room booking collections post directly to Reception Shift & GL | Front desk collects via cash/UPI/wallet | Auto-generates Bill & Journal (4030) |
| **16** | **Badminton Court Slots** | 60-min serialized play; fees waived for active subscribers | Front desk books on schedule deck | Overlap prevention & plan detection |
| **17** | **Wholesale Stock & Costing** | Moving-average valuation; no fake returns on consumed goods | Receiving staff verifies GSTIN | Weighted ml consumption tracking |
| **18** | **Segregation of Duties** | Cashiers cannot edit back-office ledgers or view audit files | Role-gated Filament admin panel | HTTP 403 Forbidden on unauthorized URLs |
| **19** | **Resilient Tally Outbox** | Accounting bridge outage never stalls clubhouse sales | Automated background worker | Durable queue with idempotency keys |
| **20** | **Tamper-Proof Audit Trail** | Sensitive overrides & approvals logged permanently | Viewable by Auditors & President | Append-only event and shift logs |

---
*Official Policy Manual maintained by Skysys Infosolutions OPC Pvt. Ltd. for Malabar Club, Vadakkencherry, Kerala.*
