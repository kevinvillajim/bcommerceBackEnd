<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\UserEntity;

interface UserRepositoryInterface
{
    /**
     * Buscar un usuario por ID
     */
    public function findById(int $id): ?UserEntity;

    /**
     * Buscar un usuario por email
     */
    public function findByEmail(string $email): ?UserEntity;

    /**
     * Guardar un usuario
     */
    public function save(UserEntity $user): UserEntity;

    /**
     * Actualizar un usuario
     */
    public function update(UserEntity $user): bool;

    /**
     * Eliminar un usuario
     */
    public function delete(int $id): bool;

    /**
     * Listar usuarios con paginación opcional
     */
    public function findAll(int $page = 1, int $limit = 10): array;

    /**
     * Autenticar usuario con credenciales
     */
    public function authenticate(string $email, string $password): ?UserEntity;
}
