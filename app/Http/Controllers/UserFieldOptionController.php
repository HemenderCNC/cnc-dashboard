<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Options;

class UserFieldOptionController extends Controller
{
    public function getOptions()
    {
        // Fetch all options
        $options = Options::all();
        // Group options by category
        $groupedOptions = $options->groupBy('category');
        
        // Format the grouped options
        $formattedOptions = $groupedOptions->map(function ($items, $category) {
            return $items->map(function ($item) {
                return [
                    'id' => $item->_id, // MongoDB ID
                    'value' => $item->value,
                    'group' => $item->group ?? null, // Optional grouping, set as null if not available
                ];
            });
        });
        
        return response()->json([
            'success' => true,
            'data' => $formattedOptions,
        ], 200);
    }
    
}