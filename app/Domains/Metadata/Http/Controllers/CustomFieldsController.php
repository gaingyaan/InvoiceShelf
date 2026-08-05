<?php

namespace App\Domains\Metadata\Http\Controllers;

use App\Domains\Metadata\Application\CustomFieldService;
use App\Domains\Metadata\Http\Requests\CustomFieldRequest;
use App\Domains\Metadata\Http\Resources\CustomFieldResource;
use App\Domains\Metadata\Models\CustomField;
use App\Platform\Http\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CustomFieldsController extends Controller
{
    public function __construct(
        private readonly CustomFieldService $customFieldService,
    ) {}

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', CustomField::class);

        $limit = $request->has('limit') ? $request->limit : 5;

        $customFields = CustomField::applyFilters($request->all())
            ->whereCompany()
            ->latest()
            ->paginateData($limit);

        return CustomFieldResource::collection($customFields);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\CustomFieldRequest  $request
     * @return Response
     */
    public function store(CustomFieldRequest $request)
    {
        $this->authorize('create', CustomField::class);

        $customField = $this->customFieldService->create(
            $request->validated(),
            $request->input('default_answer'),
            (int) $request->header('company'),
        );

        return new CustomFieldResource($customField);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function show(CustomField $customField)
    {
        $this->authorize('view', $customField);

        return new CustomFieldResource($customField);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function update(CustomFieldRequest $request, CustomField $customField)
    {
        $this->authorize('update', $customField);

        $this->customFieldService->update(
            $customField,
            $request->validated(),
            $request->input('default_answer'),
        );

        return new CustomFieldResource($customField);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy(CustomField $customField)
    {
        $this->authorize('delete', $customField);

        if ($customField->customFieldValues()->exists()) {
            $customField->customFieldValues()->delete();
        }

        $customField->forceDelete();

        return response()->json([
            'success' => true,
        ]);
    }
}
