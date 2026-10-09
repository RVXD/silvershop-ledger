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

## Linking source documents

Each entry has an optional polymorphic **`Document`** `has_one`, so an accounting document (an invoice,
a credit memo) can be referenced from the ledger without the ledger depending on the module that
produces it. The relation is **set-once**: attaching a document is a reference link, not a money change,
so it is the one write allowed on an otherwise-immutable entry — any later attempt to re-point or change
it is rejected.

```php
$entry->attachDocument($invoice); // links once; a second call is a no-op
$entry->getDocumentLabel();        // "INV-2026-00001" (the document Number, or its title) or ''
```

The entry's CMS summary shows a **Document** column. [silvershop/invoicing](https://github.com/RVXD/silvershop-invoicing)
wires this up automatically when both modules are installed — nothing to configure.

## Installation

```bash
composer require silvershop/ledger
```

Then run `dev/build`, and optionally the backfill task for existing orders.

## Status

Prototype (Layer A of a larger design). Magento-style Invoice / Credit-memo documents are provided by
[silvershop/invoicing](https://github.com/RVXD/silvershop-invoicing), which references its documents from
these entries via the `Document` link above. A field-level audit layer (`Versioned` / diff table) is a
possible future layer. See the design notes in the consuming project for the full plan.

## Licence

BSD-3-Clause.
