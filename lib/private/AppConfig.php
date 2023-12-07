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
	private const LAZY_MAX_LENGTH = 32;

	private array $fastCache = [];      // cache for fast config keys
	private array $lazyCache = [];      // cache for lazy config keys
	private array $loaded = [];         // loaded lazy group
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
	 * @param string $app id of the app
	 *
	 * @return string[] list of stored config keys
	 * @since 29.0.0
	 */
	public function getKeys(string $app): array {
		$this->assertParams($app);
		$this->loadConfig($app, ignoreLazyGroup: true);
		$keys = array_keys($this->cache[$app] ?? []);
		sort($keys);
		return $keys;
	}

	/**
	 * @inheritDoc
	 * @param string $app id of the app
	 * @param string $key config key
	 * @param string $lazyGroup search key within a lazy group (since 29.0.0)
	 *
	 * @return bool TRUE if key exists
	 * @since 7.0.0
	 */
	public function hasKey(string $app, string $key, string $lazyGroup = ''): bool {
		$this->assertParams($app, $key, $lazyGroup);
		$this->loadConfig($lazyGroup);
		($lazyGroup === '') ? $cache = &$this->fastCache : $cache = &$this->lazyCache;
		/** @psalm-suppress UndefinedVariable */
		return isset($cache[$app][$key]);
	}

	/**
	 * @param string $app id of the app
	 * @param string $key config key
	 * @param string $lazyGroup lazy group
	 *
	 * @throws AppConfigUnknownKeyException if config key is not known
	 * @return bool
	 * @since 29.0.0
	 */
	public function isSensitiveKey(string $app, string $key, string $lazyGroup = ''): bool {
		$this->assertParams($app, $key, $lazyGroup);
		$this->loadConfig($lazyGroup);
		($lazyGroup === '') ? $cache = &$this->fastCache : $cache = &$this->lazyCache;
		/** @psalm-suppress UndefinedVariable */
		return $cache[$app][$key]['sensitive'] ?? throw new AppConfigUnknownKeyException();
	}

	/**
	 * @inheritDoc
	 * @param string $app if of the app
	 * @param string $key config key
	 *
	 * @return string|null lazy group or NULL if key is not found
	 */
	public function getLazyGroup(string $app, string $key): ?string {
		$this->assertParams($app, $key);
		$qb = $this->connection->getQueryBuilder();
		$qb->select('lazy_group')
		   ->from('appconfig')
		   ->where($qb->expr()->eq('appid', $qb->createNamedParameter($app)))
		   ->andWhere($qb->expr()->eq('configkey', $qb->createNamedParameter($key)));
		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		return $row['lazy_group'] ?? null;
	}


	/**
	 * @inheritDoc
	 * @param string $app id of the app
	 * @param string $key config keys prefix to search
	 * @param bool $filtered filter sensitive config values
	 *
	 * @return array<string, string> [configKey => configValue]
	 * @since 29.0.0
	 */
	public function getAllValues(string $app, string $key = '', bool $filtered = false): array {
		$this->assertParams($app, $key);
		$this->loadConfig(ignoreLazyGroup: true);

		$values = array_merge($this->fastCache[$app], $this->lazyCache[$app] ?? []);

		/**
		 * Using the old (deprecated) list of sensitive values.
		 */
		foreach ($this->getSensitiveKeys($app) as $sensitiveKeyExp) {
			$sensitiveKeys = preg_grep($sensitiveKeyExp, array_keys($values));
			foreach ($sensitiveKeys as $sensitiveKey) {
				$values[$sensitiveKey]['sensitive'] = true;
			}
		}

		return array_map(function (array $entry) use ($app): mixed {
			return ($entry['sensitive']) ? IConfig::SENSITIVE_VALUE : $entry['value'];
		}, $values);
	}

	/**
	 * @inheritDoc
	 * @param string $key config key
	 * @param string $lazyGroup lazy group
	 *
	 * @return array<string, string> [appId => configValue]
	 * @since 29.0.0
	 */
	public function searchValues(string $key, string $lazyGroup = ''): array {
		$this->assertParams('', $key, $lazyGroup);
		$this->loadConfig($lazyGroup);
		$values = [];
		/** @var array<array-key, array<array-key, mixed>> $cache */
		($lazyGroup === '') ? $cache = &$this->fastCache : $cache = &$this->lazyCache;
		foreach (array_keys($cache) as $app) {
			if (isset($cache[$app][$key])) {
				$values[$app] = $cache[$app][$key]['value'];
			}
		}

		return $values;
	}


	/**
	 * @inheritDoc
	 * @param string $app id of the app
	 * @param string $key config key
	 * @param string $default default value (optional)
	 * @param string $lazyGroup name of the lazy group (optional)
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
	public function getValueString(string $app, string $key, string $default = '', string $lazyGroup = ''): string {
		$this->assertParams($app, $key, $lazyGroup);
		$this->loadConfig($lazyGroup);
		($lazyGroup === '') ? $cache = &$this->fastCache : $cache = &$this->lazyCache;
		/** @psalm-suppress UndefinedVariable */
		return $cache[$app][$key]['value'] ?? $default;
	}

	/**
	 * @inheritDoc
	 * @param string $app id of the app
	 * @param string $key config key
	 * @param int $default default value
	 * @param string $lazyGroup name of the lazy group
	 *
	 * @return int stored config value or $default if not set in database
	 * @since 29.0.0
	 * @see IAppConfig for explanation about lazy grouping
	 * @see self::getValueString()
	 * @see self::getValueFloat()
	 * @see self::getValueBool()
	 * @see self::getValueArray()
	 */
	public function getValueInt(string $app, string $key, int $default = 0, string $lazyGroup = ''): int {
		return (int) $this->getValueString($app, $key, (string)$default);
	}

	/**
	 * @inheritDoc
	 * @param string $app id of the app
	 * @param string $key config key
	 * @param float $default default value (optional)
	 * @param string $lazyGroup name of the lazy group (optional)
	 *
	 * @return float stored config value or $default if not set in database
	 * @since 29.0.0
	 * @see IAppConfig for explanation about lazy grouping
	 * @see self::getValueString()
	 * @see self::getValueInt()
	 * @see self::getValueBool()
	 * @see self::getValueArray()
	 */
	public function getValueFloat(string $app, string $key, float $default = 0, string $lazyGroup = ''): float {
		return (float) $this->getValueString($app, $key, (string)$default);
	}

	/**
	 * @inheritDoc
	 * @param string $app id of the app
	 * @param string $key config key
	 * @param bool $default default value (optional)
	 * @param string $lazyGroup name of the lazy group (optional)
	 *
	 * @return bool stored config value or $default if not set in database
	 * @since 29.0.0
	 * @see IAppConfig for explanation about lazy grouping
	 * @see self::getValueString()
	 * @see self::getValueInt()
	 * @see self::getValueFloat()
	 * @see self::getValueArray()
	 */
	public function getValueBool(string $app, string $key, bool $default = false, string $lazyGroup = ''): bool {
		return in_array($this->getValueString($app, $key, $default ? 'true' : 'false'), ['1', 'true', 'yes', 'on']);
	}

	/**
	 * @inheritDoc
	 * @param string $app id of the app
	 * @param string $key config key
	 * @param array $default default value (optional)
	 * @param string $lazyGroup name of the lazy group (optional)
	 *
	 * @return array stored config value or $default if not set in database
	 * @since 29.0.0
	 * @see IAppConfig for explanation about lazy grouping
	 * @see self::getValueString()
	 * @see self::getValueInt()
	 * @see self::getValueFloat()
	 * @see self::getValueBool()
	 */
	public function getValueArray(string $app, string $key, array $default = [], string $lazyGroup = ''): array {
		try {
			$defaultJson = json_encode($default, JSON_THROW_ON_ERROR);
			$value = json_decode($this->getValueString($app, $key, $defaultJson), true, JSON_THROW_ON_ERROR);
			return (is_array($value)) ? $value : [$value];
		} catch (JsonException) {
			return [];
		}
	}

	/**
	 * @inheritDoc
	 * @param string $app id of the app
	 * @param string $key config key
	 * @param string $value config value
	 * @param string $lazyGroup name of the lazy group
	 * @param bool|null $sensitive value should be hidden when needed. if NULL sensitive flag is not changed in database
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
		?bool $sensitive = null,
		string $lazyGroup = ''
	): bool {
		$this->assertParams($app, $key, $lazyGroup);
		$this->loadConfig($lazyGroup);
		// store value if not known yet, or value is different, or sensitivity changed
		$updated = !$this->hasKey($app, $key, $lazyGroup)
				   || $value !== $this->getValueString($app, $key, $value, $lazyGroup)
				   || ($sensitive !== null && $sensitive !== $this->isSensitiveKey($app, $key, $lazyGroup));
		if (!$updated) {
			return false;
		}

		// update local cache, do not touch sensitive if null or set it to false if new key
		($lazyGroup === '') ? $cache = &$this->fastCache : $cache = &$this->lazyCache;
		$cache[$app][$key] = [
			'value' => $value,
			'sensitive' => $sensitive ?? $cache[$app][$key]['sensitive'] ?? false,
			'lazyGroup' => $lazyGroup
		];

		$insert = $this->connection->getQueryBuilder();
		$insert->insert('appconfig')
			   ->setValue('appid', $insert->createNamedParameter($app))
			   ->setValue('lazy_group', $insert->createNamedParameter($lazyGroup))
			   ->setValue('sensitive', $insert->createNamedParameter(($sensitive ?? false) ? 1 : 0, IQueryBuilder::PARAM_INT))
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
				   ->set('lazy_group', $update->createNamedParameter($lazyGroup))
				   ->where($update->expr()->eq('appid', $update->createNamedParameter($app)))
				   ->andWhere($update->expr()->eq('configkey', $update->createNamedParameter($key)));
			if ($sensitive !== null) {
				$update->set('sensitive', $update->createNamedParameter($sensitive ? 1 : 0, IQueryBuilder::PARAM_INT));
			}

			$update->executeStatement();
		}

		return true;
	}

	/**
	 * @inheritDoc
	 * @param string $app id of the app
	 * @param string $key config key
	 * @param int $value config value
	 * @param string $lazyGroup name of the lazy group
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
	public function setValueInt(string $app, string $key, int $value, ?bool $sensitive = null, string $lazyGroup = ''): bool {
		return $this->setValueString($app, $key, (string) $value, $sensitive, $lazyGroup);
	}

	/**
	 * @inheritDoc
	 * @param string $app id of the app
	 * @param string $key config key
	 * @param float $value config value
	 * @param string $lazyGroup name of the lazy group
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
	public function setValueFloat(string $app, string $key, float $value, ?bool $sensitive = null, string $lazyGroup = ''): bool {
		return $this->setValueString($app, $key, (string) $value, $sensitive, $lazyGroup);
	}

	/**
	 * @inheritDoc
	 * @param string $app id of the app
	 * @param string $key config key
	 * @param bool $value config value
	 * @param string $lazyGroup name of the lazy group
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
	public function setValueBool(string $app, string $key, bool $value, ?bool $sensitive = null, string $lazyGroup = ''): bool {
		return $this->setValueString($app, $key, $value ? 'true' : 'false', $sensitive, $lazyGroup);
	}

	/**
	 * @inheritDoc
	 * @param string $app id of the app
	 * @param string $key config key
	 * @param array $value config value
	 * @param string $lazyGroup name of the lazy group
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
	public function setValueArray(string $app, string $key, array $value, ?bool $sensitive = null, string $lazyGroup = ''): bool {
		try {
			return $this->setValueString($app, $key, json_encode($value, JSON_THROW_ON_ERROR), $sensitive, $lazyGroup);
		} catch (JsonException $e) {
			$this->logger->warning('could not setValueArray', ['app' => $app, 'key' => $key, 'exception' => $e]);
		}

		return false;
	}

	/**
	 * @inheritDoc
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
	 * @param string $lazyGroup
	 *
	 * @since 29.0.0
	 * @see IAppConfig for explanation about lazy grouping
	 */
	public function deleteLazyGroup(string $lazyGroup): void {
		$this->assertParams(lazyGroup: $lazyGroup);
		$qb = $this->connection->getQueryBuilder();
		$qb->delete('appconfig')
		   ->where($qb->expr()->eq('lazy_group', $qb->createNamedParameter($lazyGroup)));
		$qb->executeStatement();

		$this->clearCache();
	}

	/**
	 * @inheritDoc
	 * @since 29.0.0
	 */
	public function clearCache(): void {
		$this->lazyCache = $this->fastCache = [];
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
			'fastCache' => $this->fastCache,
			'lazyCache' => $this->lazyCache,
			'loaded' => $this->loaded,
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
	 * @throws InvalidArgumentException
	 */
	private function assertParams(string $app = '', string $configKey = '', string $lazyGroup = '', bool $allowEmptyApp = false): void {
		if (!$allowEmptyApp && $app === '') {
			throw new InvalidArgumentException('app cannot be an empty string');
		}
		if (strlen($app) > self::APP_MAX_LENGTH) {
			throw new InvalidArgumentException('Value (' . $app . ') for app is too long (' . self::APP_MAX_LENGTH . ')');
		}
		if (strlen($configKey) > self::KEY_MAX_LENGTH) {
			throw new InvalidArgumentException('Value (' . $configKey . ') for key is too long (' . self::KEY_MAX_LENGTH . ')');
		}
		if (strlen($lazyGroup) > self::LAZY_MAX_LENGTH) {
			throw new InvalidArgumentException('Value (' . $lazyGroup . ') for lazyGroup is too long (' . self::LAZY_MAX_LENGTH . ')');
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
		// TODO: why use of __ALL__ ? still needed ?
		$this->loadConfig(self::ALL_APPS_CONFIG, ignoreLazyGroup: true);
	}

	/**
	 * We store config in multiple internal cache, so we don't load everything
	 *
	 * @param string $app
	 * @param string $lazyGroup
	 * @param bool $ignoreLazyGroup
	 */
	private function loadConfig(string $lazyGroup = '', bool $ignoreLazyGroup = false): void {
		if ($this->isLoaded($lazyGroup)) {
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
			$qb->select('appid', 'configkey', 'configvalue', 'sensitive', 'lazy_group');
			if (!$ignoreLazyGroup) {
				$qb->where($qb->expr()->eq('lazy_group', $qb->createNamedParameter($lazyGroup)));
			}
		}

		try {
			$result = $qb->executeQuery();
		} catch (DBException $e) {
			/**
			 * in case of issue with field name, it means that migration is not completed.
			 * Falling back to a request without select on lazy_group.
			 * This whole try/catch and the migrationCompleted variable can be removed in NC30.
			 */
			if ($e->getReason() !== DBException::REASON_INVALID_FIELD_NAME) {
				throw $e;
			}

			$this->migrationCompleted = false;
			$this->loadConfig($lazyGroup, $ignoreLazyGroup);
			return;
		}

		$this->setLoadedStatus($lazyGroup); // in case the group is empty
		$rows = $result->fetchAll();
		foreach ($rows as $row) {
			if ($ignoreLazyGroup) {
				$this->setLoadedStatus($row['lazy_group'] ?? '');
			}
			// if migration is not completed, 'lazy_group' does not exist in $row
			(($row['lazy_group'] ?? '') === '') ? $cache = &$this->fastCache : $cache = &$this->lazyCache;
			$cache[$row['appid']][$row['configkey']] =
				[
					'value' => $row['configvalue'],
					'lazyGroup' => $row['lazy_group'],
					'sensitive' => ($row['sensitive'] === 1)
				];
		}
		$result->closeCursor();
		if ($lazyGroup === '' && !$ignoreLazyGroup) {
			$this->storeDistributedCache();
		}
	}

	/**
	 * @param string $lazyGroup
	 *
	 * @return bool
	 */
	private function isLoaded(string $lazyGroup): bool {
		return in_array($lazyGroup, $this->loaded);
	}

	private function setLoadedStatus(string $lazyGroup): void {
		if ($this->isLoaded($lazyGroup)) {
			return;
		}

		$this->loaded[] = $lazyGroup;
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
			$this->setLoadedStatus('');
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
	 * @deprecated
	 *
	 * @param string $app app
	 * @param string $key key
	 * @param string|float|int $value value
	 *
	 * @return bool True if the value was inserted or updated, false if the value was the same
	 */
	public function setValue($app, $key, $value) {
		return $this->setValueString($app, $key, (string)$value);
	}


	/**
	 * Deletes a key
	 *
	 * @param string $app app
	 * @param string $key key
	 * @deprecated use unsetKey()
	 * @see self::unsetKey()
	 * @return boolean
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
