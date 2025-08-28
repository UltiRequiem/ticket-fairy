<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'capacity',
        'price',
        'event_date',
        'location',
    ];

    protected $casts = [
        'event_date' => 'datetime',
        'price' => 'decimal:2',
    ];

    /**
     * Get the tickets for the event.
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * Get the number of available tickets.
     */
    public function getAvailableTicketsAttribute(): int
    {
        return $this->capacity - $this->tickets()->count();
    }

    /**
     * Check if the event has available tickets.
     */
    public function hasAvailableTickets(int $quantity = 1): bool
    {
        return $this->available_tickets >= $quantity;
    }
}