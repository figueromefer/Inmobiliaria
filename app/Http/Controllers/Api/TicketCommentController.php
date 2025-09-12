<?php
// app/Http/Controllers/Api/TicketCommentController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CommentStoreRequest;
use App\Models\MaintenanceTicket;
use App\Models\MaintenanceComment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TicketCommentController extends Controller
{
    public function index(MaintenanceTicket $ticket)
    {
        return response()->json($ticket->comments()->with('author')->paginate(50));
    }

    public function store(CommentStoreRequest $request, MaintenanceTicket $ticket)
    {
        $attachments = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $attachments[] = $file->store("tickets/{$ticket->id}/attachments", 'public');
            }
        }

        $comment = MaintenanceComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'body' => $request->input('body'),
            'attachments' => $attachments ?: null,
        ]);

        // event(new TicketCommented($ticket, $comment));

        return response()->json($comment->load('author'), 201);
    }
}
