<?php

namespace Tests\Feature;

use App\Domain\Entities\ChatEntity;
use App\Domain\Entities\MessageEntity;
use App\Infrastructure\Repositories\EloquentChatRepository;
use App\Models\Chat;
use App\Models\Message;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChatRepositoryTest extends TestCase
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
    public function it_creates_a_chat()
    {
        // Crear una entidad de chat
        $Chat = new ChatEntity(
            $this->buyer->id,
            $this->seller->id,
            $this->product->id
        );

        // Guardar la chat
        $savedChat = $this->repository->createChat($Chat);

        // Verificar que se guardó correctamente
        $this->assertNotNull($savedChat->getId());
        $this->assertEquals($this->buyer->id, $savedChat->getUserId());
        $this->assertEquals($this->seller->id, $savedChat->getSellerId());
        $this->assertEquals($this->product->id, $savedChat->getProductId());

        // Verificar en la base de datos
        $this->assertDatabaseHas('chats', [
            'id' => $savedChat->getId(),
            'user_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'product_id' => $this->product->id,
            'status' => 'active',
        ]);
    }

    #[Test]
    public function it_adds_a_message_to_chat()
    {
        // Crear una chat en la base de datos
        $chat = Chat::create([
            'user_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'product_id' => $this->product->id,
            'status' => 'active',
        ]);

        // Crear una entidad de mensaje
        $message = new MessageEntity(
            $chat->id,
            $this->buyer->id,
            'Hola, estoy interesado en tu producto'
        );

        // Guardar el mensaje
        $savedMessage = $this->repository->addMessage($message);

        // Verificar que se guardó correctamente
        $this->assertNotNull($savedMessage->getId());
        $this->assertEquals($chat->id, $savedMessage->getChatId());
        $this->assertEquals($this->buyer->id, $savedMessage->getSenderId());
        $this->assertEquals('Hola, estoy interesado en tu producto', $savedMessage->getContent());

        // Verificar en la base de datos
        $this->assertDatabaseHas('messages', [
            'id' => $savedMessage->getId(),
            'chat_id' => $chat->id,
            'sender_id' => $this->buyer->id,
            'content' => 'Hola, estoy interesado en tu producto',
            'is_read' => false,
        ]);
    }

    #[Test]
    public function it_retrieves_messages_for_chat()
    {
        // Crear una chat en la base de datos
        $chat = Chat::create([
            'user_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'product_id' => $this->product->id,
            'status' => 'active',
        ]);

        // Crear varios mensajes
        $messages = [
            ['chat_id' => $chat->id, 'sender_id' => $this->buyer->id, 'content' => 'Mensaje 1', 'is_read' => false],
            ['chat_id' => $chat->id, 'sender_id' => $this->seller->id, 'content' => 'Mensaje 2', 'is_read' => true],
            ['chat_id' => $chat->id, 'sender_id' => $this->buyer->id, 'content' => 'Mensaje 3', 'is_read' => false],
        ];

        foreach ($messages as $msg) {
            Message::create($msg);
        }

        // Obtener mensajes
        $retrievedMessages = $this->repository->getMessagesForChat($chat->id);

        // Verificar que se obtuvieron correctamente
        $this->assertCount(3, $retrievedMessages);
        $this->assertEquals('Mensaje 3', $retrievedMessages[0]->getContent());
        $this->assertEquals('Mensaje 2', $retrievedMessages[1]->getContent());
        $this->assertEquals('Mensaje 1', $retrievedMessages[2]->getContent());
    }

    #[Test]
    public function it_marks_messages_as_read()
    {
        // Crear una chat en la base de datos
        $chat = Chat::create([
            'user_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'product_id' => $this->product->id,
            'status' => 'active',
        ]);

        // Crear mensajes no leídos del vendedor
        Message::create([
            'chat_id' => $chat->id,
            'sender_id' => $this->seller->id,
            'content' => 'Mensaje del vendedor 1',
            'is_read' => false,
        ]);

        Message::create([
            'chat_id' => $chat->id,
            'sender_id' => $this->seller->id,
            'content' => 'Mensaje del vendedor 2',
            'is_read' => false,
        ]);

        // Crear un mensaje del comprador
        Message::create([
            'chat_id' => $chat->id,
            'sender_id' => $this->buyer->id,
            'content' => 'Mensaje del comprador',
            'is_read' => false,
        ]);

        // Marcar como leídos los mensajes del vendedor
        $this->repository->markMessagesAsRead($chat->id, $this->buyer->id);

        // Verificar que se marcaron como leídos
        $this->assertDatabaseCount('messages', 3);
        $this->assertDatabaseHas('messages', [
            'sender_id' => $this->seller->id,
            'is_read' => true,
        ]);

        // El mensaje del comprador no debe marcarse como leído
        $this->assertDatabaseHas('messages', [
            'sender_id' => $this->buyer->id,
            'is_read' => false,
        ]);
    }
}
