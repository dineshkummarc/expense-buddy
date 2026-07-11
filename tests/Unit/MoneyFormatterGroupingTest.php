<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\AmountGrouping;
use App\Models\SystemSetting;
use App\Support\ExpenseBuddyTestHarness;
use App\Support\MoneyFormatter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MoneyFormatterGroupingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        ExpenseBuddyTestHarness::install(withDemo: false);
        MoneyFormatter::resetGroupingCache();
    }

    #[Test]
    public function it_formats_western_asian_and_none_groupings(): void
    {
        SystemSetting::query()->update(['amount_grouping' => AmountGrouping::Western]);
        MoneyFormatter::resetGroupingCache();
        $this->assertSame('1,234,567.89', MoneyFormatter::format('1234567.89'));

        SystemSetting::query()->update(['amount_grouping' => AmountGrouping::Asian]);
        MoneyFormatter::resetGroupingCache();
        $this->assertSame('12,34,567.89', MoneyFormatter::format('1234567.89'));

        SystemSetting::query()->update(['amount_grouping' => AmountGrouping::None]);
        MoneyFormatter::resetGroupingCache();
        $this->assertSame('1234567.89', MoneyFormatter::format('1234567.89'));
    }
}
