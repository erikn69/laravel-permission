<?php

namespace Spatie\Permission\Tests;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use Laravel\Passport\Passport;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middlewares\RoleMiddleware;
use Spatie\Permission\Tests\TestModels\UserWithoutHasRoles;

class RoleMiddlewareTest extends TestCase
{
    protected $roleMiddleware;

    protected function setUp(): void
    {
        parent::setUp();

        $this->roleMiddleware = new RoleMiddleware();
    }

    /** @test */
    public function a_guest_cannot_access_a_route_protected_by_rolemiddleware()
    {
        $this->assertEquals(
            403,
            $this->runMiddleware($this->roleMiddleware, 'testRole')
        );
    }

    /** @test */
    public function a_user_cannot_access_a_route_protected_by_role_middleware_of_another_guard()
    {
        Auth::login($this->testUser);

        $this->testUser->assignRole('testRole');

        $this->assertEquals(
            403,
            $this->runMiddleware($this->roleMiddleware, 'testAdminRole')
        );
    }

    /** @test */
    public function a_client_cannot_access_a_route_protected_by_role_middleware_of_another_guard(): void
    {
        $client = Passport::actingAsClient($this->testClient, ['*']);

        $this->testClient->assignRole('clientRole');

        $this->assertEquals(
            403,
            $this->runMiddleware($this->roleMiddleware, 'testAdminRole')
        );
    }

    /** @test */
    public function a_user_can_access_a_route_protected_by_role_middleware_if_have_this_role()
    {
        Auth::login($this->testUser);

        $this->testUser->assignRole('testRole');

        $this->assertEquals(
            200,
            $this->runMiddleware($this->roleMiddleware, 'testRole')
        );
    }

    /** @test */
    public function a_client_can_access_a_route_protected_by_role_middleware_if_have_this_role(): void
    {
        Passport::actingAsClient($this->testClient, ['*']);

        $this->testClient->assignRole('clientRole');

        $this->assertEquals(
            200,
            $this->runMiddleware($this->roleMiddleware, 'clientRole')
        );
    }

    /** @test */
    public function a_user_can_access_a_route_protected_by_this_role_middleware_if_have_one_of_the_roles()
    {
        Auth::login($this->testUser);

        $this->testUser->assignRole('testRole');

        $this->assertEquals(
            200,
            $this->runMiddleware($this->roleMiddleware, 'testRole|testRole2')
        );

        $this->assertEquals(
            200,
            $this->runMiddleware($this->roleMiddleware, ['testRole2', 'testRole'])
        );
    }

    /** @test */
    public function a_client_can_access_a_route_protected_by_this_role_middleware_if_have_one_of_the_roles(): void
    {
        Passport::actingAsClient($this->testClient, ['*']);

        $this->testClient->assignRole('clientRole');

        $this->assertEquals(
            200,
            $this->runMiddleware($this->roleMiddleware, 'clientRole|testRole2')
        );

        $this->assertEquals(
            200,
            $this->runMiddleware($this->roleMiddleware, ['testRole2', 'clientRole'])
        );
    }

    /** @test */
    public function a_user_cannot_access_a_route_protected_by_the_role_middleware_if_have_not_has_roles_trait()
    {
        $userWithoutHasRoles = UserWithoutHasRoles::create(['email' => 'test_not_has_roles@user.com']);

        Auth::login($userWithoutHasRoles);

        $this->assertEquals(
            403,
            $this->runMiddleware($this->roleMiddleware, 'testRole')
        );
    }

    /** @test */
    public function a_user_cannot_access_a_route_protected_by_the_role_middleware_if_have_a_different_role()
    {
        Auth::login($this->testUser);

        $this->testUser->assignRole(['testRole']);

        $this->assertEquals(
            403,
            $this->runMiddleware($this->roleMiddleware, 'testRole2')
        );
    }

    /** @test */
    public function a_client_cannot_access_a_route_protected_by_the_role_middleware_if_have_a_different_role(): void
    {
        Passport::actingAsClient($this->testClient, ['*']);

        $this->testUser->assignRole(['clientRole']);

        $this->assertEquals(
            403,
            $this->runMiddleware($this->roleMiddleware, 'clientRole2')
        );
    }

    /** @test */
    public function a_user_cannot_access_a_route_protected_by_role_middleware_if_have_not_roles()
    {
        Auth::login($this->testUser);

        $this->assertEquals(
            403,
            $this->runMiddleware($this->roleMiddleware, 'testRole|testRole2')
        );
    }

    /** @test */
    public function a_client_cannot_access_a_route_protected_by_role_middleware_if_have_not_roles(): void
    {
        Passport::actingAsClient($this->testClient, ['*']);

        $this->assertEquals(
            403,
            $this->runMiddleware($this->roleMiddleware, 'testRole|testRole2')
        );
    }

    /** @test */
    public function a_user_cannot_access_a_route_protected_by_role_middleware_if_role_is_undefined()
    {
        Auth::login($this->testUser);

        $this->assertEquals(
            403,
            $this->runMiddleware($this->roleMiddleware, '')
        );
    }

    /** @test */
    public function a_client_cannot_access_a_route_protected_by_role_middleware_if_role_is_undefined(): void
    {
        Passport::actingAsClient($this->testClient, ['*']);

        $this->assertEquals(
            403,
            $this->runMiddleware($this->roleMiddleware, '')
        );
    }

    /** @test */
    public function the_required_roles_can_be_fetched_from_the_exception()
    {
        Auth::login($this->testUser);

        $message = null;
        $requiredRoles = [];

        try {
            $this->roleMiddleware->handle(new Request(), function () {
                return (new Response())->setContent('<html></html>');
            }, 'some-role');
        } catch (UnauthorizedException $e) {
            $message = $e->getMessage();
            $requiredRoles = $e->getRequiredRoles();
        }

        $this->assertEquals('User does not have the right roles.', $message);
        $this->assertEquals(['some-role'], $requiredRoles);
    }

    /** @test */
    public function the_required_roles_can_be_displayed_in_the_exception()
    {
        Auth::login($this->testUser);
        Config::set(['permission.display_role_in_exception' => true]);

        $message = null;

        try {
            $this->roleMiddleware->handle(new Request(), function () {
                return (new Response())->setContent('<html></html>');
            }, 'some-role');
        } catch (UnauthorizedException $e) {
            $message = $e->getMessage();
        }

        $this->assertStringEndsWith('Necessary roles are some-role', $message);
    }

    /** @test */
    public function use_not_existing_custom_guard_in_role()
    {
        $class = null;

        try {
            $this->roleMiddleware->handle(new Request(), function () {
                return (new Response())->setContent('<html></html>');
            }, 'testRole', 'xxx');
        } catch (InvalidArgumentException $e) {
            $class = get_class($e);
        }

        $this->assertEquals(InvalidArgumentException::class, $class);
    }

    /** @test */
    public function user_can_not_access_role_with_guard_admin_while_login_using_default_guard()
    {
        Auth::login($this->testUser);

        $this->testUser->assignRole('testRole');

        $this->assertEquals(
            403,
            $this->runMiddleware($this->roleMiddleware, 'testRole', 'admin')
        );
    }

    /** @test */
    public function client_can_not_access_role_with_guard_admin_while_login_using_default_guard(): void
    {
        Passport::actingAsClient($this->testClient, ['*']);

        $this->testUser->assignRole('clientRole');

        $this->assertEquals(
            403,
            $this->runMiddleware($this->roleMiddleware, 'clientRole', 'admin')
        );
    }

    /** @test */
    public function user_can_access_role_with_guard_admin_while_login_using_admin_guard()
    {
        Auth::guard('admin')->login($this->testAdmin);

        $this->testAdmin->assignRole('testAdminRole');

        $this->assertEquals(
            200,
            $this->runMiddleware($this->roleMiddleware, 'testAdminRole', 'admin')
        );
    }

    /** @test */
    public function the_middleware_can_be_created_with_static_using_method()
    {
        $this->assertSame(
            'Spatie\Permission\Middlewares\RoleMiddleware:testAdminRole',
            RoleMiddleware::using('testAdminRole')
        );
        $this->assertEquals(
            'Spatie\Permission\Middlewares\RoleMiddleware:testAdminRole,my-guard',
            RoleMiddleware::using('testAdminRole', 'my-guard')
        );
        $this->assertEquals(
            'Spatie\Permission\Middlewares\RoleMiddleware:testAdminRole|anotherRole',
            RoleMiddleware::using(['testAdminRole', 'anotherRole'])
        );
    }
}
