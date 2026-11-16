<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\DeliveryZones\Pages\CreateDeliveryZone;
use App\Filament\Resources\DeliveryZones\Pages\EditDeliveryZone;
use App\Filament\Resources\DeliveryZones\Pages\ListDeliveryZones;
use App\Models\ActivityLog;
use App\Models\DeliveryZone;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Province;
use App\Models\User;
use App\Services\Delivery\DeliveryCalculator;
use Carbon\CarbonImmutable;
use Database\Seeders\DeliveryZoneSeeder;
use Database\Seeders\ProvinceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * کرایه‌ی تحویل = کرایه‌ی روز تحویل + اضافه‌ی وزن (تصمیم صاحب پروژه، ۲۶ عقرب ۱۴۰۵):
 * محاسبه، منطقه‌ی هر ولایت، داده‌ی اولیه‌ی کابل و صفحه‌های پنل.
 */
class DeliveryTest extends TestCase
{
    use RefreshDatabase;

    private DeliveryZone $zone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ProvinceSeeder::class);
        $this->zone = $this->zone();
    }

    /**
     * @param  list<int|string>  $slotFees
     * @param  list<array{0: int|null, 1: int|string}>  $tiers
     * @param  array<string, mixed>  $override
     */
    private function zone(array $slotFees = [300, 250, 200, 150, 100], ?array $tiers = null, array $override = []): DeliveryZone
    {
        $zone = DeliveryZone::query()->create($override + [
            'name' => ['fa' => 'کابل'],
            'province_id' => Province::query()->where('code', 'KAB')->value('id'),
            'cutoff_hour' => 16,
            'days_off' => [5],
            'default_item_weight_grams' => 500,
        ]);
        foreach ($slotFees as $i => $fee) {
            $zone->slots()->create(['day_rank' => $i + 1, 'fee' => $fee]);
        }
        foreach ($tiers ?? [[5000, 0], [10000, 100], [20000, 200], [50000, 400], [null, 800]] as $i => [$upTo, $fee]) {
            $zone->weightTiers()->create(['up_to_grams' => $upTo, 'extra_fee' => $fee, 'position' => $i]);
        }

        return $zone->fresh(['slots', 'weightTiers']);
    }

    private function calc(): DeliveryCalculator
    {
        return app(DeliveryCalculator::class);
    }

    /** دوشنبه ۱۶ نوامبر ۲۰۲۶ ساعت ۱۰ صبح به وقت کابل */
    private function monday(int $hour = 10): CarbonImmutable
    {
        return CarbonImmutable::parse('2026-11-16 '.sprintf('%02d:00', $hour), 'Asia/Kabul');
    }

    public function test_weight_fee_follows_tiers_and_gets_higher_for_heavier_loads(): void
    {
        $fee = fn (int $grams): string => $this->calc()->weightFee($this->zone, $grams);

        $this->assertSame('0.00', $fee(0));
        $this->assertSame('0.00', $fee(5000));      // دقیقاً سقف پله‌ی اول
        $this->assertSame('100.00', $fee(5001));
        $this->assertSame('100.00', $fee(10000));
        $this->assertSame('200.00', $fee(20000));
        $this->assertSame('400.00', $fee(50000));
        $this->assertSame('800.00', $fee(50001));   // از همه‌ی پله‌ها سنگین‌تر
        $this->assertSame('800.00', $fee(900000));
    }

    public function test_tiers_work_in_any_order_and_without_an_open_tier_or_without_tiers(): void
    {
        $shuffled = $this->zone([300], [[20000, 200], [5000, 0], [10000, 100]], ['province_id' => null]);
        $this->assertSame('100.00', $this->calc()->weightFee($shuffled, 7000));
        $this->assertSame('200.00', $this->calc()->weightFee($shuffled, 20000));
        $this->assertSame('200.00', $this->calc()->weightFee($shuffled, 99999));   // بی پله‌ی بی سقف: بالاترین پله

        $none = $this->zone([300], [], ['name' => ['fa' => 'بی وزن'], 'province_id' => Province::query()->where('code', 'HER')->value('id')]);
        $this->assertSame('0.00', $this->calc()->weightFee($none, 80000));
    }

    public function test_options_start_tomorrow_get_cheaper_each_day_and_skip_friday(): void
    {
        $options = $this->calc()->options($this->zone, 1000, $this->monday());

        $this->assertCount(5, $options);
        $this->assertSame(['2026-11-17', '2026-11-18', '2026-11-19', '2026-11-21', '2026-11-22'], array_map(fn ($o) => $o->dateString(), $options));   // جمعه ۲۰ رد شد
        $this->assertSame(['300.00', '250.00', '200.00', '150.00', '100.00'], array_map(fn ($o) => $o->total, $options));
        $this->assertSame([1, 2, 3, 4, 5], array_map(fn ($o) => $o->index, $options));

        $this->assertTrue($options[0]->isTomorrow);
        $this->assertSame('فردا', $options[0]->dayName());
        $this->assertSame('چهارشنبه', $options[1]->dayName());
        $this->assertSame('شنبه', $options[3]->dayName());
        $this->assertSame('۲۸ عقرب ۱۴۰۵', $options[2]->dateLong());   // پنجشنبه ۱۹ نوامبر ۲۰۲۶
    }

    public function test_order_after_cutoff_starts_the_day_after_tomorrow(): void
    {
        $options = $this->calc()->options($this->zone, 1000, $this->monday(16));

        $this->assertSame('2026-11-18', $options[0]->dateString());
        $this->assertFalse($options[0]->isTomorrow);
        $this->assertSame('300.00', $options[0]->total);
        // یک دقیقه مانده به ساعت قطع هنوز فردا
        $before = $this->calc()->options($this->zone, 1000, CarbonImmutable::parse('2026-11-16 15:59', 'Asia/Kabul'));
        $this->assertSame('2026-11-17', $before[0]->dateString());
    }

    public function test_total_is_day_fee_plus_weight_fee_and_the_clock_uses_kabul_time(): void
    {
        // ساعت ۱۱:۳۰ شب UTC یکشنبه = ۴:۰۰ صبح دوشنبه‌ی کابل
        $utc = CarbonImmutable::parse('2026-11-15 23:30', 'UTC');
        $options = $this->calc()->options($this->zone, 12000, $utc);

        $this->assertSame('2026-11-17', $options[0]->dateString());   // فردا به وقت کابل = سه‌شنبه
        $this->assertSame('200.00', $options[0]->weightFee);
        $this->assertSame('500.00', $options[0]->total);              // ۳۰۰ + ۲۰۰
        $this->assertSame('300.00', $options[0]->slotFee);
        $this->assertSame('300.00', $options[4]->total);              // ۱۰۰ + ۲۰۰
    }

    public function test_inactive_slots_all_days_off_and_too_heavy_give_no_options(): void
    {
        $this->zone->slots()->where('day_rank', 2)->update(['is_active' => false]);
        $options = $this->calc()->options($this->zone->fresh(['slots', 'weightTiers']), 1000, $this->monday());
        $this->assertSame(['300.00', '200.00', '150.00', '100.00'], array_map(fn ($o) => $o->total, $options));

        $allOff = $this->zone([300], null, ['province_id' => null, 'days_off' => [1, 2, 3, 4, 5, 6, 7]]);
        $this->assertSame([], $this->calc()->options($allOff, 1000, $this->monday()));

        $limited = $this->zone([300], null, ['name' => ['fa' => 'محدود'], 'province_id' => Province::query()->where('code', 'HER')->value('id'), 'max_weight_grams' => 30000]);
        $this->assertNotEmpty($this->calc()->options($limited, 30000, $this->monday()));
        $this->assertSame([], $this->calc()->options($limited, 30001, $this->monday()));
        $this->assertTrue($this->calc()->isTooHeavy($limited, 30001));
        $this->assertFalse($this->calc()->isTooHeavy($this->zone, 9999999));   // بی سقف
    }

    public function test_chosen_day_is_recalculated_and_a_stale_or_invented_day_is_rejected(): void
    {
        $now = $this->monday();

        $this->assertSame('250.00', $this->calc()->option($this->zone, 1000, '2026-11-18', $now)->total);
        $this->assertNull($this->calc()->option($this->zone, 1000, '2026-11-20', $now), 'جمعه بی تحویل است');
        $this->assertNull($this->calc()->option($this->zone, 1000, '2026-12-31', $now));
        // بعد از ساعت قطع، «فردا» دیگر نیست
        $this->assertNull($this->calc()->option($this->zone, 1000, '2026-11-17', $this->monday(17)));
    }

    public function test_cart_weight_uses_default_weight_for_unweighted_items_and_rounds_up(): void
    {
        $heavy = new ProductVariant(['weight_grams' => 2500]);
        $unweighted = new ProductVariant;           // وزن نوشته نشده ← ۵۰۰ گرام

        $grams = $this->calc()->totalWeight($this->zone, [
            ['variant' => $heavy, 'qty' => 2],        // ۵۰۰۰
            ['variant' => $unweighted, 'qty' => 3],   // ۱۵۰۰
            ['variant' => $heavy, 'qty' => '0.5'],    // ۱۲۵۰
        ]);

        $this->assertSame(7750, $grams);
        $this->assertSame(1, $this->calc()->totalWeight($this->zone, [['variant' => new ProductVariant(['weight_grams' => 1]), 'qty' => '0.1']]));   // به بالا گرد می‌شود
        $this->assertSame(0, $this->calc()->totalWeight($this->zone, []));
    }

    public function test_zone_lookup_prefers_the_provinces_own_zone_and_needs_delivery_enabled(): void
    {
        $kabul = Province::query()->where('code', 'KAB')->first();
        $herat = Province::query()->where('code', 'HER')->first();

        $this->assertSame($this->zone->id, $this->calc()->zoneFor($kabul)->id);
        $this->assertNull($this->calc()->zoneFor($herat), 'هرات «تحویل فعال» ندارد');
        $this->assertNull($this->calc()->zoneFor(null));

        $catchAll = $this->zone([500], null, ['name' => ['fa' => 'بقیه'], 'province_id' => null]);
        $herat->update(['delivery_enabled' => true]);
        $this->assertSame($catchAll->id, $this->calc()->zoneFor($herat)->id);
        $this->assertSame($this->zone->id, $this->calc()->zoneFor($kabul)->id, 'منطقه‌ی خود ولایت مقدم است');

        $this->zone->update(['is_active' => false]);
        $this->assertSame($catchAll->id, $this->calc()->zoneFor($kabul)->id);
    }

    public function test_fee_range_for_the_site_text(): void
    {
        $this->assertSame(['min' => '100.00', 'max' => '300.00'], $this->calc()->feeRange($this->zone));
        $this->assertNull($this->calc()->feeRange($this->zone([], [], ['province_id' => null])));
    }

    public function test_seeder_creates_kabul_with_owners_starting_fee_and_is_safe_to_repeat(): void
    {
        $this->artisan('migrate:fresh');   // منطقه‌ی دستیِ setUp پاک شود
        $this->seed(ProvinceSeeder::class);
        $this->seed(DeliveryZoneSeeder::class);
        $this->seed(DeliveryZoneSeeder::class);

        $zone = DeliveryZone::query()->with(['slots', 'weightTiers', 'province'])->firstOrFail();
        $this->assertSame(1, DeliveryZone::query()->count());
        $this->assertSame('KAB', $zone->province->code);
        $fees = $zone->slots->pluck('fee')->map(fn ($f) => (float) $f)->all();
        $sorted = $fees;
        rsort($sorted);
        $this->assertSame($sorted, $fees, 'هر روز دیرتر ارزان‌تر');
        $this->assertSame([5], $zone->days_off);
        $this->assertSame(0, ActivityLog::query()->whereIn('subject_type', ['deliveryzone', 'deliveryslot', 'deliveryweighttier'])->count());

        // سنگین‌تر، کرایه‌ی بیشتر
        $light = $this->calc()->weightFee($zone, 1000);
        $heavy = $this->calc()->weightFee($zone, 60000);
        $this->assertTrue(bccomp($heavy, $light, 2) > 0);
    }

    /**
     * قید صاحب پروژه (۲۶ عقرب ۱۴۰۵) روی عددهای اولیه: بار تا ۵ کیلو هیچ‌وقت بیشتر از ۲۰۰ افغانی؛
     * بالاتر از ۵ کیلو بین ۲۵۰ تا ۸۰۰. اگر کسی عددهای seeder را عوض کند و قید بشکند، این تست می‌گوید.
     */
    public function test_default_fees_keep_the_owners_limits_for_every_weight_and_every_day(): void
    {
        $this->artisan('migrate:fresh');
        $this->seed(ProvinceSeeder::class);
        $this->seed(DeliveryZoneSeeder::class);
        $zone = DeliveryZone::query()->with(['slots', 'weightTiers'])->firstOrFail();

        foreach ([0, 1, 500, 2500, 4999, 5000] as $grams) {
            foreach ($this->calc()->options($zone, $grams, $this->monday()) as $option) {
                $this->assertTrue(bccomp($option->total, '200', 2) <= 0, "بار {$grams} گرام نباید بیشتر از ۲۰۰ باشد: {$option->total}");
                $this->assertTrue(bccomp($option->total, '100', 2) >= 0);
            }
        }
        foreach ([5001, 7000, 10000, 15000, 20000, 35000, 50000, 50001, 120000] as $grams) {
            foreach ($this->calc()->options($zone, $grams, $this->monday()) as $option) {
                $this->assertTrue(bccomp($option->total, '250', 2) >= 0, "بار {$grams} گرام نباید کمتر از ۲۵۰ باشد: {$option->total}");
                $this->assertTrue(bccomp($option->total, '800', 2) <= 0, "بار {$grams} گرام نباید بیشتر از ۸۰۰ باشد: {$option->total}");
            }
        }

        // و کرایه با وزن بالاتر هیچ‌وقت کم نمی‌شود
        $previous = '0.00';
        foreach ([1000, 5000, 5001, 10000, 20000, 50000, 80000] as $grams) {
            $fee = $this->calc()->options($zone, $grams, $this->monday())[0]->total;
            $this->assertTrue(bccomp($fee, $previous, 2) >= 0);
            $previous = $fee;
        }
    }

    public function test_migration_lowers_old_default_fees_but_keeps_fees_the_admin_changed(): void
    {
        $migration = require database_path('migrations/2026_11_18_000400_lower_default_delivery_fees.php');

        // عددهای قدیمی seeder (همان منطقه‌ی setUp) ← تازه
        $zone = $this->zone;
        $migration->up();
        $zone = $zone->fresh(['slots', 'weightTiers']);
        $this->assertSame(['200.00', '175.00', '150.00', '125.00', '100.00'], $zone->slots->pluck('fee')->all());
        $this->assertSame(['0.00', '150.00', '250.00', '400.00', '600.00'], $zone->weightTiers->pluck('extra_fee')->all());

        // برگشت‌پذیر
        $migration->down();
        $this->assertSame(['300.00', '250.00', '200.00', '150.00', '100.00'], $zone->fresh(['slots'])->slots->pluck('fee')->all());

        // مدیر یک عدد را عوض کرده ← هیچ‌چیز دست نمی‌خورد
        $zone->slots()->where('day_rank', 1)->update(['fee' => 350]);
        $migration->up();
        $this->assertSame(['350.00', '250.00', '200.00', '150.00', '100.00'], $zone->fresh(['slots'])->slots->pluck('fee')->all());
        $this->assertSame(['0.00', '100.00', '200.00', '400.00', '800.00'], $zone->fresh(['weightTiers'])->weightTiers->pluck('extra_fee')->all());
    }

    // ───────────────────────── پنل ─────────────────────────

    public function test_only_owner_and_manager_see_delivery_pages(): void
    {
        foreach ([UserRole::Owner, UserRole::Manager] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get('/admin/delivery-zones')->assertOk()->assertSee('کابل');
            $this->get('/admin/delivery-zones/'.$this->zone->id.'/edit')->assertOk()->assertSee('روز تحویل ۱')->assertSee('پله‌ی ۱');
        }
        foreach ([UserRole::Editor, UserRole::Staff] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get('/admin/delivery-zones')->assertForbidden();
        }
    }

    public function test_edit_form_shows_kilograms_and_saves_fees_in_persian_digits(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Owner]));

        $component = Livewire::test(EditDeliveryZone::class, ['record' => $this->zone->getKey()])
            ->assertFormSet(function (array $state): void {
                $tiers = array_values($state['weightTiers']);
                $this->assertSame('5', $tiers[0]['up_to_grams']);        // گرام در دیتابیس، کیلو در فورم
                $this->assertSame('0', $tiers[0]['extra_fee']);
                $this->assertNull($tiers[4]['up_to_grams']);
                $this->assertSame('300', array_values($state['slots'])[0]['fee']);
            });

        $slots = $component->get('data.slots');
        $slots[array_key_first($slots)]['fee'] = '۳۵۰';          // ارقام فارسی
        $tiers = $component->get('data.weightTiers');
        $tiers[array_key_first($tiers)]['up_to_grams'] = '۲٫۵';   // ۲٫۵ کیلو

        $component->fillForm(['slots' => $slots, 'weightTiers' => $tiers, 'cutoff_hour' => 14, 'days_off' => [5, 4]])
            ->call('save')
            ->assertHasNoFormErrors();

        $zone = $this->zone->fresh(['slots', 'weightTiers']);
        $this->assertSame('350.00', $zone->slots->first()->fee);
        $this->assertSame(2500, $zone->weightTiers->first()->up_to_grams);
        $this->assertSame(14, $zone->cutoff_hour);
        $this->assertEqualsCanonicalizing([4, 5], array_map('intval', $zone->days_off));
        $this->assertTrue(ActivityLog::query()->where('action', 'deliveryslot.updated')->exists(), 'تغییر کرایه در تاریخچه ثبت می‌شود');
    }

    public function test_weight_tiers_with_a_duplicate_weight_or_two_open_tiers_are_rejected_with_row_numbers(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Owner]));
        $component = Livewire::test(EditDeliveryZone::class, ['record' => $this->zone->getKey()]);

        $tiers = $component->get('data.weightTiers');
        $keys = array_keys($tiers);
        $tiers[$keys[1]]['up_to_grams'] = '5';          // با پله‌ی اول (۵ کیلو) یکی
        $component->fillForm(['weightTiers' => $tiers])->call('save')->assertHasFormErrors(['weightTiers']);
        $this->assertStringContainsString('پله‌ی ۱ و پله‌ی ۲', $component->errors()->first('data.weightTiers'));

        $tiers = $component->get('data.weightTiers');
        $tiers[$keys[0]]['up_to_grams'] = '';           // دو پله‌ی بی عدد: اول و آخر
        $tiers[$keys[1]]['up_to_grams'] = '10';
        $component->fillForm(['weightTiers' => $tiers])->call('save')->assertHasFormErrors(['weightTiers']);
        $this->assertStringContainsString('۱، ۵', $component->errors()->first('data.weightTiers'));

        $this->assertSame(5000, $this->zone->fresh(['weightTiers'])->weightTiers->first()->up_to_grams);
    }

    public function test_the_open_tier_can_be_anywhere_in_the_list_and_still_means_heavier_than_all(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Owner]));
        $component = Livewire::test(EditDeliveryZone::class, ['record' => $this->zone->getKey()]);

        // پله‌ی بی عدد را اول بگذار (سه پله‌ی دیگر عددی)
        $tiers = $component->get('data.weightTiers');
        $keys = array_keys($tiers);
        $tiers[$keys[0]] = ['up_to_grams' => '', 'extra_fee' => '900'];
        $tiers[$keys[4]] = ['up_to_grams' => '30', 'extra_fee' => '300'];
        $component->fillForm(['weightTiers' => $tiers])->call('save')->assertHasNoFormErrors();

        $zone = $this->zone->fresh(['weightTiers', 'slots']);
        $this->assertSame('900.00', $this->calc()->weightFee($zone, 60000), 'سنگین‌تر از همه ← پله‌ی بی عدد، هر جا که باشد');
        $this->assertSame('300.00', $this->calc()->weightFee($zone, 25000));
        $this->assertSame('100.00', $this->calc()->weightFee($zone, 7000));   // پله‌ی ۱۰ کیلوی همین منطقه‌ی آزمایشی
    }

    public function test_negative_or_text_fee_is_rejected(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Owner]));
        $component = Livewire::test(EditDeliveryZone::class, ['record' => $this->zone->getKey()]);

        $slots = $component->get('data.slots');
        $slots[array_key_first($slots)]['fee'] = 'abc';
        $component->fillForm(['slots' => $slots])->call('save')->assertHasFormErrors();

        $this->assertSame('300.00', $this->zone->fresh(['slots'])->slots->first()->fee);
    }

    public function test_a_second_zone_for_the_same_province_is_rejected_and_a_new_zone_can_be_created(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Owner]));
        $kabulId = Province::query()->where('code', 'KAB')->value('id');
        $heratId = Province::query()->where('code', 'HER')->value('id');

        Livewire::test(CreateDeliveryZone::class)
            ->fillForm(['name' => ['fa' => 'کابل دوم'], 'province_id' => $kabulId, 'slots' => [['fee' => '300', 'is_active' => true]], 'weightTiers' => []])
            ->call('create')
            ->assertHasFormErrors(['province_id']);

        Livewire::test(CreateDeliveryZone::class)
            ->fillForm(['name' => ['fa' => 'هرات'], 'province_id' => $heratId, 'cutoff_hour' => 16, 'default_item_weight_grams' => 500,
                'slots' => [['fee' => '400', 'is_active' => true], ['fee' => '300', 'is_active' => true]], 'weightTiers' => [['up_to_grams' => '10', 'extra_fee' => '150']]])
            ->call('create')
            ->assertHasNoFormErrors();

        $herat = DeliveryZone::query()->where('province_id', $heratId)->with(['slots', 'weightTiers'])->firstOrFail();
        $this->assertSame(['400.00', '300.00'], $herat->slots->pluck('fee')->all());
        $this->assertSame([1, 2], $herat->slots->pluck('day_rank')->all());
        $this->assertSame(10000, $herat->weightTiers->first()->up_to_grams);
    }

    public function test_list_page_summarizes_the_fees(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Owner]));

        Livewire::test(ListDeliveryZones::class)->assertCanSeeTableRecords([$this->zone])
            ->assertSee('۳۰۰ ← ۲۵۰ ← ۲۰۰ ← ۱۵۰ ← ۱۰۰');
    }

    public function test_variant_weight_can_be_saved_from_the_product_form_service(): void
    {
        // وزن جنس همان ستون weight_grams است که فورم جنس ذخیره می‌کند و کرایه از آن حساب می‌شود
        $product = Product::factory()->active()->withVariant(['weight_grams' => 12000])->create();
        $variant = $product->variants()->firstOrFail();

        $grams = $this->calc()->totalWeight($this->zone, [['variant' => $variant, 'qty' => 2]]);

        $this->assertSame(24000, $grams);
        $this->assertSame('400.00', $this->calc()->weightFee($this->zone, $grams));
    }
}
