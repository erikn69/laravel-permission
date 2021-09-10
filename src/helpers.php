<?php

if (! function_exists('getModelForGuard')) {
    /**
     * @param string $guard
     *
     * @return string|null
     */
    function getModelForGuard(string $guard)
    {
        return collect(config('auth.guards'))
            ->map(function ($guard) {
                if (! isset($guard['provider'])) {
                    return;
                }

                return config("auth.providers.{$guard['provider']}.model");
            })->get($guard);
    }
}

if (! function_exists('setPermissionsTeamId')) {
    /**
     * @param int $id
     *
     */
    function setPermissionsTeamId(int $id)
    {
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($id);
    }
}

if( !function_exists('toRawSql') ){ function toRawSql($sql,$bindings=[]){ $aux='/fix/'.\Str::uuid().'/fix/';
    return str_replace($aux, '?', array_reduce($bindings,function($sql,$binding)use($aux){
        return preg_replace('/\?/', (is_string($binding) ? "'".str_replace(['\'','?'],["\\'",$aux],$binding)."'" : (is_null($binding)? 'NULL' :(is_numeric($binding)? $binding : ( is_bool($binding) ? (int)$binding : ( $binding instanceof \DateTime? $binding->format('\'Y-m-d H:i:s\'') : "'".str_replace('\'',"\\'",is_array($binding)? json_encode($binding) : $binding )."'" ) ) ) ) ) , $sql, 1);
    },$sql));
} }
if( !function_exists('logsSql') ){ function logsSql(){
    echo PHP_EOL;
    \DB::listen(function($query){
        echo toRawSql($query->sql,$query->bindings).PHP_EOL;
    });
} }
