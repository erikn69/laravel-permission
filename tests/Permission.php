<?php

namespace Spatie\Permission\Test;

class Permission extends \Spatie\Permission\Models\Permission
{
    protected $primaryKey = 'permission_id';

    protected $visible = [
      'permission_id',
      'name',
    ];
}
