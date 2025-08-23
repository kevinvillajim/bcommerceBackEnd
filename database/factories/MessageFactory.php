<?php

namespace Database\Factories;

use App\Models\Chat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Message>
 */
class MessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $chat = Chat::inRandomOrder()->first();

        // Si no hay chats, crear uno
        if (! $chat) {
            $chat = Chat::factory()->create();
        }

        // Obtener IDs del chat
        $userId = $chat->user_id;
        $sellerId = $chat->seller_id;

        // Elegir aleatoriamente si el mensaje es del comprador o del vendedor
        $senderId = $this->faker->boolean ? $userId : $sellerId;

        return [
            'chat_id' => $chat->id,
            'sender_id' => $senderId,
            'content' => $this->faker->paragraph,
            'is_read' => $this->faker->boolean(70), // 70% de probabilidad de que sea leído
        ];
    }

    /**
     * Mensaje no leído
     */
    public function unread()
    {
        return $this->state(function (array $attributes) {
            return [
                'is_read' => false,
            ];
        });
    }

    /**
     * Mensaje enviado por el comprador
     */
    public function fromBuyer()
    {
        return $this->state(function (array $attributes) {
            $chat = Chat::find($attributes['chat_id']);

            return [
                'sender_id' => $chat->user_id,
            ];
        });
    }

    /**
     * Mensaje enviado por el vendedor
     */
    public function fromSeller()
    {
        return $this->state(function (array $attributes) {
            $chat = Chat::find($attributes['chat_id']);

            return [
                'sender_id' => $chat->seller_id,
            ];
        });
    }
}
