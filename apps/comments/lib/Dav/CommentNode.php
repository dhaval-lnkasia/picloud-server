<?php
/**
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Vincent Petry <pvince81@owncloud.com>
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

namespace OCA\Comments\Dav;

use OCP\Comments\IComment;
use OCP\Comments\ICommentsManager;
use OCP\Comments\MessageTooLongException;
use OCP\ILogger;
use OCP\IUserManager;
use OCP\IUserSession;
use Sabre\DAV\Exception\BadRequest;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Exception\MethodNotAllowed;
use Sabre\DAV\PropPatch;

class CommentNode implements \Sabre\DAV\INode, \Sabre\DAV\IProperties {
	public const NS_OWNCLOUD = 'http://owncloud.org/ns';

	public const PROPERTY_NAME_UNREAD = '{http://owncloud.org/ns}isUnread';
	public const PROPERTY_NAME_MESSAGE = '{http://owncloud.org/ns}message';
	public const PROPERTY_NAME_ACTOR_DISPLAYNAME = '{http://owncloud.org/ns}actorDisplayName';

	/** @var  IComment */
	public $comment;

	/** @var ICommentsManager */
	protected $commentsManager;

	/** @var  ILogger */
	protected $logger;

	/** @var array list of properties with key being their name and value their setter */
	protected $properties = [];

	/** @var IUserManager */
	protected $userManager;

	/** @var IUserSession */
	protected $userSession;

	/**
	 * CommentNode constructor.
	 *
	 * @param ICommentsManager $commentsManager
	 * @param IComment $comment
	 * @param IUserManager $userManager
	 * @param IUserSession $userSession
	 * @param ILogger $logger
	 */
	public function __construct(
		ICommentsManager $commentsManager,
		IComment $comment,
		IUserManager $userManager,
		IUserSession $userSession,
		ILogger $logger
	) {
		$this->commentsManager = $commentsManager;
		$this->comment = $comment;
		$this->logger = $logger;

		$methods = \get_class_methods($this->comment);
		$methods = \array_filter($methods, function ($name) {
			return \strpos($name, 'get') === 0;
		});
		foreach ($methods as $getter) {
			$name = '{'.self::NS_OWNCLOUD.'}' . \lcfirst(\substr($getter, 3));
			$this->properties[$name] = $getter;
		}
		$this->userManager = $userManager;
		$this->userSession = $userSession;
	}

	/**
	 * returns a list of all possible property names
	 *
	 * @return array
	 */
	public static function getPropertyNames() {
		return [
			'{http://owncloud.org/ns}id',
			'{http://owncloud.org/ns}parentId',
			'{http://owncloud.org/ns}topmostParentId',
			'{http://owncloud.org/ns}childrenCount',
			'{http://owncloud.org/ns}verb',
			'{http://owncloud.org/ns}actorType',
			'{http://owncloud.org/ns}actorId',
			'{http://owncloud.org/ns}creationDateTime',
			'{http://owncloud.org/ns}latestChildDateTime',
			'{http://owncloud.org/ns}objectType',
			'{http://owncloud.org/ns}objectId',
			// re-used property names are defined as constants
			self::PROPERTY_NAME_MESSAGE,
			self::PROPERTY_NAME_ACTOR_DISPLAYNAME,
			self::PROPERTY_NAME_UNREAD
		];
	}

	protected function checkWriteAccessOnComment() {
		$user = $this->userSession->getUser();
		if ($this->comment->getActorType() !== 'users'
			|| $user === null
			|| $this->comment->getActorId() !== $user->getUID()
		) {
			throw new Forbidden('Only authors are allowed to edit their comment.');
		}
	}

	/**
	 * Deleted the current node
	 *
	 * @return void
	 */
	public function delete() {
		$this->checkWriteAccessOnComment();
		$this->commentsManager->delete($this->comment->getId());
	}

	/**
	 * Returns the name of the node.
	 *
	 * This is used to generate the url.
	 *
	 * @return string
	 */
	public function getName() {
		return $this->comment->getId();
	}

	/**
	 * Renames the node
	 *
	 * @param string $name The new name
	 * @throws MethodNotAllowed
	 */
	public function setName($name) {
		throw new MethodNotAllowed();
	}

	/**
	 * Returns the last modification time, as a unix timestamp
	 *
	 * @return int
	 */
	public function getLastModified() {
		return null;
	}

	/**
	 * update the comment's message
	 *
	 * @param $propertyValue
	 * @return bool
	 * @throws BadRequest
	 * @throws Forbidden
	 */
	public function updateComment($propertyValue) {
		$this->checkWriteAccessOnComment();
		try {
			$this->comment->setMessage($propertyValue);
			$this->commentsManager->save($this->comment);
			return true;
		} catch (\Exception $e) {
			$this->logger->logException($e, ['app' => 'dav/comments']);
			if ($e instanceof MessageTooLongException) {
				$msg = 'Message exceeds allowed character limit of ';
				throw new BadRequest($msg . IComment::MAX_MESSAGE_LENGTH, 0, $e);
			}
			throw $e;
		}
	}

	/**
	 * Updates properties on this node.
	 *
	 * This method received a PropPatch object, which contains all the
	 * information about the update.
	 *
	 * To update specific properties, call the 'handle' method on this object.
	 * Read the PropPatch documentation for more information.
	 *
	 * @param PropPatch $propPatch
	 * @return void
	 */
	public function propPatch(PropPatch $propPatch) {
		// other properties than 'message' are read only
		$propPatch->handle(self::PROPERTY_NAME_MESSAGE, [$this, 'updateComment']);
	}

	/**
	 * Returns a list of properties for this nodes.
	 *
	 * The properties list is a list of propertynames the client requested,
	 * encoded in clark-notation {xmlnamespace}tagname
	 *
	 * If the array is empty, it means 'all properties' were requested.
	 *
	 * Note that it's fine to liberally give properties back, instead of
	 * conforming to the list of requested properties.
	 * The Server class will filter out the extra.
	 *
	 * @param array|null $properties requested properties or null for all
	 * @return array
	 */
	public function getProperties($properties) {
		if (($properties === null) || ($properties === [])) {
			$properties = \array_keys($this->properties);
		}

		$result = [];
		foreach ($properties as $property) {
			$getter = $this->properties[$property];
			if (\method_exists($this->comment, $getter)) {
				$result[$property] = $this->comment->$getter();
			}
		}

		if ($this->comment->getActorType() === 'users') {
			$user = $this->userManager->get($this->comment->getActorId());
			$displayName = $user === null ? null : $user->getDisplayName();
			$result[self::PROPERTY_NAME_ACTOR_DISPLAYNAME] = $displayName;
		}

		$unread = null;
		$user =  $this->userSession->getUser();
		if ($user !== null) {
			$readUntil = $this->commentsManager->getReadMark(
				$this->comment->getObjectType(),
				$this->comment->getObjectId(),
				$user
			);
			if ($readUntil === null) {
				$unread = 'true';
			} else {
				$unread = $this->comment->getCreationDateTime() > $readUntil;
				// re-format for output
				$unread = $unread ? 'true' : 'false';
			}
		}
		$result[self::PROPERTY_NAME_UNREAD] = $unread;

		return $result;
	}
}
