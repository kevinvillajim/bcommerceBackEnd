<?php

namespace App\Domain\Entities;

use Carbon\Carbon;

class AdminLogEntity
{
    private int $id;

    private string $level;

    private string $eventType;

    private string $message;

    private ?array $context;

    private ?string $method;

    private ?string $url;

    private ?string $ipAddress;

    private ?string $userAgent;

    private ?int $userId;

    private ?int $statusCode;

    private string $errorHash;

    private Carbon $createdAt;

    private ?array $user;

    public function __construct(
        int $id,
        string $level,
        string $eventType,
        string $message,
        ?array $context,
        ?string $method,
        ?string $url,
        ?string $ipAddress,
        ?string $userAgent,
        ?int $userId,
        ?int $statusCode,
        string $errorHash,
        Carbon $createdAt,
        ?array $user = null
    ) {
        $this->id = $id;
        $this->level = $level;
        $this->eventType = $eventType;
        $this->message = $message;
        $this->context = $context;
        $this->method = $method;
        $this->url = $url;
        $this->ipAddress = $ipAddress;
        $this->userAgent = $userAgent;
        $this->userId = $userId;
        $this->statusCode = $statusCode;
        $this->errorHash = $errorHash;
        $this->createdAt = $createdAt;
        $this->user = $user;
    }

    // Getters
    public function getId(): int
    {
        return $this->id;
    }

    public function getLevel(): string
    {
        return $this->level;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getContext(): ?array
    {
        return $this->context;
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    public function getErrorHash(): string
    {
        return $this->errorHash;
    }

    public function getCreatedAt(): Carbon
    {
        return $this->createdAt;
    }

    public function getUser(): ?array
    {
        return $this->user;
    }

    /**
     * Verificar si es un error crítico
     */
    public function isCritical(): bool
    {
        return $this->level === 'critical';
    }

    /**
     * Verificar si es un error
     */
    public function isError(): bool
    {
        return in_array($this->level, ['critical', 'error']);
    }

    /**
     * Obtener el tiempo transcurrido desde que se creó el log
     */
    public function getTimeAgo(): string
    {
        return $this->createdAt->diffForHumans();
    }

    /**
     * Convertir a array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'level' => $this->level,
            'event_type' => $this->eventType,
            'message' => $this->message,
            'context' => $this->context,
            'method' => $this->method,
            'url' => $this->url,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'user_id' => $this->userId,
            'status_code' => $this->statusCode,
            'error_hash' => $this->errorHash,
            'created_at' => $this->createdAt->toISOString(),
            'time_ago' => $this->getTimeAgo(),
            'is_critical' => $this->isCritical(),
            'is_error' => $this->isError(),
            'user' => $this->user,
        ];
    }
}
