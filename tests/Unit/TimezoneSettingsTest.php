<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\AmountGrouping;
use App\Models\SystemSetting;
use App\Support\ExpenseBuddyTestHarness;
use App\Support\TimezoneList;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TimezoneSettingsTest extends TestCase
{
    #[Test]
    public function timezone_list_includes_common_identifiers(): void
    {
        $options = TimezoneList::options();

        $this->assertArrayHasKey('UTC', $options);
        $this->assertArrayHasKey('Asia/Dhaka', $options);
        $this->assertStringContainsString('Asia/Dhaka', $options['Asia/Dhaka']);
    }

    #[Test]
    public function administrator_can_save_application_timezone(): void
    {
        $admin = ExpenseBuddyTestHarness::install(withDemo: true);

        $this->actingAs($admin)
            ->put('/admin/settings', [
                'settings_section' => 'general',
                'system_name' => 'ExpenseBuddy',
                'default_currency_id' => SystemSetting::query()->value('default_currency_id'),
                'timezone' => 'Asia/Dhaka',
                'amount_grouping' => 'asian',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('Asia/Dhaka', SystemSetting::query()->value('timezone'));
        $this->assertSame('Asia/Dhaka', config('app.timezone'));
        $this->assertSame(AmountGrouping::Asian, SystemSetting::query()->first()?->amount_grouping);
    }
}
