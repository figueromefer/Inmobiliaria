<?php
// app/Http/Controllers/Api/TicketController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TicketStoreRequest;
use App\Http\Requests\TicketUpdateRequest;
use App\Models\MaintenanceTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $query = MaintenanceTicket::with(['property','creator','assignee'])
            ->when($request->property_id, fn($q,$v) => $q->where('property_id', $v))
            ->when($request->status, fn($q,$v) => $q->where('status', $v))
            ->when($request->priority, fn($q,$v) => $q->where('priority', $v))
            ->when($request->search, function($q,$v){
                $q->where(function($qq) use ($v){
                    $qq->where('title','like',"%{$v}%")
                       ->orWhere('description','like',"%{$v}%");
                });
            })
            ->orderByDesc('created_at');

        return response()->json($query->paginate(20));
    }

    public function store(TicketStoreRequest $request)
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;
        $ticket = MaintenanceTicket::create($data);

        // (Opcional) Notificar asignado
        // event(new TicketCreated($ticket));

        return response()->json($ticket->load(['property','creator','assignee']), 201);
    }

    public function show(MaintenanceTicket $ticket)
    {
        $ticket->load(['property','creator','assignee','comments.author']);
        return response()->json($ticket);
    }

    public function update(TicketUpdateRequest $request, MaintenanceTicket $ticket)
    {
        $oldStatus = $ticket->status;
        $ticket->fill($request->validated());

        if ($ticket->status === MaintenanceTicket::STATUS_COMPLETED && !$ticket->closed_at) {
            $ticket->closed_at = Carbon::now();
        }
        if (in_array($ticket->status, [MaintenanceTicket::STATUS_OPEN, MaintenanceTicket::STATUS_IN_PROGRESS]) ) {
            $ticket->closed_at = null;
        }
        if ($ticket->status === MaintenanceTicket::STATUS_COMPLETED && $ticket->priority === 'high') {
            $ticket->priority = null;
        }

        $ticket->save();

        if ($oldStatus !== $ticket->status) {
            // event(new TicketStatusChanged($ticket, $oldStatus, $ticket->status));
        }

        return response()->json($ticket->load(['property','creator','assignee']));
    }

    public function destroy(MaintenanceTicket $ticket)
    {
        $ticket->delete();
        return response()->json(['ok' => true]);
    }
}
