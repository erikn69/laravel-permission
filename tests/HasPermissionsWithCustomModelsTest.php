<?php

namespace Spatie\Permission\Test;

use DB;
use Spatie\Permission\PermissionRegistrar;

class HasPermissionsWithCustomModelsTest extends HasPermissionsTest
{
    /** @var bool */
    protected $useCustomModels = true;

    /** @test */
    public function it_can_use_custom_model_permission()
    {
        $this->assertSame(get_class($this->testUserPermission), Permission::class);
    }

    /** @test */
    public function it_can_use_custom_fields_from_cache()
    {
        $this->testUserRole->givePermissionTo($this->testUserPermission);
        app(PermissionRegistrar::class)->getPermissions();

        DB::enableQueryLog();
        $this->assertSame('P', Permission::findByName('edit-articles')->type);
        $this->assertSame('R', Permission::findByName('edit-articles')->roles[0]->type);
        DB::disableQueryLog();

        $this->assertSame(0, count(DB::getQueryLog()));
    }
}
