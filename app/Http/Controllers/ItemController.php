<?php

namespace App\Http\Controllers;

use App\Repositories\CategorySheetRepository;
use App\Repositories\ItemSheetRepository;
use App\Repositories\TransactionSheetRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ItemController extends Controller
{
    public function __construct(
        private readonly ItemSheetRepository $items,
        private readonly CategorySheetRepository $categories,
        private readonly TransactionSheetRepository $transactions,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $category = (string) $request->query('category', '');
        $location = (string) $request->query('location', '');
        $search = strtolower((string) $request->query('search', ''));
        $lowStock = $request->boolean('lowStock');

        $items = array_filter(
            $this->items->allItems(),
            function (array $item) use ($category, $location, $search, $lowStock): bool {
                if ($category !== '' && $item['category'] !== $category) {
                    return false;
                }
                if ($location !== '' && $item['location'] !== $location) {
                    return false;
                }
                if ($lowStock && $item['availableQty'] > $item['reorderThreshold']) {
                    return false;
                }
                if ($search !== '') {
                    return str_contains(strtolower($item['name']), $search)
                        || str_contains(strtolower($item['sku']), $search)
                        || str_contains(strtolower($item['category']), $search);
                }

                return true;
            },
        );

        return response()->json(array_values($items));
    }

    public function show(string $itemId): JsonResponse
    {
        $item = $this->items->findItem($itemId);

        if ($item === null) {
            return $this->error('ITEM_NOT_FOUND', 404);
        }

        return response()->json($item);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'sku' => ['nullable', 'string'],
            'category' => ['nullable', 'string'],
            'location' => ['nullable', 'string'],
            'totalQty' => ['nullable', 'numeric', 'min:0'],
            'availableQty' => ['nullable', 'numeric', 'min:0'],
            'reorderThreshold' => ['nullable', 'numeric', 'min:0'],
            'unitPrice' => ['nullable', 'numeric', 'min:0'],
        ]);

        $itemId = $this->items->appendItem($data);

        $this->ensureCategory($data['category'] ?? '');

        return response()->json(['ok' => true, 'itemId' => $itemId]);
    }

    public function update(Request $request, string $itemId): JsonResponse
    {
        $data = $request->validate([
            'sku' => ['sometimes', 'nullable', 'string'],
            'name' => ['sometimes', 'required', 'string'],
            'category' => ['sometimes', 'nullable', 'string'],
            'location' => ['sometimes', 'nullable', 'string'],
            'totalQty' => ['sometimes', 'numeric', 'min:0'],
            'availableQty' => ['sometimes', 'numeric', 'min:0'],
            'reorderThreshold' => ['sometimes', 'numeric', 'min:0'],
            'unitPrice' => ['sometimes', 'numeric', 'min:0'],
        ]);

        if (! $this->items->updateItem($itemId, $data)) {
            return $this->error('ITEM_NOT_FOUND', 404);
        }

        $this->ensureCategory($data['category'] ?? '');

        return response()->json(['ok' => true]);
    }

    public function destroy(string $itemId): JsonResponse
    {
        if (! $this->items->softDeleteItem($itemId)) {
            return $this->error('ITEM_NOT_FOUND', 404);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * adjustStock() from Inventory.gs: move totalQty and availableQty by
     * delta, refuse to go below zero stock, and log a RESTOCK/ADJUST
     * transaction as the signed-in admin.
     */
    public function adjust(Request $request, string $itemId): JsonResponse
    {
        $item = $this->items->findItem($itemId);

        if ($item === null) {
            return $this->error('ITEM_NOT_FOUND', 404);
        }

        $delta = $request->input('delta');

        if (! is_numeric($delta) || (float) $delta == 0) {
            return $this->error('INVALID_QTY', 422);
        }

        $delta = $delta + 0;
        $newTotal = $item['totalQty'] + $delta;
        $newAvail = $item['availableQty'] + $delta;

        if ($newAvail < 0) {
            return $this->error('INSUFFICIENT_STOCK', 422);
        }

        $this->items->updateQuantities($itemId, $newTotal, $newAvail);

        $user = $request->attributes->get('currentUser');

        $this->transactions->appendTransaction([
            'txId' => (string) Str::uuid(),
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'userEmail' => $user['email'],
            'userDisplay' => $user['displayName'],
            'itemId' => $itemId,
            'itemName' => $item['name'],
            'qty' => abs($delta),
            'type' => $delta > 0 ? 'RESTOCK' : 'ADJUST',
            'status' => 'COMPLETED',
            'borrowDate' => '',
            'dueDate' => '',
            'returnDate' => '',
            'approvedBy' => $user['email'],
            'notes' => (string) $request->input('reason', ''),
        ]);

        return response()->json(['ok' => true, 'newTotal' => $newTotal, 'newAvail' => $newAvail]);
    }

    protected function ensureCategory(string $category): void
    {
        if ($category !== '') {
            $this->categories->ensureExists($category);
        }
    }

    protected function error(string $code, int $status): JsonResponse
    {
        return response()->json(['error' => $code], $status);
    }
}
