# Malabar Club — project rules

Build the staff-operated club management application described in `docs/PROJECT_SPEC.md`. Read `docs/IMPLEMENTATION_PLAN.md`, `docs/DECISIONS.md`, and `docs/PROGRESS.md` before implementation. The confirmed client requirements override earlier assumptions. The project is one club, not a SaaS platform.

## Working approach

- Implement functional code and connected workflows, not a collection of dashboard mockups. Ordinary implementation decisions are already authorised: choose a reasonable default, record it, and continue.
- Follow the delivery stages in order. A written plan alone is not completion. Resume from actual code and recorded progress instead of rebuilding previous work.
- Preserve existing files and user work. Before scaffolding, inspect this directory. Since documentation makes the directory non-empty, scaffold Laravel in a temporary directory and merge application files carefully if needed. Never overwrite this brief, rules, or existing code blindly.
- Use PHP 8.4, Laravel 13, MySQL 8.4/InnoDB, Filament 5, Livewire 4, Blade, Tailwind CSS 4.1+, and Alpine.js. Resolve compatible stable releases with Composer/npm and commit lockfiles. Do not use old-major snippets for current packages. Optional package conflicts should be solved without silently downgrading the core stack.
- Use one application and one authoritative transactional database. Keep business actions in shared application/domain services; controllers, Filament actions, jobs, and Livewire components call those services.
- Staff UI uses Filament; the counter POS, reception, and booking screens use task-specific Livewire views. Default UI is English, responsive, keyboard-friendly, and readable at a busy counter. Store Unicode correctly.

## Non-negotiable business rules

- No credit for members, guests, dependents, or affiliated members. No role, including administrator, can override this policy through ordinary billing. Service fulfilment requires confirmed payment or a sufficient confirmed prepaid debit/reservation.
- A card identifies an account. The server ledger owns the balance. Validate the cardholder, lock the account for spending, reject insufficient funds, and protect submissions/callbacks with idempotency keys. Do not permit offline prepaid debits.
- Use signed integer paise for posted money, exact decimal arithmetic for quantities/rates, explicit rounding, and immutable transaction snapshots. Never use binary floating point for money.
- Wallet funding, sales, refunds, contractor payables, and external collections are distinct transactions. Wallet redemption is not a second cash receipt. Unused prepaid funds must reconcile to their backing liability account.
- Payment providers are external to the database transaction. Handle duplicate callbacks and captured-but-not-posted exceptions explicitly. Commit bill, wallet allocation, journal, stock movement, and outbox events consistently; never issue goods merely because a browser reports success.
- Food is supplied by an external contractor. Paid food bill lines, including prepaid redemptions, feed next-day contractor settlements. Unused recharges do not. Track batch membership uniquely, payments, refunds, adjustments, and residual payables.
- Posted bills, stock movements, wallet entries, and journals are not edited or deleted. Correct them with linked, authorised reversals/adjustments. Closed financial periods and settled contractor batches are locked.
- Financial journals balance. Department P&L uses consumption costs, approved recognition rules, and expenses; collections minus payments is a cash-flow report.
- Guest entry for bar/card room requires registration, an eligible sponsoring member or affiliated member, and the applicable paid pass. Guest identity, sponsor, occupant, payer, and prepaid account are separate concepts.
- Prevent concurrent overlapping reservations with a shared database locking protocol. Gym/badminton plans and base club membership are separate entitlements.

## Integrations and verification

- Tally and access hardware use authenticated adapters and a retryable outbox. Tally unavailability must not block a valid paid sale. Do not expose a Tally or biometric device port directly to the public internet.
- Use manufacturer-supported biometric matching. Map device identity to club identity. Do not implement facial recognition models or store raw biometric samples in app tables/logs.
- Development simulators must have unmistakable status, use development fixtures, and fail closed in production. Access responses remain provisional until the controller/terminal acknowledges them. Never label simulated behaviour as live-tested hardware integration.
- Enforce authorisation on the server, private document access, receipt integrity, and separate live/test payment credentials. Do not put secrets, real member documents, or biometric templates in Git.
- Test on MySQL for locking/concurrency and production-relevant financial behaviour. Test actual services, HTTP authorisation, meaningful database constraints, and end-to-end scenarios. SQLite-only tests do not prove MySQL concurrency.
- Update `docs/PROGRESS.md` with what was implemented, exact commands/tests run, results, known defects, and integration status. Update `docs/DECISIONS.md` for new defaults. Never report a test or integration as passed if it was not executed successfully.
- Do not deploy, contact members, transfer money, activate door controls, or submit live accounting vouchers as part of development. Use a test Tally company and non-production integrations until explicitly configured for a real rollout.
