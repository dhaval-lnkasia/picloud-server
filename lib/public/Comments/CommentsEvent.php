<?php
/**
 * @author Joas Schilling <coding@schilljs.com>
 *
 * @copyright LNKASIA TECHSOL
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCP\Comments;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Class CommentsEvent
 *
 * @package OCP\Comments
 * @since 9.0.0
 */
class CommentsEvent extends Event {
	public const EVENT_ADD = 'OCP\Comments\ICommentsManager::addComment';
	public const EVENT_UPDATE = 'OCP\Comments\ICommentsManager::updateComment';
	public const EVENT_DELETE = 'OCP\Comments\ICommentsManager::deleteComment';

	/** @var string */
	protected $event;
	/** @var IComment */
	protected $comment;

	/**
	 * DispatcherEvent constructor.
	 *
	 * @param string $event
	 * @param IComment $comment
	 * @since 9.0.0
	 */
	public function __construct($event, IComment $comment) {
		$this->event = $event;
		$this->comment = $comment;
	}

	/**
	 * @return string
	 * @since 9.0.0
	 */
	public function getEvent() {
		return $this->event;
	}

	/**
	 * @return IComment
	 * @since 9.0.0
	 */
	public function getComment() {
		return $this->comment;
	}
}
