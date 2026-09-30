<?php

declare(strict_types=1);

namespace SilverShop\Ledger;

use SilverStripe\Forms\DateField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\Reports\Report;

/**
 * Ledger postings summarised by type (Placed, Captured, Refunded, …) for a period — a compact financial
 * overview that complements core's Shop Sales report. Lives in the ledger module because it reports its own data.
 */
class LedgerSummaryReport extends Report
{
    public function title()
    {
        return _t(__CLASS__ . '.TITLE', 'Ledger summary by type');
    }

    public function description()
    {
        return _t(__CLASS__ . '.DESC', 'Totals of ledger postings grouped by type, for a period.');
    }

    public function group()
    {
        return _t('SilverShop\\Reports.GROUP', 'Shop');
    }

    public function sort()
    {
        return 300;
    }

    public function parameterFields()
    {
        return FieldList::create(
            DateField::create('StartDate', _t(__CLASS__ . '.Start', 'From')),
            DateField::create('EndDate', _t(__CLASS__ . '.End', 'To'))
        );
    }

    public function sourceRecords($params = null)
    {
        $query = SQLSelect::create(
            ['Type' => '"Type"', 'Entries' => 'COUNT(*)', 'Total' => 'SUM("Amount")'],
            '"SilverShop_PostingEntry"'
        );

        if (!empty($params['StartDate'])) {
            $query->addWhere(['"Created" >= ?' => $params['StartDate'] . ' 00:00:00']);
        }
        if (!empty($params['EndDate'])) {
            $query->addWhere(['"Created" <= ?' => $params['EndDate'] . ' 23:59:59']);
        }

        $query->setGroupBy('"Type"')->setOrderBy('"Type"');

        $list = ArrayList::create();
        foreach ($query->execute() as $row) {
            $list->push(ArrayData::create([
                'TypeLabel' => _t(ShopPostingEntry::class . '.TYPE_' . $row['Type'], (string) $row['Type']),
                'Entries' => (int) $row['Entries'],
                'Total' => number_format((float) $row['Total'], 2),
            ]));
        }

        return $list;
    }

    public function columns()
    {
        return [
            'TypeLabel' => _t(__CLASS__ . '.ColType', 'Type'),
            'Entries' => _t(__CLASS__ . '.ColEntries', 'Entries'),
            'Total' => _t(__CLASS__ . '.ColTotal', 'Total'),
        ];
    }
}
