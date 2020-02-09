<?php
/**
 * @copyright Copyright (C) 2020, Friendica
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 */

namespace Friendica\Repository;

use Exception;
use Friendica\BaseRepository;
use Friendica\Core\Hook;
use Friendica\Model;
use Friendica\Collection;
use Friendica\Network\HTTPException\InternalServerErrorException;
use Friendica\Network\HTTPException\NotFoundException;
use Friendica\Util\DateTimeFormat;

class Notify extends BaseRepository
{
	protected static $table_name = 'notify';

	protected static $model_class = Model\Notify::class;

	protected static $collection_class = Collection\Notifies::class;

	/**
	 * {@inheritDoc}
	 *
	 * @return Model\Notify
	 */
	protected function create(array $data)
	{
		return new Model\Notify($this->dba, $this->logger, $this, $data);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return Collection\Notifies
	 */
	public function select(array $condition = [], array $params = [])
	{
		$params['order'] = $params['order'] ?? ['date' => 'DESC'];

		return parent::select($condition, $params);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return Model\Notify
	 * @throws NotFoundException
	 */
	public function getByID(int $id)
	{
		return $this->selectFirst(['id' => $id, 'uid' => local_user()]);
	}

	/**
	 * Set seen state of notifications of the local_user()
	 *
	 * @param bool         $seen   optional true or false. default true
	 * @param Model\Notify $notify optional a notify, which should be set seen (including his parents)
	 *
	 * @return bool true on success, false on error
	 *
	 * @throws Exception
	 */
	public function setSeen(bool $seen = true, Model\Notify $notify = null)
	{
		if (empty($notify)) {
			$conditions = ['uid' => local_user()];
		} else {
			$conditions = ['(`link` = ? OR (`parent` != 0 AND `parent` = ? AND `otype` = ?)) AND `uid` = ?',
				$notify->link,
				$notify->parent,
				$notify->otype,
				local_user()];
		}

		return $this->dba->update('notify', ['seen' => $seen], $conditions);
	}

	/**
	 * @param array $fields
	 *
	 * @return Model\Notify|false
	 *
	 * @throws InternalServerErrorException
	 * @throws Exception
	 */
	public function insert(array $fields)
	{
		$fields['date'] = DateTimeFormat::utcNow();

		Hook::callAll('enotify_store', $fields);

		if (empty($fields)) {
			$this->logger->debug('Abort adding notification entry');
			return false;
		}

		$this->logger->debug('adding notification entry', ['fields' => $fields]);

		return parent::insert($fields);
	}
}
