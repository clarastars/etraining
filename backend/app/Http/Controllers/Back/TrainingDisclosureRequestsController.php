<?php

declare(strict_types=1);

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Http\Requests\Back\StoreTrainingDisclosureRequestRequest;
use App\Http\Requests\Back\UpdateTrainingDisclosureRequestRequest;
use App\Models\Back\TrainingDisclosureRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TrainingDisclosureRequestsController extends Controller
{
    public function index(): Response
    {
        abort_unless(auth()->user()->can('manage-recorded-courses'), 403);

        $requests = TrainingDisclosureRequest::query()
            ->with('company:id,name_ar,name_en')
            ->latest()
            ->paginate(30);

        $requests->getCollection()->transform(
            fn (TrainingDisclosureRequest $request) => $this->toListItem($request)
        );

        return Inertia::render('Back/TrainingDisclosure/Requests/Index', [
            'requests' => $requests,
        ]);
    }

    public function create(): Response
    {
        abort_unless(auth()->user()->can('manage-recorded-courses'), 403);

        return Inertia::render('Back/TrainingDisclosure/Requests/Create');
    }

    public function store(StoreTrainingDisclosureRequestRequest $request): RedirectResponse
    {
        $draft = $this->normalizeTrainees($request->input('trainees', []));

        $disclosureRequest = TrainingDisclosureRequest::query()->create([
            'company_id' => $request->input('company_id'),
            'company_name' => $request->input('company_name'),
            'trainees_count' => (int) $request->input('trainees_count'),
            'trainees_draft' => $draft,
        ]);

        return redirect()
            ->route('back.training-disclosure.requests.show', $disclosureRequest)
            ->with('success', __('words.training-disclosure-request-created'));
    }

    public function show(TrainingDisclosureRequest $trainingDisclosureRequest): Response
    {
        abort_unless(auth()->user()->can('manage-recorded-courses'), 403);

        $trainingDisclosureRequest->load('company:id,name_ar,name_en');

        return Inertia::render('Back/TrainingDisclosure/Requests/Show', [
            'disclosureRequest' => $this->toDetail($trainingDisclosureRequest),
        ]);
    }

    public function update(
        UpdateTrainingDisclosureRequestRequest $request,
        TrainingDisclosureRequest $trainingDisclosureRequest
    ): RedirectResponse {
        $draft = $this->normalizeTrainees($request->input('trainees', []));

        $trainingDisclosureRequest->update([
            'company_id' => $request->input('company_id'),
            'company_name' => $request->input('company_name'),
            'trainees_count' => (int) $request->input('trainees_count'),
            'trainees_draft' => $draft,
        ]);

        return redirect()
            ->route('back.training-disclosure.requests.show', $trainingDisclosureRequest)
            ->with('success', __('words.training-disclosure-request-updated'));
    }

    public function destroy(TrainingDisclosureRequest $trainingDisclosureRequest): RedirectResponse
    {
        abort_unless(auth()->user()->can('manage-recorded-courses'), 403);

        $trainingDisclosureRequest->delete();

        return redirect()
            ->route('back.training-disclosure.requests.index')
            ->with('success', __('words.training-disclosure-request-deleted'));
    }

    /**
     * @param  array<int, mixed>  $trainees
     * @return list<array{name: string, phone: string|null, email: string|null}>
     */
    private function normalizeTrainees(array $trainees): array
    {
        $normalized = [];

        foreach ($trainees as $row) {
            if (! is_array($row)) {
                continue;
            }

            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $phone = trim((string) ($row['phone'] ?? ''));
            $email = trim((string) ($row['email'] ?? ''));

            $normalized[] = [
                'name' => $name,
                'phone' => $phone !== '' ? $phone : null,
                'email' => $email !== '' ? $email : null,
            ];
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private function toListItem(TrainingDisclosureRequest $request): array
    {
        return [
            'id' => $request->id,
            'number' => $request->number,
            'company_id' => $request->company_id,
            'company_name' => $request->company_name,
            'trainees_count' => $request->trainees_count,
            'draft_count' => count($request->trainees_draft ?? []),
            'created_at' => $request->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toDetail(TrainingDisclosureRequest $request): array
    {
        $company = $request->company;

        return [
            'id' => $request->id,
            'number' => $request->number,
            'company_id' => $request->company_id,
            'company_name' => $request->company_name,
            'company' => $company ? [
                'id' => $company->id,
                'name_ar' => $company->name_ar,
                'name_en' => $company->name_en,
            ] : null,
            'trainees_count' => $request->trainees_count,
            'trainees' => array_values($request->trainees_draft ?? []),
            'created_at' => $request->created_at?->toIso8601String(),
            'updated_at' => $request->updated_at?->toIso8601String(),
        ];
    }
}
