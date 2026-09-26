<?php

namespace SilverShop\Ledger;

use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordViewer;
use SilverStripe\Forms\GridField\GridFieldExportButton;

/**
 * A global, read-only view of every posting entry across all orders — searchable by type, order
 * reference and customer email, with Order and Customer columns, and CSV export for accounting.
 *
 * The ledger is immutable and code-generated, so the grid uses a RecordViewer config: each row has a
 * view (not edit) action and there is no add/delete control.
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

    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm($id, $fields);

        $grid = $form->Fields()->dataFieldByName($this->sanitiseClassName($this->modelClass));
        if ($grid instanceof GridField) {
            // Immutable records: view-only rows (no edit pencil), but keep the CSV export.
            $config = GridFieldConfig_RecordViewer::create();
            $config->addComponent(GridFieldExportButton::create('buttons-before-left'));
            $grid->setConfig($config);
        }

        return $form;
    }
}
