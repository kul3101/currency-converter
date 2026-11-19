<?php
declare(strict_types=1);

namespace Sidelah\CurrencyConverter\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Sidelah\CurrencyConverter\Model\HistoryRange as Range;

class HistoryRange implements OptionSourceInterface
{
    /**
     * @inheritdoc
     *
     * @return list<array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach (Range::cases() as $range) {
            $options[] = ['value' => $range->value, 'label' => $range->getLabel()];
        }

        return $options;
    }
}
