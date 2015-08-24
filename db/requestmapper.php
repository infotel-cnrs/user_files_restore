<?php

/**
 * ownCloud - User Files Restore
 *
 * @author Patrick Paysant <ppaysant@linagora.com>
 * @copyright 2015 CNRS DSI
 * @license This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\User_Files_Restore\Db;

use \OCP\IDb;
use \OCP\IL10N;
use \OCP\AppFramework\Db\Mapper;

class RequestMapper extends Mapper
{
    const STATUS_TODO = 1;
    const STATUS_RUNNING = 2;
    const STATUS_DONE = 3;

    protected $l;

    public function __construct(IDb $db, IL10N $l)
    {
        $this->l = $l;

        parent::__construct($db, 'user_files_restore');
    }

    /**
     * Store a restore request in database
     * @param  int    $uid      User identifier
     * @param  string $path     path to the file (or directory) to restore, relative to user's home dir (in owncloud)
     * @param  int    $version  The resource's version taken (if possible) nb of days before today (should be 1, 6 or 15 days)
     * @param  string $filetype File type ('file' or 'dir')
     * @param  int $limit
     * @param  int $offset
     * @return OCA\User_Files_Restore\Db\request The created request
     */
    public function saveRequest($uid, $path, $version, $filetype, $limit=null, $offset=null)
    {
        if ($filetype !== 'file' and $filetype !== 'dir') {
            throw new \Exception($this->l->t('Server error: filetype not allowed.'));
            return false;
        }

        $sql = "SELECT * FROM *PREFIX*user_files_restore WHERE uid = ? AND path = ? AND status = " . self::STATUS_TODO;
        try {
            $request = $this->findEntity($sql, array($uid, $path), $limit, $offset);

            $this->delete($request);
        }
        catch (\OCP\AppFramework\Db\DoesNotExistException $e) {
        }
        catch (\OCP\AppFramework\Db\MultipleObjectsReturnedException $e) {
            throw new \Exception($this->l->t('Server error: more than one request with same requester/path pair.'));
            return false;
        }

        $request = new Request;
        $request->setUid($uid);
        $request->setDateRequest(date('Y-m-d H:i:s'));
        $request->setPath($path);
        $request->setFiletype($filetype);
        $request->setVersion((int)$version);
        $request->setStatus(self::STATUS_TODO);

        $this->insert($request);

        return $request;
    }

    public function getRequests($uid, $status=0, $limit=null, $offset=null)
    {
        if ($status == 0) {
            $sql = "SELECT * FROM *PREFIX*user_files_restore WHERE uid = ?";
            $requests = $this->findEntities($sql, array($uid), $limit, $offset);
        }
        else {
            $sql = "SELECT * FROM *PREFIX*user_files_restore WHERE uid = ? AND status = ?";
            $requests = $this->findEntities($sql, array($uid, $status), $limit, $offset);
        }

        return $requests;
    }

    /**
     * Delete a restore request in database
     * @param  int    $uid      User identifier
     * @param  string $id       Request identifier
     * @return OCA\User_Files_Restore\Db\request The created request
     */
    public function cancelRequest($uid, $id)
    {
        $sql = "SELECT * FROM *PREFIX*user_files_restore WHERE uid = ? AND id = ? AND status = " . self::STATUS_TODO;
        try {
            $request = $this->findEntity($sql, array($uid, $id), $limit, $offset);

            $this->delete($request);
        }
        catch (\OCP\AppFramework\Db\DoesNotExistException $e) {
            throw new \Exception($this->l->t('Server error: no open todo request with this id.'));
            return false;
        }
        catch (\OCP\AppFramework\Db\MultipleObjectsReturnedException $e) {
            throw new \Exception($this->l->t('Server error: more than one request with same id.'));
            return false;
        }

        return $request;
    }
}