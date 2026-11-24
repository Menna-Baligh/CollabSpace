<?php

namespace App\Http\Controllers\Api\V1\Meetings;

use App\Enums\MeetingParticipantStatus;
use App\Enums\MeetingRecordingStatus;
use App\Events\MeetingCommentCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Meetings\InviteParticipantsRequest;
use App\Http\Requests\Meetings\RsvpMeetingRequest;
use App\Http\Requests\Meetings\StoreMeetingCommentRequest;
use App\Http\Requests\Meetings\StoreMeetingNoteRequest;
use App\Http\Requests\Meetings\StoreMeetingRecordingRequest;
use App\Http\Requests\Meetings\StoreMeetingRequest;
use App\Http\Requests\Meetings\UpdateMeetingRequest;
use App\Http\Resources\MeetingCommentResource;
use App\Http\Resources\MeetingNoteResource;
use App\Http\Resources\MeetingParticipantResource;
use App\Http\Resources\MeetingRecordingResource;
use App\Http\Resources\MeetingResource;
use App\Http\Resources\MeetingSummaryResource;
use App\Http\Resources\MeetingTranscriptResource;
use App\Jobs\SummarizeMeetingTranscriptJob;
use App\Jobs\TranscribeMeetingRecordingJob;
use App\Models\Meeting;
use App\Models\MeetingComment;
use App\Models\MeetingRecording;
use App\Models\Project;
use App\Models\User;
use App\Services\MeetingService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class MeetingController extends Controller
{
    use ApiResponse;

    public function __construct(protected MeetingService $meetingService) {}

    /**
     * List user meetings.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 15);
        $paginator = $this->meetingService->listMeetings(
            $request->user(),
            $request->only(['project_id', 'status', 'date', 'view']),
            $perPage
        );

        return $this->successResponse(
            MeetingResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    /**
     * Schedule a new meeting.
     */
    public function store(StoreMeetingRequest $request): JsonResponse
    {
        if ($projectId = $request->validated('project_id')) {
            $project = Project::findOrFail($projectId);
            if (! $request->user()->isAdmin() && ! $request->user()->isMemberOfProject($project)) {
                return $this->failureResponse(__('meetings.unauthorized_project_meeting'), 'forbidden', status: Response::HTTP_FORBIDDEN);
            }
        }

        $meeting = $this->meetingService->createMeeting($request->user(), $request->validated());

        return $this->successResponse(
            new MeetingResource($meeting),
            __('meetings.meeting_created'),
            status: Response::HTTP_CREATED
        );
    }

    /**
     * Show meeting details.
     */
    public function show(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('view', $meeting);

        $meeting->load(['project', 'creator', 'participants']);

        return $this->successResponse(new MeetingResource($meeting));
    }

    /**
     * Update meeting.
     */
    public function update(UpdateMeetingRequest $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('update', $meeting);

        $meeting = $this->meetingService->updateMeeting($meeting, $request->validated());

        return $this->successResponse(new MeetingResource($meeting), __('meetings.meeting_updated'));
    }

    /**
     * Cancel meeting.
     */
    public function cancel(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('update', $meeting);

        $meeting = $this->meetingService->cancelMeeting($meeting);

        return $this->successResponse(new MeetingResource($meeting), __('meetings.meeting_cancelled'));
    }

    /**
     * Delete meeting.
     */
    public function destroy(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('delete', $meeting);

        $meeting->delete();

        return $this->successResponse(null, __('meetings.meeting_deleted'));
    }

    /**
     * Invite participants.
     */
    public function invite(InviteParticipantsRequest $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('update', $meeting);

        $participantIds = $request->validated('participant_ids');
        $meeting->participants()->syncWithoutDetaching(array_fill_keys($participantIds, [
            'status' => MeetingParticipantStatus::INVITED->value,
        ]));

        return $this->successResponse(
            MeetingParticipantResource::collection($meeting->load('participants')->participants),
            __('meetings.participants_invited')
        );
    }

    /**
     * RSVP to meeting invitation.
     */
    public function rsvp(RsvpMeetingRequest $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('view', $meeting);

        $status = MeetingParticipantStatus::from($request->validated('status'));
        $this->meetingService->updateRsvp($meeting, $request->user(), $status);

        return $this->successResponse(null, __('meetings.rsvp_updated'));
    }

    /**
     * Record attendance (join / leave).
     */
    public function attendance(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('view', $meeting);

        $action = $request->input('action', 'join');
        $this->meetingService->recordAttendance($meeting, $request->user(), $action);

        return $this->successResponse(null, __('meetings.attendance_recorded'));
    }

    /**
     * Get join URL for meeting.
     */
    public function join(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('view', $meeting);

        $url = $this->meetingService->getJoinUrl($meeting, $request->user());

        return $this->successResponse([
            'meeting' => new MeetingResource($meeting->load(['project', 'creator', 'participants'])),
            'join_url' => $url,
        ]);
    }

    /**
     * Upload meeting recording.
     */
    public function storeRecording(StoreMeetingRecordingRequest $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('update', $meeting);

        $file = $request->file('recording');
        $disk = config('filesystems.default', 'local');
        $path = $file->store('meeting_recordings', $disk);

        try {
            $recording = MeetingRecording::create([
                'meeting_id' => $meeting->id,
                'storage_path' => $path,
                'disk' => $disk,
                'duration_seconds' => $request->integer('duration_seconds') ?: null,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType() ?: 'video/mp4',
            ]);
        } catch (Throwable $e) {
            Storage::disk($disk)->delete($path);
            throw $e;
        }

        $meeting->update(['recording_status' => MeetingRecordingStatus::AVAILABLE]);

        // Automatically dispatch transcription
        TranscribeMeetingRecordingJob::dispatch($recording);

        return $this->successResponse(
            new MeetingRecordingResource($recording),
            __('meetings.recording_uploaded'),
            status: Response::HTTP_CREATED
        );
    }

    /**
     * Download recording securely.
     */
    public function downloadRecording(Request $request, Meeting $meeting, MeetingRecording $recording)
    {
        $this->authorize('view', $meeting);

        $disk = Storage::disk($recording->disk);
        if (! $disk->exists($recording->storage_path)) {
            return $this->failureResponse('Recording file not found.', 'not_found', status: Response::HTTP_NOT_FOUND);
        }

        return $disk->download($recording->storage_path, "meeting_{$meeting->id}_recording.mp4");
    }

    /**
     * List meeting notes.
     */
    public function notes(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('view', $meeting);

        $notes = $meeting->notes()->with('user:id,name,avatar')->latest('id')->get();

        return $this->successResponse(MeetingNoteResource::collection($notes));
    }

    /**
     * Add meeting note.
     */
    public function storeNote(StoreMeetingNoteRequest $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('view', $meeting);

        $note = $meeting->notes()->create([
            'user_id' => $request->user()->id,
            'content' => $request->validated('content'),
        ]);

        return $this->successResponse(
            new MeetingNoteResource($note->load('user:id,name,avatar')),
            __('meetings.note_added'),
            status: Response::HTTP_CREATED
        );
    }

    /**
     * List meeting comments.
     */
    public function comments(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('view', $meeting);

        $comments = $meeting->comments()
            ->with('user:id,name,avatar')
            ->orderBy('id', 'asc')
            ->paginate(min($request->integer('per_page', 25), 100));

        return $this->successResponse(
            MeetingCommentResource::collection($comments->items()),
            meta: [
                'current_page' => $comments->currentPage(),
                'last_page' => $comments->lastPage(),
                'per_page' => $comments->perPage(),
                'total' => $comments->total(),
            ]
        );
    }

    /**
     * Add comment to meeting.
     */
    public function storeComment(StoreMeetingCommentRequest $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('view', $meeting);

        $comment = $meeting->comments()->create([
            'user_id' => $request->user()->id,
            'comment' => $request->validated('comment'),
        ]);

        $comment->load('user:id,name,avatar');

        try {
            broadcast(new MeetingCommentCreated($comment))->toOthers();
        } catch (Throwable) {
        }

        return $this->successResponse(
            new MeetingCommentResource($comment),
            __('meetings.comment_added'),
            status: Response::HTTP_CREATED
        );
    }

    /**
     * Update meeting comment.
     */
    public function updateComment(StoreMeetingCommentRequest $request, Meeting $meeting, MeetingComment $comment): JsonResponse
    {
        $this->authorize('update', $comment);

        $comment->update([
            'comment' => $request->validated('comment'),
        ]);

        return $this->successResponse(
            new MeetingCommentResource($comment->load('user:id,name,avatar')),
            __('meetings.comment_updated')
        );
    }

    /**
     * Delete meeting comment.
     */
    public function deleteComment(Request $request, Meeting $meeting, MeetingComment $comment): JsonResponse
    {
        $this->authorize('delete', $comment);

        $comment->delete();

        return $this->successResponse(null, __('meetings.comment_deleted'));
    }

    /**
     * Request or retry transcription.
     */
    public function transcribe(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('update', $meeting);

        $recording = $meeting->recordings()->latest('id')->first();
        if (! $recording) {
            return $this->failureResponse('No recording available for this meeting to transcribe.', 'bad_request');
        }

        TranscribeMeetingRecordingJob::dispatch($recording);

        return $this->successResponse(null, __('meetings.transcription_requested'));
    }

    /**
     * Get meeting transcript.
     */
    public function transcript(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('view', $meeting);

        $transcript = $meeting->transcript()->with(['segments.speaker:id,name,avatar'])->first();

        if (! $transcript) {
            return $this->failureResponse('Transcript not available yet.', 'not_found', status: Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse(new MeetingTranscriptResource($transcript));
    }

    /**
     * Request or retry AI summary.
     */
    public function summarize(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('update', $meeting);

        $transcript = $meeting->transcript;
        if (! $transcript || ! $transcript->full_text) {
            return $this->failureResponse('Transcript required before generating AI summary.', 'bad_request');
        }

        SummarizeMeetingTranscriptJob::dispatch($transcript);

        return $this->successResponse(null, __('meetings.summary_requested'));
    }

    /**
     * Get AI summary.
     */
    public function summary(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('view', $meeting);

        $summary = $meeting->summary;
        if (! $summary) {
            return $this->failureResponse('Summary not available yet.', 'not_found', status: Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse(new MeetingSummaryResource($summary));
    }
}
