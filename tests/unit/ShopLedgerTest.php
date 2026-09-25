<?php

namespace SilverShop\Ledger\Tests;

use SilverShop\Ledger\ShopPostingEntry;
use SilverShop\Model\Order;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\ORM\ValidationException;

/**
 * Covers the posting-ledger hooks (placement + payment capture), idempotency and immutability.
 */
class ShopLedgerTest extends SapphireTest
{
    protected static $fixture_file = 'ShopLedgerTest.yml';

    protected static bool $use_draft_site = true;

    public function testPlacedEntryPostedOnPlacement(): void
    {
        $order = $this->objFromFixture(Order::class, 'cart');

        $this->assertFalse(
            ShopPostingEntry::get()->filter(['OrderID' => $order->ID, 'Type' => 'Placed'])->exists(),
            'no Placed entry while the order is still a cart'
        );

        $order->Status = 'Unpaid';
        $order->write();

        $entry = ShopPostingEntry::get()->filter(['OrderID' => $order->ID, 'Type' => 'Placed'])->first();
        $this->assertNotNull($entry, 'a Placed entry is posted when the order is placed');
        $this->assertSame('Placed', $entry->Type);
        $this->assertEquals((float) $order->Total(), (float) $entry->Amount);
    }

    public function testPlacementIsIdempotent(): void
    {
        $order = $this->objFromFixture(Order::class, 'cart');

        $order->Status = 'Unpaid';
        $order->write();
        $order->write(false, false, true); // force a second write

        $this->assertCount(
            1,
            ShopPostingEntry::get()->filter(['OrderID' => $order->ID, 'Type' => 'Placed']),
            'placement is recorded only once'
        );
    }

    public function testCapturedEntryPostedOnPayment(): void
    {
        $order = $this->objFromFixture(Order::class, 'placed');

        $payment = Payment::create();
        $payment->OrderID = $order->ID;
        $payment->Gateway = 'Manual';
        $payment->MoneyAmount = 100;
        $payment->MoneyCurrency = 'NZD';
        $payment->Status = 'Captured';
        $payment->write();

        $entry = ShopPostingEntry::get()->filter(['PaymentID' => $payment->ID, 'Type' => 'Captured'])->first();
        $this->assertNotNull($entry, 'a Captured entry is posted when a payment is captured');
        $this->assertEquals(100.0, (float) $entry->Amount);
        $this->assertSame($order->ID, $entry->OrderID);
    }

    public function testEntriesAreImmutable(): void
    {
        $entry = ShopPostingEntry::create();
        $entry->Type = 'Adjusted';
        $entry->Note = 'original';
        $entry->write();

        $this->assertFalse($entry->canEdit());
        $this->assertFalse($entry->canDelete());

        $entry->Note = 'changed';
        $this->expectException(ValidationException::class);
        $entry->write();
    }
}
