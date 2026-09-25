<?php

namespace SilverShop\Ledger;

use SilverStripe\Admin\ModelAdmin;

/**
 * A global, read-only view of every posting entry across all orders — searchable by type, order
 * reference and customer email, with Order and Customer columns, and CSV export for accounting.
 *
 * Read-only falls out of the model: {@link ShopPostingEntry} blocks create/edit/delete, so the
 * grid shows no add/edit/delete controls.
 */
class LedgerAdmin extends ModelAdmin
{
    private static string $url_segment = 'ledger';

    private static string $menu_title = 'Ledger';

    private static string $menu_icon_class = 'font-icon-book-open';

    private static array $managed_models = [
        ShopPostingEntry::class,
    ];

    /**
     * The ledger is immutable and code-generated — never import rows into it (export stays available).
     */
    public $showImportForm = false;
}
