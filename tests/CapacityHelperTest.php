<?php

namespace BowRP\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Tests for rb_capacity_helper.php — pure logic functions.
 * No database or CodeIgniter instance required.
 */
class CapacityHelperTest extends TestCase
{
    // ── rb_format_hours ───────────────────────────────────────────────────────

    public function test_format_hours_short_integer(): void
    {
        $this->assertSame('8h', rb_format_hours(8));
    }

    public function test_format_hours_short_decimal(): void
    {
        $this->assertSame('4.5h', rb_format_hours(4.5));
    }

    public function test_format_hours_long_singular(): void
    {
        $this->assertSame('1 hour', rb_format_hours(1, false));
    }

    public function test_format_hours_long_plural(): void
    {
        $this->assertSame('4.5 hours', rb_format_hours(4.5, false));
    }

    public function test_format_hours_zero(): void
    {
        $this->assertSame('0h', rb_format_hours(0));
    }

    public function test_format_hours_rounds_to_one_decimal(): void
    {
        // 4.55 rounds to 4.6
        $this->assertSame('4.6h', rb_format_hours(4.55));
    }

    // ── rb_format_capacity_percent ────────────────────────────────────────────

    public function test_capacity_percent_full(): void
    {
        $this->assertSame('100%', rb_format_capacity_percent(8, 8));
    }

    public function test_capacity_percent_half(): void
    {
        $this->assertSame('50%', rb_format_capacity_percent(4, 8));
    }

    public function test_capacity_percent_zero_allocated(): void
    {
        $this->assertSame('0%', rb_format_capacity_percent(0, 8));
    }

    public function test_capacity_percent_zero_available_with_allocated(): void
    {
        $this->assertSame('∞%', rb_format_capacity_percent(4, 0));
    }

    public function test_capacity_percent_zero_zero(): void
    {
        $this->assertSame('0%', rb_format_capacity_percent(0, 0));
    }

    public function test_capacity_percent_over_100(): void
    {
        $this->assertSame('125%', rb_format_capacity_percent(10, 8));
    }

    // ── rb_capacity_status_class ──────────────────────────────────────────────

    public function test_status_class_empty(): void
    {
        $this->assertSame('rb-empty', rb_capacity_status_class(0, 8));
    }

    public function test_status_class_low(): void
    {
        // 3/8 = 37.5%
        $this->assertSame('rb-low', rb_capacity_status_class(3, 8));
    }

    public function test_status_class_medium(): void
    {
        // 5/8 = 62.5%
        $this->assertSame('rb-medium', rb_capacity_status_class(5, 8));
    }

    public function test_status_class_high(): void
    {
        // 7/8 = 87.5%
        $this->assertSame('rb-high', rb_capacity_status_class(7, 8));
    }

    public function test_status_class_full(): void
    {
        // 8/8 = 100%
        $this->assertSame('rb-full', rb_capacity_status_class(8, 8));
    }

    public function test_status_class_overbooked(): void
    {
        // 10/8 = 125%
        $this->assertSame('rb-overbooked', rb_capacity_status_class(10, 8));
    }

    public function test_status_class_zero_available_zero_allocated(): void
    {
        $this->assertSame('rb-empty', rb_capacity_status_class(0, 0));
    }

    public function test_status_class_zero_available_with_hours(): void
    {
        $this->assertSame('rb-overbooked', rb_capacity_status_class(4, 0));
    }

    // ── rb_get_week_dates ─────────────────────────────────────────────────────

    public function test_week_dates_returns_five_days_by_default(): void
    {
        $dates = rb_get_week_dates('2025-01-08'); // A Wednesday
        $this->assertCount(5, $dates);
    }

    public function test_week_dates_starts_on_monday(): void
    {
        $dates = rb_get_week_dates('2025-01-08'); // Wednesday week: Mon 06
        $this->assertSame('2025-01-06', $dates[0]);
    }

    public function test_week_dates_ends_on_friday(): void
    {
        $dates = rb_get_week_dates('2025-01-08');
        $this->assertSame('2025-01-10', end($dates));
    }

    public function test_week_dates_include_weekend_returns_seven(): void
    {
        $dates = rb_get_week_dates('2025-01-08', true);
        $this->assertCount(7, $dates);
    }

    public function test_week_dates_given_monday_starts_same_day(): void
    {
        $dates = rb_get_week_dates('2025-01-06'); // Is already Monday
        $this->assertSame('2025-01-06', $dates[0]);
    }

    // ── rb_get_date_range ─────────────────────────────────────────────────────

    public function test_date_range_inclusive_both_ends(): void
    {
        $dates = rb_get_date_range('2025-01-06', '2025-01-10');
        $this->assertSame('2025-01-06', $dates[0]);
        $this->assertSame('2025-01-10', $dates[4]);
        $this->assertCount(5, $dates);
    }

    public function test_date_range_excludes_weekends(): void
    {
        // 2025-01-06 (Mon) to 2025-01-12 (Sun) = 5 working days
        $dates = rb_get_date_range('2025-01-06', '2025-01-12', true);
        $this->assertCount(5, $dates);
        $this->assertNotContains('2025-01-11', $dates); // Saturday
        $this->assertNotContains('2025-01-12', $dates); // Sunday
    }

    public function test_date_range_single_day(): void
    {
        $dates = rb_get_date_range('2025-01-07', '2025-01-07');
        $this->assertCount(1, $dates);
        $this->assertSame('2025-01-07', $dates[0]);
    }

    // ── rb_count_working_days ─────────────────────────────────────────────────

    public function test_count_working_days_full_week(): void
    {
        $this->assertSame(5, rb_count_working_days('2025-01-06', '2025-01-10'));
    }

    public function test_count_working_days_across_two_weeks(): void
    {
        // Mon 06 → Fri 17 = 10 working days
        $this->assertSame(10, rb_count_working_days('2025-01-06', '2025-01-17'));
    }

    public function test_count_working_days_weekend_only(): void
    {
        $this->assertSame(0, rb_count_working_days('2025-01-11', '2025-01-12'));
    }

    // ── rb_color_brightness ───────────────────────────────────────────────────

    public function test_white_is_light(): void
    {
        $this->assertTrue(rb_color_brightness('#ffffff'));
    }

    public function test_black_is_dark(): void
    {
        $this->assertFalse(rb_color_brightness('#000000'));
    }

    public function test_light_yellow_is_light(): void
    {
        $this->assertTrue(rb_color_brightness('#ffff00'));
    }

    public function test_dark_blue_is_dark(): void
    {
        $this->assertFalse(rb_color_brightness('#003366'));
    }

    public function test_short_hex_notation(): void
    {
        // #fff = white → light
        $this->assertTrue(rb_color_brightness('#fff'));
        // #000 = black → dark
        $this->assertFalse(rb_color_brightness('#000'));
    }

    public function test_without_hash_prefix(): void
    {
        $this->assertTrue(rb_color_brightness('ffffff'));
        $this->assertFalse(rb_color_brightness('000000'));
    }

    // ── rb_text_color_for_bg ──────────────────────────────────────────────────

    public function test_text_color_black_on_light_bg(): void
    {
        $this->assertSame('#000000', rb_text_color_for_bg('#ffffff'));
    }

    public function test_text_color_white_on_dark_bg(): void
    {
        $this->assertSame('#ffffff', rb_text_color_for_bg('#000000'));
    }
}
