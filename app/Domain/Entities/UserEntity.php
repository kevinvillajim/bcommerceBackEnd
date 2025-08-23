<?php

namespace App\Domain\Entities;

class UserEntity
{
    private ?int $id;

    private string $name;

    private string $email;

    private string $password;

    private ?int $age;

    private ?string $gender;

    private ?string $location;

    private bool $isBlocked;

    private ?string $emailVerifiedAt;

    private ?string $rememberToken;

    private ?string $createdAt;

    private ?string $updatedAt;

    // Relación con UserStrikeEntity
    private array $strikes = [];

    public function __construct(
        string $name,
        string $email,
        string $password,
        ?int $age = null,
        ?string $gender = null,
        ?string $location = null,
        bool $isBlocked = false,
        ?string $emailVerifiedAt = null,
        ?string $rememberToken = null,
        ?int $id = null,
        ?string $createdAt = null,
        ?string $updatedAt = null
    ) {
        $this->name = $name;
        $this->email = $email;
        $this->password = $password;
        $this->age = $age;
        $this->gender = $gender;
        $this->location = $location;
        $this->isBlocked = $isBlocked;
        $this->emailVerifiedAt = $emailVerifiedAt;
        $this->rememberToken = $rememberToken;
        $this->id = $id;
        $this->createdAt = $createdAt ?? date('Y-m-d H:i:s');
        $this->updatedAt = $updatedAt ?? date('Y-m-d H:i:s');
    }

    /**
     * Crear una nueva instancia de usuario
     */
    public static function create(
        string $name,
        string $email,
        string $password,
        ?int $age = null,
        ?string $gender = null,
        ?string $location = null
    ): self {
        return new self(
            $name,
            $email,
            $password,
            $age,
            $gender,
            $location
        );
    }

    /**
     * Reconstruir un usuario desde la base de datos
     */
    public static function reconstitute(
        int $id,
        string $name,
        string $email,
        string $password,
        ?int $age,
        ?string $gender,
        ?string $location,
        bool $isBlocked,
        ?string $emailVerifiedAt,
        ?string $rememberToken,
        string $createdAt,
        string $updatedAt
    ): self {
        return new self(
            $name,
            $email,
            $password,
            $age,
            $gender,
            $location,
            $isBlocked,
            $emailVerifiedAt,
            $rememberToken,
            $id,
            $createdAt,
            $updatedAt
        );
    }

    // Getters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getAge(): ?int
    {
        return $this->age;
    }

    public function getGender(): ?string
    {
        return $this->gender;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function isBlocked(): bool
    {
        return $this->isBlocked;
    }

    public function getEmailVerifiedAt(): ?string
    {
        return $this->emailVerifiedAt;
    }

    public function getRememberToken(): ?string
    {
        return $this->rememberToken;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?string
    {
        return $this->updatedAt;
    }

    /**
     * Obtiene los strikes del usuario
     *
     * @return UserStrikeEntity[]
     */
    public function getStrikes(): array
    {
        return $this->strikes;
    }

    // Setters
    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        $this->updateTimestamp();

        return $this;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        $this->updateTimestamp();

        return $this;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;
        $this->updateTimestamp();

        return $this;
    }

    public function setAge(?int $age): self
    {
        $this->age = $age;
        $this->updateTimestamp();

        return $this;
    }

    public function setGender(?string $gender): self
    {
        $this->gender = $gender;
        $this->updateTimestamp();

        return $this;
    }

    public function setLocation(?string $location): self
    {
        $this->location = $location;
        $this->updateTimestamp();

        return $this;
    }

    public function setBlocked(bool $isBlocked): self
    {
        $this->isBlocked = $isBlocked;
        $this->updateTimestamp();

        return $this;
    }

    public function setEmailVerifiedAt(?string $emailVerifiedAt): self
    {
        $this->emailVerifiedAt = $emailVerifiedAt;
        $this->updateTimestamp();

        return $this;
    }

    public function setRememberToken(?string $rememberToken): self
    {
        $this->rememberToken = $rememberToken;
        $this->updateTimestamp();

        return $this;
    }

    public function setCreatedAt(?string $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function setUpdatedAt(?string $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Establece los strikes del usuario
     *
     * @param  UserStrikeEntity[]  $strikes
     */
    public function setStrikes(array $strikes): self
    {
        $this->strikes = $strikes;

        return $this;
    }

    // Métodos de negocio
    public function block(): void
    {
        $this->isBlocked = true;
        $this->updateTimestamp();
    }

    public function unblock(): void
    {
        $this->isBlocked = false;
        $this->updateTimestamp();
    }

    /**
     * Añade un strike al usuario
     */
    public function addStrike(UserStrikeEntity $strike): void
    {
        $this->strikes[] = $strike;
        if ($this->getStrikeCount() >= 3) {
            $this->block();
        }
    }

    /**
     * Obtiene el número de strikes del usuario
     */
    public function getStrikeCount(): int
    {
        return count($this->strikes);
    }

    /**
     * Verifica si el email del usuario está verificado
     */
    public function isEmailVerified(): bool
    {
        return $this->emailVerifiedAt !== null;
    }

    /**
     * Marca el email del usuario como verificado
     */
    public function markEmailAsVerified(): void
    {
        $this->emailVerifiedAt = date('Y-m-d H:i:s');
        $this->updateTimestamp();
    }

    /**
     * Actualiza el timestamp de modificación
     */
    private function updateTimestamp(): void
    {
        $this->updatedAt = date('Y-m-d H:i:s');
    }

    // Método para convertir a array
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'age' => $this->age,
            'gender' => $this->gender,
            'location' => $this->location,
            'is_blocked' => $this->isBlocked,
            'email_verified_at' => $this->emailVerifiedAt,
            'remember_token' => $this->rememberToken,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'strikes' => array_map(function (UserStrikeEntity $strike) {
                return $strike->toArray();
            }, $this->strikes),
        ];
    }
}
