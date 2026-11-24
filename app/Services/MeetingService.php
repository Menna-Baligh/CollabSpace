<?php

namespace App\Services;

use App\Enums\MeetingParticipantStatus;
use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MeetingService
{
    public function __construct(protected JitsiService $jitsiService) {}

    /**
     * List meetings for the current user with various filters.
     */
    public function listMeetings(User $user, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Meeting::query()
            ->forUser($user)
            ->with(['project:id,name', 'creator:id,name,avatar', 'participants'])
            ->orderBy('start_time', 'asc');

        if (! empty($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['date'])) {
            $date = Carbon::parse($filters['date'])->format('Y-m-d');
            $query->whereDate('start_time', $date);
        }

        if (! empty($filters['view'])) {
            $baseDate = ! empty($filters['date']) ? Carbon::parse($filters['date']) : now();
            if ($filters['view'] === 'week') {
                $query->whereBetween('start_time', [$baseDate->copy()->startOfWeek(), $baseDate->copy()->endOfWeek()]);
            } elseif ($filters['view'] === 'month') {
                $query->whereBetween('start_time', [$baseDate->copy()->startOfMonth(), $baseDate->copy()->endOfMonth()]);
            }
        }

        return $query->paginate(min($perPage, 100));
    }

    /**
     * Create meeting.
     */
    public function createMeeting(User $creator, array $data): Meeting
    {
        $startTime = Carbon::parse($data['start_time']);
        $endTime = Carbon::parse($data['end_time']);

        if ($endTime->lte($startTime)) {
            throw new InvalidArgumentException('End time must be after start time.');
        }

        $roomName = $this->jitsiService->generateRoomName('meet_'.($data['project_id'] ?? 'general'));

        return DB::transaction(function () use ($creator, $data, $startTime, $endTime, $roomName) {
            $meeting = Meeting::create([
                'project_id' => $data['project_id'] ?? null,
                'creator_id' => $creator->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'timezone' => $data['timezone'] ?? 'UTC',
                'status' => MeetingStatus::SCHEDULED,
                'provider' => 'jitsi',
                'room_name' => $roomName,
            ]);

            // Add creator as accepted participant
            $meeting->participants()->attach($creator->id, [
                'status' => MeetingParticipantStatus::ACCEPTED->value,
            ]);

            // Add invited participants
            if (! empty($data['participant_ids'])) {
                $participantIds = array_diff($data['participant_ids'], [$creator->id]);
                if (! empty($participantIds)) {
                    $meeting->participants()->attach($participantIds, [
                        'status' => MeetingParticipantStatus::INVITED->value,
                    ]);
                }
            }

            return $meeting->load(['project', 'creator', 'participants']);
        });
    }

    /**
     * Update meeting details.
     */
    public function updateMeeting(Meeting $meeting, array $data): Meeting
    {
        if (isset($data['start_time']) || isset($data['end_time'])) {
            $startTime = isset($data['start_time']) ? Carbon::parse($data['start_time']) : $meeting->start_time;
            $endTime = isset($data['end_time']) ? Carbon::parse($data['end_time']) : $meeting->end_time;

            if ($endTime->lte($startTime)) {
                throw new InvalidArgumentException('End time must be after start time.');
            }

            $data['start_time'] = $startTime;
            $data['end_time'] = $endTime;
        }

        $meeting->update($data);

        return $meeting->load(['project', 'creator', 'participants']);
    }

    /**
     * Cancel meeting.
     */
    public function cancelMeeting(Meeting $meeting): Meeting
    {
        $meeting->update(['status' => MeetingStatus::CANCELLED]);

        return $meeting;
    }

    /**
     * Generate join URL for user.
     */
    public function getJoinUrl(Meeting $meeting, User $user): string
    {
        return $this->jitsiService->buildJoinUrl($meeting->room_name, $user, true);
    }

    /**
     * Update user RSVP status.
     */
    public function updateRsvp(Meeting $meeting, User $user, MeetingParticipantStatus $status): void
    {
        $meeting->participants()->syncWithoutDetaching([
            $user->id => [
                'status' => $status->value,
            ],
        ]);
    }

    /**
     * Record attendance timestamps.
     */
    public function recordAttendance(Meeting $meeting, User $user, string $action): void
    {
        $data = ['has_attended' => true];
        if ($action === 'join') {
            $data['joined_at'] = now();
            if ($meeting->status === MeetingStatus::SCHEDULED) {
                $meeting->update(['status' => MeetingStatus::LIVE]);
            }
        } elseif ($action === 'leave') {
            $data['left_at'] = now();
        }

        $meeting->participants()->syncWithoutDetaching([
            $user->id => $data,
        ]);
    }

    /**
     * Check if user is authorized to access meeting.
     */
    public function canAccess(User $user, Meeting $meeting): bool
    {
        if ($user->isAdmin() || $meeting->creator_id === $user->id) {
            return true;
        }

        if ($meeting->project_id && $user->isMemberOfProject($meeting->project_id)) {
            return true;
        }

        return $meeting->participants()->where('user_id', $user->id)->exists();
    }
}
