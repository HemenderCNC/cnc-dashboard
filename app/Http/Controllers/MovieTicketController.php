<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MovieTicket;
use App\Models\Media;
use Illuminate\Support\Facades\Validator;
use App\Services\FileUploadService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use App\Mail\MovieTicketAppliedMail;
use App\Models\User;

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
            'amount' => 'required|numeric|gt:0|max:400',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Validate only one ticket per month per user
        $ticketDate = Carbon::parse($request->date);
        $startDate = Carbon::createFromDate($ticketDate->year, $ticketDate->month, 1)->startOfMonth()->toDateString();
        $endDate = Carbon::createFromDate($ticketDate->year, $ticketDate->month, 1)->endOfMonth()->toDateString();

        $exists = MovieTicket::where('created_by', $request->user->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->exists();

        if ($exists) {
            return response()->json([
                'errors' => [
                    'date' => ['You have already submitted a movie ticket for this month. Only one ticket is allowed per month.']
                ]
            ], 422);
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

        $recipients = ['hr@codeandcore.com', 'saurabhsoni.cnc@gmail.com'];
        $recipients = array_unique(array_filter($recipients));
        
        if (!empty($recipients)) {
            try {
                Mail::to($recipients)->send(new MovieTicketAppliedMail($movieTicket, $request->user));
            } catch (\Exception $e) {
                \Log::error('Movie Ticket email failed: ' . $e->getMessage());
            }
        }

        return response()->json(['message' => 'Movie ticket added successfully', 'ticket' => $movieTicket], 201);
    }

    // Get All Movie Tickets
    public function index(Request $request)
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', -1);
        
        $yearInput = $request->input('year');
        $monthInput = $request->input('month');

        $query = MovieTicket::query();

        if ($yearInput !== 'all' && $monthInput !== 'all') {
            $year = $yearInput ? (int) $yearInput : Carbon::now()->year;
            $month = $monthInput ? (int) $monthInput : Carbon::now()->month;
            $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth()->toDateString();
            $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth()->toDateString();
            $query->whereBetween('date', [$startDate, $endDate]);
        } elseif ($yearInput !== 'all' && $monthInput === 'all') {
            $year = (int) $yearInput;
            $startDate = Carbon::createFromDate($year, 1, 1)->startOfYear()->toDateString();
            $endDate = Carbon::createFromDate($year, 12, 31)->endOfYear()->toDateString();
            $query->whereBetween('date', [$startDate, $endDate]);
        } elseif ($yearInput === 'all' && $monthInput !== 'all') {
            $month = $monthInput ? (int) $monthInput : Carbon::now()->month;
            $paddedMonth = str_pad($month, 2, '0', STR_PAD_LEFT);
            $query->where('date', 'like', "%-$paddedMonth-%");
        }

        if ($request->user->role->slug !== 'administrator') {
            $query->where('created_by', $request->user->id);
        }

        if ($limit == -1) {
            $tickets = $query->with('creator:id,name,last_name,email')->orderBy('created_at', 'desc')->get();
            $tickets = $tickets->map(function ($ticket) {
                return [
                    'id' => (string) $ticket->_id,
                    'image' => $ticket->image,
                    'date' => $ticket->date,
                    'amount' => $ticket->amount,
                    'created_by' => [
                        'id' => $ticket->creator ? (string) $ticket->creator->_id : null,
                        'name' => $ticket->creator ? trim($ticket->creator->name . ' ' . ($ticket->creator->last_name ?? '')) : null,
                        'email' => $ticket->creator ? $ticket->creator->email : null,
                    ],
                ];
            });
            return response()->json([
                'data' => $tickets,
                'meta' => [
                    'page' => 1,
                    'limit' => $limit,
                    'total' => $tickets->count(),
                    'total_pages' => 1,
                ]
            ], 200);
        }

        $tickets = $query->with('creator:id,name,last_name,email')->orderBy('created_at', 'desc')->paginate($limit, ['*'], 'page', $page);
        
        $ticketsin = $tickets->map(function ($ticket) {
            return [
                'id' => (string) $ticket->_id,
                'image' => $ticket->image,
                'date' => $ticket->date,
                'amount' => $ticket->amount,
                'created_by' => [
                    'id' => $ticket->creator ? (string) $ticket->creator->_id : null,
                    'name' => $ticket->creator ? trim($ticket->creator->name . ' ' . ($ticket->creator->last_name ?? '')) : null,
                    'email' => $ticket->creator ? $ticket->creator->email : null,
                ],
            ];
        });
        return response()->json([
            'data' => $ticketsin,
            'meta' => [
                'page' => $tickets->currentPage(),
                'limit' => $tickets->perPage(),
                'total' => $tickets->total(),
                'total_pages' => ceil($tickets->total() / $tickets->perPage()),
            ]
        ], 200);
    }

    // Get Single Movie Ticket by ID
    public function show($id)
    {
        $ticket = MovieTicket::with('creator:id,name,last_name,email')->find($id);

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
                    'name' => $ticket->creator ? trim($ticket->creator->name . ' ' . ($ticket->creator->last_name ?? '')) : null,
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
            'amount' => 'required|numeric|gt:0|max:400',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Validate only one ticket per month per user (excluding current ticket)
        $ticketDate = Carbon::parse($request->date);
        $startDate = Carbon::createFromDate($ticketDate->year, $ticketDate->month, 1)->startOfMonth()->toDateString();
        $endDate = Carbon::createFromDate($ticketDate->year, $ticketDate->month, 1)->endOfMonth()->toDateString();

        $exists = MovieTicket::where('created_by', $request->user->id)
            ->where('_id', '!=', $id)
            ->whereBetween('date', [$startDate, $endDate])
            ->exists();

        if ($exists) {
            return response()->json([
                'errors' => [
                    'date' => ['You have already submitted a movie ticket for this month. Only one ticket is allowed per month.']
                ]
            ], 422);
        }

        // Handle Image Update
        if ($request->hasFile('image')) {
            // Delete old festival image from storage and media collection
            if (!empty($ticket->image['media_id'])) {
                $media = Media::find($ticket->image['media_id']);

                if ($media) {
                    $this->fileUploadService->delete($media->file_path, $ticket->image['media_id']); // Delete file from storage
                    $media->delete(); // Remove media record from database
                }
            }
            if ($ticket->image) {
                $this->fileUploadService->delete($ticket->image['file_path'], $ticket->image['media_id']);
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
                $this->fileUploadService->delete($media->file_path, $ticket->image['media_id']); // Delete file from storage and record from Media Table
            }
        }
        $ticket->delete();
        return response()->json(['message' => 'Movie ticket deleted successfully'], 200);
    }
}
