# SilverShop Ledger

An immutable financial **posting ledger** / audit trail for [SilverShop](https://github.com/silvershop/silvershop-core)
orders and payments.

Every money-affecting event on an order is recorded as an append-only `ShopPostingEntry` — placed,
captured, refunded, voided — fed from the order-status and payment signals SilverShop already exposes.
Entries are never edited or deleted, so the ledger is a trustworthy accounting record.

This addresses the long-standing [silvershop-core#4](https://github.com/silvershop/silvershop-core/issues/4)
("Create Audit Log / Posting Tables"). It is **additive and opt-in** and requires **no changes to core** —
it only hooks existing extension points.

## What it does

- **`ShopPostingEntry`** — an immutable, append-only DataObject. `Type` (Placed / Captured / Refunded /
  Voided / Adjusted / WriteOff), `Amount`, `Currency`, `Order`, `Payment`, `Author`, `Reference`, `Note`,
  `Data`. Cannot be edited or deleted once written.
- **`OrderLedgerExtension`** — posts a `Placed` entry when an order first reaches a placed status, and
  adds a read-only **Ledger** tab to the order in the CMS.
- **`PaymentLedgerExtension`** — posts `Captured` / `Refunded` / `Voided` entries as omnipay payments
  settle.
- **`LedgerBackfillTask`** (`sake dev/tasks/SilverShop-Ledger-LedgerBackfillTask`) — backfills `Placed`
  entries for orders placed before the module was installed.

## Installation

```bash
composer require silvershop/ledger
```

Then run `dev/build`, and optionally the backfill task for existing orders.

## Status

Prototype (Layer A of a larger design). A field-level audit layer (`Versioned` / diff table) and
Magento-style Invoice / Credit-memo documents are possible future layers. See the design notes in the
consuming project for the full plan.

## Licence

BSD-3-Clause.
