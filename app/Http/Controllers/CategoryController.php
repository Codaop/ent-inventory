<?php

namespace App\Http\Controllers;

use App\Repositories\CategorySheetRepository;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function __construct(private readonly CategorySheetRepository $categories) {}

    public function index(): JsonResponse
    {
        return response()->json($this->categories->all());
    }
}
