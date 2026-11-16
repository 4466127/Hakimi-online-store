<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\DeliveryZones\Pages\EditDeliveryZone;
use App\Models\DeliveryZone;
use App\Models\User;
use Database\Seeders\DeliveryZoneSeeder;
use Database\Seeders\ProvinceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * اشکالی که صاحب پروژه دید: پله‌ی آخر (بی عدد، «سنگین‌تر از همه») را با کرایه‌ی نوشته‌شده ذخیره می‌کند
 * ولی خطای «پله‌ی بی عدد فقط می‌تواند آخرین پله باشد» می‌آید.
 */
class DeliveryTierSaveTest extends TestCase
{
    use RefreshDatabase;

    public function test_adding_a_new_open_ended_last_tier_after_editing_the_old_one_saves(): void
    {
        $this->seed(ProvinceSeeder::class);
        $this->seed(DeliveryZoneSeeder::class);
        $this->actingAs(User::factory()->create(['role' => UserRole::Owner]));
        $zone = DeliveryZone::query()->firstOrFail();

        $component = Livewire::test(EditDeliveryZone::class, ['record' => $zone->getKey()]);
        $tiers = $component->get('data.weightTiers');
        $keys = array_keys($tiers);
        // پله‌ی آخرِ قبلی (بی عدد) را ۱۵۰ کیلو می‌کند و یک پله‌ی تازه‌ی بی عدد اضافه می‌کند
        $tiers[$keys[4]]['up_to_grams'] = '150';
        $tiers[$keys[4]]['extra_fee'] = '350';
        $tiers['new-tier'] = ['up_to_grams' => '', 'extra_fee' => '500'];

        $component->fillForm(['weightTiers' => $tiers])->call('save');

        $component->assertHasNoFormErrors();
        $zone = $zone->fresh(['weightTiers']);
        $this->assertCount(6, $zone->weightTiers);
        $this->assertSame([5000, 10000, 20000, 50000, 150000, null], $zone->weightTiers->pluck('up_to_grams')->all());
        $this->assertSame(['0.00', '150.00', '250.00', '400.00', '350.00', '500.00'], $zone->weightTiers->pluck('extra_fee')->all());
    }
}
