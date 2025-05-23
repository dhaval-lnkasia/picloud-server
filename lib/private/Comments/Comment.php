<?php
/**
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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
namespace OC\Comments;

use OCP\Comments\IComment;
use OCP\Comments\IllegalIDChangeException;
use OCP\Comments\MessageTooLongException;

class Comment implements IComment {
	protected array $data = [
		'id'              => '',
		'parentId'        => '0',
		'topmostParentId' => '0',
		'childrenCount'   => '0',
		'message'         => '',
		'verb'            => '',
		'actorType'       => '',
		'actorId'         => '',
		'objectType'      => '',
		'objectId'        => '',
		'creationDT'      => null,
		'latestChildDT'   => null,
	];

	/**
	 * Comment constructor.
	 *
	 * @param array|null $data	optional, array with keys according to column names from
	 * 					the comments database scheme
	 */
	public function __construct(array $data = null) {
		if (\is_array($data)) {
			$this->fromArray($data);
		}
	}

	/**
	 * returns the ID of the comment
	 *
	 * It may return an empty string, if the comment was not stored.
	 * It is expected that the concrete Comment implementation gives an ID
	 * by itself (e.g. after saving).
	 *
	 * @return string
	 * @since 9.0.0
	 */
	public function getId() {
		return $this->data['id'];
	}

	/**
	 * sets the ID of the comment and returns itself
	 *
	 * It is only allowed to set the ID only, if the current id is an empty
	 * string (which means it is not stored in a database, storage or whatever
	 * the concrete implementation does), or vice versa. Changing a given ID is
	 * not permitted and must result in an IllegalIDChangeException.
	 *
	 * @param string $id
	 * @return IComment
	 * @throws IllegalIDChangeException
	 * @since 9.0.0
	 */
	public function setId($id) {
		if (!\is_string($id)) {
			throw new \InvalidArgumentException('String expected.');
		}

		$id = \trim($id);
		if ($this->data['id'] === '' || ($this->data['id'] !== '' && $id === '')) {
			$this->data['id'] = $id;
			return $this;
		}

		throw new IllegalIDChangeException('Not allowed to assign a new ID to an already saved comment.');
	}

	/**
	 * returns the parent ID of the comment
	 *
	 * @return string the parent id or "0" if there is no parent
	 * @since 9.0.0
	 */
	public function getParentId() {
		return $this->data['parentId'];
	}

	/**
	 * sets the parent ID and returns itself
	 *
	 * @param string $parentId
	 * @return IComment
	 * @since 9.0.0
	 */
	public function setParentId($parentId) {
		if (!\is_string($parentId)) {
			throw new \InvalidArgumentException('String expected.');
		}
		$this->data['parentId'] = \trim($parentId);
		return $this;
	}

	/**
	 * returns the topmost parent ID of the comment
	 *
	 * @return string
	 * @since 9.0.0
	 */
	public function getTopmostParentId() {
		return $this->data['topmostParentId'];
	}

	/**
	 * sets the topmost parent ID and returns itself
	 *
	 * @param string $id
	 * @return IComment
	 * @since 9.0.0
	 */
	public function setTopmostParentId($id) {
		if (!\is_string($id)) {
			throw new \InvalidArgumentException('String expected.');
		}
		$this->data['topmostParentId'] = \trim($id);
		return $this;
	}

	/**
	 * returns the number of children
	 *
	 * @return int
	 * @since 9.0.0
	 */
	public function getChildrenCount() {
		return $this->data['childrenCount'];
	}

	/**
	 * sets the number of children
	 *
	 * @param int $count
	 * @return IComment
	 * @since 9.0.0
	 */
	public function setChildrenCount($count) {
		if (!\is_int($count)) {
			throw new \InvalidArgumentException('Integer expected.');
		}
		$this->data['childrenCount'] = $count;
		return $this;
	}

	/**
	 * returns the message of the comment
	 *
	 * @return string
	 * @since 9.0.0
	 */
	public function getMessage() {
		return $this->data['message'];
	}

	/**
	 * sets the message of the comment and returns itself
	 *
	 * @param string $message
	 * @return IComment
	 * @throws MessageTooLongException
	 * @since 9.0.0
	 */
	public function setMessage($message) {
		if (!\is_string($message)) {
			throw new \InvalidArgumentException('String expected.');
		}
		$message = \trim($message);
		if (\strlen($message) > IComment::MAX_MESSAGE_LENGTH) {
			throw new MessageTooLongException('Comment message must not exceed ' . IComment::MAX_MESSAGE_LENGTH . ' characters');
		}
		$this->data['message'] = $message;
		return $this;
	}

	/**
	 * returns the verb of the comment
	 *
	 * @return string
	 * @since 9.0.0
	 */
	public function getVerb() {
		return $this->data['verb'];
	}

	/**
	 * sets the verb of the comment, e.g. 'comment' or 'like'
	 *
	 * @param string $verb
	 * @return IComment
	 * @since 9.0.0
	 */
	public function setVerb($verb) {
		if (!\is_string($verb) || !\trim($verb)) {
			throw new \InvalidArgumentException('Non-empty String expected.');
		}
		$verb = \trim($verb);
		if (\strlen($verb) > IComment::MAX_VERB_LENGTH) {
			throw new \InvalidArgumentException('Comment verb must not exceed ' . IComment::MAX_VERB_LENGTH . ' characters');
		}

		$this->data['verb'] = \trim($verb);
		return $this;
	}

	/**
	 * returns the actor type
	 *
	 * @return string
	 * @since 9.0.0
	 */
	public function getActorType() {
		return $this->data['actorType'];
	}

	/**
	 * returns the actor ID
	 *
	 * @return string
	 * @since 9.0.0
	 */
	public function getActorId() {
		return $this->data['actorId'];
	}

	/**
	 * sets (overwrites) the actor type and id
	 *
	 * @param string $actorType e.g. 'users'
	 * @param string $actorId e.g. 'zombie234'
	 * @return IComment
	 * @since 9.0.0
	 */
	public function setActor($actorType, $actorId) {
		if (
			!\is_string($actorType) || !\trim($actorType)
			|| !\is_string($actorId)   || !\trim($actorId)
		) {
			throw new \InvalidArgumentException('String expected.');
		}
		$this->data['actorType'] = \trim($actorType);
		$this->data['actorId']   = \trim($actorId);
		return $this;
	}

	/**
	 * returns the creation date of the comment.
	 *
	 * If not explicitly set, it shall default to the time of initialization.
	 *
	 * @return \DateTime
	 * @since 9.0.0
	 */
	public function getCreationDateTime() {
		return $this->data['creationDT'];
	}

	/**
	 * sets the creation date of the comment and returns itself
	 *
	 * @param \DateTime $timestamp
	 * @return IComment
	 * @since 9.0.0
	 */
	public function setCreationDateTime(\DateTime $timestamp) {
		$this->data['creationDT'] = $timestamp;
		return $this;
	}

	/**
	 * returns the DateTime of the most recent child, if set, otherwise null
	 *
	 * @return \DateTime|null
	 * @since 9.0.0
	 */
	public function getLatestChildDateTime() {
		return $this->data['latestChildDT'];
	}

	/**
	 * sets the date of the most recent child
	 *
	 * @param \DateTime|null $dateTime
	 * @return IComment
	 * @since 9.0.0
	 */
	public function setLatestChildDateTime(\DateTime $dateTime = null) {
		$this->data['latestChildDT'] = $dateTime;
		return $this;
	}

	/**
	 * returns the object type the comment is attached to
	 *
	 * @return string
	 * @since 9.0.0
	 */
	public function getObjectType() {
		return $this->data['objectType'];
	}

	/**
	 * returns the object id the comment is attached to
	 *
	 * @return string
	 * @since 9.0.0
	 */
	public function getObjectId() {
		return $this->data['objectId'];
	}

	/**
	 * sets (overwrites) the object of the comment
	 *
	 * @param string $objectType e.g. 'files'
	 * @param string $objectId e.g. '16435'
	 * @return IComment
	 * @since 9.0.0
	 */
	public function setObject($objectType, $objectId) {
		if (
			!\is_string($objectType) || !\trim($objectType)
			|| !\is_string($objectId)   || !\trim($objectId)
		) {
			throw new \InvalidArgumentException('String expected.');
		}
		$this->data['objectType'] = \trim($objectType);
		$this->data['objectId']   = \trim($objectId);
		return $this;
	}

	/**
	 * sets the comment data based on an array with keys as taken from the
	 * database.
	 *
	 * @param [] $data
	 * @return IComment
	 */
	protected function fromArray($data) {
		foreach (\array_keys($data) as $key) {
			// translate DB keys to internal setter names
			$setter = 'set' . \implode('', \array_map('ucfirst', \explode('_', $key)));
			$setter = \str_replace('Timestamp', 'DateTime', $setter);

			if (\method_exists($this, $setter)) {
				$this->$setter($data[$key]);
			}
		}

		foreach (['actor', 'object'] as $role) {
			if (isset($data[$role . '_type'], $data[$role.'_id'])) {
				$setter = 'set' . \ucfirst($role);
				$this->$setter($data[$role . '_type'], $data[$role . '_id']);
			}
		}

		return $this;
	}
}
