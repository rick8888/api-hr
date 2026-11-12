<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Employee\Models\SelfServiceNote;
use App\Http\Controllers\Controller;
use App\Http\Requests\SelfService\StoreSelfServiceNoteRequest;
use App\Http\Requests\SelfService\UpdateSelfServiceNoteRequest;
use App\Http\Resources\SelfServiceNoteResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class SelfServiceNoteController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return SelfServiceNoteResource::collection(
            SelfServiceNote::query()
                ->where('user_id', $request->user()->id)
                ->orderByDesc('pinned')
                ->orderByDesc('updated_at')
                ->get(),
        );
    }

    public function store(StoreSelfServiceNoteRequest $request): JsonResponse
    {
        $note = SelfServiceNote::query()->create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return (new SelfServiceNoteResource($note))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateSelfServiceNoteRequest $request, int $note): SelfServiceNoteResource
    {
        $record = $this->findForActor($request, $note);
        $record->update($request->validated());

        return new SelfServiceNoteResource($record->refresh());
    }

    public function destroy(Request $request, int $note): Response
    {
        $this->findForActor($request, $note)->delete();

        return response()->noContent();
    }

    private function findForActor(Request $request, int $note): SelfServiceNote
    {
        return SelfServiceNote::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($note);
    }
}
