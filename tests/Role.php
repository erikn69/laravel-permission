<?php

namespace Spatie\Permission\Test;

class Role extends \Spatie\Permission\Models\Role
{
    protected $primaryKey = 'role_id';

    protected $visible = [
      'role_id',
      'name',
    ];
}
