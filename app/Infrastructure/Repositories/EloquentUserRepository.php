<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Entities\UserEntity;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

class EloquentUserRepository implements UserRepositoryInterface
{
    public function findById(int $id): ?UserEntity
    {
        $user = User::find($id);

        if (! $user) {
            return null;
        }

        return $this->mapToEntity($user);
    }

    public function findByEmail(string $email): ?UserEntity
    {
        // Forzamos el tipo con esta variable adicional para satisfacer al analizador estático
        /** @var User|null $userModel */
        $userModel = User::where('email', $email)->first();

        if (! $userModel) {
            return null;
        }

        return $this->mapToEntity($userModel);
    }

    public function save(UserEntity $userEntity): UserEntity
    {
        $user = new User;
        $user->name = $userEntity->getName();
        $user->email = $userEntity->getEmail();

        if ($userEntity->getPassword()) {
            $user->password = Hash::make($userEntity->getPassword());
        }

        $user->is_blocked = $userEntity->isBlocked();
        $user->save();

        return $this->mapToEntity($user);
    }

    public function update(UserEntity $userEntity): bool
    {
        $user = User::find($userEntity->getId());

        if (! $user) {
            return false;
        }

        $user->name = $userEntity->getName();
        $user->email = $userEntity->getEmail();
        $user->age = $userEntity->getAge();
        $user->gender = $userEntity->getGender();
        $user->location = $userEntity->getLocation();
        $user->is_blocked = $userEntity->isBlocked();

        // IMPORTANTE: Solo actualizar la contraseña si no está vacía
        $password = $userEntity->getPassword();
        if ($password && strlen($password) > 0 && ! str_starts_with($password, '$2y$')) {
            $user->password = Hash::make($password);
        }

        return $user->save();
    }

    public function delete(int $id): bool
    {
        $user = User::find($id);

        if (! $user) {
            return false;
        }

        return $user->delete();
    }

    public function findAll(int $page = 1, int $limit = 10): array
    {
        $users = User::paginate($limit, ['*'], 'page', $page);

        $result = [
            'data' => [],
            'total' => $users->total(),
            'per_page' => $users->perPage(),
            'current_page' => $users->currentPage(),
            'last_page' => $users->lastPage(),
        ];

        foreach ($users->items() as $user) {
            $result['data'][] = $this->mapToEntity($user);
        }

        return $result;
    }

    public function authenticate(string $email, string $password): ?UserEntity
    {
        /** @var User|null $userModel */
        $userModel = User::where('email', $email)->first();

        if (! $userModel || ! Hash::check($password, $userModel->password)) {
            return null;
        }

        return $this->mapToEntity($userModel);
    }

    /**
     * Mapea un modelo Eloquent a una entidad de dominio
     *
     * @param  User  $user  El modelo de usuario
     * @return UserEntity La entidad de dominio
     *
     * @throws \InvalidArgumentException Si no se proporciona un modelo User válido
     */
    private function mapToEntity($user): UserEntity
    {
        // Validación de tipo robusta
        if ($user instanceof Builder) {
            throw new \InvalidArgumentException(
                'Se esperaba una instancia de User, pero se recibió una instancia de Builder. '.
                    'Asegúrate de llamar a ->first() o ->get() en tus consultas.'
            );
        }

        if (! ($user instanceof User)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Se esperaba una instancia de %s, pero se recibió %s.',
                    User::class,
                    is_object($user) ? get_class($user) : gettype($user)
                )
            );
        }

        // Manejo seguro de la relación strikes
        $strikes = [];
        if (method_exists($user, 'strikes')) {
            $strikes = $user->strikes()->get()->toArray();
        }

        // Usar correctamente el método reconstitute con el orden correcto de parámetros
        return UserEntity::reconstitute(
            $user->id,
            $user->name,
            $user->email,
            $user->password, // Pasamos el password hash directamente
            $user->age,
            $user->gender,
            $user->location,
            (bool) $user->is_blocked, // Asegurarse de que sea booleano
            $user->email_verified_at ? $user->email_verified_at->format('Y-m-d H:i:s') : null,
            $user->remember_token,
            $user->created_at ? $user->created_at->format('Y-m-d H:i:s') : null,
            $user->updated_at ? $user->updated_at->format('Y-m-d H:i:s') : null
        );
    }

    /**
     * Busca un usuario del modelo para autenticación
     *
     * @param  string  $email  El email del usuario
     * @return User|null El modelo de usuario o null si no se encuentra
     */
    public function findModelForAuth(string $email): ?User
    {
        /** @var User|null $user */
        $user = User::where('email', $email)->first();

        return $user;
    }
}
