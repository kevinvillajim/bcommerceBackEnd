<?php

namespace Tests\Unit\UseCases\Chat;

use App\Domain\Entities\MessageEntity;
use App\Domain\Interfaces\ChatFilterInterface;
use App\Domain\Repositories\ChatRepositoryInterface;
use App\UseCases\Chat\SendMessageUseCase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SendMessageUseCaseTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_sends_a_valid_message()
    {
        // Crear mocks
        $chatRepository = Mockery::mock(ChatRepositoryInterface::class);
        $chatFilter = Mockery::mock(ChatFilterInterface::class);

        // Datos de prueba
        $chatId = 1;
        $senderId = 2;
        $content = 'Este es un mensaje válido';

        // Expectativas
        $chatFilter->shouldReceive('containsProhibitedContent')
            ->once()
            ->with($content, $senderId, 3)
            ->andReturn(false);

        $chatRepository->shouldReceive('addMessage')
            ->once()
            ->withArgs(function (MessageEntity $message) use ($chatId, $senderId, $content) {
                return $message->getChatId() === $chatId
                    && $message->getSenderId() === $senderId
                    && $message->getContent() === $content;
            })
            ->andReturn(new MessageEntity(
                $chatId,
                $senderId,
                $content,
                false,
                1, // ID asignado
                new \DateTime,
                new \DateTime
            ));

        // Crear el caso de uso
        $useCase = new SendMessageUseCase($chatRepository, $chatFilter,
            3
        );

        // Ejecutar
        $result = $useCase->execute($chatId, $senderId, $content);

        // Verificar
        $this->assertTrue($result['success']);
        $this->assertInstanceOf(MessageEntity::class, $result['message']);
    }

    #[Test]
    public function it_rejects_prohibited_content()
    {
        // Crear mocks
        $chatRepository = Mockery::mock(ChatRepositoryInterface::class);
        $chatFilter = Mockery::mock(ChatFilterInterface::class);

        // Datos de prueba
        $chatId = 1;
        $senderId = 2;
        $content = 'Mi número es 0987654321';
        $censoredContent = 'Mi número es ********';

        // Expectativas
        $chatFilter->shouldReceive('containsProhibitedContent')
            ->once()
            ->with($content, $senderId, 3)
            ->andReturn(true);

        $chatFilter->shouldReceive('getRejectReason')
            ->once()
            ->with($content)
            ->andReturn('contiene número telefónico');

        $chatFilter->shouldReceive('censorProhibitedContent')
            ->once()
            ->with($content)
            ->andReturn($censoredContent);

        // No debe llamar a addMessage
        $chatRepository->shouldNotReceive('addMessage');

        // Crear el caso de uso
        $useCase = new SendMessageUseCase($chatRepository, $chatFilter,
            3
        );

        // Ejecutar
        $result = $useCase->execute($chatId, $senderId, $content);

        // Verificar
        $this->assertFalse($result['success']);
        $this->assertEquals('Mensaje rechazado: contiene número telefónico', $result['message']);
        $this->assertEquals($censoredContent, $result['censored_content']);
    }
}
