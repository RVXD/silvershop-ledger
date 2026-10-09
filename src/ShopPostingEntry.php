<?php

namespace SilverShop\Ledger;

use SilverShop\Model\Order;
use SilverShop\ORM\FieldType\ShopCurrency;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

/**
 * An immutable, append-only financial posting entry: one row per money-affecting event on an order
 * (placed, captured, refunded, …). Fed from the order-status and payment signals SilverShop already
 * exposes; never edited or deleted, so the ledger is a trustworthy accounting record.
 *
 * @property string  $Type
 * @property float   $Amount
 * @property ?string $Currency
 * @property ?string $Reference
 * @property ?string $Note
 * @property ?string $Data
 * @property int     $DocumentID
 * @property ?string $DocumentClass
 * @method   Order      Order()
 * @method   Payment    Payment()
 * @method   Member     Author()
 * @method   DataObject Document()
 */
class ShopPostingEntry extends DataObject
{
    private static string $table_name = 'SilverShop_PostingEntry';

    private static array $db = [
        'Type' => "Enum('Placed,Captured,Refunded,Voided,Adjusted,WriteOff','Placed')",
        'Amount' => ShopCurrency::class,
        'Currency' => 'Varchar(3)',
        'Reference' => 'Varchar(255)',
        'Note' => 'Text',
        'Data' => 'Text',
    ];

    private static array $has_one = [
        'Order' => Order::class,
        'Payment' => Payment::class,
        'Author' => Member::class,
        // Polymorphic link to the source financial document (e.g. a silvershop/invoicing invoice or credit
        // memo), attached once by the document module. Kept generic so the ledger depends on nothing.
        'Document' => DataObject::class,
    ];

    private static string $default_sort = '"Created" ASC, "ID" ASC';

    private static array $summary_fields = [
        'Created.Nice' => 'When',
        'Type' => 'Type',
        'Amount.Nice' => 'Amount',
        'Order.Reference' => 'Order',
        'Order.Name' => 'Customer',
        'Reference' => 'Reference',
        'Author.Name' => 'By',
    ];

    public function summaryFields(): array
    {
        return [
            'Created.Nice' => _t(self::class . '.col_Date', 'Date'),
            'TypeLabel' => _t(self::class . '.col_Type', 'Type'),
            'Amount.Nice' => _t(self::class . '.col_Amount', 'Amount'),
            'Order.Reference' => _t(self::class . '.col_Order', 'Order'),
            'Order.Name' => _t(self::class . '.col_Customer', 'Customer'),
            'DocumentLabel' => _t(self::class . '.col_Document', 'Document'),
            'Reference' => _t(self::class . '.col_Reference', 'Reference'),
            'Author.Name' => _t(self::class . '.col_By', 'By'),
        ];
    }

    /**
     * The posting type as a translated label (e.g. "Captured" → "Geïncasseerd"). Falls back to the raw
     * enum value when a locale has no translation.
     */
    public function getTypeLabel(): string
    {
        return _t(self::class . '.TYPE_' . $this->Type, (string) $this->Type);
    }

    private static array $searchable_fields = [
        'Type',
        'Reference',
        'Order.Reference',
        'Order.LatestEmail',
    ];

    /**
     * Append-only: an entry's financial data may be created but never changed once persisted. The one
     * exception is attaching the source {@link Document} once (a reference link, not a money change) —
     * allowed only while it is still empty.
     */
    protected function onBeforeWrite(): void
    {
        if ($this->isInDB() && !$this->isAttachingDocument()) {
            throw new ValidationException(_t(
                self::class . '.IMMUTABLE',
                'Posting entries are immutable and cannot be changed.'
            ));
        }

        parent::onBeforeWrite();
    }

    /**
     * True when this write only attaches the source document for the first time (DocumentID/DocumentClass,
     * previously empty) and changes nothing else.
     */
    private function isAttachingDocument(): bool
    {
        $changed = $this->getChangedFields(true, self::CHANGE_VALUE);
        unset($changed['LastEdited']);
        if ($changed === [] || array_diff(array_keys($changed), ['DocumentID', 'DocumentClass']) !== []) {
            return false;
        }

        return (int) ($changed['DocumentID']['before'] ?? 0) === 0 && (int) $this->DocumentID > 0;
    }

    /**
     * Attach the source document once (no-op if already linked). Keeps the attach logic (and the immutable
     * write) in the ledger so document modules just call this.
     */
    public function attachDocument(DataObject $document): void
    {
        if ($this->DocumentID || !$document->exists()) {
            return;
        }
        $this->DocumentID = $document->ID;
        $this->DocumentClass = $document->ClassName;
        $this->write();
    }

    /**
     * A short label for the linked document (its number/title), for the ledger admin. Empty when unlinked.
     */
    public function getDocumentLabel(): string
    {
        $document = $this->Document();
        if (!$document || !$document->exists()) {
            return '';
        }

        return (string) ($document->Number ?: $document->getTitle());
    }

    public function canEdit($member = null): bool
    {
        return false;
    }

    public function canDelete($member = null): bool
    {
        return false;
    }

    /**
     * Entries are only ever created in code (from order/payment events), never by hand in the CMS.
     */
    public function canCreate($member = null, $context = []): bool
    {
        return false;
    }
}
