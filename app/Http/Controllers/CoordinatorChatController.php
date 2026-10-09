<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Venue;
use App\Services\Coordinator\CoordinatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CoordinatorChatController extends Controller
{
    private const SUPPORTED_LOCALES = ['id', 'en', 'ja'];

    public function __construct(private readonly CoordinatorService $coordinator) {}

    public function start(Request $request, string $venueSlug): JsonResponse
    {
        $venue = $this->publishedVenue($venueSlug);

        $locale = in_array($request->input('locale'), self::SUPPORTED_LOCALES, true)
            ? $request->input('locale')
            : $venue->default_locale;

        $conversation = $this->coordinator->startConversation($venue, $locale);

        return response()->json([
            'guest_token' => $conversation->guest_token,
            'locale' => $conversation->locale,
        ]);
    }

    public function message(Request $request, string $venueSlug): JsonResponse
    {
        $venue = $this->publishedVenue($venueSlug);

        $data = $request->validate([
            'guest_token' => ['required', 'uuid'],
            'message' => ['required', 'string', 'max:2000'],
            'scene' => ['nullable', Rule::in(Conversation::SCENES)],
            'selected_space' => ['nullable', 'string', 'max:120'],
            'selected_facility' => ['nullable', 'integer', 'min:1'],
            'reservation' => ['nullable', 'array'],
            'reservation.start_date' => ['nullable', 'date_format:Y-m-d'],
            'reservation.end_date' => ['nullable', 'date_format:Y-m-d'],
            'reservation.candidates' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'reservation.recruiters' => ['nullable', 'integer', 'min:0', 'max:500'],
            'reservation.spaces' => ['nullable', 'integer', 'min:1', 'max:10'],
            'reservation.space_type_slug' => ['nullable', 'string', 'max:120'],
        ]);

        $conversation = $this->findConversation($venue, $data['guest_token']);

        $this->coordinator->rememberContext($venue, $conversation, [
            'scene' => $data['scene'] ?? null,
            'selected_facility' => $data['selected_facility'] ?? null,
            'selected_space' => $data['selected_space'] ?? ($data['reservation']['space_type_slug'] ?? null),
            ...array_key_exists('reservation', $data) ? ['reservation' => array_filter($data['reservation'] ?? [], fn ($value) => $value !== null && $value !== '')] : [],
        ]);

        try {
            $assistantMessage = $this->coordinator->reply($venue, $conversation, $data['message']);
        } catch (\Throwable $e) {
            Log::error('Coordinator reply failed', ['venue_id' => $venue->id, 'error' => $e->getMessage()]);

            return response()->json([
                'error' => 'coordinator_unavailable',
                'message' => 'The AI Venue Coordinator is temporarily unavailable. Please try again in a moment.',
            ], 503);
        }

        return response()->json([
            'message' => $this->formatMessage($assistantMessage),
            'status' => $conversation->fresh()->status,
        ]);
    }

    public function history(Request $request, string $venueSlug): JsonResponse
    {
        $venue = $this->publishedVenue($venueSlug);

        $data = $request->validate(['guest_token' => ['required', 'uuid']]);

        $conversation = $this->findConversation($venue, $data['guest_token']);

        $messages = $conversation->messages()
            ->whereIn('role', [ConversationMessage::ROLE_GUEST, ConversationMessage::ROLE_ASSISTANT, ConversationMessage::ROLE_SYSTEM])
            ->orderBy('created_at')
            ->get()
            ->map(fn (ConversationMessage $m) => $this->formatMessage($m))
            ->values();

        return response()->json([
            'messages' => $messages,
            'status' => $conversation->status,
        ]);
    }

    private function publishedVenue(string $venueSlug): Venue
    {
        $venue = Venue::where('slug', $venueSlug)->first();

        abort_if(! $venue || ! $venue->isPublished(), 404);

        return $venue;
    }

    private function findConversation(Venue $venue, string $guestToken): Conversation
    {
        $conversation = Conversation::where('venue_id', $venue->id)
            ->where('guest_token', $guestToken)
            ->first();

        if (! $conversation) {
            throw ValidationException::withMessages([
                'guest_token' => 'This conversation no longer exists. Please start a new one.',
            ]);
        }

        return $conversation;
    }

    private function formatMessage(ConversationMessage $message): array
    {
        return [
            'role' => $message->role,
            'content' => $message->content,
            'ui_payload' => $message->ui_payload,
            'suggested_actions' => $this->suggestedActions($message->ui_payload),
            'created_at' => $message->created_at->toIso8601String(),
        ];
    }

    /**
     * Interface actions that follow from what the coordinator just showed, so
     * the organiser can step into the matching scene instead of typing again.
     *
     * @param  list<array<string, mixed>>|null  $uiPayload
     * @return list<array{action: string, space?: string}>
     */
    private function suggestedActions(?array $uiPayload): array
    {
        $actions = [];

        foreach ($uiPayload ?? [] as $payload) {
            $type = $payload['type'] ?? null;
            $space = $payload['space']['space_type_slug'] ?? $payload['quote']['space_type_slug'] ?? null;

            if ($type === 'space_detail' && $space) {
                $actions[] = ['action' => 'view_space', 'space' => $space];
                $actions[] = ['action' => 'reserve', 'space' => $space];
            } elseif ($type === 'availability' && ($payload['available'] ?? false) && $space) {
                $actions[] = ['action' => 'reserve', 'space' => $space];
            } elseif ($type === 'handover') {
                $actions[] = ['action' => 'staff'];
            }
        }

        return collect($actions)->unique(fn (array $action) => $action['action'].($action['space'] ?? ''))->values()->all();
    }
}
