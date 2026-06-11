<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento de broadcast (US03, Escenario 3): el backend sincronizó el balance
 * del exchange y el dashboard debe actualizarse de forma reactiva vía
 * WebSockets (Reverb) sobre el canal privado del usuario.
 */
class BalanceUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly float $balance,
        public readonly \DateTimeInterface $capturedAt,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        // Canal ya autorizado en routes/channels.php (App.Models.User.{id})
        return new PrivateChannel('App.Models.User.'.$this->user->id);
    }

    public function broadcastAs(): string
    {
        return 'balance.updated';
    }

    /**
     * Payload mínimo: solo lo que la UI necesita para reaccionar.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'balance' => round($this->balance, 2),
            'captured_at' => $this->capturedAt->format(DATE_ATOM),
        ];
    }
}
