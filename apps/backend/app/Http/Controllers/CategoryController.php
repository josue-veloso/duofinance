<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\Couple;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $couple = $this->getCouple($request);

        $categories = $couple->categories()
            ->orderBy('name', 'asc')
            ->get();

        return response()->json([
            'categories' => $categories->map(
                fn (Category $c) => (new CategoryResource($c))->toArray($request)
            )->values()->all(),
        ]);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $couple = $this->getCouple($request);

        $category = $couple->categories()->create($request->validated());

        return response()->json([
            'category' => (new CategoryResource($category))->toArray($request),
        ], 201);
    }

    public function destroy(Request $request, string $id): Response|JsonResponse
    {
        $couple = $this->getCouple($request);

        $category = $couple->categories()->where('id', $id)->first();
        if (!$category) {
            return response()->json(['error' => 'Categoria não encontrada.'], 404);
        }

        $category->delete();

        return response()->noContent();
    }

    protected function getCouple(Request $request): Couple
    {
        $couple = $request->attributes->get('couple') ?? $request->user()->currentCouple();

        if (!$couple) {
            abort(403, 'User is not part of a couple');
        }

        return $couple;
    }
}
