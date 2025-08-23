<?php

namespace App\Models;

use Illuminate\Auth\Passwords\CanResetPassword as CanResetPasswordTrait;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements CanResetPassword, MustVerifyEmail, JWTSubject
{
    use CanResetPasswordTrait, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'age',
        'gender',
        'location',
        'is_blocked',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_blocked' => 'boolean',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        // Cuando un usuario es actualizado
        static::updated(function ($user) {
            // Si el usuario fue bloqueado, actualizar el estado del vendedor
            if ($user->isDirty('is_blocked') && $user->is_blocked) {
                // Buscar y actualizar el perfil de vendedor asociado
                $seller = Seller::where('user_id', $user->id)->first();
                if ($seller) {
                    $seller->status = 'inactive';
                    $seller->save();
                }
            }
        });
    }

    /**
     * Obtiene los strikes asociados a este usuario.
     */
    public function strikes(): HasMany
    {
        return $this->hasMany(UserStrike::class);
    }

    /**
     * Verifica si el usuario está bloqueado.
     */
    public function isBlocked(): bool
    {
        return (bool) $this->is_blocked;
    }

    /**
     * Bloquea al usuario.
     */
    public function block(): self
    {
        $this->is_blocked = true;
        $this->save();

        return $this;
    }

    /**
     * Desbloquea al usuario.
     */
    public function unblock(): void
    {
        $this->is_blocked = false;
        $this->save();
    }

    /**
     * Obtiene el número de strikes activos del usuario.
     */
    public function getStrikeCount(): int
    {
        return $this->strikes()->count();
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function markEmailAsVerified(): bool
    {
        return $this->forceFill([
            'email_verified_at' => $this->freshTimestamp(),
        ])->save();
    }

    /**
     * Get the seller profile associated with the user
     */
    public function seller(): HasOne
    {
        return $this->hasOne(Seller::class);
    }

    /**
     * Get the orders for the user
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the ratings given by this user
     */
    public function givenRatings(): HasMany
    {
        return $this->hasMany(Rating::class, 'user_id');
    }

    /**
     * Get the ratings received by this user (as a seller)
     */
    public function receivedRatings(): HasMany
    {
        return $this->hasMany(Rating::class, 'seller_id', 'id')
            ->whereHas('seller', function ($query) {
                $query->where('user_id', $this->id);
            });
    }

    /**
     * Check if the user is a seller
     */
    public function isSeller(): bool
    {
        return $this->seller()->exists();
    }

    /**
     * Check if the user is an admin
     */
    /**
     * Check if the user is an admin
     */
    public function isAdmin(): bool
    {
        // Verificar si el usuario tiene una relación con el modelo Admin
        return $this->admin()->exists() && $this->admin->status === 'active';
    }

    /**
     * Get the admin profile associated with the user
     */
    public function admin(): HasOne
    {
        return $this->hasOne(Admin::class);
    }

    /**
     * Get the user's role
     */
    public function getRole(): string
    {
        if ($this->isAdmin()) {
            return 'admin';
        }

        if ($this->isSeller()) {
            return 'seller';
        }

        return 'user';
    }

    /**
     * Check if the user has a specific role
     */
    public function hasRole(string $role): bool
    {
        $userRole = $this->getRole();

        // Direct role match
        if ($userRole === $role) {
            return true;
        }

        // Handle super_admin as admin variation
        if ($role === 'super_admin' && $userRole === 'admin') {
            return true;
        }

        return false;
    }

    /**
     * Get the products created by this user (if seller).
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Get the ratings given by this user.
     */
    public function ratingsGiven(): HasMany
    {
        return $this->hasMany(Rating::class, 'user_id');
    }

    /**
     * Get the shopping cart for this user.
     */
    public function cart(): HasOne
    {
        return $this->hasOne(ShoppingCart::class);
    }
}
