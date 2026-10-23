<?php

namespace App\Services\Admin;

use App\Enums\DatecitoStatus;
use App\Enums\SwipeType;
use App\Models\ActivityEvent;
use App\Models\Block;
use App\Models\Connection;
use App\Models\Datecito;
use App\Models\Message;
use App\Models\Report;
use App\Models\SafeSession;
use App\Models\Swipe;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Números de una persona concreta para su ficha en el CRM.
 */
class UserInsights
{
    /**
     * @return array<string, mixed>
     */
    public function stats(User $user): array
    {
        $given = Swipe::query()->where('user_id', $user->id)->selectRaw('type, COUNT(*) as n')->groupBy('type')->pluck('n', 'type');
        $received = Swipe::query()->where('target_id', $user->id)->selectRaw('type, COUNT(*) as n')->groupBy('type')->pluck('n', 'type');
        $connections = Connection::query()->forUser($user);
        $datecitos = Datecito::query()->whereIn('connection_id', Connection::query()->forUser($user)->select('id'));

        $byType = fn ($counts) => [
            'like' => (int) ($counts[SwipeType::Like->value] ?? 0),
            'pass' => (int) ($counts[SwipeType::Pass->value] ?? 0),
            'super' => (int) ($counts[SwipeType::Super->value] ?? 0),
            'total' => (int) $counts->sum(),
        ];

        $firstEvent = ActivityEvent::query()->where('user_id', $user->id)->min('occurred_at');
        $activeDays = (int) DB::table('activity_events')->where('user_id', $user->id)->selectRaw('COUNT(DISTINCT DATE(occurred_at)) as n')->value('n');

        return [
            'swipes_given' => $byType($given),
            'swipes_received' => $byType($received),
            'connections' => [
                'total' => (clone $connections)->count(),
                'active' => (clone $connections)->active()->count(),
                'with_messages' => (clone $connections)->whereNotNull('last_message_at')->count(),
            ],
            'messages_sent' => Message::query()->where('sender_id', $user->id)->count(),
            'datecitos' => [
                'total' => (clone $datecitos)->count(),
                'proposed_by_user' => Datecito::query()->where('proposer_id', $user->id)->count(),
                'confirmed' => (clone $datecitos)->where('status', DatecitoStatus::Confirmed)->count(),
                'cancelled' => (clone $datecitos)->where('status', DatecitoStatus::Cancelled)->count(),
            ],
            'reports' => [
                'made' => Report::query()->where('reporter_id', $user->id)->count(),
                'received' => Report::query()->where('reported_id', $user->id)->count(),
                'received_open' => Report::query()->where('reported_id', $user->id)->whereIn('status', ['open', 'reviewing'])->count(),
            ],
            'blocks' => [
                'made' => Block::query()->where('blocker_id', $user->id)->count(),
                'received' => Block::query()->where('blocked_id', $user->id)->count(),
            ],
            'safe_sessions' => [
                'total' => SafeSession::query()->where('user_id', $user->id)->count(),
                'help_requested' => SafeSession::query()->where('user_id', $user->id)->whereNotNull('help_requested_at')->count(),
            ],
            'here_sessions_started' => ActivityEvent::query()->where('user_id', $user->id)->where('name', 'here.store')->where(fn (Builder $q) => $q->whereNull('status')->orWhere('status', '<', 400))->count(),
            'trusted_contacts' => $user->trustedContacts()->count(),
            'activity' => [
                'events' => ActivityEvent::query()->where('user_id', $user->id)->count(),
                'events_30d' => ActivityEvent::query()->where('user_id', $user->id)->where('occurred_at', '>=', now()->subDays(30))->count(),
                'active_days' => $activeDays,
                'first_event_at' => $firstEvent === null ? null : now()->parse($firstEvent)->toIso8601String(),
                'sessions_open' => $user->tokens()->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->count(),
            ],
            'account_age_days' => (int) $user->created_at->diffInDays(now()),
        ];
    }
}
