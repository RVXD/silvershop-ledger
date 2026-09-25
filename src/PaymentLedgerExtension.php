<?php

namespace SilverShop\Ledger;

use SilverStripe\Core\Extension;
use SilverStripe\Security\Security;

/**
 * Applied to {@link \SilverStripe\Omnipay\Model\Payment}.
 *
 * Posts a ledger entry when a payment settles into a money-affecting state (captured / refunded /
 * voided). Idempotent per (Payment, Type), so re-saves of the same payment do not duplicate rows.
 */
class PaymentLedgerExtension extends Extension
{
    /**
     * Omnipay Payment.Status → ledger entry Type for the states that move money.
     */
    private const STATUS_TYPES = [
        'Captured' => 'Captured',
        'Refunded' => 'Refunded',
        'Void' => 'Voided',
    ];

    public function onAfterWrite(): void
    {
        $payment = $this->getOwner();

        $type = self::STATUS_TYPES[$payment->Status] ?? null;
        if (!$type || !$payment->ID || !$payment->OrderID) {
            return;
        }

        if (ShopPostingEntry::get()->filter(['PaymentID' => $payment->ID, 'Type' => $type])->exists()) {
            return;
        }

        $entry = ShopPostingEntry::create();
        $entry->Type = $type;
        $entry->Amount = $payment->getAmount();
        $entry->Currency = $payment->getCurrency();
        $entry->OrderID = $payment->OrderID;
        $entry->PaymentID = $payment->ID;
        $entry->Reference = $payment->Gateway;
        $entry->Note = sprintf('Payment %s', strtolower($type));

        if ($member = Security::getCurrentUser()) {
            $entry->AuthorID = $member->ID;
        }

        $entry->write();
    }
}
