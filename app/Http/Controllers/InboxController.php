<?php

namespace App\Http\Controllers;

use App\Models\InboxConversation;
use App\Models\InboxMediaUpload;
use App\Models\InboxMessage;
use App\Models\InboxQuickReply;
use App\Models\InboxSetting;
use App\Models\User;
use App\Services\Inbox\ConversationWindow;
use App\Services\Inbox\InboxPresenter;
use App\Services\Inbox\OutboundMessageSender;
use App\Services\Inbox\WhatsAppGraphClient;
use App\Support\InboxPhone;
use App\Support\MediaStorage;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InboxController extends Controller
{
    public function __construct(
        private InboxPresenter $presenter,
        private OutboundMessageSender $outbound,
        private ConversationWindow $window,
        private WhatsAppGraphClient $graph,
    ) {}

    public function index(Request $request): Response
    {
        return $this->page($request, null);
    }

    public function show(Request $request, InboxConversation $conversation): Response
    {
        return $this->page($request, $conversation);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
            'name' => ['nullable', 'string', 'max:120'],
        ]);

        $conversation = $this->outbound->startConversation($data['phone'], $data['name'] ?? null);

        return redirect()->route('inbox.show', [
            'conversation' => $conversation->id,
            'compose' => 'template',
            'status' => InboxConversation::STATUS_OPEN,
        ]);
    }

    public function storeMessage(Request $request, InboxConversation $conversation)
    {
        $data = $request->validate([
            'type' => ['nullable', 'in:text,image,document,location,template'],
            'body' => ['nullable', 'string', 'max:4096'],
        ]);

        $this->outbound->send($conversation, $request->user(), [
            'type' => $data['type'] ?? InboxMessage::TYPE_TEXT,
            'body' => $data['body'] ?? '',
        ]);

        return back();
    }

    public function storeTemplate(Request $request, InboxConversation $conversation)
    {
        $data = $request->validate([
            'template_name' => ['required', 'string', 'max:512'],
            'language' => ['required', 'string', 'max:16'],
            'parameters' => ['nullable', 'array'],
            'header_text' => ['nullable', 'string', 'max:255'],
            'header_media' => ['nullable', 'array'],
            'header_media.type' => ['nullable', 'in:image,video,document'],
            'header_media.link' => ['nullable', 'url'],
            'header_media.upload_uuid' => ['nullable', 'uuid'],
            'header_media.filename' => ['nullable', 'string', 'max:255'],
        ]);

        $headerMedia = $data['header_media'] ?? null;

        if (is_array($headerMedia) && filled($headerMedia['upload_uuid'] ?? null)) {
            $upload = InboxMediaUpload::query()->findOrFail($headerMedia['upload_uuid']);
            $binary = $upload->contents();

            if ($binary) {
                $id = $this->graph->uploadMedia(
                    $binary,
                    (string) $upload->mime,
                    $upload->original_name ?: 'file'
                );
                $headerMedia['id'] = $id;
                $headerMedia['link'] = $upload->publicUrl();
                $upload->touchUsed();
            }
        }

        $this->outbound->send($conversation, $request->user(), [
            'type' => InboxMessage::TYPE_TEMPLATE,
            'template_name' => $data['template_name'],
            'language' => $data['language'],
            'parameters' => $data['parameters'] ?? [],
            'header_text' => $data['header_text'] ?? null,
            'header_media' => $headerMedia,
        ]);

        return back();
    }

    public function updateStatus(Request $request, InboxConversation $conversation)
    {
        $data = $request->validate([
            'status' => ['required', 'in:open,pending,closed'],
        ]);

        $conversation->status = $data['status'];

        if ($data['status'] === InboxConversation::STATUS_CLOSED) {
            $conversation->needs_human_agent = false;
            $conversation->handoff_reason = null;
            $conversation->bot_paused_until = null;
        }

        $conversation->save();

        return back();
    }

    public function updateHandoff(Request $request, InboxConversation $conversation)
    {
        $data = $request->validate([
            'action' => ['required', 'in:needs_human,resume'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        if ($data['action'] === 'needs_human') {
            $conversation->requestHuman($data['reason'] ?: 'manual');
        } else {
            $conversation->resumeBot();
        }

        return back();
    }

    public function assign(Request $request, InboxConversation $conversation)
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $userId = $data['user_id'] ?? $request->user()->id;
        $agent = User::query()->findOrFail($userId);

        if (! $agent->hasAnyRole(User::ROLE_ADMIN, User::ROLE_GENERAL_MANAGER, User::ROLE_MANAGER)) {
            throw ValidationException::withMessages(['user_id' => 'لا يمكن تعيين هذا المستخدم.']);
        }

        $conversation->assigned_user_id = $agent->id;
        $conversation->save();

        return back();
    }

    public function unassign(Request $request, InboxConversation $conversation)
    {
        if ((int) $conversation->assigned_user_id !== (int) $request->user()->id) {
            abort(403);
        }

        $conversation->assigned_user_id = null;
        $conversation->save();

        return back();
    }

    public function templates()
    {
        return response()->json($this->graph->approvedTemplates());
    }

    public function bookings(InboxConversation $conversation)
    {
        return response()->json([
            'bookings' => $this->presenter->bookingsFor($conversation),
        ]);
    }

    public function storeQuickReply(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:2000'],
        ]);

        InboxQuickReply::query()->create($data);

        return back();
    }

    public function destroyQuickReply(InboxQuickReply $quickReply)
    {
        $quickReply->delete();

        return back();
    }

    public function storeMedia(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:16384'],
        ]);

        $file = $request->file('file');
        $path = MediaStorage::store($file, 'inbox/uploads');

        $upload = InboxMediaUpload::query()->create([
            'disk' => MediaStorage::DISK,
            'path' => $path,
            'mime' => $file->getMimeType(),
            'size' => $file->getSize() ?: 0,
            'original_name' => $file->getClientOriginalName(),
            'last_used_at' => now(),
        ]);

        return response()->json([
            'uuid' => $upload->uuid,
            'url' => $upload->publicUrl(),
            'mime' => $upload->mime,
            'name' => $upload->original_name,
        ]);
    }

    public function recentMedia()
    {
        $items = InboxMediaUpload::query()
            ->latest('last_used_at')
            ->latest('id')
            ->limit(24)
            ->get()
            ->map(fn (InboxMediaUpload $upload) => [
                'uuid' => $upload->uuid,
                'url' => $upload->publicUrl(),
                'mime' => $upload->mime,
                'name' => $upload->original_name,
            ]);

        return response()->json(['data' => $items]);
    }

    public function media(string $uuid)
    {
        $upload = InboxMediaUpload::query()->findOrFail($uuid);
        $upload->touchUsed();

        $contents = $upload->contents();

        if ($contents === null) {
            $url = $upload->storageUrl();
            if ($url) {
                return redirect()->away($url);
            }

            abort(404);
        }

        return response($contents, 200, [
            'Content-Type' => $upload->mime ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.($upload->original_name ?: $upload->uuid).'"',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    private function page(Request $request, ?InboxConversation $selected): Response
    {
        $user = $request->user();
        $status = $request->string('status')->toString();
        if (! in_array($status, ['open', 'pending', 'closed'], true)) {
            $status = InboxConversation::STATUS_OPEN;
        }

        $filter = $request->string('filter')->toString();
        if (! in_array($filter, ['all', 'mine', 'unassigned', 'needs_human'], true)) {
            $filter = 'all';
        }

        $query = InboxConversation::query()
            ->with(['contact', 'assignee'])
            ->where('status', $status)
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

        if ($filter === 'mine') {
            $query->where('assigned_user_id', $user->id);
        } elseif ($filter === 'unassigned') {
            $query->whereNull('assigned_user_id');
        } elseif ($filter === 'needs_human') {
            $query->where('needs_human_agent', true);
        }

        $conversations = $query->paginate(20)->withQueryString();

        $messages = [];
        $window = null;
        $selectedPayload = null;

        if ($selected) {
            $selectedPayload = $this->presenter->conversation($selected, $user);
            $window = $this->window->for($selected);
            $messages = $selected->messages()
                ->orderBy('id')
                ->get()
                ->map(fn (InboxMessage $message) => $this->presenter->message($message))
                ->values();
        }

        $settings = InboxSetting::current();

        return Inertia::render('Inbox/Index', [
            'conversations' => $this->presenter->paginated($conversations, $user),
            'counts' => $this->presenter->counts($user),
            'filters' => [
                'status' => $status,
                'filter' => $filter,
            ],
            'selected' => $selectedPayload,
            'messages' => $messages,
            'window' => $window,
            'agents' => $this->presenter->agents(),
            'quickReplies' => InboxQuickReply::query()->latest('id')->get(['id', 'title', 'body']),
            'compose' => $request->string('compose')->toString() === 'template' ? 'template' : 'freeform',
            'ai' => [
                'enabled' => $settings->botIsEnabled(),
                'paused_minutes' => $settings->whatsapp_ai_bot_pause_minutes,
            ],
            'display_phone' => InboxPhone::display((string) config('services.whatsapp.business_phone')),
        ]);
    }
}
