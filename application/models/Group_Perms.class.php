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

// Designed to handle group-based file permissions, following the same shape
// as Dept_Perms/User_Perms. Unlike department (one per user), a user can
// belong to several groups, so this class is constructed with a *user* id
// and, wherever a right is looked up, returns the most permissive (highest
// numeric) right granted by any group that user belongs to. Individual
// per-user permissions still take priority over group permissions, and
// group permissions still take priority over the department default — see
// UserPermission::getAuthority().

if (!defined('Group_Perms_class')) {
    define('Group_Perms_class', 'true');

    class Group_Perms extends databaseData
    {
        public $id; // user id, not group id
        protected $connection;
        public $user_obj;

        public $NONE_RIGHT = 0;
        public $VIEW_RIGHT = 1;
        public $READ_RIGHT = 2;
        public $WRITE_RIGHT = 3;
        public $ADMIN_RIGHT = 4;
        public $FORBIDDEN_RIGHT = -1;

        /**
         * @param int $id user id
         * @param PDO $connection
         * @param User|null $user_obj Optional User object to avoid circular dependency
         */
        public function __construct($id, PDO $connection, $user_obj = null)
        {
            $this->id = $id;
            $this->connection = $connection;
            if ($user_obj !== null) {
                $this->user_obj = $user_obj;
            }
        }

        /**
         * @return int
         */
        public function getId()
        {
            return $this->id;
        }

        /**
         * Return the rights a specific *group* (not user) has directly been
         * granted on file $data_id, for rendering the edit-form checkboxes.
         * Mirrors User_Perms::getPermissionForUser().
         * @param int $groupId
         * @param int $data_id
         * @return int|string|null
         */
        public function getPermissionForGroup($groupId, $data_id)
        {
            $query = "
              SELECT
                rights
              FROM
                {$GLOBALS['CONFIG']['db_prefix']}$this->TABLE_GROUP_PERMS
              WHERE
                group_id = :group_id
              AND
                fid = :data_id
            ";
            $stmt = $this->connection->prepare($query);
            $stmt->execute(array(
                ':group_id' => $groupId,
                ':data_id' => $data_id
            ));
            return $stmt->fetchColumn();
        }

        /**
         * Return the most permissive right any of this user's groups have been
         * granted on file $data_id. Returns -999 (mirroring User_Perms::getPermission's
         * sentinel) if none of the user's groups have a permission row for this
         * file at all — distinct from a group explicitly granting NONE_RIGHT (0),
         * which is a real, intentional "No Access" and must not be confused with
         * "no group data exists" when getAuthority() decides whether to fall
         * through to the department default.
         * @param int $data_id
         * @return int
         */
        public function getPermission($data_id)
        {
            $query = "
              SELECT
                MAX(gp.rights) AS max_rights
              FROM
                {$GLOBALS['CONFIG']['db_prefix']}$this->TABLE_GROUP_PERMS gp
              INNER JOIN
                {$GLOBALS['CONFIG']['db_prefix']}$this->TABLE_GROUP_MEMBER gm ON gm.group_id = gp.group_id
              WHERE
                gm.user_id = :user_id
              AND
                gp.fid = :data_id
            ";
            $stmt = $this->connection->prepare($query);
            $stmt->execute(array(
                ':user_id' => $this->id,
                ':data_id' => $data_id
            ));
            $result = $stmt->fetch();
            if ($result && $result['max_rights'] !== null) {
                return (int) $result['max_rights'];
            }
            return -999;
        }

        /**
         * Return a list of file IDs where any of this user's groups have >= $right,
         * mirroring Dept_Perms::loadData_UserPerm() / User_Perms's equivalent.
         * @param int $right
         * @param bool $limit
         * @return array
         */
        public function loadData_GroupPerm($right, $limit = true)
        {
            $limit_query = ($limit) ? "LIMIT {$GLOBALS['CONFIG']['max_query']}" : '';

            $query = "
              SELECT DISTINCT
                gp.fid
              FROM
                {$GLOBALS['CONFIG']['db_prefix']}$this->TABLE_DATA data,
                {$GLOBALS['CONFIG']['db_prefix']}$this->TABLE_GROUP_PERMS gp,
                {$GLOBALS['CONFIG']['db_prefix']}$this->TABLE_GROUP_MEMBER gm
              WHERE
                gm.user_id = :user_id
              AND
                gm.group_id = gp.group_id
              AND
                gp.rights >= :right
              AND
                data.id = gp.fid
              AND
                data.publishable = 1
              $limit_query
            ";
            $stmt = $this->connection->prepare($query);
            $stmt->execute(array(
                ':user_id' => $this->id,
                ':right' => $right
            ));
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        /**
         * @param bool $limit
         * @return array
         */
        public function getCurrentViewOnly($limit = true)
        {
            return $this->loadData_GroupPerm($this->VIEW_RIGHT, $limit);
        }

        /**
         * @param bool $limit
         * @return array
         */
        public function getCurrentReadRight($limit = true)
        {
            return $this->loadData_GroupPerm($this->READ_RIGHT, $limit);
        }

        /**
         * @param bool $limit
         * @return array
         */
        public function getCurrentWriteRight($limit = true)
        {
            return $this->loadData_GroupPerm($this->WRITE_RIGHT, $limit);
        }

        /**
         * @param bool $limit
         * @return array
         */
        public function getCurrentAdminRight($limit = true)
        {
            return $this->loadData_GroupPerm($this->ADMIN_RIGHT, $limit);
        }
    }
}
