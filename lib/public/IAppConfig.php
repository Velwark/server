<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */
namespace OCP;

/**
 * This class provides an easy way for apps to store config values in the
 * database.
 *
 * **Note:** since 29.0.0, it supports **lazy grouping**
 *
 * ### What is lazy grouping ?
 * In order to avoid loading useless config values in memory for each request on
 * the cloud, it has been made possible to group your config keys.
 * Each group, called _lazy group_, is only loaded in memory when one its config
 * keys is retrieved.
 *
 * It is advised to only use the default lazy group, named '' (empty string), for
 * config keys used in the registered part of your code that is called even when
 * your app is not boot (as in event listeners, ...)
 *
 * **Note:** Lazy group are not linked to app ids. Multiple app can share the same
 * lazy group and config keys from those apps will be loaded in memory when one value
 * from th lazy group is retrieved.
 *
 * **Warning:** some methods from this class are marked with a warning about ignoring
 * lazy grouping, use them wisely and only on part of code called during
 * specific request/action
 *
 * @since 7.0.0
 */
interface IAppConfig {

	/**
	 * Get list of all apps that have at least one config value stored in database
	 *
	 * **WARNING:** bypass cache and request database each time
	 *
	 * @param bool $preloadValues preload all values (since 29.0.0)
	 *
	 * @return string[] list of app ids
	 * @since 7.0.0
	 */
	public function getApps(bool $preloadValues = false): array;

	/**
	 * Returns all keys related to an app.
	 * Please note that the values are not returned.
	 *
	 * **Warning:** ignore lazy grouping
	 *
	 * @param string $app id of the app
	 *
	 * @return string[] list of stored config keys
	 * @since 29.0.0
	 */
	public function getKeys(string $app): array;

	/**
	 * Check if a key exists in the list of stored config values.
	 * To search for a key while ignoring lazy grouping, use getLazyGroup()
	 *
	 * @param string $app id of the app
	 * @param string $key config key
	 * @param bool $lazy search key set as lazy loaded (since 29.0.0)
	 *
	 * @return bool TRUE if key exists
	 * @see self::isLazy()
	 * @since 7.0.0
	 */
	public function hasKey(string $app, string $key, bool $lazy = false): bool;

	/**
	 * @param string $app id of the app
	 * @param string $key config key
	 * @param bool $lazy lazy loading
	 *
	 * @return bool
	 * @since 29.0.0
	 */
	public function isSensitiveKey(string $app, string $key, bool $lazy = false): bool;

	/**
	 * Returns if the config key is lazy loaded
	 *
	 * **Warning:** might bypass cache and request database.
	 *
	 * @param string $app id of the app
	 * @param string $key config key
	 *
	 * @return bool TRUE if config is lazy loaded
	 * @since 29.0.0
	 */
	public function isLazy(string $app, string $key): bool;

	/**
	 * List all config values from an app with config key starting with $key.
	 * Returns an array with config key as key, stored value as value.
	 *
	 * @param string $app id of the app
	 * @param string $key config keys prefix to search
	 * @param bool $filtered filter sensitive config values
	 *
	 * @return array<string, string> [configKey => configValue]
	 * @since 29.0.0
	 */
	public function getAllValues(string $app, string $key = '', bool $filtered = false): array;

	/**
	 * List all apps storing a specific config key and its stored value.
	 * Returns an array with appId as key, stored value as value.
	 *
	 * @param string $key config key
	 * @param bool $lazy lazy loading
	 *
	 * @return array<string, string> [appId => configValue]
	 * @since 29.0.0
	 */
	public function searchValues(string $key, bool $lazy = false): array;

	/**
	 * Get config value assigned to a config key.
	 * If config key is not found in database, default value is returned.
	 * If config key belongs to a lazy group, the name of the lazy group needs to be specified.
	 *
	 * @param string $app id of the app
	 * @param string $key config key
	 * @param string $default default value (optional)
	 * @param string $lazy name of the lazy group (optional)
	 *
	 * @return string stored config value or $default if not set in database
	 * @since 29.0.0
	 * @see IAppConfig for explanation about lazy grouping
	 * @see self::getValueInt()
	 * @see self::getValueFloat()
	 * @see self::getValueBool()
	 * @see self::getValueArray()
	 */
	public function getValueString(string $app, string $key, string $default = '', bool $lazy = false): string;

	/**
	 * Get config value assigned to a config key.
	 * If config key is not found in database, default value is returned.
	 * If config key belongs to a lazy group, the name of the lazy group needs to be specified.
	 *
	 * @param string $app id of the app
	 * @param string $key config key
	 * @param int $default default value
	 * @param string $lazy name of the lazy group
	 *
	 * @return int stored config value or $default if not set in database
	 * @since 29.0.0
	 * @see IAppConfig for explanation about lazy grouping
	 * @see self::getValueString()
	 * @see self::getValueFloat()
	 * @see self::getValueBool()
	 * @see self::getValueArray()
	 */
	public function getValueInt(string $app, string $key, int $default = 0, bool $lazy = false): int;

	/**
	 * Get config value assigned to a config key.
	 * If config key is not found in database, default value is returned.
	 * If config key belongs to a lazy group, the name of the lazy group needs to be specified.
	 *
	 * @param string $app id of the app
	 * @param string $key config key
	 * @param float $default default value (optional)
	 * @param string $lazy name of the lazy group (optional)
	 *
	 * @return float stored config value or $default if not set in database
	 * @since 29.0.0
	 * @see IAppConfig for explanation about lazy grouping
	 * @see self::getValueString()
	 * @see self::getValueInt()
	 * @see self::getValueBool()
	 * @see self::getValueArray()
	 */
	public function getValueFloat(string $app, string $key, float $default = 0, bool $lazy = false): float;

	/**
	 * Get config value assigned to a config key.
	 * If config key is not found in database, default value is returned.
	 * If config key belongs to a lazy group, the name of the lazy group needs to be specified.
	 *
	 * @param string $app id of the app
	 * @param string $key config key
	 * @param bool $default default value (optional)
	 * @param string $lazy name of the lazy group (optional)
	 *
	 * @return bool stored config value or $default if not set in database
	 * @since 29.0.0
	 * @see IAppConfig for explanation about lazy grouping
	 * @see self::getValueString()
	 * @see self::getValueInt()
	 * @see self::getValueFloat()
	 * @see self::getValueArray()
	 */
	public function getValueBool(string $app, string $key, bool $default = false, bool $lazy = false): bool;

	/**
	 * Get config value assigned to a config key.
	 * If config key is not found in database, default value is returned.
	 * If config key belongs to a lazy group, the name of the lazy group needs to be specified.
	 *
	 * @param string $app id of the app
	 * @param string $key config key
	 * @param array $default default value
	 * @param bool $lazy search within lazy loaded config
	 *
	 * @return array stored config value or $default if not set in database
	 * @since 29.0.0
	 * @see IAppConfig for explanation about lazy grouping
	 * @see self::getValueString()
	 * @see self::getValueInt()
	 * @see self::getValueFloat()
	 * @see self::getValueBool()
	 */
	public function getValueArray(string $app, string $key, array $default = [], bool $lazy = false): array;

	/**
	 * Store a config key and its value in database
	 * If config key is already known with the exact same config value, the database is not updated.
	 * If config key is not supposed to be read during the boot of the cloud, it is advised to set it as lazy loaded.
	 *
	 * @param string $app id of the app
	 * @param string $key config key
	 * @param string $value config value
	 * @param bool|null $sensitive value should be hidden when needed. if NULL sensitive flag is not changed in database
	 * @param string $lazy name of the lazy group
	 *
	 * @return bool TRUE if value was different, therefor updated in database
	 * @since 29.0.0
	 * @see IAppConfig for explanation about lazy grouping
	 * @see self::setValueInt()
	 * @see self::setValueFloat()
	 * @see self::setValueBool()
	 * @see self::setValueArray()
	 */
	public function setValueString(string $app, string $key, string $value, bool $lazy = false, ?bool $sensitive = null): bool;

	/**
	 * Store a config key and its value in database
	 * If config key is already known with the exact same config value, the database is not updated.
	 * If config key is not supposed to be read during the boot of the cloud, it is advised to set it as lazy loaded.
	 *
	 * @param string $app id of the app
	 * @param string $key config key
	 * @param int $value config value
	 * @param bool $lazy set config as lazy loaded
	 * @param bool|null $sensitive value should be hidden when needed. if NULL sensitive flag is not changed in database
	 *
	 * @return bool TRUE if value was different, therefor updated in database
	 * @since 29.0.0
	 * @see IAppConfig for explanation about lazy grouping
	 * @see self::setValueString()
	 * @see self::setValueFloat()
	 * @see self::setValueBool()
	 * @see self::setValueArray()
	 */
	public function setValueInt(string $app, string $key, int $value, bool $lazy = false, ?bool $sensitive = null): bool;

	/**
	 * Store a config key and its value in database
	 * If config key is already known with the exact same config value, the database is not updated.
	 * If config key is not supposed to be read during the boot of the cloud, it is advised to set it as lazy loaded.
	 *
	 * @param string $app id of the app
	 * @param string $key config key
	 * @param float $value config value
	 * @param bool $lazy set config as lazy loaded
	 * @param bool|null $sensitive value should be hidden when needed. if NULL sensitive flag is not changed in database
	 *
	 * @return bool TRUE if value was different, therefor updated in database
	 * @since 29.0.0
	 * @see IAppConfig for explanation about lazy grouping
	 * @see self::setValueString()
	 * @see self::setValueInt()
	 * @see self::setValueBool()
	 * @see self::setValueArray()
	 */
	public function setValueFloat(string $app, string $key, float $value, bool $lazy = false, ?bool $sensitive = null): bool;

	/**
	 * Store a config key and its value in database
	 * If config key is already known with the exact same config value, the database is not updated.
	 * If config key is not supposed to be read during the boot of the cloud, it is advised to set it as lazy loaded.
	 *
	 * @param string $app id of the app
	 * @param string $key config key
	 * @param bool $value config value
	 * @param bool $lazy set config as lazy loaded
	 * @param bool|null $sensitive value should be hidden when needed. if NULL sensitive flag is not changed in database
	 *
	 * @return bool TRUE if value was different, therefor updated in database
	 * @since 29.0.0
	 * @see IAppConfig for explanation about lazy grouping
	 * @see self::setValueString()
	 * @see self::setValueInt()
	 * @see self::setValueFloat()
	 * @see self::setValueArray()
	 */
	public function setValueBool(string $app, string $key, bool $value, bool $lazy = false, ?bool $sensitive = null): bool;

	/**
	 * Store a config key and its value in database
	 * If config key is already known with the exact same config value, the database is not updated.
	 * If config key is not supposed to be read during the boot of the cloud, it is advised to set it as lazy loaded.
	 *
	 * @param string $app id of the app
	 * @param string $key config key
	 * @param array $value config value
	 * @param bool $lazy set config as lazy loaded
	 * @param bool|null $sensitive value should be hidden when needed. if NULL sensitive flag is not changed in database
	 *
	 * @return bool TRUE if value was different, therefor updated in database
	 * @since 29.0.0
	 * @see IAppConfig for explanation about lazy grouping
	 * @see self::setValueString()
	 * @see self::setValueInt()
	 * @see self::setValueFloat()
	 * @see self::setValueBool()
	 */
	public function setValueArray(string $app, string $key, array $value, bool $lazy = false, ?bool $sensitive = null): bool;

	/**
	 * Delete single config key from database.
	 *
	 * @param string $app id of the app
	 * @param string $key config key
	 * @since 29.0.0
	 */
	public function unsetKey(string $app, string $key): void;

	/**
	 * delete all config keys linked to an app
	 *
	 * @param string $app id of the app
	 * @since 29.0.0
	 */
	public function unsetAppKeys(string $app): void;

	/**
	 * Clear the cache.
	 *
	 * Clearing cache consist of emptying the internal cache and the distributed cache.
	 * The cache will be rebuilt only the next time a config value is requested.
	 *
	 * @since 29.0.0
	 */
	public function clearCache(): void;

	/**
	 * For debug purpose.
	 * Returns the cached information.
	 *
	 * @return array
	 * @since 29.0.0
	 */
	public function statusCache(): array;

	/*
	 *
	 * #######################################################################
	 * # Below this mark are the method deprecated and replaced since 29.0.0 #
	 * #######################################################################
	 *
	 */

	/**
	 * get multiply values, either the app or key can be used as wildcard by setting it to false
	 * @deprecated use getAllValues()
	 *
	 * @param string|false $key
	 * @param string|false $app
	 *
	 * @return array|false
	 * @since 7.0.0
	 * @deprecated use getAllValues()
	 * @see self::getAllValues()
	 */
	public function getValues($app, $key);

	/**
	 * get all values of the app or and filters out sensitive data
	 * @deprecated use getAllValues()
	 *
	 * @param string $app
	 *
	 * @return array
	 * @since 12.0.0
	 * @see self::getAllValues()
	 */
	public function getFilteredValues($app);
}
