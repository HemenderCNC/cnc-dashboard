<?php
namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class MongoTestController extends Controller
{
    public function index()
    {
        try {
            // Try reading databases (forces connection)
            $databases = DB::connection('mongodb')->getMongoClient()->listDatabases();
            return response()->json([
                'status' => true,
                'message' => 'MongoDB Connection OK',
                'databases' => $databases,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'MongoDB Connection Failed: ' . $e->getMessage(),
            ]);
        }
    }
}
