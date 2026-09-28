<?php

namespace SilverShop\Ledger;

use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordViewer;
use SilverStripe\Security\Security;

/**
 * Applied to {@link \SilverShop\Model\Order}.
 *
 * Posts a "Placed" ledger entry the first time an order reaches a placed status, and shows a
 * read-only Ledger grid on the order in the CMS. Idempotent: the entry is written at most once.
 */
class OrderLedgerExtension extends Extension
{
    public function onAfterWrite(): void
    {
        $order = $this->getOwner();

        if (!$order->ID
            || !in_array($order->Status, (array) $order->config()->get('placed_status'), true)
        ) {
            return;
        }

        // Only the first placement is recorded.
        if (ShopPostingEntry::get()->filter(['OrderID' => $order->ID, 'Type' => 'Placed'])->exists()) {
            return;
        }

        $entry = ShopPostingEntry::create();
        $entry->Type = 'Placed';
        $entry->Amount = $order->Total();
        $entry->Currency = $order->Currency();
        $entry->OrderID = $order->ID;
        $entry->Reference = $order->Reference;
        $entry->Note = _t(self::class . '.NOTE_PLACED', 'Order placed');

        if ($member = Security::getCurrentUser()) {
            $entry->AuthorID = $member->ID;
        }

        $entry->write();
    }

    public function updateCMSFields(FieldList $fields): void
    {
        $order = $this->getOwner();
        if (!$order->isInDB()) {
            return;
        }

        $fields->addFieldToTab('Root.Ledger', GridField::create(
            'LedgerEntries',
            _t(self::class . '.LEDGER', 'Ledger'),
            ShopPostingEntry::get()->filter('OrderID', $order->ID),
            GridFieldConfig_RecordViewer::create()
        ));
        $fields->findOrMakeTab('Root.Ledger')->setTitle(_t(self::class . '.TAB_LEDGER', 'Ledger'));
    }
}
