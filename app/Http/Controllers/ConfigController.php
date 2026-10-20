<?php

namespace App\Http\Controllers;

use App\Repositories\ConfigSheetRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConfigController extends Controller
{
    public function __construct(private readonly ConfigSheetRepository $config) {}

    public function index(): JsonResponse
    {
        return response()->json(['config' => $this->config->all()]);
    }

    public function show(string $key): JsonResponse
    {
        $value = $this->config->get($key);

        if ($value === null) {
            return response()->json(['error' => 'CONFIG_NOT_FOUND'], 404);
        }

        return response()->json(['key' => $key, 'value' => $value]);
    }

    public function update(Request $request, string $key): JsonResponse
    {
        $data = $request->validate([
            'value' => ['required', 'string'],
        ]);

        $this->config->set($key, $data['value']);

        return response()->json(['ok' => true]);
    }

    public function destroy(string $key): JsonResponse
    {
        if (! $this->config->delete($key)) {
            return response()->json(['error' => 'CONFIG_NOT_FOUND'], 404);
        }

        return response()->json(['ok' => true]);
    }
}
