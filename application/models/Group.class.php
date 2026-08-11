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

// Reusable named user groups. A user can belong to any number of groups;
// groups can be granted a file permission the same way a department or an
// individual user can (see Group_Perms.class.php).

if (!defined('Group_class')) {
    define('Group_class', 'true', false);

    class Group extends databaseData
    {
        protected $connection;

        /**
         * @param int $id
         * @param PDO $connection
         */
        public function __construct($id, PDO $connection)
        {
            $this->field_name = 'name';
            $this->field_id = 'id';
            $this->result_limit = 1;
            $this->tablename = 'group';
            $this->connection = $connection;
            $this->setId($id);
        }

        /**
         * @return string
         */
        public function getDescription()
        {
            $query = "SELECT description FROM {$GLOBALS['CONFIG']['db_prefix']}group WHERE id = :id";
            $stmt = $this->connection->prepare($query);
            $stmt->execute(array(':id' => $this->id));
            $result = $stmt->fetch();
            return $result ? $result['description'] : '';
        }

        /**
         * Get all groups, sorted by name
         * @param PDO $pdo
         * @return array
         */
        public static function getAllGroups(PDO $pdo)
        {
            $groups = array();
            $query = "SELECT id, name, description FROM {$GLOBALS['CONFIG']['db_prefix']}group ORDER BY name";
            $stmt = $pdo->prepare($query);
            $stmt->execute();
            $result = $stmt->fetchAll();
            foreach ($result as $row) {
                $groups[] = $row;
            }
            return $groups;
        }

        /**
         * Get all member user IDs for this group
         * @return array
         */
        public function getMemberIds()
        {
            $query = "SELECT user_id FROM {$GLOBALS['CONFIG']['db_prefix']}group_member WHERE group_id = :group_id";
            $stmt = $this->connection->prepare($query);
            $stmt->execute(array(':group_id' => $this->id));
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        /**
         * Get all members of this group, with names
         * @return array
         */
        public function getMembers()
        {
            $query = "
              SELECT
                u.id,
                u.first_name,
                u.last_name
              FROM
                {$GLOBALS['CONFIG']['db_prefix']}group_member gm
              INNER JOIN
                {$GLOBALS['CONFIG']['db_prefix']}user u ON u.id = gm.user_id
              WHERE
                gm.group_id = :group_id
              ORDER BY
                u.last_name, u.first_name
            ";
            $stmt = $this->connection->prepare($query);
            $stmt->execute(array(':group_id' => $this->id));
            return $stmt->fetchAll();
        }

        /**
         * Replace this group's membership list wholesale
         * @param array $userIds
         */
        public function setMembers(array $userIds)
        {
            $del = $this->connection->prepare("DELETE FROM {$GLOBALS['CONFIG']['db_prefix']}group_member WHERE group_id = :group_id");
            $del->execute(array(':group_id' => $this->id));

            $insert = $this->connection->prepare("INSERT INTO {$GLOBALS['CONFIG']['db_prefix']}group_member (group_id, user_id) VALUES (:group_id, :user_id)");
            foreach ($userIds as $userId) {
                $insert->execute(array(':group_id' => $this->id, ':user_id' => $userId));
            }
        }

        /**
         * Get all group IDs a given user belongs to
         * @param int $userId
         * @param PDO $pdo
         * @return array
         */
        public static function getGroupIdsForUser($userId, PDO $pdo)
        {
            $query = "SELECT group_id FROM {$GLOBALS['CONFIG']['db_prefix']}group_member WHERE user_id = :user_id";
            $stmt = $pdo->prepare($query);
            $stmt->execute(array(':user_id' => $userId));
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        /**
         * Delete this group and everything that references it (memberships, file permissions)
         */
        public function deleteGroup()
        {
            $stmt = $this->connection->prepare("DELETE FROM {$GLOBALS['CONFIG']['db_prefix']}group_perms WHERE group_id = :id");
            $stmt->execute(array(':id' => $this->id));

            $stmt = $this->connection->prepare("DELETE FROM {$GLOBALS['CONFIG']['db_prefix']}group_member WHERE group_id = :id");
            $stmt->execute(array(':id' => $this->id));

            $stmt = $this->connection->prepare("DELETE FROM {$GLOBALS['CONFIG']['db_prefix']}group WHERE id = :id");
            $stmt->execute(array(':id' => $this->id));
        }
    }
}
