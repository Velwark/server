<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitContactsInteraction
{
    public static $prefixLengthsPsr4 = array (
        'O' => 
        array (
            'OCA\\ContactsInteraction\\' => 24,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'OCA\\ContactsInteraction\\' => 
        array (
            0 => __DIR__ . '/..' . '/../lib',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
        'OCA\\ContactsInteraction\\AddressBook' => __DIR__ . '/..' . '/../lib/AddressBook.php',
        'OCA\\ContactsInteraction\\AddressBookProvider' => __DIR__ . '/..' . '/../lib/AddressBookProvider.php',
        'OCA\\ContactsInteraction\\AppInfo\\Application' => __DIR__ . '/..' . '/../lib/AppInfo/Application.php',
        'OCA\\ContactsInteraction\\BackgroundJob\\CleanupJob' => __DIR__ . '/..' . '/../lib/BackgroundJob/CleanupJob.php',
        'OCA\\ContactsInteraction\\Card' => __DIR__ . '/..' . '/../lib/Card.php',
        'OCA\\ContactsInteraction\\Controller\\ConfigController' => __DIR__ . '/..' . '/../lib/Controller/ConfigController.php',
        'OCA\\ContactsInteraction\\DAV\\Plugin' => __DIR__ . '/..' . '/../lib/DAV/Plugin.php',
        'OCA\\ContactsInteraction\\Db\\CardSearchDao' => __DIR__ . '/..' . '/../lib/Db/CardSearchDao.php',
        'OCA\\ContactsInteraction\\Db\\RecentContact' => __DIR__ . '/..' . '/../lib/Db/RecentContact.php',
        'OCA\\ContactsInteraction\\Db\\RecentContactMapper' => __DIR__ . '/..' . '/../lib/Db/RecentContactMapper.php',
        'OCA\\ContactsInteraction\\Listeners\\ContactInteractionListener' => __DIR__ . '/..' . '/../lib/Listeners/ContactInteractionListener.php',
        'OCA\\ContactsInteraction\\Migration\\Version010000Date20200304152605' => __DIR__ . '/..' . '/../lib/Migration/Version010000Date20200304152605.php',
        'OCA\\ContactsInteraction\\Settings\\Personal' => __DIR__ . '/..' . '/../lib/Settings/Personal.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitContactsInteraction::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitContactsInteraction::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInitContactsInteraction::$classMap;

        }, null, ClassLoader::class);
    }
}
