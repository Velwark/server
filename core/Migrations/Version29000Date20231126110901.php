<?php

declare(strict_types=1);
/**
 * @copyright 2023 Maxence Lange <maxence@artificial-owl.com>
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OC\Core\Migrations;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

// Create new field in appconfig for the new IAppConfig API, including lazy grouping.
class Version29000Date20231126110901 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('appconfig')) {
			return null;
		}

		$table = $schema->getTable('appconfig');
		if ($table->hasColumn('lazy')) {
			return null;
		}

		$table->addColumn('lazy', Types::BOOLEAN, ['notnull' => false, 'default' => false]);
		$table->addColumn('sensitive', Types::BOOLEAN, ['notnull' => false, 'default' => false]);

		if ($table->hasIndex('appconfig_config_key_index')) {
			$table->dropIndex('appconfig_config_key_index');
		}

		$table->addIndex(['lazy'], 'ac_lazy_i');
		$table->addIndex(['appid', 'lazy'], 'ac_app_lazy_i');
		$table->addIndex(['appid', 'lazy', 'configkey'], 'ac_app_lazy_key_i');

		return $schema;
	}
}
