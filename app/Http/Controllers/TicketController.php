<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TicketController extends Controller
{
    /**
     * Purchase tickets for an event.
     */
    public function purchase(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_id' => 'required|exists:events,id',
            'user_id' => 'required|exists:users,id',
            'quantity' => 'required|integer|min:1|max:10',
        ]);

        try {
            return DB::transaction(function () use ($validated) {
                $event = Event::lockForUpdate()->findOrFail($validated['event_id']);
                
                // Check if the event has already passed
                if ($event->event_date < now()) {
                    throw ValidationException::withMessages([
                        'event_id' => 'Event has already passed. Tickets cannot be purchased.'
                    ]);
                }

                // Check if enough tickets are available
                if (!$event->hasAvailableTickets($validated['quantity'])) {
                    throw ValidationException::withMessages([
                        'quantity' => 'Not enough tickets available. Only ' . $event->available_tickets . ' tickets remaining.'
                    ]);
                }

                $tickets = [];
                for ($i = 0; $i < $validated['quantity']; $i++) {
                    $tickets[] = Ticket::create([
                        'event_id' => $validated['event_id'],
                        'user_id' => $validated['user_id'],
                        'ticket_number' => Ticket::generateTicketNumber(),
                        'purchase_date' => now(),
                        'status' => 'active',
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Tickets purchased successfully',
                    'tickets' => $tickets,
                    'remaining_capacity' => $event->fresh()->available_tickets
                ], 201);
            });
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while purchasing tickets',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get tickets for a specific user.
     */
    public function getUserTickets(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $tickets = Ticket::with('event')
            ->where('user_id', $validated['user_id'])
            ->get();

        return response()->json([
            'success' => true,
            'tickets' => $tickets
        ]);
    }

    /**
     * Get available events with ticket information.
     */
    public function getAvailableEvents(): JsonResponse
    {
        $events = Event::with('tickets')
            ->where('event_date', '>', now())
            ->get()
            ->map(function ($event) {
                return [
                    'id' => $event->id,
                    'name' => $event->name,
                    'description' => $event->description,
                    'capacity' => $event->capacity,
                    'price' => $event->price,
                    'event_date' => $event->event_date,
                    'location' => $event->location,
                    'available_tickets' => $event->available_tickets,
                    'sold_tickets' => $event->tickets->count(),
                ];
            });

        return response()->json([
            'success' => true,
            'events' => $events
        ]);
    }
}