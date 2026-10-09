<?php

namespace App\Services\Reservation;

use App\Models\Booking;
use App\Models\Venue;

/**
 * Builds the WhatsApp / phone / email hand-over links that carry a booking
 * summary (or a plain "talk to the team" request) to the venue team.
 */
class ReservationHandover
{
    /**
     * @return array{whatsapp_url: ?string, phone_url: ?string, email_url: ?string}
     */
    public function forBooking(Venue $venue, Booking $booking, string $locale): array
    {
        $message = $this->reservationMessage($booking, $locale);

        return $this->links($venue, $message, $this->subject('reservation', $locale).' '.$booking->reference);
    }

    /**
     * @return array{whatsapp_url: ?string, phone_url: ?string, email_url: ?string}
     */
    public function forStaff(Venue $venue, string $locale): array
    {
        return $this->links($venue, $this->subject('staff_message', $locale), $this->subject('staff', $locale));
    }

    /**
     * @return array{whatsapp_url: ?string, phone_url: ?string, email_url: ?string}
     */
    private function links(Venue $venue, string $message, string $subject): array
    {
        $whatsapp = preg_replace('/\D+/', '', (string) $venue->whatsapp);
        $phone = preg_replace('/[^\d+]/', '', (string) $venue->phone);

        return [
            'whatsapp_url' => $whatsapp !== '' ? 'https://wa.me/'.$whatsapp.'?text='.rawurlencode($message) : null,
            'phone_url' => $phone !== '' ? 'tel:'.$phone : null,
            'email_url' => filled($venue->email) ? 'mailto:'.$venue->email.'?subject='.rawurlencode($subject).'&body='.rawurlencode($message) : null,
        ];
    }

    private function reservationMessage(Booking $booking, string $locale): string
    {
        $booking->loadMissing('spaceType');

        $labels = match ($locale) {
            'en' => ['Hello, I would like to book a space for a recruitment event.', 'Name', 'Company', 'Start date', 'End date', 'Attendees', 'Spaces', 'Space type', 'Special request', 'Reference', 'Event type'],
            'ja' => ['こんにちは。採用イベントのためにスペースを予約したいです。', 'お名前', '会社名', '開始日', '終了日', '参加人数', 'スペース数', 'スペースタイプ', 'ご要望', '予約番号', 'イベント種別'],
            default => ['Halo, saya ingin memesan ruang untuk acara rekrutmen.', 'Nama', 'Perusahaan', 'Tanggal mulai', 'Tanggal selesai', 'Peserta', 'Jumlah ruang', 'Tipe ruang', 'Permintaan khusus', 'Referensi', 'Jenis acara'],
        };

        $attendees = $booking->candidates + $booking->recruiters;

        return implode("\n", [
            $labels[0],
            '',
            "{$labels[1]}: {$booking->guest_name}",
            "{$labels[2]}: ".($booking->company_name ?: '-'),
            "{$labels[10]}: ".($booking->event_type ? ($this->eventTypeLabel($booking->event_type, $locale)) : '-'),
            "{$labels[3]}: ".$booking->start_date->locale($locale)->translatedFormat('j F Y'),
            "{$labels[4]}: ".$booking->end_date->locale($locale)->translatedFormat('j F Y'),
            "{$labels[5]}: {$attendees}",
            "{$labels[6]}: {$booking->space_count}",
            "{$labels[7]}: ".$booking->spaceType->translatedName($locale),
            "{$labels[8]}: ".($booking->notes ?: '-'),
            '',
            "{$labels[9]}: {$booking->reference}",
        ]);
    }

    /**
     * Organiser-facing name of an event type, in the organiser's language.
     */
    public function eventTypeLabel(string $eventType, string $locale): string
    {
        return [
            'en' => ['job_fair' => 'Job fair', 'walk_in_interview' => 'Walk-in interviews', 'assessment' => 'Assessment & testing', 'briefing' => 'Briefing / talent talk', 'campus_hiring' => 'Campus hiring', 'other' => 'Other'],
            'ja' => ['job_fair' => '合同説明会・ジョブフェア', 'walk_in_interview' => '来場型面接', 'assessment' => '適性検査・テスト', 'briefing' => '会社説明会', 'campus_hiring' => '新卒採用イベント', 'other' => 'その他'],
            'id' => ['job_fair' => 'Job fair', 'walk_in_interview' => 'Walk-in interview', 'assessment' => 'Asesmen & tes', 'briefing' => 'Pembekalan / talent talk', 'campus_hiring' => 'Campus hiring', 'other' => 'Lainnya'],
        ][$locale][$eventType] ?? $eventType;
    }

    private function subject(string $key, string $locale): string
    {
        return [
            'en' => ['reservation' => 'Venue booking request', 'staff' => 'Question for the venue team', 'staff_message' => 'Hello, I would like to speak with the venue team.'],
            'ja' => ['reservation' => '会場予約リクエスト', 'staff' => '会場スタッフへのご相談', 'staff_message' => 'こんにちは。会場のスタッフとお話ししたいです。'],
            'id' => ['reservation' => 'Permintaan booking venue', 'staff' => 'Pertanyaan untuk tim venue', 'staff_message' => 'Halo, saya ingin bicara dengan tim venue.'],
        ][$locale][$key];
    }
}
