<?php
/**
 * @copyright Copyright (c) 2023 Kate Döen <kate.doeen@nextcloud.com>
 *
 * @author Kate Döen <kate.doeen@nextcloud.com>
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

namespace OCP\Settings;

/**
 * @since 29.0.0
 *
 * @psalm-type DeclarativeSettingsSectionType = 'admin'|'personal'
 *
 * @psalm-type DeclarativeSettingsStorageType = 'db'|'external'
 *
 * @psalm-type DeclarativeSettingsValueTypes = string|int|float|bool|list<string>
 *
 * @psalm-type DeclarativeSettingsFormField = array{
 *   id: string,
 *   title: string,
 *   description?: string,
 *   type: 'string'|'number'|'boolean'|'enum',
 *   isSecure?: bool,
 *   isMultiValue?: bool,
 *   format?: 'integer|float',
 *   values?: list<string>,
 *   storage_type?: DeclarativeSettingsStorageType,
 * }
 *
 * @psalm-type DeclarativeSettingsFormFieldWithValue = DeclarativeSettingsFormField&array{
 *     value: DeclarativeSettingsValueTypes,
 * }
 *
 * @psalm-type DeclarativeSettingsFormSchema = array{
 *   id: string,
 *   priority: int,
 *   storage_type?: DeclarativeSettingsStorageType,
 *   section_type: DeclarativeSettingsSectionType,
 *   section_id: string,
 * }
 *
 * @psalm-type DeclarativeSettingsFormSchemaWithValues = DeclarativeSettingsFormSchema&array{
 *   app: string,
 *   fields: list<DeclarativeSettingsFormFieldWithValue>,
 * }
 *
 * @psalm-type DeclarativeSettingsFormSchemaWithoutValues = DeclarativeSettingsFormSchema&array{
 *   fields: list<DeclarativeSettingsFormField>,
 * }
 */
interface IDeclarativeSettingsForm {
	/**
	 * Gets the schema that defines the declarative settings form
	 *
	 * @return DeclarativeSettingsFormSchemaWithoutValues
	 * @since 29.0.0
	 */
	public function getSchema(): array;
}
