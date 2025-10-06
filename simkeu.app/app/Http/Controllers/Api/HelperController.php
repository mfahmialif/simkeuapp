<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HelperController extends Controller
{
    /**
     * Get ENUM values from a specific table column.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getEnumValues(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'table'         => 'required|string',
            'column'        => 'required|string',
            'delete_column' => 'nullable|array',
        ]);

        $table        = $validated['table'];
        $column       = $validated['column'];
        $deleteColumn = $validated['delete_column'] ?? [];

        $columnInfo = DB::select("SHOW COLUMNS FROM `$table` WHERE Field = ?", [$column]);

        if (empty($columnInfo)) {
            return response()->json([
                'message' => 'Column not found.',
            ], 404);
        }

        $type = $columnInfo[0]->Type;

        if (! preg_match('/^enum\((.*)\)$/', $type, $matches)) {
            return response()->json([
                'message' => 'The specified column is not an ENUM type.',
            ], 400);
        }

        $enum = array_map(fn($value) => trim($value, "'"), explode(',', $matches[1]));

        if (! empty($deleteColumn)) {
            $enum = array_values(array_filter($enum, fn($val) => ! in_array($val, $deleteColumn)));
        }

        return response()->json($enum);
    }
}