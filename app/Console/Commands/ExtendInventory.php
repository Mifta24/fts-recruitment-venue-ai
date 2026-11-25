<?php

namespace App\Console\Commands;

use App\Models\SpaceInventory;
use App\Models\SpaceType;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('inventory:extend {--days=365 : How many days ahead spaces should be open for booking}')]
#[Description('Open further days for every space type, repeating the units and prices of the week before; existing days and bookings are never changed')]
class ExtendInventory extends Command
{
    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $added = 0;

        SpaceType::query()->where('is_active', true)->with('venue')->each(function (SpaceType $spaceType) use ($days, &$added): void {
            $added += $this->extend($spaceType, $days);
        });

        $this->info("Opened {$added} day(s).");

        return self::SUCCESS;
    }

    private function extend(SpaceType $spaceType, int $days): int
    {
        $last = SpaceInventory::query()->where('space_type_id', $spaceType->id)->orderByDesc('event_date')->first();

        if (! $last) {
            return 0;
        }

        $horizon = CarbonImmutable::now($spaceType->venue->timezone)->startOfDay()->addDays($days);
        $week = SpaceInventory::query()->where('space_type_id', $spaceType->id)
            ->whereDate('event_date', '>', $last->event_date->copy()->subDays(7)->toDateString())
            ->get()
            ->keyBy(fn (SpaceInventory $day) => $day->event_date->toDateString())
            ->map(fn (SpaceInventory $day) => ['total_spaces' => $day->total_spaces, 'price' => (float) $day->price])
            ->all();

        $rows = [];
        for ($date = CarbonImmutable::parse($last->event_date)->addDay(); $date->lte($horizon); $date = $date->addDay()) {
            $template = $week[$date->subDays(7)->toDateString()] ?? ['total_spaces' => $last->total_spaces, 'price' => (float) $last->price];
            $week[$date->toDateString()] = $template;

            $rows[] = [
                'space_type_id' => $spaceType->id,
                'event_date' => $date->startOfDay()->toDateTimeString(),
                'total_spaces' => $template['total_spaces'],
                'booked_spaces' => 0,
                'price' => $template['price'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $inserted = 0;
        foreach (array_chunk($rows, 200) as $chunk) {
            $inserted += SpaceInventory::query()->insertOrIgnore($chunk);
        }

        return $inserted;
    }
}
