<?php

namespace Spatie\Permission\Test;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Spatie\Permission\Traits\HasPermissionsCache;
use Spatie\Permission\Traits\HasRoles;

class Admin extends Model implements AuthorizableContract, AuthenticatableContract
{
    use HasRoles;
    use Authorizable;
    use Authenticatable;
    use HasPermissionsCache;

    protected $fillable = ['email'];

    public $timestamps = false;

    protected $table = 'admins';

    public function getMorphClass()
    {
        return 'admin';
    }
}
