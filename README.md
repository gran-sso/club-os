# Malabar Club — Antigravity development pack

Prepared for Skysys Infosolutions OPC Pvt. Ltd. • 17 October 2026

This pack is the starting specification and instruction set for developing Malabar Club's application in Antigravity. It contains development documentation and prompts. Application code has not yet been generated or tested by this pack.

## Start building

1. Extract the ZIP. Open the enclosed `malabar-club` folder as your Antigravity project/workspace.
2. Keep the supplied `AGENTS.md`, `docs`, and `prompts` in that folder.
3. Copy all text from `prompts/START_BUILD_PROMPT.txt` into Antigravity's coding agent.
4. Let the agent scaffold the project, implement the first working financial workflow, and continue through the delivery stages. The prompt authorises ordinary implementation choices and records assumptions instead of asking repeated business questions.
5. In a later session, use `prompts/CONTINUE_BUILD_PROMPT.txt`. The agent should resume from `docs/PROGRESS.md` and the actual code, without restarting the project.
6. Use `prompts/REVIEW_IMPLEMENTATION_PROMPT.txt` when reviewing a substantial milestone.

The folder already contains documentation, so the initial Laravel scaffold must preserve it. The start prompt explains how to handle a non-empty project directory safely.

## Pack contents

| File | Purpose |
| --- | --- |
| `AGENTS.md` | Short, persistent project rules |
| `docs/PROJECT_SPEC.md` | Confirmed requirements, working defaults, modules, data model, financial rules, and integration boundaries |
| `docs/IMPLEMENTATION_PLAN.md` | Build stages, dependencies, and completion criteria |
| `docs/ACCEPTANCE_TESTS.md` | Business, concurrency, accounting, and integration scenarios |
| `docs/DECISIONS.md` | Defaults already selected; record subsequent implementation decisions here |
| `docs/PROGRESS.md` | Honest implementation and test status, initially not started |
| `prompts/START_BUILD_PROMPT.txt` | First implementation request |
| `prompts/CONTINUE_BUILD_PROMPT.txt` | Resume the next unfinished stage |
| `prompts/REVIEW_IMPLEMENTATION_PROMPT.txt` | Review code and workflows against the agreed requirements |

## First visible result

The first working result should let a staff member sign in, find a member, register or replace a card, receive a confirmed recharge, and complete a paid sale with a receipt. The member balance, cashier collection, sale, and balanced journal entries must agree. It should also prove that two counters cannot spend the same prepaid balance.

The main deliverable is the staff-operated club system. A member self-service portal is a later, optional extension and must not delay the confirmed operational requirements.

## Integrations and real-world setup

Tally, access-control terminals, card readers, and printing have explicit adapter interfaces. Missing equipment or credentials must not block unrelated development. Development simulators must be clearly identified, disabled in production, and never reported as verified live integrations.

Real tariffs, tax and accounting mappings, opening balances, installed Tally details, hardware credentials, and club records are configuration/onboarding inputs. They are not reasons to delay coding. Sample data is fictional and intended only for development.

Keep this pack and the generated application in a private Git repository once development starts. Save a checkpoint after each tested stage. Do not put credentials or actual biometric data in Git.
