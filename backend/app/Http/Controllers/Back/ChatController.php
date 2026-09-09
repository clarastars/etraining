<?php

declare(strict_types=1);

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Models\Back\Company;
use App\Models\Back\Invoice;
use App\Models\Back\Trainee;
use App\Models\Back\TraineeBlockList;
use App\Models\Back\WhatsAppBotSender;
use App\Models\Back\WhatsAppConversation;
use App\Models\Back\WhatsAppMessage;
use App\Models\Back\WhatsAppQuickReply;
use App\Models\Back\WhatsAppTag;
use App\Models\GosiEmployeeData;
use App\Models\User;
use App\Services\MaqsamService;
use App\Services\TelnyxWhatsAppService;
use App\Support\WhatsAppBroadcast;
use App\Support\WhatsAppAiSettings;
use App\Support\WhatsAppBotPause;
use App\Support\WhatsAppBotStatus;
use App\Support\WhatsAppConversationHandoff;
use App\Support\WhatsAppConversationSync;
use App\Support\WhatsAppConversationTrainee;
use App\Support\WhatsAppMessagingWindow;
use App\Support\WhatsAppTraineeLinker;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ChatController extends Controller
{
    private const CONVERSATIONS_PER_PAGE = 20;

    private const MESSAGES_PER_PAGE = 50;

    public function __construct(
        private readonly TelnyxWhatsAppService $whatsAppService,
        private readonly MaqsamService $maqsamService,
    ) {
        $this->middleware('can:access-whatsapp-chats');
    }

    public function index(): Response
    {
        return Inertia::render('Back/Chat/Index', [
            'configured' => $this->whatsAppService->isConfigured(),
            'canManageTemplates' => $this->whatsAppService->canManageTemplates(),
            'maqsamConfigured' => $this->maqsamService->isConfigured(),
            'maqsamAgentEmail' => auth()->user()->email,
        ]);
    }

    public function conversations(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => 'nullable|integer|min:1',
            'q' => 'nullable|string|max:200',
            'mine' => 'nullable|boolean',
            'unassigned' => 'nullable|boolean',
            'tag_id' => 'nullable|uuid|exists:whatsapp_tags,id',
            'agent_id' => 'nullable|uuid|exists:users,id',
            'status' => 'nullable|string|in:open,pending,closed',
            'company_ids' => 'nullable|array',
            'company_ids.*' => 'uuid',
        ]);

        $status = $validated['status'] ?? WhatsAppConversation::STATUS_OPEN;

        $query = WhatsAppConversation::query()
            ->with([
                'agents:id,name',
                'tags:id,name,color',
                'trainee' => WhatsAppConversationTrainee::eagerLoadConstraint(),
                'trainee.company' => $this->companyRelationConstraint(),
            ])
            ->where('status', $status)
            ->orderByDesc('last_message_at')
            ->orderByDesc('updated_at');

        if (! empty($validated['q'])) {
            $search = $validated['q'];
            $query->where(function ($builder) use ($search) {
                $builder->where('phone', 'LIKE', '%' . $search . '%')
                    ->orWhereHas('trainee', function ($traineeQuery) use ($search) {
                        $traineeQuery->withTrashed()
                            ->where(function ($inner) use ($search) {
                                $inner->where('name', 'LIKE', '%' . $search . '%')
                                    ->orWhere('phone', 'LIKE', '%' . $search . '%')
                                    ->orWhere('identity_number', 'LIKE', '%' . $search . '%');
                            });
                    });
            });
        }

        if (! empty($validated['mine'])) {
            $query->whereHas('agents', function ($agentsQuery) {
                $agentsQuery->where('users.id', auth()->id());
            });
        }

        if (! empty($validated['unassigned'])) {
            $query->whereDoesntHave('agents');
        }

        if (! empty($validated['tag_id'])) {
            $query->whereHas('tags', function ($tagsQuery) use ($validated) {
                $tagsQuery->where('whatsapp_tags.id', $validated['tag_id']);
            });
        }

        if (! empty($validated['agent_id'])) {
            $query->whereHas('agents', function ($agentsQuery) use ($validated) {
                $agentsQuery->where('users.id', $validated['agent_id']);
            });
        }

        if (! empty($validated['company_ids'])) {
            $companyIds = array_values(array_unique($validated['company_ids']));
            $query->whereHas('trainee', function ($traineeQuery) use ($companyIds) {
                $traineeQuery->withTrashed()->whereIn('company_id', $companyIds);
            });
        }

        $paginator = $query->paginate(self::CONVERSATIONS_PER_PAGE);
        $authId = auth()->id();

        $traineeIds = $paginator->getCollection()
            ->pluck('trainee_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
        $unpaidCounts = Invoice::unpaidCountsByTraineeIds($traineeIds);

        $paginator->getCollection()->transform(function (WhatsAppConversation $conversation) use ($authId, $unpaidCounts) {
            $traineeId = $conversation->trainee_id ? (string) $conversation->trainee_id : null;
            $unpaidCount = $traineeId
                ? (int) ($unpaidCounts[$traineeId] ?? 0)
                : 0;

            return $this->formatConversation($conversation, $authId, $unpaidCount);
        });

        $payload = $paginator->toArray();
        $payload['counts'] = $this->conversationCounts();
        $payload['tag_counts'] = $this->tagConversationCounts();
        $payload['bot_configured'] = $this->isWhatsAppBotConfigured();

        return response()->json($payload);
    }

    public function assignableAgents(): JsonResponse
    {
        $auth = auth()->user();
        $roleIds = $auth->roles()->pluck('id');

        if ($roleIds->isEmpty()) {
            return response()->json(['agents' => []]);
        }

        $agents = User::query()
            ->permission('access-whatsapp-chats')
            ->where('id', '!=', $auth->id)
            ->whereHas('roles', function ($query) use ($roleIds) {
                $query->whereIn('roles.id', $roleIds);
            })
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
            ])
            ->values()
            ->all();

        return response()->json(['agents' => $agents]);
    }

    public function assignAgent(Request $request, WhatsAppConversation $conversation): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'uuid', 'exists:users,id'],
        ]);

        $authId = auth()->id();
        $userId = $validated['user_id'] ?? $authId;

        if ($userId !== $authId) {
            $this->assertAssignablePeer((string) $userId);
        }

        if (! $conversation->agents()->where('users.id', $userId)->exists()) {
            $conversation->agents()->attach($userId, [
                'assigned_at' => now(),
            ]);
        }

        $conversation->load([
            'agents:id,name',
            'tags:id,name,color',
            'trainee.company' => $this->companyRelationConstraint(),
        ]);

        WhatsAppConversationSync::broadcast($conversation);

        return response()->json([
            'conversation' => $this->formatConversation($conversation, $authId),
        ]);
    }

    public function unassignAgent(WhatsAppConversation $conversation): JsonResponse
    {
        $userId = auth()->id();
        $conversation->agents()->detach($userId);

        $conversation->load([
            'agents:id,name',
            'tags:id,name,color',
            'trainee.company' => $this->companyRelationConstraint(),
        ]);

        WhatsAppConversationSync::broadcast($conversation);

        return response()->json([
            'conversation' => $this->formatConversation($conversation, $userId),
        ]);
    }

    public function updateStatus(Request $request, WhatsAppConversation $conversation): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:open,pending,closed',
        ]);

        $conversation->update([
            'status' => $validated['status'],
        ]);

        $conversation->load([
            'agents:id,name',
            'tags:id,name,color',
            'trainee.company' => $this->companyRelationConstraint(),
        ]);

        WhatsAppConversationSync::broadcast($conversation);

        return response()->json([
            'conversation' => $this->formatConversation($conversation, auth()->id()),
        ]);
    }

    public function markConversationRead(WhatsAppConversation $conversation): JsonResponse
    {
        if ($conversation->has_unread) {
            $conversation->update(['has_unread' => false]);

            $conversation->load([
                'agents:id,name',
                'tags:id,name,color',
                'trainee.company' => $this->companyRelationConstraint(),
            ]);

            WhatsAppConversationSync::broadcast($conversation);
        } else {
            $conversation->load([
                'agents:id,name',
                'tags:id,name,color',
                'trainee.company' => $this->companyRelationConstraint(),
            ]);
        }

        return response()->json([
            'conversation' => $this->formatConversation($conversation, auth()->id()),
        ]);
    }

    public function tags(): JsonResponse
    {
        WhatsAppConversationHandoff::ensureNeedHumanAgentTag();

        $tags = WhatsAppTag::query()
            ->orderBy('name')
            ->get(['id', 'name', 'color']);

        $counts = $this->tagConversationCounts($tags->pluck('id')->all());

        return response()->json([
            'tags' => $tags->map(static function (WhatsAppTag $tag) use ($counts) {
                return [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'color' => $tag->color,
                    'conversation_count' => (int) ($counts[$tag->id] ?? 0),
                ];
            })->values(),
        ]);
    }

    public function storeTag(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:60',
            'color' => 'nullable|string|max:32',
        ]);

        $name = trim($validated['name']);

        $tag = WhatsAppTag::query()->firstOrCreate(
            ['name' => $name],
            ['color' => $validated['color'] ?? null]
        );

        return response()->json([
            'tag' => [
                'id' => $tag->id,
                'name' => $tag->name,
                'color' => $tag->color,
                'conversation_count' => 0,
            ],
        ]);
    }

    public function attachTag(Request $request, WhatsAppConversation $conversation): JsonResponse
    {
        $validated = $request->validate([
            'tag_id' => 'nullable|uuid|exists:whatsapp_tags,id',
            'name' => 'nullable|string|max:60',
            'color' => 'nullable|string|max:32',
        ]);

        if (empty($validated['tag_id']) && empty($validated['name'])) {
            return response()->json([
                'message' => 'A tag id or name is required.',
            ], 422);
        }

        if (! empty($validated['tag_id'])) {
            $tag = WhatsAppTag::query()->findOrFail($validated['tag_id']);
        } else {
            $tag = WhatsAppTag::query()->firstOrCreate(
                ['name' => trim($validated['name'])],
                ['color' => $validated['color'] ?? null]
            );
        }

        $conversation->tags()->syncWithoutDetaching([$tag->id]);

        $conversation->load([
            'agents:id,name',
            'tags:id,name,color',
            'trainee.company' => $this->companyRelationConstraint(),
        ]);

        WhatsAppConversationSync::broadcast($conversation);

        return response()->json([
            'conversation' => $this->formatConversation($conversation, auth()->id()),
            'tag' => [
                'id' => $tag->id,
                'name' => $tag->name,
                'color' => $tag->color,
                'conversation_count' => 0,
            ],
        ]);
    }

    public function detachTag(WhatsAppConversation $conversation, WhatsAppTag $tag): JsonResponse
    {
        $conversation->tags()->detach($tag->id);

        $conversation->load([
            'agents:id,name',
            'tags:id,name,color',
            'trainee.company' => $this->companyRelationConstraint(),
        ]);

        WhatsAppConversationSync::broadcast($conversation);

        return response()->json([
            'conversation' => $this->formatConversation($conversation, auth()->id()),
        ]);
    }

    public function messages(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => 'required|string|max:30',
            'limit' => 'nullable|integer|min:1|max:100',
            'before' => 'nullable|date',
            'before_id' => 'nullable|uuid',
        ]);

        $phone = $this->whatsAppService->normalizePhoneDigits($validated['phone']);
        $phoneDigits = preg_replace('/\D+/', '', $phone) ?? '';
        $limit = (int) ($validated['limit'] ?? self::MESSAGES_PER_PAGE);

        $query = WhatsAppMessage::query()
            ->where(function ($builder) use ($phone, $phoneDigits) {
                $builder->where('phone', $phone);
                if ($phoneDigits !== '' && $phoneDigits !== $phone) {
                    $builder->orWhere('phone', $phoneDigits);
                }
            })
            ->with(['user:id,name', 'media']);

        if (! empty($validated['before'])) {
            $before = $validated['before'];
            $beforeId = $validated['before_id'] ?? null;

            $query->where(function ($builder) use ($before, $beforeId) {
                $builder->where('sent_at', '<', $before);
                if ($beforeId) {
                    $builder->orWhere(function ($sameSecond) use ($before, $beforeId) {
                        $sameSecond->where('sent_at', '=', $before)
                            ->where('id', '<', $beforeId);
                    });
                }
            });
        }

        $messages = $query
            ->orderByDesc('sent_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit + 1)
            ->get();

        $hasMore = $messages->count() > $limit;
        if ($hasMore) {
            $messages = $messages->take($limit);
        }

        $formatted = $messages
            ->reverse()
            ->values()
            ->map(fn (WhatsAppMessage $msg) => $this->formatMessage($msg));

        $oldest = $formatted->first();

        return response()->json([
            'messages' => $formatted,
            'has_more' => $hasMore,
            'next_before' => $oldest['date_sent'] ?? null,
            'next_before_id' => $oldest['id'] ?? null,
        ]);
    }

    public function botStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => 'required|string|max:30',
        ]);

        return response()->json(
            WhatsAppBotStatus::forPhone($validated['phone'])
        );
    }

    public function pauseBot(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => 'required|string|max:30',
            'minutes' => 'nullable|integer|min:1|max:1440',
        ]);

        $minutes = (int) ($validated['minutes'] ?? WhatsAppBotStatus::configuredPauseMinutes());
        WhatsAppBotPause::pauseForAgent($validated['phone'], $minutes);

        return response()->json([
            'message' => WhatsAppBotStatus::pauseSuccessMessage($minutes),
            'bot' => WhatsAppBotStatus::forPhone($validated['phone']),
        ]);
    }

    public function resumeBot(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => 'required|string|max:30',
        ]);

        WhatsAppBotPause::resume($validated['phone']);

        return response()->json([
            'message' => __('words.whatsapp-bot-resumed'),
            'bot' => WhatsAppBotStatus::forPhone($validated['phone']),
        ]);
    }

    public function searchTrainees(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => 'required|string|max:200',
            'page' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:50',
            'company_id' => 'nullable|uuid|exists:companies,id',
        ]);

        $search = $validated['search'];
        $page = (int) ($validated['page'] ?? 1);
        $limit = (int) ($validated['limit'] ?? 10);

        $query = Trainee::query()
            ->where(function ($builder) use ($search) {
                $builder->where('name', 'LIKE', '%' . $search . '%')
                    ->orWhere('phone', 'LIKE', '%' . $search . '%')
                    ->orWhere('identity_number', 'LIKE', '%' . $search . '%');
            })
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->with(['company' => $this->companyRelationConstraint()]);

        if (! empty($validated['company_id'])) {
            $query->where('company_id', $validated['company_id']);
        }

        $total = (clone $query)->count();
        $trainees = $query
            ->orderBy('name')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get(['id', 'name', 'phone', 'identity_number', 'company_id']);

        return response()->json([
            'trainees' => $trainees->map(fn (Trainee $trainee) => [
                'id' => $trainee->id,
                'name' => $trainee->name,
                'phone' => $trainee->phone,
                'identity_number' => $trainee->identity_number,
                'company_name' => $this->companyDisplayName(
                    $trainee->company ?: $this->resolveCompanyForTrainee($trainee)
                ),
                'show_url' => route('back.trainees.show', $trainee->id),
            ])->values(),
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'has_more' => ($page * $limit) < $total,
        ]);
    }

    public function companyFilters(): JsonResponse
    {
        $companyIds = auth()->user()
            ->chatCompanyFilters()
            ->allRelatedIds()
            ->all();

        $companies = Company::withoutGlobalScopes()
            ->whereIn('id', $companyIds)
            ->orderBy('name_ar')
            ->get(['id', 'name_ar', 'name_en'])
            ->map(fn (Company $company) => [
                'id' => $company->id,
                'name' => $this->companyDisplayName($company),
            ])
            ->values()
            ->all();

        return response()->json(['companies' => $companies]);
    }

    public function updateCompanyFilters(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_ids' => 'nullable|array',
            'company_ids.*' => 'uuid',
        ]);

        $requestedIds = array_values(array_unique($validated['company_ids'] ?? []));

        if ($requestedIds !== []) {
            $existingIds = Company::withoutGlobalScopes()
                ->whereIn('id', $requestedIds)
                ->pluck('id')
                ->map(static fn ($id) => (string) $id)
                ->all();

            $missing = array_diff($requestedIds, $existingIds);
            if ($missing !== []) {
                throw ValidationException::withMessages([
                    'company_ids' => [__('validation.exists', ['attribute' => 'company_ids'])],
                ]);
            }

            $requestedIds = $existingIds;
        }

        auth()->user()->chatCompanyFilters()->sync($requestedIds);

        return $this->companyFilters();
    }

    public function clearCompanyFilters(): JsonResponse
    {
        auth()->user()->chatCompanyFilters()->detach();

        return response()->json(['companies' => []]);
    }

    public function searchCompanies(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => 'required|string|max:200',
            'page' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $search = $validated['search'];
        $page = (int) ($validated['page'] ?? 1);
        $limit = (int) ($validated['limit'] ?? 10);

        $query = Company::withoutGlobalScopes()
            ->where(function ($builder) use ($search) {
                $builder->where('name_ar', 'LIKE', '%' . $search . '%')
                    ->orWhere('name_en', 'LIKE', '%' . $search . '%');
            });

        $total = (clone $query)->count();
        $companies = $query
            ->orderBy('name_ar')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get(['id', 'name_ar', 'name_en']);

        $companyIds = $companies->pluck('id');
        $traineeCounts = Trainee::query()
            ->whereIn('company_id', $companyIds)
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->selectRaw('company_id, COUNT(*) as aggregate')
            ->groupBy('company_id')
            ->pluck('aggregate', 'company_id');

        $activeCounts = $this->activeTraineesWithPhoneQuery()
            ->whereIn('company_id', $companyIds)
            ->selectRaw('company_id, COUNT(*) as aggregate')
            ->groupBy('company_id')
            ->pluck('aggregate', 'company_id');

        return response()->json([
            'companies' => $companies->map(static fn (Company $company) => [
                'id' => $company->id,
                'name' => $company->name_ar ?: $company->name_en,
                'name_ar' => $company->name_ar,
                'name_en' => $company->name_en,
                'trainees_with_phone_count' => (int) ($traineeCounts[$company->id] ?? 0),
                'active_trainees_count' => (int) ($activeCounts[$company->id] ?? 0),
            ])->values(),
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'has_more' => ($page * $limit) < $total,
        ]);
    }

    public function companyActiveTrainees(Request $request, string $company): JsonResponse
    {
        $companyModel = Company::withoutGlobalScopes()->findOrFail($company);

        $request->validate([
            'pending_invoice_month' => 'nullable|string|regex:/^\d{4}-\d{2}$/',
        ]);
        $pendingInvoiceMonth = $this->normalizePendingInvoiceMonth(
            $request->input('pending_invoice_month')
        );

        $query = $this->activeTraineesWithPhoneQuery()
            ->where('company_id', $companyModel->id);
        $this->applyPendingInvoicesTraineeFilter($query, $pendingInvoiceMonth, $companyModel->id);

        $trainees = $query
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'identity_number', 'company_id']);

        return response()->json([
            'company' => [
                'id' => $companyModel->id,
                'name' => $companyModel->name_ar ?: $companyModel->name_en,
            ],
            'pending_invoice_month' => $pendingInvoiceMonth,
            'count' => $trainees->count(),
            'trainees' => $trainees->map(static fn (Trainee $trainee) => [
                'id' => $trainee->id,
                'name' => $trainee->name,
                'phone' => $trainee->phone,
                'identity_number' => $trainee->identity_number,
            ])->values(),
        ]);
    }

    public function companyPendingInvoiceMonths(string $company): JsonResponse
    {
        $companyModel = Company::withoutGlobalScopes()->findOrFail($company);

        $traineeIds = $this->activeTraineesWithPhoneQuery()
            ->where('company_id', $companyModel->id)
            ->pluck('id')
            ->all();

        if ($traineeIds === []) {
            return response()->json([
                'company' => [
                    'id' => $companyModel->id,
                    'name' => $companyModel->name_ar ?: $companyModel->name_en,
                ],
                'months' => [],
            ]);
        }

        $invoices = Invoice::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('company_id', $companyModel->id)
            ->whereIn('trainee_id', $traineeIds)
            ->whereNull('paid_at')
            ->where('status', '!=', Invoice::STATUS_ARCHIVED)
            ->whereNotNull('from_date')
            ->get(['trainee_id', 'from_date']);

        $monthNames = [
            1 => 'january',
            2 => 'february',
            3 => 'march',
            4 => 'april',
            5 => 'may',
            6 => 'june',
            7 => 'july',
            8 => 'august',
            9 => 'september',
            10 => 'october',
            11 => 'november',
            12 => 'december',
        ];

        $months = $invoices
            ->groupBy(static function (Invoice $invoice): string {
                return Carbon::parse($invoice->from_date)->format('Y-m');
            })
            ->map(static function ($group, string $monthKey) use ($monthNames) {
                $month = Carbon::createFromFormat('!Y-m', $monthKey)->startOfMonth();
                $monthNameKey = $monthNames[(int) $month->month] ?? 'january';

                return [
                    'month' => $monthKey,
                    'label' => __('words.' . $monthNameKey),
                    'year' => (int) $month->year,
                    'invoice_count' => $group->count(),
                    'trainee_count' => $group->pluck('trainee_id')->unique()->count(),
                ];
            })
            ->sortByDesc('month')
            ->values();

        return response()->json([
            'company' => [
                'id' => $companyModel->id,
                'name' => $companyModel->name_ar ?: $companyModel->name_en,
            ],
            'months' => $months,
        ]);
    }

    public function companyTrainees(Request $request, string $company): JsonResponse
    {
        $companyModel = Company::query()->findOrFail($company);

        $validated = $request->validate([
            'page' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:50',
            'search' => 'nullable|string|max:200',
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $limit = (int) ($validated['limit'] ?? 10);

        $query = Trainee::query()
            ->where('company_id', $companyModel->id)
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->with(['company' => $this->companyRelationConstraint()]);

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'LIKE', '%' . $search . '%')
                    ->orWhere('phone', 'LIKE', '%' . $search . '%')
                    ->orWhere('identity_number', 'LIKE', '%' . $search . '%');
            });
        }

        $total = (clone $query)->count();
        $trainees = $query
            ->orderBy('name')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get(['id', 'name', 'phone', 'identity_number', 'company_id']);

        return response()->json([
            'company' => [
                'id' => $companyModel->id,
                'name' => $companyModel->name_ar ?: $companyModel->name_en,
            ],
            'trainees' => $trainees->map(fn (Trainee $trainee) => [
                'id' => $trainee->id,
                'name' => $trainee->name,
                'phone' => $trainee->phone,
                'identity_number' => $trainee->identity_number,
                'company_name' => $this->companyDisplayName(
                    $trainee->company ?: $this->resolveCompanyForTrainee($trainee)
                ),
                'show_url' => route('back.trainees.show', $trainee->id),
            ])->values(),
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'has_more' => ($page * $limit) < $total,
        ]);
    }

    public function quickReplies(): JsonResponse
    {
        $replies = WhatsAppQuickReply::query()
            ->orderByDesc('created_at')
            ->get(['id', 'title', 'body', 'user_id', 'created_at']);

        return response()->json([
            'quick_replies' => $replies->map(static fn (WhatsAppQuickReply $reply) => [
                'id' => $reply->id,
                'title' => $reply->title,
                'body' => $reply->body,
                'user_id' => $reply->user_id,
                'created_at' => optional($reply->created_at)->toIso8601String(),
            ])->values(),
        ]);
    }

    public function storeQuickReply(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:120',
            'body' => 'required|string|max:4000',
        ]);

        $reply = WhatsAppQuickReply::query()->create([
            'title' => $validated['title'],
            'body' => $validated['body'],
            'user_id' => auth()->id(),
        ]);

        return response()->json([
            'quick_reply' => [
                'id' => $reply->id,
                'title' => $reply->title,
                'body' => $reply->body,
                'user_id' => $reply->user_id,
                'created_at' => optional($reply->created_at)->toIso8601String(),
            ],
        ], 201);
    }

    public function destroyQuickReply(WhatsAppQuickReply $quickReply): JsonResponse
    {
        $quickReply->delete();

        return response()->json([
            'ok' => true,
        ]);
    }

    public function traineeContext(string $trainee): JsonResponse
    {
        $model = Trainee::withTrashed()->findOrFail($trainee);
        $company = $this->resolveCompanyForTrainee($model);

        $attributes = array_filter([
            'phone' => $model->phone,
            'identity_number' => $model->identity_number,
            'email' => $model->email,
            'name' => $model->name,
        ], static fn ($value) => filled($value));

        $blockList = empty($attributes)
            ? null
            : TraineeBlockList::query()
                ->where(function ($query) use ($attributes) {
                    foreach ($attributes as $column => $value) {
                        $query->orWhere($column, $value);
                    }
                })
                ->first();

        $isSuspended = ! is_null($model->suspended_at);
        $isBlocked = ($model->trashed() && ! $isSuspended) || ($blockList !== null && ! $isSuspended);
        $deletedRemark = trim((string) ($model->deleted_remark ?? ''));
        $blockListReason = $blockList ? trim((string) ($blockList->reason ?? '')) : '';
        $reason = $deletedRemark !== ''
            ? $deletedRemark
            : ($blockListReason !== '' ? $blockListReason : '');

        $invoiceColumns = [
            'id',
            'company_id',
            'number',
            'grand_total',
            'status',
            'from_date',
            'to_date',
            'paid_at',
            'created_at',
        ];

        $invoices = $model->invoices()
            ->with(['company' => $this->companyRelationConstraint()])
            ->notPaid()
            ->where('status', '!=', Invoice::STATUS_ARCHIVED)
            ->orderByDesc('from_date')
            ->get($invoiceColumns);

        $paidInvoices = $model->invoices()
            ->with(['company' => $this->companyRelationConstraint()])
            ->paid()
            ->orderByDesc('paid_at')
            ->limit(3)
            ->get($invoiceColumns);

        $mapInvoice = fn (Invoice $invoice) => [
            'id' => $invoice->id,
            'number_formatted' => $invoice->number_formatted,
            'company_name' => $this->companyDisplayName($invoice->company),
            'grand_total' => round((float) $invoice->grand_total, 2),
            'status' => $invoice->status,
            'status_formatted' => $invoice->status_formatted,
            'month_of' => $invoice->month_of,
            'paid_at' => optional($invoice->paid_at)->toDateString(),
            'show_url' => route('back.finance.invoices.show', $invoice->id),
        ];

        $gosiRecord = null;
        $gosiEmployerName = null;
        if (filled($model->identity_number)) {
            $gosiRecord = GosiEmployeeData::query()
                ->where('nin_or_iqama', $model->identity_number)
                ->orderByDesc('updated_at')
                ->first(['id', 'updated_at', 'data']);

            if ($gosiRecord) {
                $gosiPayload = $gosiRecord->data;
                if (is_string($gosiPayload)) {
                    $decoded = json_decode($gosiPayload, true);
                    $gosiPayload = is_array($decoded) ? $decoded : [];
                }
                if (! is_array($gosiPayload)) {
                    $gosiPayload = [];
                }

                $gosiEmployerName = collect($gosiPayload['employmentStatusInfo'] ?? [])
                    ->pluck('employerName')
                    ->filter(static fn ($name) => filled($name))
                    ->unique()
                    ->values()
                    ->implode(' · ') ?: null;
            }
        }

        return response()->json([
            'trainee' => [
                'id' => $model->id,
                'name' => $model->name,
                'company_name' => $this->companyDisplayName($company),
                'company_show_url' => $model->company_id
                    ? route('back.companies.show', $model->company_id)
                    : null,
                'show_url' => $model->show_url,
                'registration_date' => $model->created_at
                    ? $model->created_at->toDateString()
                    : null,
            ],
            'documents' => $this->formatTraineeDocuments($model),
            'account_status' => [
                'is_active' => ! $isSuspended && ! $isBlocked,
                'is_suspended' => $isSuspended,
                'is_blocked' => $isBlocked,
                'suspended_at' => optional($model->suspended_at)->toIso8601String(),
                'reason' => $reason !== '' ? $reason : null,
                'in_block_list' => $blockList ? [
                    'reason' => $blockList->reason,
                ] : null,
            ],
            'gosi_status' => [
                'fetched_at' => optional(optional($gosiRecord)->updated_at)->toDateString(),
                'employer_name' => $gosiEmployerName,
            ],
            'invoices' => $invoices->map($mapInvoice)->values(),
            'paid_invoices' => $paidInvoices->map($mapInvoice)->values(),
            'total_owed' => round((float) $invoices->sum('grand_total'), 2),
            'count' => $invoices->count(),
        ]);
    }

    public function sendMessage(Request $request): JsonResponse
    {
        $this->ensureConfigured();

        $validated = $request->validate([
            'phone' => 'required|string|max:30',
            'body' => 'required|string|max:1600',
            'trainee_id' => 'nullable|uuid|exists:trainees,id',
        ]);

        $conversation = null;
        $formatted = null;

        try {
            $response = $this->whatsAppService->sendFreeformMessage(
                $validated['phone'],
                $validated['body'],
                $validated['trainee_id'] ?? null
            );

            $stored = WhatsAppMessage::query()
                ->where('phone', $this->whatsAppService->normalizePhoneDigits($validated['phone']))
                ->where('direction', WhatsAppMessage::DIRECTION_OUTBOUND)
                ->where('body', $validated['body'])
                ->orderByDesc('created_at')
                ->first();

            if ($stored) {
                $stored->update([
                    'user_id' => auth()->id(),
                ]);
            }

            WhatsAppBotPause::pauseForAgent($validated['phone']);
            $conversation = WhatsAppConversationSync::assignCurrentUser(
                $this->whatsAppService->normalizePhoneDigits($validated['phone'])
            );

            $stored?->load('user:id,name');
            $formatted = $stored ? $this->formatMessage($stored) : $response;
            $formatted['is_note'] = false;
            $formatted['author'] = [
                'id' => auth()->id(),
                'name' => auth()->user()->name,
            ];
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => $formatted,
            'conversation' => $conversation
                ? $this->formatConversation($conversation, auth()->id())
                : null,
        ]);
    }

    public function sendTemplate(Request $request): JsonResponse
    {
        $this->ensureConfigured();

        $validated = $request->validate([
            'phone' => 'required|string|max:30',
            'content_sid' => 'required|string|max:64',
            'content_variables' => 'nullable|array',
            'content_variables.*' => 'nullable|string|max:1000',
            'trainee_id' => 'nullable|uuid|exists:trainees,id',
        ]);

        $conversation = null;
        $formatted = null;

        try {
            $response = $this->whatsAppService->sendTemplate(
                $validated['phone'],
                $validated['content_sid'],
                $validated['content_variables'] ?? [],
                $validated['trainee_id'] ?? null
            );

            $stored = WhatsAppMessage::query()
                ->where('phone', $this->whatsAppService->normalizePhoneDigits($validated['phone']))
                ->where('direction', WhatsAppMessage::DIRECTION_OUTBOUND)
                ->orderByDesc('created_at')
                ->first();

            if ($stored) {
                $stored->update([
                    'user_id' => auth()->id(),
                ]);
            }

            $normalized = $this->whatsAppService->normalizePhoneDigits($validated['phone']);
            $conversation = WhatsAppConversation::query()
                ->with(['agents:id,name', 'tags:id,name,color', 'trainee.company:id,name_ar'])
                ->where('phone', $normalized)
                ->first();

            $stored?->load('user:id,name');
            $formatted = $stored ? $this->formatMessage($stored) : $response;
            $formatted['is_note'] = false;
            $formatted['author'] = [
                'id' => auth()->id(),
                'name' => auth()->user()->name,
            ];
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => $formatted,
            'conversation' => $conversation
                ? $this->formatConversation($conversation, auth()->id())
                : null,
        ]);
    }

    public function sendTemplateToCompany(Request $request): JsonResponse
    {
        $this->ensureConfigured();

        $validated = $request->validate([
            'company_id' => 'required|uuid',
            'content_sid' => 'required|string|max:64',
            'content_variables' => 'nullable|array',
            'content_variables.*' => 'nullable|string|max:1000',
            'pending_invoice_month' => 'nullable|string|regex:/^\d{4}-\d{2}$/',
        ]);

        $company = Company::withoutGlobalScopes()->findOrFail($validated['company_id']);
        $pendingInvoiceMonth = $this->normalizePendingInvoiceMonth(
            $validated['pending_invoice_month'] ?? null
        );

        $query = $this->activeTraineesWithPhoneQuery()
            ->where('company_id', $company->id)
            ->with(['company' => $this->companyRelationConstraint()]);
        $this->applyPendingInvoicesTraineeFilter($query, $pendingInvoiceMonth, $company->id);

        $trainees = $query
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'company_id', 'english_name', 'identity_number']);

        if ($trainees->isEmpty()) {
            return response()->json([
                'message' => $pendingInvoiceMonth
                    ? __('words.whatsapp-company-no-pending-invoice-trainees')
                    : __('words.whatsapp-company-no-active-trainees'),
            ], 422);
        }

        $sent = 0;
        $failed = [];

        foreach ($trainees as $trainee) {
            try {
                $this->whatsAppService->sendTemplate(
                    (string) $trainee->phone,
                    $validated['content_sid'],
                    $validated['content_variables'] ?? [],
                    (string) $trainee->id
                );

                $normalized = $this->whatsAppService->normalizePhoneDigits((string) $trainee->phone);
                $stored = WhatsAppMessage::query()
                    ->where('phone', $normalized)
                    ->where('direction', WhatsAppMessage::DIRECTION_OUTBOUND)
                    ->orderByDesc('created_at')
                    ->first();

                if ($stored && auth()->id()) {
                    $stored->update(['user_id' => auth()->id()]);
                }

                $sent++;
            } catch (RuntimeException $exception) {
                $failed[] = [
                    'id' => $trainee->id,
                    'name' => $trainee->name,
                    'phone' => $trainee->phone,
                    'error' => $exception->getMessage(),
                ];
            }
        }

        return response()->json([
            'company' => [
                'id' => $company->id,
                'name' => $company->name_ar ?: $company->name_en,
            ],
            'total' => $trainees->count(),
            'sent' => $sent,
            'failed_count' => count($failed),
            'failed' => $failed,
            'message' => __('words.whatsapp-company-template-sent', [
                'sent' => $sent,
                'total' => $trainees->count(),
            ]),
        ]);
    }

    public function sendNote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => 'required|string|max:30',
            'body' => 'required|string|max:1600',
            'trainee_id' => 'nullable|uuid|exists:trainees,id',
        ]);

        $normalizedPhone = $this->whatsAppService->normalizePhoneDigits($validated['phone']);
        $trainee = $validated['trainee_id']
            ? Trainee::query()->find($validated['trainee_id'])
            : $this->whatsAppService->findTraineeByPhone($normalizedPhone);

        $note = WhatsAppMessage::query()->create([
            'trainee_id' => $trainee?->id,
            'phone' => $normalizedPhone,
            'direction' => WhatsAppMessage::DIRECTION_OUTBOUND,
            'is_note' => true,
            'user_id' => auth()->id(),
            'body' => $validated['body'],
            'status' => 'internal_note',
            'sent_at' => now(),
        ]);

        $note->load('user:id,name');

        WhatsAppBroadcast::messageStored($note);

        return response()->json([
            'message' => $this->formatMessage($note),
        ]);
    }

    public function saveToS3(Request $request, string $id): JsonResponse
    {
        $message = WhatsAppMessage::query()->findOrFail($id);

        $request->validate([
            'media_url' => 'required|url',
        ]);

        $mediaUrl = $request->input('media_url');

        try {
            $media = $message->addMediaFromUrl($mediaUrl)
                ->toMediaCollection('whatsapp_media', 's3');

            return response()->json([
                'success' => true,
                'message' => __('words.saved-to-s3'),
                'media_id' => $media->id,
                'url' => route('back.media.download', ['media_id' => $media->id]),
            ]);
        } catch (\Exception $exception) {
            return response()->json([
                'message' => 'Failed to save media to S3: ' . $exception->getMessage(),
            ], 422);
        }
    }

    public function templates(): JsonResponse
    {
        $this->ensureConfigured();

        return response()->json([
            'templates' => $this->whatsAppService->listTemplates(),
            'can_manage' => $this->whatsAppService->canManageTemplates(),
            'available_auto_tags' => $this->whatsAppService->availableAutoTags(),
        ]);
    }

    public function showTemplate(string $contentSid): JsonResponse
    {
        $this->ensureConfigured();

        return response()->json([
            'template' => $this->whatsAppService->getTemplate($contentSid),
            'available_auto_tags' => $this->whatsAppService->availableAutoTags(),
        ]);
    }

    public function storeTemplate(Request $request): JsonResponse
    {
        $this->ensureCanManageTemplates();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:512', 'regex:/^[a-z0-9_]+$/'],
            'category' => 'required|string|in:UTILITY,MARKETING,AUTHENTICATION',
            'language' => 'required|string|max:20',
            'body' => 'required|string|max:1024',
            'header' => 'nullable|string|max:60',
            'footer' => 'nullable|string|max:60',
            'variable_samples' => 'nullable|array',
            'variable_samples.*' => 'nullable|string|max:200',
            'buttons' => 'nullable|array|max:3',
            'buttons.*.type' => 'required_with:buttons|string|in:QUICK_REPLY,URL,PHONE_NUMBER',
            'buttons.*.text' => 'required_with:buttons|string|max:25',
            'buttons.*.url' => 'nullable|string|max:2000',
            'buttons.*.phone_number' => 'nullable|string|max:30',
            'buttons.*.example' => 'nullable|string|max:200',
        ]);

        try {
            $template = $this->whatsAppService->createTemplate($validated);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'template' => $template,
            'message' => __('words.whatsapp-template-created'),
        ], 201);
    }

    public function updateTemplate(Request $request, string $contentSid): JsonResponse
    {
        $this->ensureCanManageTemplates();

        $validated = $request->validate([
            'body' => 'required|string|max:1024',
            'header' => 'nullable|string|max:60',
            'footer' => 'nullable|string|max:60',
            'variable_samples' => 'nullable|array',
            'variable_samples.*' => 'nullable|string|max:200',
            'buttons' => 'nullable|array|max:3',
            'buttons.*.type' => 'required_with:buttons|string|in:QUICK_REPLY,URL,PHONE_NUMBER',
            'buttons.*.text' => 'required_with:buttons|string|max:25',
            'buttons.*.url' => 'nullable|string|max:2000',
            'buttons.*.phone_number' => 'nullable|string|max:30',
            'buttons.*.example' => 'nullable|string|max:200',
        ]);

        try {
            $template = $this->whatsAppService->updateTemplate($contentSid, $validated);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'template' => $template,
            'message' => __('words.whatsapp-template-updated'),
        ]);
    }

    public function destroyTemplate(string $contentSid): JsonResponse
    {
        $this->ensureCanManageTemplates();

        try {
            $this->whatsAppService->deleteTemplate($contentSid);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => __('words.whatsapp-template-deleted'),
        ]);
    }

    /**
     * @return array{open: int, pending: int, closed: int, unassigned: int}
     */
    private function conversationCounts(): array
    {
        $byStatus = WhatsAppConversation::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'open' => (int) ($byStatus[WhatsAppConversation::STATUS_OPEN] ?? 0),
            'pending' => (int) ($byStatus[WhatsAppConversation::STATUS_PENDING] ?? 0),
            'closed' => (int) ($byStatus[WhatsAppConversation::STATUS_CLOSED] ?? 0),
            'unassigned' => (int) WhatsAppConversation::query()
                ->whereDoesntHave('agents')
                ->count(),
        ];
    }

    /**
     * Count open conversations plus conversations that have assigned agents, per tag.
     *
     * @param  array<int, string>|null  $tagIds
     * @return array<string, int>
     */
    private function tagConversationCounts(?array $tagIds = null): array
    {
        $query = WhatsAppConversation::query()
            ->join('whatsapp_conversation_tag as wct', 'wct.conversation_id', '=', 'whatsapp_conversations.id')
            ->where(function ($builder) {
                $builder->where('whatsapp_conversations.status', WhatsAppConversation::STATUS_OPEN)
                    ->orWhereExists(function ($agents) {
                        $agents->selectRaw('1')
                            ->from('whatsapp_conversation_agents')
                            ->whereColumn('whatsapp_conversation_agents.conversation_id', 'whatsapp_conversations.id');
                    });
            })
            ->groupBy('wct.tag_id')
            ->selectRaw('wct.tag_id, COUNT(DISTINCT whatsapp_conversations.id) as total');

        if ($tagIds !== null && $tagIds !== []) {
            $query->whereIn('wct.tag_id', $tagIds);
        }

        return $query->pluck('total', 'tag_id')
            ->map(static fn ($total) => (int) $total)
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatConversation(WhatsAppConversation $conversation, $authId = null, ?int $unpaidInvoiceCount = null): array
    {
        WhatsAppTraineeLinker::attachTraineeIfMissing($conversation);
        WhatsAppConversationTrainee::loadOnto($conversation, $this->companyRelationConstraint());

        $company = $conversation->trainee
            ? $this->resolveCompanyForTrainee($conversation->trainee)
            : null;

        if ($unpaidInvoiceCount === null) {
            $unpaidInvoiceCount = Invoice::unpaidCountForTrainee(
                $conversation->trainee_id ? (string) $conversation->trainee_id : null
            );
        }

        return [
            'id' => $conversation->id,
            'phone' => $conversation->phone,
            'status' => $conversation->status ?: WhatsAppConversation::STATUS_OPEN,
            'trainee' => WhatsAppConversationTrainee::format(
                $conversation->trainee,
                $this->companyDisplayName($company),
                $conversation->trainee && $conversation->trainee->company_id
                    ? route('back.companies.show', $conversation->trainee->company_id)
                    : null,
            ),
            'last_message' => [
                'body' => $conversation->last_message_body,
                'direction' => $conversation->last_message_direction,
                'is_note' => (bool) $conversation->last_message_is_note,
                'sent_at' => optional($conversation->last_message_at)->toIso8601String(),
            ],
            'messaging_window' => WhatsAppMessagingWindow::forConversation($conversation),
            'updated_at' => optional($conversation->last_message_at)->timestamp
                ?? optional($conversation->updated_at)->timestamp
                ?? 0,
            'agents' => $conversation->agents->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
            ])->values()->all(),
            'tags' => $conversation->tags->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'color' => $tag->color,
            ])->values()->all(),
            'is_assigned_to_me' => $authId
                ? $conversation->agents->contains('id', $authId)
                : false,
            'is_unassigned' => $conversation->agents->isEmpty(),
            'has_unread' => (bool) $conversation->has_unread,
            'bot_is_paused' => WhatsAppBotPause::isPaused($conversation),
            'bot_paused_until' => optional($conversation->bot_paused_until)->toIso8601String(),
            'unpaid_invoice_count' => $unpaidInvoiceCount,
        ];
    }

    private function isWhatsAppBotConfigured(): bool
    {
        if (WhatsAppAiSettings::isReady()) {
            return true;
        }

        return WhatsAppBotSender::query()
            ->whereNotNull('workflow_id')
            ->whereHas('workflow', static function ($query): void {
                $query->where('is_active', true);
            })
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatMessage(WhatsAppMessage $msg): array
    {
        $formatted = $this->whatsAppService->formatStoredMessage($msg);
        $formatted['id'] = $msg->id;
        $formatted['phone'] = $msg->phone;
        $formatted['is_note'] = (bool) $msg->is_note;
        $formatted['is_bot'] = ! empty($formatted['is_bot']);

        if ($formatted['is_bot']) {
            $formatted['author'] = [
                'id' => null,
                'name' => 'Bot',
                'is_bot' => true,
            ];
        } else {
            $formatted['author'] = $msg->user ? [
                'id' => $msg->user->id,
                'name' => $msg->user->name,
            ] : null;
        }

        $formatted['saved_media'] = $msg->persistedMediaPayload();

        return $formatted;
    }

    private function assertAssignablePeer(string $userId): void
    {
        $auth = auth()->user();
        $roleIds = $auth->roles()->pluck('id');

        if ($roleIds->isEmpty()) {
            throw new AccessDeniedHttpException(__('words.chat-assign-peer-denied'));
        }

        $isPeer = User::query()
            ->permission('access-whatsapp-chats')
            ->where('id', $userId)
            ->whereHas('roles', function ($query) use ($roleIds) {
                $query->whereIn('roles.id', $roleIds);
            })
            ->exists();

        if (! $isPeer) {
            throw new AccessDeniedHttpException(__('words.chat-assign-peer-denied'));
        }
    }

    private function ensureConfigured(): void
    {
        if (! $this->whatsAppService->isConfigured()) {
            abort(503, __('words.whatsapp-not-configured'));
        }
    }

    private function ensureCanManageTemplates(): void
    {
        $this->ensureConfigured();

        if (! $this->whatsAppService->canManageTemplates()) {
            abort(503, __('words.whatsapp-templates-manage-not-configured'));
        }
    }

    /**
     * Bypass Company global scopes (e.g. ptc-ksa.com/.net filters) for chat display.
     */
    /**
     * Active trainees: not soft-deleted, not suspended, and have a phone number.
     */
    private function activeTraineesWithPhoneQuery()
    {
        return Trainee::query()
            ->whereNull('suspended_at')
            ->whereNotNull('phone')
            ->where('phone', '!=', '');
    }

    private function normalizePendingInvoiceMonth(?string $month): ?string
    {
        if ($month === null || $month === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('!Y-m', $month)->format('Y-m');
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages([
                'pending_invoice_month' => __('validation.regex', ['attribute' => 'pending_invoice_month']),
            ]);
        }
    }

    private function applyPendingInvoicesTraineeFilter($query, ?string $pendingInvoiceMonth, ?string $companyId = null): void
    {
        if ($pendingInvoiceMonth === null || $pendingInvoiceMonth === '') {
            return;
        }

        $month = Carbon::createFromFormat('!Y-m', $pendingInvoiceMonth)->startOfMonth();
        $monthStart = $month->toDateString();
        $monthEnd = $month->copy()->endOfMonth()->toDateString();

        $query->whereHas('invoices', static function ($invoiceQuery) use ($monthStart, $monthEnd, $companyId): void {
            $invoiceQuery->withoutGlobalScopes()
                ->whereNull('deleted_at')
                ->whereNull('paid_at')
                ->where('status', '!=', Invoice::STATUS_ARCHIVED)
                ->whereDate('from_date', '>=', $monthStart)
                ->whereDate('from_date', '<=', $monthEnd);

            if ($companyId) {
                $invoiceQuery->where('company_id', $companyId);
            }
        });
    }

    private function companyRelationConstraint(): \Closure
    {
        return static function ($query): void {
            $query->withoutGlobalScopes()->select(['id', 'name_ar', 'name_en']);
        };
    }

    private function resolveCompanyForTrainee(?Trainee $trainee): ?Company
    {
        if (! $trainee || ! $trainee->company_id) {
            return null;
        }

        if ($trainee->relationLoaded('company') && $trainee->getRelation('company')) {
            return $trainee->getRelation('company');
        }

        return Company::withoutGlobalScopes()
            ->select(['id', 'name_ar', 'name_en'])
            ->find($trainee->company_id);
    }

    /**
     * @return array<string, array{url: string, mime_type: string|null, name: string|null}|null>
     */
    private function formatTraineeDocuments(Trainee $trainee): array
    {
        $collections = [
            'non_registration_proof' => 'non-registration-proof',
            'gosi_certificate' => 'gosi-certificate',
            'qiwa_contract' => 'qiwa-contract',
        ];

        $mediaByCollection = $trainee->media()
            ->whereIn('collection_name', array_values($collections))
            ->orderByDesc('id')
            ->get()
            ->unique('collection_name')
            ->keyBy('collection_name');

        $documents = [];
        foreach ($collections as $key => $collectionName) {
            $media = $mediaByCollection->get($collectionName);
            $documents[$key] = $media ? [
                'url' => route('back.media.download', ['media_id' => $media->id]),
                'mime_type' => $media->mime_type,
                'name' => $media->file_name,
            ] : null;
        }

        return $documents;
    }

    private function companyDisplayName(?Company $company): ?string
    {
        if (! $company) {
            return null;
        }

        $name = trim((string) ($company->name_ar ?: $company->name_en ?: ''));

        return $name !== '' ? $name : null;
    }
}
