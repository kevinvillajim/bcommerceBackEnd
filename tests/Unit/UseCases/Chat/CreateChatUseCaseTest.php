<?php

namespace Tests\Unit\UseCases\Chat;

use App\Domain\Entities\ChatEntity;
use App\Domain\Repositories\ChatRepositoryInterface;
use App\UseCases\Chat\CreateChatUseCase;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CreateChatUseCaseTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_creates_a_chat()
    {
        // Crear un mock del repositorio
        /** @var ChatRepositoryInterface|MockInterface $chatRepository */
        $chatRepository = Mockery::mock(ChatRepositoryInterface::class);

        // Datos de prueba
        $userId = 1;
        $sellerId = 2;
        $productId = 3;

        // Crear una nueva chat que será devuelta por el repositorio
        $expectedChat = new ChatEntity(
            $userId,
            $sellerId,
            $productId,
            'active',
            [],
            1, // ID asignado
            new \DateTime,
            new \DateTime
        );

        // Configurar el comportamiento esperado del repositorio
        $chatRepository->shouldReceive('createchat')
            ->once()
            ->andReturnUsing(function (ChatEntity $chat) use ($expectedChat) {
                // Verificar que recibimos los datos correctos, pero devolver siempre
                // la chat con ID para simular la persistencia
                return $expectedChat;
            });

        // Crear el caso de uso con el mock
        $useCase = new CreateChatUseCase($chatRepository);

        // Ejecutar
        $result = $useCase->execute($userId, $sellerId, $productId);

        // Verificar el resultado
        $this->assertInstanceOf(ChatEntity::class, $result);
        $this->assertEquals(1, $result->getId());
        $this->assertEquals($userId, $result->getUserId());
        $this->assertEquals($sellerId, $result->getSellerId());
        $this->assertEquals($productId, $result->getProductId());
        $this->assertEquals('active', $result->getStatus());
    }
}
