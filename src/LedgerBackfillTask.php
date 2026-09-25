<?php

namespace SilverShop\Ledger;

use SilverShop\Model\Order;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Backfills a "Placed" ledger entry for existing placed orders that don't have one yet — e.g. orders
 * placed before the ledger was installed. Idempotent: re-running adds nothing new.
 */
class LedgerBackfillTask extends BuildTask
{
    protected string $title = 'Ledger: backfill Placed entries for existing orders';

    protected static string $description =
        'Re-saves placed orders so missing "Placed" ledger entries are created (idempotent).';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $created = 0;

        foreach (Order::get()->filter('Status', (array) Order::config()->get('placed_status')) as $order) {
            $existed = ShopPostingEntry::get()
                ->filter(['OrderID' => $order->ID, 'Type' => 'Placed'])
                ->exists();

            // forceWrite so onAfterWrite fires even though the order itself is unchanged
            // (in real placement the order IS changed, so the hook fires naturally).
            $order->write(false, false, true);

            if (!$existed) {
                $created++;
            }
        }

        $output->writeln("Backfilled Placed entries for {$created} order(s).");
        $output->writeln('Total ledger entries now: ' . ShopPostingEntry::get()->count());

        return Command::SUCCESS;
    }
}
