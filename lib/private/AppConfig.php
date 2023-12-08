<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2017, Joas Schilling <coding@schilljs.com>
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Jakob Sack <mail@jakobsack.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @author michaelletzgus <michaelletzgus@users.noreply.github.com>
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

namespace OC;

use InvalidArgumentException;
use JsonException;
use OCP\DB\Exception as DBException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Exceptions\AppConfigUnknownKeyException;
use OCP\IAppConfig;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

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
 * **Warning:** some methods from this class are marked with a warning about ignoring
 * lazy grouping, use them wisely and only on part of code called during
 * specific request/action
 *
 * @since 7.0.0
 */
class AppConfig implements IAppConfig {
	private const CACHE_PREFIX = 'core/AppConfig/';
	private const CACHE_TTL = 3600;
	private const ALL_APPS_CONFIG = '__ALL__';
	private const APP_MAX_LENGTH = 32;
	private const KEY_MAX_LENGTH = 64;

	private array $fastCache = [], $lazyCache = [], $sensitive = [];         // cache for normal and lazy loaded config keys
	private bool $fastLoaded = false, $lazyLoaded = false;
	private ?ICache $distributedCache = null;

	/** @deprecated */
	private bool $configLoaded = false;
	/** @deprecated */
	private bool $migrationCompleted = true;

	public function __construct(
		ICacheFactory $cacheFactory,
		protected IDBConnection $connection,
		private LoggerInterface $logger,
	) {
		if ($cacheFactory->isAvailable()) {
			$this->distributedCache = $cacheFactory->createDistributed(self::CACHE_PREFIX);
		}
	}

	/**
	 * @inheritDoc
	 *
	 * @param bool $preloadValues since 29.0.0 preload all values
	 *
	 * @return string[] list of app ids
	 * @since 7.0.0
	 */
	public function getApps(bool $preloadValues = false): array {
		if ($preloadValues) {
			$this->loadConfigAll();
			$keys = array_keys(array_merge($this->fastCache, $this->lazyCache));
			sort($keys);

			return $keys;
		}

		$qb = $this->connection->getQueryBuilder();
		$qb->selectDistinct('appid')
		   ->from('appconfig')
		   ->orderBy('appid', 'asc')
		   ->groupBy('appid');
		$result = $qb->executeQuery();

		$rows = $result->fetchAll();
		$apps = [];
		foreach ($rows as $row) {
			$apps[] = $row['appid'];
		}

		return $apps;
	}

	/**
	 * @inheritDoc
	 *
	 * @param string $app id of the app
	 *
	 * @return string[] list of stored config keys
	 * @since 29.0.0
	 */
	public function getKeys(string $app): array {
		$this->assertParams($app);
		$this->loadConfig(null);
		$keys = array_keys(array_merge($this->fastCache[$app] ?? [], $this->lazyCache[$app] ?? []));
		sort($keys);

		return $keys;
	}

	/**
	 * @inheritDoc
	 *
	 * @param string $app id of the app
	 * @param string $key config key
	 * @param bool $lazy search within lazy config (since 29.0.0)
	 *
	 * @return bool TRUE if key exists
	 * @since 7.0.0
	 */
	public function hasKey(string $app, string $key, bool $lazy = false): bool {
		$this->assertParams($app, $key);
		$this->loadConfig($lazy);
		($lazy) ? $cache = &$this->lazyCache : $cache = &$this->fastCache;

		/** @psalm-suppress UndefinedVariable */
		return isset($cache[$app][$key]);
	}

	/**
	 * @param string $app id of the app
	 * @param string $key config key
	 * @param bool $lazy lazy config
	 *
	 * @return bool
	 * @throws AppConfigUnknownKeyException if config key is not known
	 * @since 29.0.0
	 */
	public function isSensitiveKey(string $app, string $key, bool $lazy = false): bool {
		$this->assertParams($app, $key);
		$this->loadConfig($lazy);
		($lazy) ? $cache = &$this->lazyCache : $cache = &$this->fastCache;

		/** @psalm-suppress UndefinedVariable */
		return $cache[$app][$key]['sensitive'] ?? throw new AppConfigUnknownKeyException();
	}

	/**
	 * @inheritDoc
	 *
	 * @param string $app if of the app
	 * @param string $key config key
	 *
	 * @return string|null lazy group or NULL if key is not found
	 */
	public function isLazy(string $app, string $key): bool {
		$this->assertParams($app, $key);
		$qb = $this->connection->getQueryBuilder();
		$qb->select('lazy')
		   ->from('appconfig')
		   ->where($qb->expr()->eq('appid', $qb->createNamedParameter($app)))
		   ->andWhere($qb->expr()->eq('configkey', $qb->createNamedParameter($key)));
		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		return $row['lazy'] ?? throw new AppConfigUnknownKeyException();
	}


	/**
	 * @inheritDoc
	 *
	 * @param string $app id of the app
	 * @param string $key config keys prefix to search
	 * @param bool $filtered filter sensitive config values
	 *
	 * @return array<string, string> [configKey => configValue]
	 * @since 29.0.0
	 */
	public function getAllValues(string $app, string $key = '', bool $filtered = false): array {
		$this->assertParams($app, $key);
		$this->loadConfig(null, $filtered); // if we want to filter values, we need to get sensitivity

		$values = array_merge($this->fastCache[$app], $this->lazyCache[$app] ?? []);

		if (!$filtered) {
			return $values;
		}

		/**
		 * Using the old (deprecated) list of sensitive values.
		 */
		foreach ($this->getSensitiveKeys($app) as $sensitiveKeyExp) {
			$sensitiveKeys = preg_grep($sensitiveKeyExp, array_keys($values));
			foreach ($sensitiveKeys as $sensitiveKey) {
				$this->sensitive[$app][$sensitiveKey] = true;
			}
		}

		$result = [];
		foreach ($values as $key => $value) {
			$result[$key] = ($this->sensitive[$app][$key] ?? false) ? IConfig::SENSITIVE_VALUE : $value;
		}

		return $result;
	}

	/**
	 * @inheritDoc
	 *
	 * @param string $key config key
	 * @param bool $lazy lazy config
	 *
	 * @return array<string, string> [appId => configValue]
	 * @since 29.0.0
	 */
	public function searchValues(string $key, bool $lazy = false): array {
		$this->assertParams('', $key, true);
		$this->loadConfig($lazy);
		$values = [];
		/** @var array<array-key, array<array-key, mixed>> $cache */
		($lazy) ? $cache = &$this->lazyCache : $cache = &$this->fastCache;
		foreach (array_keys($cache) as $app) {
			if (isset($cache[$app][$key])) {
				$values[$app] = $cache[$app][$key];
			}
		}

		return $values;
	}


	/**
	 * @inheritDoc
	 *
	 * @param string $app id of the app
	 * @param string $key config key
	 * @param string $default default value (optional)
	 * @param string $lazy name of the lazy group (optional)
	 *
	 * @return string stored config value or $default if not set in database
	 * @throws InvalidArgumentException
	 * @since 29.0.0
	 * @see IAppConfig for explanation about lazy grouping
	 * @see self::getValueInt()
	 * @see self::getValueFloat()
	 * @see self::getValueBool()
	 * @see self::getValueArray()
	 */
	public function getValueString(string $app, string $key, string $default = '', bool $lazy = false): string {
		$this->assertParams($app, $key);
		$this->loadConfig($lazy);
		($lazy) ? $cache = &$this->lazyCache : $cache = &$this->fastCache;

		/** @psalm-suppress UndefinedVariable */
		return $cache[$app][$key] ?? $default;
	}

	/**
	 * @inheritDoc
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
	public function getValueInt(string $app, string $key, int $default = 0, bool $lazy = false): int {
		return (int)$this->getValueString($app, $key, (string)$default, $lazy);
	}

	/**
	 * @inheritDoc
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
	public function getValueFloat(string $app, string $key, float $default = 0, bool $lazy = false): float {
		return (float)$this->getValueString($app, $key, (string)$default, $lazy);
	}

	/**
	 * @inheritDoc
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
	public function getValueBool(string $app, string $key, bool $default = false, bool $lazy = false): bool {
		return in_array(
			$this->getValueString($app, $key, $default ? 'true' : 'false'), ['1', 'true', 'yes', 'on'], $lazy
		);
	}

	/**
	 * @inheritDoc
	 *
	 * @param string $app id of the app
	 * @param string $key config key
	 * @param array $default default value (optional)
	 * @param string $lazy name of the lazy group (optional)
	 *
	 * @return array stored config value or $default if not set in database
	 * @since 29.0.0
	 * @see IAppConfig for explanation about lazy grouping
	 * @see self::getValueString()
	 * @see self::getValueInt()
	 * @see self::getValueFloat()
	 * @see self::getValueBool()
	 */
	public function getValueArray(string $app, string $key, array $default = [], bool $lazy = false): array {
		try {
			$defaultJson = json_encode($default, JSON_THROW_ON_ERROR);
			$value = json_decode(
				$this->getValueString($app, $key, $defaultJson, $lazy), true, JSON_THROW_ON_ERROR
			);

			return (is_array($value)) ? $value : [$value];
		} catch (JsonException) {
			return [];
		}
	}

	/**
	 * @inheritDoc
	 *
	 * @param string $app id of the app
	 * @param string $key config key
	 * @param string $value config value
	 * @param string $lazy name of the lazy group
	 * @param bool|null $sensitive value should be hidden when needed. if NULL sensitive flag is not changed
	 *     in database
	 *
	 * @return bool TRUE if value was different, therefor updated in database
	 * @since 29.0.0
	 * @see IAppConfig for explanation about lazy grouping
	 * @see self::setValueInt()
	 * @see self::setValueFloat()
	 * @see self::setValueBool()
	 * @see self::setValueArray()
	 */
	public function setValueString(
		string $app,
		string $key,
		string $value,
		bool $lazy = false,
		?bool $sensitive = null
	): bool {
		$this->assertParams($app, $key, $lazy);
		$this->loadConfig($lazy);

		// store value if not known yet, or value is different, or sensitivity changed
		$updated = !$this->hasKey($app, $key, $lazy)
				   || $value !== $this->getValueString($app, $key, $value, $lazy)
				   || ($sensitive !== null && $sensitive !== $this->isSensitiveKey($app, $key, $lazy));
		if (!$updated) {
			return false;
		}

		// update local cache, do not touch sensitive if null or set it to false if new key
		($lazy) ? $cache = &$this->lazyCache : $cache = &$this->fastCache;
		/** @psalm-suppress UndefinedVariable */
		$cache[$app][$key] = $value;
		$this->sensitive[$app][$key] = $sensitive ?? $this->sensitive[$app][$key] ?? false;

		$insert = $this->connection->getQueryBuilder();
		$insert->insert('appconfig')
			   ->setValue('appid', $insert->createNamedParameter($app))
			   ->setValue('lazy', $insert->createNamedParameter($lazy, IQueryBuilder::PARAM_BOOL))
			   ->setValue('sensitive', $insert->createNamedParameter($sensitive ?? false, IQueryBuilder::PARAM_BOOL))
			   ->setValue('configkey', $insert->createNamedParameter($key))
			   ->setValue('configvalue', $insert->createNamedParameter($value));
		try {
			$insert->executeStatement();
		} catch (DBException $e) {
			if ($e->getReason() !== DBException::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
				throw $e; // TODO: throw exception or just log and returns false !?
			}

			$update = $this->connection->getQueryBuilder();
			$update->update('appconfig')
				   ->set('configvalue', $update->createNamedParameter($value))
				   ->set('lazy', $update->createNamedParameter($lazy, IQueryBuilder::PARAM_BOOL))
				   ->where($update->expr()->eq('appid', $update->createNamedParameter($app)))
				   ->andWhere($update->expr()->eq('configkey', $update->createNamedParameter($key)));
			if ($sensitive !== null) {
				$update->set(
					'sensitive', $update->createNamedParameter($sensitive, IQueryBuilder::PARAM_BOOL)
				);
			}

			$update->executeStatement();
		}

		return true;
	}

	/**
	 * @inheritDoc
	 *
	 * @param string $app id of the app
	 * @param string $key config key
	 * @param int $value config value
	 * @param string $lazyGroup name of the lazy group
	 * @param bool|null $sensitive value should be hidden when needed. if NULL sensitive flag is not changed
	 *     in database
	 *
	 * @return bool TRUE if value was different, therefor updated in database
	 * @since 29.0.0
	 * @see IAppConfig for explanation about lazy grouping
	 * @see self::setValueString()
	 * @see self::setValueFloat()
	 * @see self::setValueBool()
	 * @see self::setValueArray()
	 */
	public function setValueInt(
		string $app,
		string $key,
		int $value,
		bool $lazy = false,
		?bool $sensitive = null
	): bool {
		return $this->setValueString($app, $key, (string)$value, $lazy, $sensitive);
	}

	/**
	 * @inheritDoc
	 *
	 * @param string $app id of the app
	 * @param string $key config key
	 * @param float $value config value
	 * @param string $lazyGroup name of the lazy group
	 * @param bool|null $sensitive value should be hidden when needed. if NULL sensitive flag is not changed
	 *     in database
	 *
	 * @return bool TRUE if value was different, therefor updated in database
	 * @since 29.0.0
	 * @see IAppConfig for explanation about lazy grouping
	 * @see self::setValueString()
	 * @see self::setValueInt()
	 * @see self::setValueBool()
	 * @see self::setValueArray()
	 */
	public function setValueFloat(
		string $app,
		string $key,
		float $value,
		bool $lazy = false,
		?bool $sensitive = null
	): bool {
		return $this->setValueString($app, $key, (string)$value, $lazy, $sensitive);
	}

	/**
	 * @inheritDoc
	 *
	 * @param string $app id of the app
	 * @param string $key config key
	 * @param bool $value config value
	 * @param string $lazyGroup name of the lazy group
	 * @param bool|null $sensitive value should be hidden when needed. if NULL sensitive flag is not changed
	 *     in database
	 *
	 * @return bool TRUE if value was different, therefor updated in database
	 * @since 29.0.0
	 * @see IAppConfig for explanation about lazy grouping
	 * @see self::setValueString()
	 * @see self::setValueInt()
	 * @see self::setValueFloat()
	 * @see self::setValueArray()
	 */
	public function setValueBool(
		string $app,
		string $key,
		bool $value,
		bool $lazy = false,
		?bool $sensitive = null
	): bool {
		return $this->setValueString($app, $key, $value ? 'true' : 'false', $lazy, $sensitive);
	}

	/**
	 * @inheritDoc
	 *
	 * @param string $app id of the app
	 * @param string $key config key
	 * @param array $value config value
	 * @param string $lazyGroup name of the lazy group
	 * @param bool|null $sensitive value should be hidden when needed. if NULL sensitive flag is not changed
	 *     in database
	 *
	 * @return bool TRUE if value was different, therefor updated in database
	 * @since 29.0.0
	 * @see IAppConfig for explanation about lazy grouping
	 * @see self::setValueString()
	 * @see self::setValueInt()
	 * @see self::setValueFloat()
	 * @see self::setValueBool()
	 */
	public function setValueArray(
		string $app,
		string $key,
		array $value,
		bool $lazy = false,
		?bool $sensitive = null
	): bool {
		try {
			return $this->setValueString($app, $key, json_encode($value, JSON_THROW_ON_ERROR), $lazy, $sensitive);
		} catch (JsonException $e) {
			$this->logger->warning(
				'could not setValueArray', ['app' => $app, 'key' => $key, 'exception' => $e]
			);
		}

		return false;
	}

	/**
	 * @inheritDoc
	 *
	 * @param string $app id of the app
	 * @param string $key config key
	 * @param string $lazyGroup name of the lazy group
	 *
	 * @since 29.0.0
	 */
	public function unsetKey(string $app, string $key): void {
		$this->assertParams($app, $key);
		$qb = $this->connection->getQueryBuilder();
		$qb->delete('appconfig')
		   ->where($qb->expr()->eq('appid', $qb->createNamedParameter($app)))
		   ->andWhere($qb->expr()->eq('configkey', $qb->createNamedParameter($key)));
		$qb->executeStatement();

		// we really want to delete that key
		unset($this->lazyCache[$app][$key]);
		if ($this->hasKey($app, $key)) {
			unset($this->fastCache[$app][$key]);
			$this->storeDistributedCache();
		}
	}

	/**
	 * @inheritDoc
	 *
	 * @param string $app id of the app
	 *
	 * @since 29.0.0
	 */
	public function unsetAppKeys(string $app): void {
		$this->assertParams($app);
		$qb = $this->connection->getQueryBuilder();
		$qb->delete('appconfig')
		   ->where($qb->expr()->eq('appid', $qb->createNamedParameter($app)));
		$qb->executeStatement();

		$this->clearCache();
	}

	/**
	 * @inheritDoc
	 * @since 29.0.0
	 */
	public function clearCache(): void {
		$this->lazyLoaded = $this->fastLoaded = false;
		$this->lazyCache = $this->fastCache = $this->sensitive = [];
		$this->storeDistributedCache();
	}

	/**
	 * @return array
	 * @throws JsonException
	 */
	public function statusCache(): array {
		$distributed = [];
		if ($this->distributedCache !== null) {
			$distributed['fastCache'] = $this->getDistributedCache('fastCache');
		}

		return [
			'fastLoaded' => $this->fastLoaded,
			'fastCache' => $this->fastCache,
			'lazyLoaded' => $this->lazyLoaded,
			'lazyCache' => $this->lazyCache,
			'distributedCache' => $distributed
		];
	}

	/**
	 * Confirm the string set for app, key and lazyGroup fit the database description
	 *
	 * @param string $app
	 * @param string $configKey
	 * @param string $lazyGroup
	 * @param bool $allowEmptyApp
	 *
	 * @throws InvalidArgumentException
	 */
	private function assertParams(string $app = '', string $configKey = '', bool $allowEmptyApp = false
	): void {
		if (!$allowEmptyApp && $app === '') {
			throw new InvalidArgumentException('app cannot be an empty string');
		}
		if (strlen($app) > self::APP_MAX_LENGTH) {
			throw new InvalidArgumentException(
				'Value (' . $app . ') for app is too long (' . self::APP_MAX_LENGTH . ')'
			);
		}
		if (strlen($configKey) > self::KEY_MAX_LENGTH) {
			throw new InvalidArgumentException(
				'Value (' . $configKey . ') for key is too long (' . self::KEY_MAX_LENGTH . ')'
			);
		}
	}


	/**
	 * @param string $item
	 *
	 * @return array
	 * @throws JsonException
	 */
	private function getDistributedCache(string $item): array {
		return json_decode(
			$this->distributedCache->get($item),
			true,
			32,
			JSON_THROW_ON_ERROR
		);
	}

	private function loadConfigAll(): void {
		$this->loadConfig(null);
	}

	/**
	 * Load normal config or config set as lazy loaded
	 *
	 * @param bool|null $lazy set to TRUE to load config set as lazy loaded, set to NULL to load all config
	 * @param bool $loadSensitivityLevel set to TRUE to load in memory the sensitivity level for each config
	 *     value. (Will refresh the cache)
	 */
	private function loadConfig(?bool $lazy = false, bool $loadSensitivityLevel = false): void {
		if ($loadSensitivityLevel) {
			$this->clearCache();
		}

		if ($this->isLoaded($lazy)) {
			return;
		}

		$qb = $this->connection->getQueryBuilder();
		$qb->from('appconfig');
		/**
		 * The use of $this->>migrationCompleted is only needed to manage the
		 * database during the upgrading process to nc29.
		 */
		if (!$this->migrationCompleted) {
			$qb->select('appid', 'configkey', 'configvalue');
		} else {
			$qb->select('appid', 'configkey', 'configvalue', 'sensitive', 'lazy');
			if ($lazy !== null) {
				$qb->where($qb->expr()->eq('lazy', $qb->createNamedParameter($lazy, IQueryBuilder::PARAM_BOOL)));
			}
		}

		try {
			$result = $qb->executeQuery();
		} catch (DBException $e) {
			/**
			 * in case of issue with field name, it means that migration is not completed.
			 * Falling back to a request without select on lazy.
			 * This whole try/catch and the migrationCompleted variable can be removed in NC30.
			 */
			if ($e->getReason() !== DBException::REASON_INVALID_FIELD_NAME) {
				throw $e;
			}

			$this->migrationCompleted = false;
			$this->loadConfig($lazy, $loadSensitivityLevel);

			return;
		}

		$rows = $result->fetchAll();
		foreach ($rows as $row) {
			// if migration is not completed, 'lazy' does not exist in $row
			(($row['lazy'] ?? false) == true) ? $cache = &$this->lazyCache : $cache = &$this->fastCache;
			$cache[$row['appid']][$row['configkey']] = $row['configvalue'];
			$this->sensitive[$row['appid']][$row['configkey']] = $row['sensitive'];
		}

		$result->closeCursor();
		$this->setAsLoaded($lazy);

		// store as soon as we know we have fastCache filled
		if ($lazy ?? true) {
			$this->storeDistributedCache();
		}
	}

	/**
	 * if $lazy is:
	 *  - false: will returns true if fast config is loaded
	 *  - true : will returns true if lazy config is loaded
	 *  - null : will returns true if both config are loaded
	 *
	 * @param bool $lazy
	 *
	 * @return bool
	 */
	private function isLoaded(?bool $lazy): bool {
		if ($lazy === null) {
			return $this->lazyLoaded && $this->fastLoaded;
		}

		return $lazy ? $this->lazyLoaded : $this->fastLoaded;
	}

	/**
	 * if $lazy is:
	 * - false: set fast config as loaded
	 * - true : set lazy config as loaded
	 * - null : set both config as loaded
	 *
	 * @param bool $lazy
	 */
	private function setAsLoaded(?bool $lazy): void {
		if ($lazy !== null) {
			$lazy ? $this->lazyLoaded = true : $this->fastLoaded = true;

			return;
		}
		$this->fastLoaded = true;
		$this->lazyLoaded = true;
	}

	private function loadDistributedCache(string $lazyGroup = ''): void {
		if ($this->distributedCache === null) {
			return;
		}

		if ($lazyGroup !== '') {
			return;
		}

		try {
			$this->fastCache = $this->getDistributedCache('fastCache');
			$this->setAsLoaded(false);
		} catch (JsonException $e) {
			$this->logger->warning('AppConfig distributed cache seems corrupted', ['exception' => $e]);
			$this->fastCache = [];
			$this->storeDistributedCache();
		}
	}

	/**
	 * update local cache into distributed system
	 *
	 * @param bool $onlyCache
	 *
	 * @return void
	 */
	private function storeDistributedCache(): void {
		if ($this->distributedCache === null) {
			return;
		}

		try {
			$fastCache = json_encode($this->fastCache, JSON_THROW_ON_ERROR);
			$this->distributedCache->set('fastCache', $fastCache, self::CACHE_TTL);
		} catch (JsonException) {
			$this->logger->warning('...');
		}
	}

	/**
	 * All methods below this line are set as deprecated.
	 */

	/**
	 * Gets the config value
	 *
	 * @param string $app app
	 * @param string $key key
	 * @param string $default = null, default value if the key does not exist
	 *
	 * @return string the value or $default
	 * @deprecated - use getValue*()
	 *
	 * This function gets a value from the appconfig table. If the key does
	 * not exist the default value will be returned
	 */
	public function getValue($app, $key, $default = null) {
		$this->loadConfig();

		return $this->fastCache[$app][$key] ?? $default;
	}

	/**
	 * Sets a value. If the key did not exist before it will be created.
	 *
	 * @param string $app app
	 * @param string $key key
	 * @param string|float|int $value value
	 *
	 * @return bool True if the value was inserted or updated, false if the value was the same
	 * @deprecated
	 *
	 */
	public function setValue($app, $key, $value) {
		return $this->setValueString($app, $key, (string)$value);
	}


	/**
	 * Deletes a key
	 *
	 * @param string $app app
	 * @param string $key key
	 *
	 * @return boolean
	 * @see self::unsetKey()
	 * @deprecated use unsetKey()
	 */
	public function deleteKey($app, $key) {
		$this->unsetKey($app, $key);

		return false;
	}

	/**
	 * Remove app from appconfig
	 * Removes all keys in appconfig belonging to the app.
	 *
	 * @param string $app app
	 *
	 * @return boolean
	 * @deprecated use unsetAppKeys()
	 * @see self::unsetAppKeys()
	 */
	public function deleteApp($app) {
		$this->unsetAppKeys($app);

		return false;
	}


	/**
	 * get multiple values, either the app or key can be used as wildcard by setting it to false
	 *
	 * @param string|false $app
	 * @param string|false $key
	 *
	 * @return array|false
	 * @deprecated 29.0.0 use getAllValues()
	 */
	public function getValues($app, $key) {
		if (($app !== false) === ($key !== false)) {
			return false;
		}

		if (!$app) {
			return $this->searchValues($key);
		} else {
			return $this->getAllValues($app);
		}
	}

	/**
	 * get all values of the app or and filters out sensitive data
	 *
	 * @param string $app
	 *
	 * @return array
	 * @deprecated 29.0.0 use getAllFilteredValues()
	 */
	public function getFilteredValues($app) {
		$values = $this->getAllValues($app, filtered: true);
		foreach ($this->getSensitiveKeys($app) as $sensitiveKeyExp) {
			$sensitiveKeys = preg_grep($sensitiveKeyExp, array_keys($values));
			foreach ($sensitiveKeys as $sensitiveKey) {
				$values[$sensitiveKey] = IConfig::SENSITIVE_VALUE;
			}
		}

		return $values;
	}

	/**
	 * @param string $app
	 *
	 * @return string[]
	 * @deprecated data sensitivity should be set when calling setValue*()
	 */
	private function getSensitiveKeys(string $app): array {
		$sensitiveValues = [
			'circles' => [
				'/^key_pairs$/',
				'/^local_gskey$/',
			],
			'external' => [
				'/^sites$/',
			],
			'integration_discourse' => [
				'/^private_key$/',
				'/^public_key$/',
			],
			'integration_dropbox' => [
				'/^client_id$/',
				'/^client_secret$/',
			],
			'integration_github' => [
				'/^client_id$/',
				'/^client_secret$/',
			],
			'integration_gitlab' => [
				'/^client_id$/',
				'/^client_secret$/',
				'/^oauth_instance_url$/',
			],
			'integration_google' => [
				'/^client_id$/',
				'/^client_secret$/',
			],
			'integration_jira' => [
				'/^client_id$/',
				'/^client_secret$/',
				'/^forced_instance_url$/',
			],
			'integration_onedrive' => [
				'/^client_id$/',
				'/^client_secret$/',
			],
			'integration_openproject' => [
				'/^client_id$/',
				'/^client_secret$/',
				'/^oauth_instance_url$/',
			],
			'integration_reddit' => [
				'/^client_id$/',
				'/^client_secret$/',
			],
			'integration_suitecrm' => [
				'/^client_id$/',
				'/^client_secret$/',
				'/^oauth_instance_url$/',
			],
			'integration_twitter' => [
				'/^consumer_key$/',
				'/^consumer_secret$/',
				'/^followed_user$/',
			],
			'integration_zammad' => [
				'/^client_id$/',
				'/^client_secret$/',
				'/^oauth_instance_url$/',
			],
			'notify_push' => [
				'/^cookie$/',
			],
			'spreed' => [
				'/^bridge_bot_password$/',
				'/^hosted-signaling-server-(.*)$/',
				'/^recording_servers$/',
				'/^signaling_servers$/',
				'/^signaling_ticket_secret$/',
				'/^signaling_token_privkey_(.*)$/',
				'/^signaling_token_pubkey_(.*)$/',
				'/^sip_bridge_dialin_info$/',
				'/^sip_bridge_shared_secret$/',
				'/^stun_servers$/',
				'/^turn_servers$/',
				'/^turn_server_secret$/',
			],
			'support' => [
				'/^last_response$/',
				'/^potential_subscription_key$/',
				'/^subscription_key$/',
			],
			'theming' => [
				'/^imprintUrl$/',
				'/^privacyUrl$/',
				'/^slogan$/',
				'/^url$/',
			],
			'user_ldap' => [
				'/^(s..)?ldap_agent_password$/',
			],
			'user_saml' => [
				'/^idp-x509cert$/',
			],
		];

		return $sensitiveValues[$app] ?? [];
	}

	/**
	 * Clear all the cached app config values
	 * New cache will be generated next time a config value is retrieved
	 *
	 * @deprecated use clearCache();
	 * @see self::clearCache()
	 */
	public function clearCachedConfig(): void {
		$this->clearCache();
	}
}
