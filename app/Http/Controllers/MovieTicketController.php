<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MovieTicket;
use App\Models\Media;
use Illuminate\Support\Facades\Validator;
use App\Services\FileUploadService;

class MovieTicketController extends Controller
{
    protected $fileUploadService;

    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
    }

    // Create Movie Ticket
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg|max:2048',
            'date' => 'required|date|before_or_equal:today',
            'amount' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Handle File Upload
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $this->fileUploadService->upload($request->file('image'), 'uploads', $request->user->id);
        }

        $movieTicket = MovieTicket::create([
            'image' => $imagePath,
            'date' => $request->date,
            'amount' => $request->amount,
            'created_by' => $request->user->id,
        ]);

        return response()->json(['message' => 'Movie ticket added successfully', 'ticket' => $movieTicket], 201);
    }

    // Get All Movie Tickets
    public function index()
    {
        $tickets = MovieTicket::with('creator:id,name,email') // Eager load creator details
        ->orderBy('created_at', 'desc')
        ->get();

        return response()->json([
            'tickets' => $tickets->map(function ($ticket) {
                return [
                    'id' => (string) $ticket->_id,
                    'image' => $ticket->image,
                    'date' => $ticket->date,
                    'amount' => $ticket->amount,
                    'created_by' => [
                        'id' => $ticket->creator ? (string) $ticket->creator->_id : null,
                        'name' => $ticket->creator ? $ticket->creator->name : null,
                        'email' => $ticket->creator ? $ticket->creator->email : null,
                    ],
                ];
            }),
        ], 200);
    }

    // Get Single Movie Ticket by ID
    public function show($id)
    {
        $ticket = MovieTicket::with('creator:id,name,email')->find($id);

        if (!$ticket) {
            return response()->json(['message' => 'Movie ticket not found'], 404);
        }
    
        return response()->json([
            'ticket' => [
                'id' => (string) $ticket->_id,
                'image' => $ticket->image,
                'date' => $ticket->date,
                'amount' => $ticket->amount,
                'created_by' => [
                    'id' => $ticket->creator ? (string) $ticket->creator->_id : null,
                    'name' => $ticket->creator ? $ticket->creator->name : null,
                    'email' => $ticket->creator ? $ticket->creator->email : null,
                ],
            ],
        ], 200);
    }

    // Update Movie Ticket
    public function update(Request $request, $id)
    {
        $ticket = MovieTicket::find($id);
        if (!$ticket) {
            return response()->json(['message' => 'Ticket not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'date' => 'required|date|before_or_equal:today',
            'amount' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Handle Image Update
        if ($request->hasFile('image')) {
            // Delete old festival image from storage and media collection
            if (!empty($ticket->image['media_id'])) {
                $media = Media::find($ticket->image['media_id']);

                if ($media) {
                    $this->fileUploadService->delete($media->file_path,$ticket->image['media_id']); // Delete file from storage
                    $media->delete(); // Remove media record from database
                }
            }
            if ($ticket->image) {
                $this->fileUploadService->delete($ticket->image['file_path'],$ticket->image['media_id']);
            }
            $imagePath = $this->fileUploadService->upload($request->file('image'), 'uploads', $request->user->id);
            $ticket->image = $imagePath;
        }


        $ticket->date = $request->date;
        $ticket->amount = $request->amount;
        $ticket->save();

        return response()->json(['message' => 'Movie ticket updated successfully', 'ticket' => $ticket], 200);
    }

    // Delete Movie Ticket
    public function destroy($id)
    {
        $ticket = MovieTicket::find($id);
        if (!$ticket) {
            return response()->json(['message' => 'Ticket not found'], 404);
        }

        // Check if the holiday has an associated image
        if (!empty($ticket->image['media_id'])) {
            $media = Media::find($ticket->image['media_id']);

            if ($media) {
                $this->fileUploadService->delete($media->file_path,$ticket->image['media_id']); // Delete file from storage and record from Media Table
            }
        }
        $ticket->delete();
        return response()->json(['message' => 'Movie ticket deleted successfully'], 200);
    }
}
