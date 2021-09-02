<?php

namespace Spatie\Permission;

use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Contracts\Permission;
use Spatie\Permission\Contracts\Role;

class PermissionRegistrar
{
    /** @var \Illuminate\Contracts\Cache\Repository */
    protected $cache;

    /** @var \Illuminate\Cache\CacheManager */
    protected $cacheManager;

    /** @var string */
    protected $permissionClass;

    /** @var string */
    protected $roleClass;

    /** @var \Illuminate\Database\Eloquent\Collection */
    protected $permissions;

    /** @var string */
    public static $pivotRole;

    /** @var string */
    public static $pivotPermission;

    /** @var \DateInterval|int */
    public static $cacheExpirationTime;

    /** @var bool */
    public static $teams;

    /** @var string */
    public static $teamsKey;

    /** @var int */
    protected $teamId = null;

    /** @var string */
    public static $cacheKey;

    /**
     * PermissionRegistrar constructor.
     *
     * @param \Illuminate\Cache\CacheManager $cacheManager
     */
    public function __construct(CacheManager $cacheManager)
    {
        $this->permissionClass = config('permission.models.permission');
        $this->roleClass = config('permission.models.role');

        $this->cacheManager = $cacheManager;
        $this->initializeCache();
    }

    public function initializeCache()
    {
        self::$cacheExpirationTime = config('permission.cache.expiration_time') ?: \DateInterval::createFromDateString('24 hours');

        self::$teams = config('permission.teams', false);
        self::$teamsKey = config('permission.column_names.team_foreign_key');

        self::$cacheKey = config('permission.cache.key');

        self::$pivotRole = config('permission.column_names.role_pivot_key') ?: 'role_id';
        self::$pivotPermission = config('permission.column_names.permission_pivot_key') ?: 'permission_id';

        $this->cache = $this->getCacheStoreFromConfig();
    }

    protected function getCacheStoreFromConfig(): \Illuminate\Contracts\Cache\Repository
    {
        // the 'default' fallback here is from the permission.php config file,
        // where 'default' means to use config(cache.default)
        $cacheDriver = config('permission.cache.store', 'default');

        // when 'default' is specified, no action is required since we already have the default instance
        if ($cacheDriver === 'default') {
            return $this->cacheManager->store();
        }

        // if an undefined cache store is specified, fallback to 'array' which is Laravel's closest equiv to 'none'
        if (! \array_key_exists($cacheDriver, config('cache.stores'))) {
            $cacheDriver = 'array';
        }

        return $this->cacheManager->store($cacheDriver);
    }

    /**
     * Set the team id for teams/groups support, this id is used when querying permissions/roles
     *
     * @param int $id
     */
    public function setPermissionsTeamId(?int $id)
    {
        $this->teamId = $id;
    }

    public function getPermissionsTeamId(): ?int
    {
        return $this->teamId;
    }

    /**
     * Register the permission check method on the gate.
     * We resolve the Gate fresh here, for benefit of long-running instances.
     *
     * @return bool
     */
    public function registerPermissions(): bool
    {
        app(Gate::class)->before(function (Authorizable $user, string $ability) {
            if (method_exists($user, 'checkPermissionTo')) {
                return $user->checkPermissionTo($ability) ?: null;
            }
        });

        return true;
    }

    /**
     * Flush the cache.
     */
    public function forgetCachedPermissions()
    {
        $this->permissions = null;

        return $this->cache->forget(self::$cacheKey);
    }

    /**
     * Clear class permissions.
     * This is only intended to be called by the PermissionServiceProvider on boot,
     * so that long-running instances like Swoole don't keep old data in memory.
     */
    public function clearClassPermissions()
    {
        $this->permissions = null;
    }

    /**
     * Get field names of a model from db
     */
    private function getModelColumnListing($class)
    {
        $model = new $class;
        $table = array_reverse(explode('.', $model->getTable()));
        $schema = \Schema::connection($model->getConnectionName());
        if (isset($table[1])) {
            $schema->getConnection()->setDatabaseName($table[1]);
        }

        return $schema->getColumnListing($table[0]);
    }

    /**
     * Array for cache alias
     */
    private function aliasModelsFields()
    {
        $alphas = range('a', 'n');

        $alias = [];
        $columns = array_unique(array_merge(
            $this->getModelColumnListing($this->getRoleClass()),
            $this->getModelColumnListing($this->getPermissionClass()),
            ['roles']
        ));
        foreach ($columns as $i => $value) {
            $alias[$alphas[$i] ?? $value] = $value;
        }

        return $alias;
    }

    /**
     * Changes array keys with alias
     *
     * * @return \Illuminate\Support\Collection
     */
    private function aliasedFromArray(array $item, array $alias)
    {
        return collect($item)->keyBy(function ($value, $key) use ($alias) {
            return $alias[$key] ?? $key;
        });
    }

    /**
     * Load permissions from cache
     * This get cache and turns array into \Illuminate\Database\Eloquent\Collection
     */
    private function loadPermissions()
    {
        if ($this->permissions) {
            return;
        }

        $roleClass = $this->getRoleClass();
        $permissionClass = $this->getPermissionClass();

        $this->permissions = $this->cache->remember(self::$cacheKey, self::$cacheExpirationTime, function () use ($permissionClass) {
            // make the cache smaller using an array with only aliased required fields
            $except = config('permission.cache.column_names_except', ['created_at','updated_at', 'deleted_at']);
            $alias = $this->aliasModelsFields();
            $aliasFlip = collect($alias)->flip()->except($except)->all();
            $roles = [];
            $permissions = $permissionClass->with('roles')->get()->map(function ($permission) use (&$roles, $aliasFlip, $except) {
                return $this->aliasedFromArray($permission->getAttributes(), $aliasFlip)->except($except)->all() +
                    [
                        $aliasFlip['roles'] => $permission->roles->map(function ($role) use (&$roles, $aliasFlip, $except) {
                            $id = $role->id;
                            $roles[$id] = $roles[$id] ?? $this->aliasedFromArray($role->getAttributes(), $aliasFlip)->except($except)->all();

                            return $id;
                        })->all()
                    ];
            })->all();

            return compact('alias', 'roles', 'permissions');
        });

        if (! is_array($this->permissions) || ! isset($this->permissions['permissions'])) {
            $this->forgetCachedPermissions();

            return $this->loadPermissions();
        }

        $this->permissions['roles'] = collect($this->permissions['roles'])->map(function ($item) use ($roleClass) {
            return (new $roleClass)->newFromBuilder($this->aliasedFromArray($item, $this->permissions['alias'])->all());
        });

        $this->permissions = Collection::make($this->permissions['permissions'])
            ->map(function ($item) use ($permissionClass) {
                $aliased_item = $this->aliasedFromArray($item, $this->permissions['alias']);
                $permission = (new $permissionClass)->newFromBuilder($aliased_item->except('roles')->all());
                $permission->setRelation('roles', Collection::make($aliased_item['roles'])
                    ->map(function ($id) {
                        return $this->permissions['roles'][$id];
                    }));

                return $permission;
            });
    }

    /**
     * Get the permissions based on the passed params.
     *
     * @param array $params
     * @param bool $onlyOne
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPermissions(array $params = [], bool $onlyOne = false): Collection
    {
        $this->loadPermissions();

        $method = $onlyOne ? 'first' : 'filter';

        $permissions = $this->permissions->$method(static function ($permission) use ($params) {
            foreach ($params as $attr => $value) {
                if ($permission->getAttribute($attr) != $value) {
                    return false;
                }
            }

            return true;
        });

        if ($onlyOne) {
            $permissions = new Collection($permissions ? [$permissions] : []);
        }

        return $permissions;
    }

    /**
     * Get an instance of the permission class.
     *
     * @return \Spatie\Permission\Contracts\Permission
     */
    public function getPermissionClass(): Permission
    {
        return app($this->permissionClass);
    }

    public function setPermissionClass($permissionClass)
    {
        $this->permissionClass = $permissionClass;

        return $this;
    }

    /**
     * Get an instance of the role class.
     *
     * @return \Spatie\Permission\Contracts\Role
     */
    public function getRoleClass(): Role
    {
        return app($this->roleClass);
    }

    /**
     * Get the instance of the Cache Store.
     *
     * @return \Illuminate\Contracts\Cache\Store
     */
    public function getCacheStore(): \Illuminate\Contracts\Cache\Store
    {
        return $this->cache->getStore();
    }

    /**
     * Get the instance of the Cache Repository.
     *
     * @return \Illuminate\Cache\Repository
     */
    public function getCacheRepository(): \Illuminate\Cache\Repository
    {
        return $this->cache;
    }
}
