<?php

namespace Spatie\Permission\Test;

class HasPermissionsWithCustomModelsTest extends HasPermissionsTest
{
    /** @var bool */
    protected $useCustomModels = true;

    /** @test */
    public function it_can_use_custom_model_permission()
    {
        $this->assertSame(get_class($this->testUserPermission), \Spatie\Permission\Test\Permission::class);
    }

    /** @test */
    public function it_can_scope_users_using_a_int()
    {
        // as id is uuid here, this test allways is gonna fail
        $this->assertTrue(true);
    }

    /** @test */
    public function it_can_scope_users_using_a_uuid()
    {
        $user1 = User::create(['email' => 'user1@test.com']);
        $user2 = User::create(['email' => 'user2@test.com']);
        $user1->givePermissionTo(['edit-articles', 'edit-news']);
        $this->testUserRole->givePermissionTo('edit-articles');
        $user2->assignRole('testRole');

        $scopedUsers1 = User::permission($this->testUserPermission->getKey())->get();
        $scopedUsers2 = User::permission([Permission::findByName('edit-news')->getKey()])->get();

        $this->assertEquals(2, $scopedUsers1->count());
        $this->assertEquals(1, $scopedUsers2->count());
    }
}
