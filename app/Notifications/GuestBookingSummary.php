<?php

namespace App\Notifications;

use App\Models\Booking;

/**
 * The few lines every guest email repeats about the booking, in the organiser's language.
 */
class GuestBookingSummary
{
    /**
     * @return list<string>
     */
    public static function lines(Booking $booking, string $locale): array
    {
        $booking->loadMissing(['venue', 'spaceType']);

        $labels = match ($locale) {
            'ja' => ['予約番号', 'スペース', '開始日', '終了日', '参加人数', '合計'],
            'id' => ['Referensi', 'Ruang', 'Mulai', 'Selesai', 'Peserta', 'Total'],
            default => ['Reference', 'Space', 'Start date', 'End date', 'Attendees', 'Total'],
        };

        $dateFormat = $locale === 'ja' ? 'Y年n月j日' : 'j F Y';
        $format = fn ($date) => $date->locale($locale)->translatedFormat($dateFormat);

        return [
            "{$labels[0]}: {$booking->reference}",
            "{$labels[1]}: {$booking->space_count} × {$booking->spaceType->translatedName($locale)}",
            "{$labels[2]}: {$format($booking->start_date)}",
            "{$labels[3]}: {$format($booking->end_date)}",
            "{$labels[4]}: ".($booking->candidates + $booking->recruiters),
            "{$labels[5]}: {$booking->venue->currency} ".number_format((float) $booking->total_price, 0, ',', '.'),
        ];
    }
}
