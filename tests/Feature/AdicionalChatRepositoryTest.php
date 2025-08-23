<?php

namespace Tests\Feature;

use App\Domain\Entities\MessageEntity;
use App\Infrastructure\Repositories\EloquentChatRepository;
use App\Models\Chat;
use App\Models\Message;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdicionalChatRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private EloquentChatRepository $repository;

    private User $buyer;

    private User $seller;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear el repositorio
        $this->repository = new EloquentChatRepository;

        // Crear usuarios y producto de prueba
        $this->buyer = User::factory()->create(['is_blocked' => false]);
        $this->seller = User::factory()->create(['is_blocked' => false]);
        $this->product = Product::factory()->create(['user_id' => $this->seller->id]);
    }

    #[Test]
    public function it_gets_chat_by_id()
    {
        // Crear una chat en la base de datos
        $chat = Chat::create([
            'user_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'product_id' => $this->product->id,
            'status' => 'active',
        ]);

        // Crear algunos mensajes
        Message::create([
            'chat_id' => $chat->id,
            'sender_id' => $this->buyer->id,
            'content' => 'Hola',
            'is_read' => false,
        ]);

        Message::create([
            'chat_id' => $chat->id,
            'sender_id' => $this->seller->id,
            'content' => 'Hola, ¿cómo estás?',
            'is_read' => false,
        ]);

        // Obtener la chat por ID
        $chat = $this->repository->getChatById($chat->id);

        // Verificar que se obtiene correctamente
        $this->assertNotNull($chat);
        $this->assertEquals($chat->getId(), $chat->getId());
        $this->assertEquals($this->buyer->id, $chat->getUserId());
        $this->assertEquals($this->seller->id, $chat->getSellerId());
        $this->assertEquals($this->product->id, $chat->getProductId());
        $this->assertEquals('active', $chat->getStatus());

        // Verificar que se incluyan los mensajes
        $messages = $this->repository->getMessagesForChat($chat->getId());
        $this->assertCount(2, $messages);
    }

    #[Test]
    public function it_returns_null_when_chat_not_found()
    {
        // Intentar obtener una chat inexistente
        $chat = $this->repository->getChatById(999);

        // Verificar que devuelve null
        $this->assertNull($chat);
    }

    #[Test]
    public function it_gets_chats_by_user_id()
    {
        // Crear varias chat para el mismo comprador
        $chat1 = Chat::create([
            'user_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'product_id' => $this->product->id,
            'status' => 'active',
        ]);

        $anotherSeller = User::factory()->create(['is_blocked' => false]);
        $anotherProduct = Product::factory()->create(['user_id' => $anotherSeller->id]);

        $chat2 = Chat::create([
            'user_id' => $this->buyer->id,
            'seller_id' => $anotherSeller->id,
            'product_id' => $anotherProduct->id,
            'status' => 'active',
        ]);

        // Obtener chat por ID de usuario
        $chats = $this->repository->getChatsByUserId($this->buyer->id);

        // Verificar que se obtienen todas las chat
        $this->assertCount(2, $chats);
        $this->assertEquals($chat1->id, $chats[0]->getId());
        $this->assertEquals($chat2->id, $chats[1]->getId());
    }

    #[Test]
    public function it_gets_chats_by_seller_id()
    {
        // Crear varias chat para el mismo vendedor
        $chat1 = Chat::create([
            'user_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'product_id' => $this->product->id,
            'status' => 'active',
        ]);

        $anotherBuyer = User::factory()->create(['is_blocked' => false]);

        $chat2 = Chat::create([
            'user_id' => $anotherBuyer->id,
            'seller_id' => $this->seller->id,
            'product_id' => $this->product->id,
            'status' => 'active',
        ]);

        // Obtener chat por ID de vendedor
        $chats = $this->repository->getChatsBySellerId($this->seller->id);

        // Verificar que se obtienen todas las chat
        $this->assertCount(2, $chats);
        $this->assertEquals($chat1->id, $chats[0]->getId());
        $this->assertEquals($chat2->id, $chats[1]->getId());
    }

    #[Test]
    public function it_gets_messages_with_pagination()
    {
        // Crear una chat en la base de datos
        $chat = Chat::create([
            'user_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'product_id' => $this->product->id,
            'status' => 'active',
        ]);

        // Crear varios mensajes
        for ($i = 1; $i <= 10; $i++) {
            Message::create([
                'chat_id' => $chat->id,
                'sender_id' => $i % 2 == 0 ? $this->buyer->id : $this->seller->id,
                'content' => "Mensaje $i",
                'is_read' => false,
            ]);
        }

        // Probar paginación: obtener primeros 5 mensajes
        $firstPage = $this->repository->getMessagesForChat($chat->id, 5, 0);
        $this->assertCount(5, $firstPage);
        $this->assertEquals('Mensaje 10', $firstPage[0]->getContent());
        $this->assertEquals('Mensaje 6', $firstPage[4]->getContent());

        // Probar segunda página
        $secondPage = $this->repository->getMessagesForChat($chat->id, 5, 5);
        $this->assertCount(5, $secondPage);
        $this->assertEquals('Mensaje 5', $secondPage[0]->getContent());
        $this->assertEquals('Mensaje 1', $secondPage[4]->getContent());
    }

    #[Test]
    public function it_updates_chat_timestamp_when_adding_message()
    {
        // Crear una chat en la base de datos
        $chat = Chat::create([
            'user_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'product_id' => $this->product->id,
            'status' => 'active',
        ]);

        // Obtener timestamp original
        $originalUpdatedAt = $chat->updated_at;

        // Esperar un momento para asegurar diferencia en timestamp
        sleep(1);

        // Añadir un mensaje
        $message = new MessageEntity(
            $chat->id,
            $this->buyer->id,
            'Hola, estoy interesado en tu producto'
        );

        $this->repository->addMessage($message);

        // Recargar el modelo desde la base de datos
        $chat = Chat::find($chat->id);

        // Verificar que el timestamp se actualizó
        $this->assertNotEquals($originalUpdatedAt->timestamp, $chat->updated_at->timestamp);
    }

    #[Test]
    public function it_handles_empty_message_list()
    {
        // Crear una chat en la base de datos sin mensajes
        $chat = Chat::create([
            'user_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'product_id' => $this->product->id,
            'status' => 'active',
        ]);

        // Obtener mensajes
        $messages = $this->repository->getMessagesForChat($chat->id);

        // Verificar que devuelve un array vacío
        $this->assertIsArray($messages);
        $this->assertEmpty($messages);
    }

    #[Test]
    public function it_returns_empty_array_for_nonexistent_chats()
    {
        // Intentar obtener chat para un usuario que no existe
        $chats = $this->repository->getChatsByUserId(9999);

        // Verificar que devuelve un array vacío
        $this->assertIsArray($chats);
        $this->assertEmpty($chats);

        // Lo mismo para vendedor
        $chats = $this->repository->getChatsBySellerId(9999);
        $this->assertIsArray($chats);
        $this->assertEmpty($chats);
    }
}
