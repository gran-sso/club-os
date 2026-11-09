# Malabar Club — phased implementation plan

Follow the stages in order, keeping each completed workflow usable. A stage is complete when its code, migrations, authorisation, tests, and operator screens work together. Do not replace testing with screenshots or stop after generating a plan.

## Stage 0 — bootstrap and architectural contracts

Set up the Laravel project, Git hygiene, PHP/Composer/Node dependencies, MySQL, asset pipeline, session authentication, permissions, Filament panel, local environment example, test database, and CI/build commands. Preserve the supplied documents during scaffolding.

Create a basic application shell with the club name, INR, Asia/Kolkata, department/counter settings and an authenticated health/integration status page. Keep secrets outside source control. Pin actual resolved dependencies. Add shared value objects/enums for money, transaction states, department attribution and external references.

Document the schema by domain and define the adapter contracts and minimal fixtures for Tally, payment verification, access control, card reading and printing. Provide a tiny Tally voucher fixture/serializer contract early so integration assumptions can be tested without waiting for the full finance interface. Mark a missing connector/device as not configured, not successful.

Exit: a real local app starts; login/permissions and MySQL tests work; production rejects development simulators; setup/commands are written to the README and progress file. A connection to real hardware is not an exit requirement for this stage.

## Stage 1 — members, affiliates, guests, and tariffs

Implement membership categories, persons/members, validity, dependents, affiliated clubs/members, sponsor relationships, card registry/block/replacement, import preview, facility definitions and effective-dated tariffs. Seed five rooms, one court, three editable counters, and five synthetic staff accounts with combined roles as needed. Membership counts are data, not licence limits.

Seed synthetic members in a separate development seeder. Do not populate fictional balances through direct balance edits. Provide accessible member search, status/privilege display, guest registration and a CSV import template. Create draft guest-access requests, but activate paid passes only after Stage 2 payment services exist.

Exit: authorised staff can administer members, register both types of sponsor, replace a card while retaining identity/history, and preview a member import. Unauthorised routes are blocked on the server. No live door-control claims are made.

## Stage 2 — first working financial workflow

Build the operational chart of accounts, append-only journal, source uniqueness, counter shifts, payment attempts/receipts, wallet account/ledger, recharge, member statement, bill lines, paid checkout, print receipt and approved reversal/refund actions.

Complete this first vertical slice: sign in → open counter → select member/card → receive confirmed recharge → complete a generic service/guest-entry sale → issue receipt → see the correct prepaid balance and collection/ledger drill-down. Activate the paid guest pass through the same posted transaction/outbox.

Start with single-tender cash, verified digital, and prepaid workflows. After their tests pass, add split-tender allocation with a complete recovery model. An incomplete split must not fulfil the sale. Make duplicate submissions/callbacks safe and test captured-but-unposted external payments.

Implement no-credit checks as a shared invariant. All role combinations must obey it. Use real MySQL concurrent connections for spend tests. Post funding and sale separately to their appropriate accounts; show cash received and wallet tender separately.

Exit: first usable counter workflow; wallet and journal reconcile; no negative authorised spend; retries do not duplicate money or bills; unpaid drafts cannot activate access; the counter can close with a counted reconciliation.

## Stage 3 — bar, card room, purchases, and inventory

Build the task-specific bar POS, guest/member eligibility checks, card-room charges, suppliers/receiving, inventory items and sellable serving mappings, store transfers, opening stock/cost, weighted-average costing, paid issues, physical counts and approved wastage/adjustments.

Integrate stock issue and COGS with the Stage 2 transaction services. Provide bar purchase/sales/consumption/variance reports and historical-cost audit. A refund does not imply a physical return; handle consumed/poured stock and returned unopened items correctly.

Exit: a bottle purchase and serving sale reconcile in base units and value; concurrent counters cannot sell the same last stock; refund/wastage paths are traceable; card-room paid entry works without adding gaming payouts.

## Stage 4 — food and next-day contractor settlement

Add food menu/items, contractor agreement and accounting mapping, paid food allocations, daily cutoff logic, previous-day batch preview, approval/payment confirmation, contractor ledger, outstanding payable and adjustment handling.

Exercise a mixed-department bill so food paid from prepaid funds enters the settlement correctly. Keep a general top-up out of the settlement. Protect each eligible allocation from duplicate batching. Include failed/partial contractor payments and a refund after an already-paid settlement.

Exit: every qualifying paid food line is traceable exactly once to settlement or outstanding payable; tomorrow's transfer list matches today's eligible food transactions; settled batches cannot be silently rewritten.

## Stage 5 — rooms, gym, and badminton

Implement room calendar, reservation/payment allocation, maintenance blocks, check-in/readiness, checkout, extensions, guest/sponsor/payer distinctions and housekeeping. Enforce payment coverage before accommodation and extensions.

Build gym/badminton monthly/annual plans, renewals, daily passes, attendance and entitlement checks; implement the court calendar, participants, plan-covered bookings, chargeable bookings, cancellation/no-show rules, and capacity/overlap protection. Use shared resource locking, with multi-resource lock ordering documented.

Implement recognition schedules for period-based services according to configurable accounting policy. Handle month ends/leap years and refunds without duplicate entitlements or revenue.

Exit: five-room and one-court workflows are functional; simultaneous overlapping requests cannot both confirm; valid plans and daily passes produce the right charges; expired/cancelled entitlements are denied consistently.

## Stage 6 — expenses, reporting, and Tally

Build expense and supplier-payment workflows, approved accrual/adjustment entries, shared-cost allocation, period closing, financial/reconciliation reports, drill-down and exports. Reconcile sales, recognised income, stock consumption costs, cash, digital clearing, prepaid liability and contractor balances.

Extend the early Tally contract into complete mapped master/voucher generation, export/import acknowledgement, durable outbox/retry, error dashboard and reconciliation. Implement the local connector transport using the actual available environment when known. Do not duplicate the same transaction by independent entry in both systems.

Tally sandbox qualification: use a test company and compare vouchers, totals, departments/cost centres and reversal behaviour. If the real installation is unavailable, finish local serialization/result-parser/connector contract tests and record device-side verification as pending. Other modules continue.

Exit: all requested report periods/filter combinations work; journals and subledgers reconcile; management P&L uses approved accounting inputs; retries are safe; exports and accepted imports are distinguished honestly.

## Stage 7 — access hardware, printing, and release preparation

Connect the selected manufacturer's supported access middleware/SDK, enrolment mapping, permission updates, pass expiry/revocation, entry logs and acknowledgement monitoring. Integrate the selected reader and any required local printing bridge. Retain the core identity/account model independently of the hardware.

Test the actual terminal/controller for active/expired members, paid/unpaid guests, affiliated sponsors, revocation delay, offline policy, and recovery. Confirm the reader's cardholder-verification approach and printer outputs on physical equipment. A simulator cannot satisfy these qualification steps.

Perform full multi-counter UAT with synthetic scenarios, load/latency measurements against the target deployment, migration dry run, private-document/role checks, dependency audit, backup restoration, opening-balance reconciliation, queue/scheduler restart recovery, and staff walkthroughs. Provide an admin/operator guide and deployment/restore runbook.

Exit: mandatory business tests pass; real integration limitations are documented; operational credentials and approvals are configured; real club data can be imported through an audited process. Record deployment-readiness separately from actual publication/activation.

## Optional Stage 8 — member self-service

Only after the mandatory staff system is complete, add member login, own statements, receipts, balances, renewals, booking requests and verified online payment/recharge. Reuse the same services and permissions. Do not make separate financial balances or an independent booking database.

Automated messaging and a native app are separate additions; neither is required to prove the club's current staff workflows.

## Evidence required at each checkpoint

Record implemented services/screens, migrations, tests and commands, actual results, how to run the current application, defects/limitations, and the next unfinished step. Keep code-complete, fixture-tested, sandbox-tested, device-tested and production-qualified statuses distinct. Report any genuine environment blocker with the exact failing command; do not ask the club repeated business questions already resolved by the defaults.

## First-session priority

Aim to complete Stages 0–2 first, producing the real member/card/recharge/paid-sale/receipt workflow. Continue into the next stage when feasible; if the coding session ends, save an exact progress checkpoint. The continuation prompt resumes from that checkpoint, not from an empty project.
