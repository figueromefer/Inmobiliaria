<?php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\TicketStoreRequest;
use App\Http\Requests\TicketUpdateRequest;
use App\Http\Requests\CommentStoreRequest;
use App\Models\MaintenanceTicket;
use App\Models\MaintenanceComment;
use App\Models\Propiedad;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketWebController extends Controller
{
    public function index(Request $request)
    {
        $query = MaintenanceTicket::with(['property','creator','assignee'])
            ->when($request->property_id, fn($q,$v) => $q->where('property_id',$v))
            ->when($request->status, fn($q,$v) => $q->where('status',$v))
            ->when($request->priority, fn($q,$v) => $q->where('priority',$v))
            ->when($request->search, function($q,$v){
                $q->where(fn($qq)=>$qq->where('title','like',"%{$v}%")->orWhere('description','like',"%{$v}%"));
            })
            ->orderByDesc('created_at');

        $tickets = $query->paginate(15)->withQueryString();
        $properties = Propiedad::orderBy('alias')->get(['pk_propiedad as id','alias']);
        return view('tickets.index', compact('tickets','properties'));
    }

    public function create()
    {
        $properties = Propiedad::orderBy('alias')->get(['pk_propiedad as id','alias']);
        $users = User::orderBy('name')->get(['id','name']);
        return view('tickets.create', compact('properties','users'));
    }

    public function store(TicketStoreRequest $request)
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;
        $ticket = MaintenanceTicket::create($data);

        return redirect()
            ->route('tickets.show', $ticket)
            ->with('success','Ticket creado correctamente.');
    }

    public function show(MaintenanceTicket $ticket)
    {
        $ticket->load(['property','creator','assignee','comments.author']);
        $users = User::orderBy('name')->get(['id','name']);
        return view('tickets.show', compact('ticket','users'));
    }

    public function edit(MaintenanceTicket $ticket)
    {
        $ticket->load(['property','creator','assignee']);
        $properties = Propiedad::orderBy('alias')->get(['pk_propiedad as id','alias']);
        $users = User::orderBy('name')->get(['id','name']);
        return view('tickets.edit', compact('ticket','properties','users'));
    }

    public function update(TicketUpdateRequest $request, MaintenanceTicket $ticket)
    {
        $ticket->fill($request->validated());
        if ($ticket->status === MaintenanceTicket::STATUS_COMPLETED && !$ticket->closed_at) $ticket->closed_at = Carbon::now();
        if (in_array($ticket->status, [MaintenanceTicket::STATUS_OPEN, MaintenanceTicket::STATUS_IN_PROGRESS])) $ticket->closed_at = null;
        $ticket->save();
        return redirect()->route('tickets.show',$ticket)->with('success','Ticket actualizado.');
    }

    public function destroy(MaintenanceTicket $ticket)
    {
        $ticket->delete();
        return redirect()->route('tickets.index')->with('success','Ticket eliminado.');
    }

    public function storeComment(CommentStoreRequest $request, MaintenanceTicket $ticket)
    {
        $attachments = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $attachments[] = $file->store("tickets/{$ticket->id}/attachments", 'public');
            }
        }

        MaintenanceComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'body' => $request->input('body'),
            'attachments' => $attachments ?: null,
        ]);

        return back()->with('success','Comentario agregado.');
    }

    public function attachment(MaintenanceTicket $ticket, string $encodedPath): BinaryFileResponse|StreamedResponse
    {
        $path = base64_decode($encodedPath, true);
        abort_if(! $path, 404);
        abort_unless(str_starts_with($path, "tickets/{$ticket->id}/attachments/"), 403);
        abort_unless(Storage::disk('public')->exists($path), 404);

        $fullPath = Storage::disk('public')->path($path);
        $mime = mime_content_type($fullPath) ?: 'application/octet-stream';
        $filename = basename($path);
        $inlineMimes = ['image/jpeg','image/png','image/gif','image/webp','image/svg+xml','application/pdf'];

        if (in_array($mime, $inlineMimes, true)) {
            return response()->file($fullPath, [
                'Content-Type' => $mime,
                'Content-Disposition' => 'inline; filename="' . $filename . '"',
            ]);
        }

        return Storage::disk('public')->download($path, $filename);
    }

    public function updateStatus(Request $request, MaintenanceTicket $ticket)
    {
        $request->validate(['status' => 'required|in:open,in_progress,completed,canceled']);
        $ticket->status = $request->status;
        $ticket->closed_at = $request->status === MaintenanceTicket::STATUS_COMPLETED ? Carbon::now() : null;
        $ticket->save();
        return back()->with('success','Estatus actualizado.');
    }
}
