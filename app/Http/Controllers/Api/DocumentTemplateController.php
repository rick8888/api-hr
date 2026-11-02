<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\DTO\DocumentTemplate\DocumentTemplateData;
use App\Application\Services\DocumentGenerationService;
use App\Application\Services\DocumentTemplateMergeContext;
use App\Application\Services\DocumentTemplateService;
use App\Domain\DocumentTemplate\Models\DocumentTemplate;
use App\Domain\Employee\Models\Employee;
use App\Http\Controllers\Controller;
use App\Http\Requests\DocumentTemplate\GenerateEmployeeDocumentRequest;
use App\Http\Requests\DocumentTemplate\PreviewDocumentTemplateRequest;
use App\Http\Requests\DocumentTemplate\StoreDocumentTemplateRequest;
use App\Http\Requests\DocumentTemplate\UpdateDocumentTemplateRequest;
use App\Http\Resources\DocumentTemplateResource;
use App\Http\Resources\GeneratedDocumentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class DocumentTemplateController extends Controller
{
    public function __construct(
        private readonly DocumentTemplateService $templates,
        private readonly DocumentGenerationService $generation,
        private readonly DocumentTemplateMergeContext $mergeContext,
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $companyOnly = filter_var($request->query('company_only', false), FILTER_VALIDATE_BOOL);

        return DocumentTemplateResource::collection(
            $this->templates->listForActor($request->user(), $companyOnly),
        );
    }

    public function catalog(Request $request): AnonymousResourceCollection
    {
        $channel = $request->query('channel');
        $channel = is_string($channel) && $channel !== '' ? $channel : 'pdf';

        return DocumentTemplateResource::collection(
            $this->templates->catalogForActor($request->user(), $channel),
        );
    }

    public function variables(): JsonResponse
    {
        return response()->json([
            'data' => $this->mergeContext->variableCatalog(),
        ]);
    }

    public function events(): JsonResponse
    {
        $emails = config('document_templates.events', []);
        $documents = config('document_templates.documents', []);

        $keys = collect(array_keys($emails))
            ->merge(array_keys($documents))
            ->unique()
            ->values();

        return response()->json([
            'data' => $keys->map(fn (string $event) => [
                'event' => $event,
                'email_template_code' => $emails[$event] ?? null,
                'pdf_template_code' => $documents[$event] ?? null,
            ]),
        ]);
    }

    public function previewTemplate(
        PreviewDocumentTemplateRequest $request,
        DocumentTemplate $document_template,
    ): Response {
        $employee = null;
        $employeeId = $request->validated('employee_id');
        if ($employeeId) {
            $employee = Employee::query()->findOrFail((int) $employeeId);
        }

        return $this->generation->previewTemplateForActor(
            $request->user(),
            $document_template,
            $employee,
        );
    }

    public function store(StoreDocumentTemplateRequest $request): JsonResponse
    {
        $template = $this->templates->createForActor(
            $request->user(),
            DocumentTemplateData::fromArray($request->validated()),
        );

        return (new DocumentTemplateResource($template))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, DocumentTemplate $document_template): DocumentTemplateResource
    {
        return new DocumentTemplateResource(
            $this->templates->findForActor($request->user(), $document_template),
        );
    }

    public function update(
        UpdateDocumentTemplateRequest $request,
        DocumentTemplate $document_template,
    ): DocumentTemplateResource {
        return new DocumentTemplateResource(
            $this->templates->updateForActor(
                $request->user(),
                $document_template,
                $request->validated(),
            ),
        );
    }

    public function publish(Request $request, DocumentTemplate $document_template): DocumentTemplateResource
    {
        return new DocumentTemplateResource(
            $this->templates->publishForActor($request->user(), $document_template),
        );
    }

    public function duplicate(Request $request, DocumentTemplate $document_template): JsonResponse
    {
        $copy = $this->templates->duplicateForActor($request->user(), $document_template);

        return (new DocumentTemplateResource($copy))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroy(Request $request, DocumentTemplate $document_template): JsonResponse
    {
        $this->templates->deleteForActor($request->user(), $document_template);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function preview(
        GenerateEmployeeDocumentRequest $request,
        Employee $employee,
    ): Response {
        $template = $this->resolveTemplate($request);

        return $this->generation->previewForActor(
            $request->user(),
            $employee,
            $template,
            $request->validated('extra') ?? [],
        );
    }

    public function generate(
        GenerateEmployeeDocumentRequest $request,
        Employee $employee,
    ): GeneratedDocumentResource {
        $template = $this->resolveTemplate($request);

        $generated = $this->generation->generateForActor(
            $request->user(),
            $employee,
            $template,
            $request->validated('extra') ?? [],
        );

        return new GeneratedDocumentResource($generated);
    }

    private function resolveTemplate(GenerateEmployeeDocumentRequest $request): DocumentTemplate
    {
        $user = $request->user();
        $id = $request->validated('document_template_id');
        if ($id) {
            $template = DocumentTemplate::query()->findOrFail($id);

            return $template;
        }

        $code = (string) $request->validated('template_code');
        $locale = (string) ($request->validated('locale') ?? 'fr');

        return $this->templates->resolvePublishedForEmployee($user, $code, $locale);
    }
}
