<?php

namespace App\Services;

use App\Models\User;

class AuthManager
{
    /**
     * @var User|null
     */
    protected $user = null;

    /**
     * Get the currently authenticated user.
     *
     * @return User|null
     */
    public function user()
    {
        return $this->user;
    }

    /**
     * Get the ID for the currently authenticated user.
     *
     * @return int|null
     */
    public function id()
    {
        return $this->user ? $this->user->id : null;
    }

    /**
     * Determine if the current user is authenticated.
     *
     * @return bool
     */
    public function check()
    {
        return $this->user !== null;
    }

    /**
     * Set the current user.
     *
     * @return void
     */
    public function setUser(User $user)
    {
        $this->user = $user;
    }
}
