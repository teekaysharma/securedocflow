<?php
/*
 * Copyright (C) 2000-2025. Stephen Lawrence
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */

// Access Request workflow: a user who lacks sufficient permission on a
// document can request a specific rights level; Admin or the file's
// Department Head (same authority as canSetHighClassification()) can grant
// or deny. Granting writes a real individual permission row (odm_user_perms)
// at the requested level — this is additive, it does not touch how
// department/group/individual permissions are otherwise evaluated.

if (!defined('AccessRequest_class')) {
    define('AccessRequest_class', 'true', false);

    class AccessRequest extends databaseData
    {
        protected $connection;
        public $id;
        public $file_id;
        public $requesting_user_id;
        public $requested_level;
        public $classification_at_request;
        public $reason;
        public $status;
        public $requested_on;
        public $resolved_by;
        public $resolved_on;
        public $resolution_note;

        public function __construct($id, PDO $connection)
        {
            $this->connection = $connection;
            $this->id = (int) $id;
            $this->loadData();
        }

        public function loadData()
        {
            $query = "SELECT * FROM {$GLOBALS['CONFIG']['db_prefix']}access_request WHERE id = :id";
            $stmt = $this->connection->prepare($query);
            $stmt->execute(array(':id' => $this->id));
            $row = $stmt->fetch();
            if ($row) {
                $this->file_id = $row['file_id'];
                $this->requesting_user_id = $row['requesting_user_id'];
                $this->requested_level = $row['requested_level'];
                $this->classification_at_request = $row['classification_at_request'];
                $this->reason = $row['reason'];
                $this->status = $row['status'];
                $this->requested_on = $row['requested_on'];
                $this->resolved_by = $row['resolved_by'];
                $this->resolved_on = $row['resolved_on'];
                $this->resolution_note = $row['resolution_note'];
            }
        }

        /**
         * Create a new pending request. Logs 'Q' (Access Requested) on the file.
         * Records the document's classification at request time so a resolver
         * can later be shown whether it has since changed (see getResolvableRequests()).
         * @param int $fileId
         * @param int $userId
         * @param int $requestedLevel
         * @param string $reason
         * @param string $classification
         * @param PDO $pdo
         * @return int the new request's id
         */
        public static function createRequest($fileId, $userId, $requestedLevel, $reason, $classification, PDO $pdo)
        {
            $query = "
              INSERT INTO {$GLOBALS['CONFIG']['db_prefix']}access_request
                (file_id, requesting_user_id, requested_level, classification_at_request, reason, status, requested_on)
              VALUES
                (:file_id, :user_id, :level, :classification, :reason, 'pending', NOW())
            ";
            $stmt = $pdo->prepare($query);
            $stmt->execute(array(
                ':file_id' => $fileId,
                ':user_id' => $userId,
                ':level' => $requestedLevel,
                ':classification' => $classification,
                ':reason' => $reason
            ));
            $newId = $pdo->lastInsertId();
            AccessLog::addLogEntry($fileId, 'Q', $pdo, $reason);
            return $newId;
        }

        /**
         * Does this user already have an unresolved request on this file?
         * @param int $fileId
         * @param int $userId
         * @param PDO $pdo
         * @return bool
         */
        public static function hasPendingRequest($fileId, $userId, PDO $pdo)
        {
            $query = "
              SELECT id FROM {$GLOBALS['CONFIG']['db_prefix']}access_request
              WHERE file_id = :file_id AND requesting_user_id = :user_id AND status = 'pending'
            ";
            $stmt = $pdo->prepare($query);
            $stmt->execute(array(':file_id' => $fileId, ':user_id' => $userId));
            return $stmt->rowCount() > 0;
        }

        /**
         * Pending requests this user is authorised to resolve: all of them if
         * Admin, otherwise only for documents in departments they head.
         * @param User $userObj
         * @param PDO $pdo
         * @return array
         */
        public static function getResolvableRequests(User $userObj, PDO $pdo)
        {
            if ($userObj->isAdmin()) {
                $query = "
                  SELECT ar.*, d.realname, d.department, d.doc_classification AS current_classification, u.first_name, u.last_name
                  FROM {$GLOBALS['CONFIG']['db_prefix']}access_request ar
                  INNER JOIN {$GLOBALS['CONFIG']['db_prefix']}data d ON d.id = ar.file_id
                  INNER JOIN {$GLOBALS['CONFIG']['db_prefix']}user u ON u.id = ar.requesting_user_id
                  WHERE ar.status = 'pending'
                  ORDER BY ar.requested_on
                ";
                $stmt = $pdo->prepare($query);
                $stmt->execute();
                return $stmt->fetchAll();
            }

            $query = "
              SELECT ar.*, d.realname, d.department, d.doc_classification AS current_classification, u.first_name, u.last_name
              FROM {$GLOBALS['CONFIG']['db_prefix']}access_request ar
              INNER JOIN {$GLOBALS['CONFIG']['db_prefix']}data d ON d.id = ar.file_id
              INNER JOIN {$GLOBALS['CONFIG']['db_prefix']}user u ON u.id = ar.requesting_user_id
              INNER JOIN {$GLOBALS['CONFIG']['db_prefix']}dept_head dh ON dh.dept_id = d.department
              WHERE ar.status = 'pending' AND dh.user_id = :user_id
              ORDER BY ar.requested_on
            ";
            $stmt = $pdo->prepare($query);
            $stmt->execute(array(':user_id' => $userObj->getId()));
            return $stmt->fetchAll();
        }

        /**
         * Grant this request: writes/replaces the individual permission row
         * for (file, requesting user) at the requested level, marks the
         * request resolved, logs 'G' (Access Granted) on the file.
         * resolve() is called first and is atomic (UPDATE ... WHERE
         * status='pending') — if two concurrent Grant/Deny submissions race
         * on the same request, only the one that actually flips the row from
         * 'pending' proceeds to write the permission; the loser is a no-op
         * rather than a duplicate/conflicting grant.
         * @param int $resolverId
         * @param string $note
         * @param PDO $pdo
         * @return bool true if this call actually resolved the request
         */
        public function grant($resolverId, $note, PDO $pdo)
        {
            if (!$this->resolve('granted', $resolverId, $note, $pdo)) {
                return false;
            }

            $del = $pdo->prepare("DELETE FROM {$GLOBALS['CONFIG']['db_prefix']}user_perms WHERE fid = :fid AND uid = :uid");
            $del->execute(array(':fid' => $this->file_id, ':uid' => $this->requesting_user_id));

            $ins = $pdo->prepare("INSERT INTO {$GLOBALS['CONFIG']['db_prefix']}user_perms (fid, uid, rights) VALUES (:fid, :uid, :rights)");
            $ins->execute(array(':fid' => $this->file_id, ':uid' => $this->requesting_user_id, ':rights' => $this->requested_level));

            AccessLog::addLogEntry($this->file_id, 'G', $pdo, $note);
            return true;
        }

        /**
         * Deny this request — no permission change, just marks it resolved
         * and logs 'N' (Access Denied) on the file.
         * @param int $resolverId
         * @param string $note
         * @param PDO $pdo
         * @return bool true if this call actually resolved the request
         */
        public function deny($resolverId, $note, PDO $pdo)
        {
            if (!$this->resolve('denied', $resolverId, $note, $pdo)) {
                return false;
            }

            AccessLog::addLogEntry($this->file_id, 'N', $pdo, $note);
            return true;
        }

        /**
         * Atomically flip this request from 'pending' to $status. The WHERE
         * clause makes this the single source of truth for "did I win the
         * race" — a plain SELECT-then-UPDATE would let two concurrent
         * requests both pass a status check before either writes.
         * @return bool true if this call's UPDATE actually changed a row
         */
        private function resolve($status, $resolverId, $note, PDO $pdo)
        {
            $query = "
              UPDATE {$GLOBALS['CONFIG']['db_prefix']}access_request
              SET status = :status, resolved_by = :resolved_by, resolved_on = NOW(), resolution_note = :note
              WHERE id = :id AND status = 'pending'
            ";
            $stmt = $pdo->prepare($query);
            $stmt->execute(array(
                ':status' => $status,
                ':resolved_by' => $resolverId,
                ':note' => $note,
                ':id' => $this->id
            ));
            if ($stmt->rowCount() === 0) {
                return false;
            }
            $this->status = $status;
            return true;
        }
    }
}
