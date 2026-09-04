<?php

namespace Database\Factories;

use App\Enums\ReportPeriod;
use App\Models\ScheduledReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduledReport>
 */
class ScheduledReportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-1 year', 'now');

        return [
            'report_type' => 'management',
            'period_type' => fake()->randomElement(ReportPeriod::cases()),
            'period_start' => $start,
            'period_end' => (clone $start)->modify('+1 month'),
            'file_path' => 'scheduled-reports/'.fake()->uuid().'.pdf',
            'generated_at' => now(),
        ];
    }
}
