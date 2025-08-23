<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\ChatEntity;
use App\Domain\Entities\MessageEntity;

interface ChatRepositoryInterface
{
    public function createChat(ChatEntity $chat): ChatEntity;

    public function getChatById(int $id): ?ChatEntity;

    public function getChatsByUserId(int $userId): array;

    public function getChatsBySellerId(int $sellerId): array;

    public function addMessage(MessageEntity $message): MessageEntity;

    public function getMessagesForChat(int $chatId, int $limit = 50, int $offset = 0): array;

    public function markMessagesAsRead(int $chatId, int $userId): void;
}
