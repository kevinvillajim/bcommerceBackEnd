<?php

namespace App\UseCases\Chat;

use App\Domain\Entities\ChatEntity;
use App\Domain\Repositories\ChatRepositoryInterface;

class CreateChatUseCase
{
    private ChatRepositoryInterface $chatRepository;

    public function __construct(ChatRepositoryInterface $chatRepository)
    {
        $this->chatRepository = $chatRepository;
    }

    public function execute(int $userId, int $sellerId, int $productId): ChatEntity
    {
        $chat = new ChatEntity(
            $userId,
            $sellerId,
            $productId
        );

        return $this->chatRepository->createChat($chat);
    }
}
