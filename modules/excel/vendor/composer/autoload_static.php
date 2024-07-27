<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit96662d1f59aa64d8ac2140b75a7025a8
{
    public static $prefixLengthsPsr4 = array (
        'O' => 
        array (
            'OpenSpout\\' => 10,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'OpenSpout\\' => 
        array (
            0 => __DIR__ . '/..' . '/openspout/openspout/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit96662d1f59aa64d8ac2140b75a7025a8::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit96662d1f59aa64d8ac2140b75a7025a8::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit96662d1f59aa64d8ac2140b75a7025a8::$classMap;

        }, null, ClassLoader::class);
    }
}
