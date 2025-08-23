<?php

namespace Database\Seeders;

use App\Models\Chat;
use App\Models\Message;
use Illuminate\Database\Seeder;

class ChatSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear chats
        $chats = Chat::factory()
            ->count(10)
            ->create();

        // Crear mensajes para cada chat
        foreach ($chats as $chat) {
            // Entre 1 y 15 mensajes por chat
            $messageCount = rand(1, 15);

            // El primer mensaje siempre es del comprador
            Message::factory()
                ->fromBuyer()
                ->create([
                    'chat_id' => $chat->id,
                    'content' => '¡Hola! Tengo interés en este producto. ¿Podrías darme más información?',
                    'created_at' => now()->subDays(rand(1, 30))->subHours(rand(1, 24)),
                ]);

            // El resto de mensajes son aleatorios
            for ($i = 1; $i < $messageCount; $i++) {
                $isBuyer = rand(0, 1) == 1;
                $content = $isBuyer
                    ? $this->getRandomBuyerMessage()
                    : $this->getRandomSellerMessage();

                Message::factory()
                    ->create([
                        'chat_id' => $chat->id,
                        'sender_id' => $isBuyer ? $chat->user_id : $chat->seller_id,
                        'content' => $content,
                        'created_at' => now()->subDays(rand(0, 30))->subHours(rand(0, 24))->subMinutes(rand(1, 59)),
                    ]);
            }
        }
    }

    /**
     * Obtener un mensaje aleatorio de comprador
     */
    private function getRandomBuyerMessage(): string
    {
        $messages = [
            '¿Cuánto tiempo tardaría en llegar a mi domicilio?',
            '¿Tienen stock disponible?',
            '¿Viene con garantía?',
            '¿Aceptan pagos con tarjeta de crédito?',
            '¿Hacen envíos a mi zona?',
            '¿Tienen este producto en otros colores?',
            '¿Cuál es el tamaño/dimensiones exactas?',
            '¿Podrían enviarme más fotos del producto?',
            '¿Este precio incluye IVA?',
            '¿Tienen descuento por compra de varios productos?',
            'Gracias por la información. Lo voy a pensar.',
            '¿Tienen tienda física donde pueda ver el producto?',
            '¡Genial! Me interesa, voy a realizar la compra.',
            '¿Qué diferencia hay con el modelo anterior?',
        ];

        return $messages[array_rand($messages)];
    }

    /**
     * Obtener un mensaje aleatorio de vendedor
     */
    private function getRandomSellerMessage(): string
    {
        $messages = [
            '¡Hola! Claro, te cuento más detalles sobre el producto...',
            'El envío tarda aproximadamente 2-3 días hábiles.',
            'Sí, contamos con stock disponible en todos los colores.',
            'El producto incluye 1 año de garantía por defectos de fabricación.',
            'Aceptamos todos los medios de pago: tarjetas, transferencias y efectivo.',
            'Realizamos envíos a todo el país sin costo adicional.',
            'Las dimensiones exactas son 30cm x 20cm x 15cm.',
            'Te he enviado más fotos por correo electrónico.',
            'Todos nuestros precios incluyen IVA.',
            'Tenemos un 10% de descuento al comprar 3 o más unidades.',
            'Estamos a tus órdenes para cualquier duda adicional.',
            'Nuestra tienda física está ubicada en el centro comercial.',
            '¡Gracias por tu compra! Te enviaremos el seguimiento en cuanto se despache.',
            'Este modelo cuenta con funciones mejoradas respecto al anterior.',
        ];

        return $messages[array_rand($messages)];
    }
}
