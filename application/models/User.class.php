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

// (C) 2002-2004 Stephen Lawrence Jr., Khoa Nguyen
// Container for user related info

if (!defined('User_class')) {
    define('User_class', 'true', false);

    class User extends databaseData
    {
        public $root_id;
        public $id;
        public $username;
        public $first_name;
        public $last_name;
        public $email;
        public $phone;
        public $department;
        public $pw_reset_code;
        public $can_add;
        public $can_checkin;

        /**
         * @param int $id
         * @param PDO $connection
         */
        public function __construct($id, PDO $connection)
        {
            $this->root_id = $GLOBALS['CONFIG']['root_id'];
            $this->field_name = 'username';
            $this->field_id = 'id';
            $this->tablename = $this->TABLE_USER;
            $this->result_limit = 1; //there is only 1 user with a certain user_name or user_id

            // Set connection and initialize without calling setId yet
            $this->connection = $connection;
            
            // Add error handling for setId call
            try {
                // Now we can safely call setId since tablename is set
                $this->setId($id);
            } catch (Exception $e) {
                error_log("User constructor error: " . $e->getMessage());
                $this->error = "Failed to initialize user with ID: " . $id;
                return;
            }

            $query = "
                    SELECT 
                        id, 
                        username, 
                        department, 
                        phone, 
                        email, 
                        last_name, 
                        first_name, 
                        pw_reset_code,
                        can_add,
                        can_checkin
                    FROM 
                        {$GLOBALS['CONFIG']['db_prefix']}user 
                    WHERE 
                        id = :id";
            $stmt = $this->connection->prepare($query);
            $stmt->execute(array(':id' => $this->id));
            $result = $stmt->fetch();

            // Check if user was found in database
            if ($result === false) {
                $this->error = "User not found in database for ID: " . $this->id;
                error_log("User constructor - User not found for ID: " . $this->id);
                return;
            }

            list(
                    $this->id,
                    $this->username,
                    $this->department,
                    $this->phone,
                    $this->email,
                    $this->last_name,
                    $this->first_name,
                    $this->pw_reset_code,
                    $this->can_add,
                    $this->can_checkin
            ) = $result;
        }

        /**
         * Return department name for current user
         * @return string
         */
        public function getDeptName()
        {
            $query = "
              SELECT
                d.name
              FROM
                {$GLOBALS['CONFIG']['db_prefix']}department d,
                {$GLOBALS['CONFIG']['db_prefix']}user u
              WHERE
                u.id = :id
              AND
                u.department = d.id";
            $stmt = $this->connection->prepare($query);
            $stmt->execute(array(
                ':id' => $this->id
            ));
            $result = $stmt->fetchColumn();

            return $result;
        }

        /**
         * Return department ID for current user
         * @return string
         */
        public function getDeptId()
        {
            return $this->department;
        }

        /**
         * Return an array of publishable documents
         * @return array
         * @param object $publishable
         */
        public function getPublishedData($publishable)
        {
            $data_published = array();
            $index = 0;
            $query = "
              SELECT
                d.id
              FROM
                {$GLOBALS['CONFIG']['db_prefix']}data d,
                {$GLOBALS['CONFIG']['db_prefix']}user u
              WHERE
                d.owner = :id
              AND
                u.id = d.owner
              AND
                d.publishable = :publishable ";
            $stmt = $this->connection->prepare($query);
            $stmt->execute(array(
                ':publishable' => $publishable,
                ':id' => $this->id
            ));
            $result = $stmt->fetchAll();

            foreach ($result as $row) {
                $data_published[$index] = $row;
                $index++;
            }
            return $data_published;
        }

        /**
         * Check whether user from object has Admin rights
         * @return Boolean
         */
        public function isAdmin()
        {
            if ($this->isRoot()) {
                return true;
            }
            $query = "
              SELECT
                admin
              FROM
                {$GLOBALS['CONFIG']['db_prefix']}admin
              WHERE
                id = :id
            ";
            $stmt = $this->connection->prepare($query);
            $stmt->execute(array(
                ':id' => $this->id
            ));
            $result = $stmt->fetchColumn();

            if ($stmt->rowCount() !=1) {
                return false;
            }

            return $result;
        }

        /**
         * Check whether user from object is root
         * @return bool
         */
        public function isRoot()
        {
            return ($this->root_id == $this->getId());
        }

        /**
        * @return boolean
        */
        public function canAdd()
        {
            if ($this->isAdmin()) {
                return true;
            }
            if ($this->can_add) {
                return true;
            }
            return false;
        }
        
        /**
        * @return boolean
        */
        public function canCheckIn()
        {
            if ($this->isAdmin()) {
                return true;
            }
            if ($this->can_checkin) {
                return true;
            }
            return false;
        }

        /**
         * @return string
         */
        public function getPassword()
        {
            $query = "
              SELECT
                password
              FROM
                $this->tablename
              WHERE
                id = :id
            ";
            $stmt = $this->connection->prepare($query);
            $stmt->execute(array(':id' => $this->id));
            $result = $stmt->fetchColumn();

            if ($stmt->rowCount() !=1) {
                header('Location:' . $GLOBALS['CONFIG']['base_url'] . 'error?ec=14');
                exit;
            }

            return $result;
        }

        /**
         * @param string $non_encrypted_password
         * @return bool
         */
        public function changePassword($non_encrypted_password)
        {
            $query = "
              UPDATE
                {$GLOBALS['CONFIG']['db_prefix']}$this->tablename
              SET
                password = :hashed_password
              WHERE
                id = :id
            ";
            $stmt = $this->connection->prepare($query);
            $stmt->execute(array(
                ':hashed_password' => password_hash($non_encrypted_password, PASSWORD_DEFAULT),
                ':id' => $this->id
            ));
            return true;
        }

        /**
         * Verify a password against this user's stored hash.
         *
         * Passwords set before this fix are stored as unsalted MD5 (or, for
         * very old rows, MySQL's legacy PASSWORD()) — both real weaknesses
         * for a system storing real client documents. New/changed passwords
         * now use password_hash() (bcrypt). Existing accounts keep working
         * without a forced reset: a successful legacy-format match here
         * transparently re-hashes and stores the password in the new format,
         * so every account upgrades itself the next time its owner logs in,
         * with no admin action or downtime required.
         * @param string $non_encrypted_password
         * @return bool
         */
        public function validatePassword($non_encrypted_password)
        {
            $query = "SELECT password FROM {$GLOBALS['CONFIG']['db_prefix']}$this->tablename WHERE id = :id";
            $stmt = $this->connection->prepare($query);
            $stmt->execute(array(':id' => $this->id));
            $row = $stmt->fetch();
            if (!$row) {
                return false;
            }
            $storedHash = $row['password'];

            if (password_verify($non_encrypted_password, $storedHash)) {
                return true;
            }

            // Legacy MD5 password, stored before this fix.
            if (hash_equals(md5($non_encrypted_password), $storedHash)) {
                $this->changePassword($non_encrypted_password);
                return true;
            }

            // No MySQL PASSWORD()-style fallback: that function was removed
            // in MySQL 8 entirely (this deployment's version), so a query
            // using it doesn't just "not match" — it throws a SQL syntax
            // error. Found by testing a wrong-password login, which crashed
            // with a 500 instead of a clean rejection, on both this rewrite
            // and the original code this replaced (the original login flow
            // in index.php had the exact same unconditional password(:x)
            // fallback query, so any wrong password ever submitted here
            // would have crashed the same way before this fix too).

            return false;
        }

        /**
         * @param string $new_name
         * @return bool
         */
        public function changeName($new_name)
        {
            $query = "
              UPDATE
                $this->tablename
              SET
                username = :new_name
              WHERE
                id = :id
            ";
            $stmt = $this->connection->prepare($query);
            $stmt->execute(array(
                ':new_name' => $new_name,
                ':id' => $this->id
            ));
            return true;
        }

       /**
        *   Determine if the current user is a reviewer or not
        *   @return boolean
        */
        public function isReviewer()
        {
            // If they are an admin, they can review
            if ($this->isAdmin()) {
                return true;
            }

            // Lets see if this non-admin user has a department they can review for, if so, they are a reviewer
            $query = "
            SELECT
              dept_id
            FROM
              {$GLOBALS['CONFIG']['db_prefix']}dept_reviewer
            WHERE
              user_id = :id
            ";
            $stmt = $this->connection->prepare($query);
            $stmt->execute(array(
                ':id' => $this->id
            ));
            if ($stmt->rowCount() > 0) {
                return true;
            }

            // Or a designated approver on any Staged Approval workflow stage,
            // even without a department-reviewer assignment.
            $query = "
              SELECT stage_id FROM {$GLOBALS['CONFIG']['db_prefix']}workflow_stage_approver
              WHERE user_id = :id
            ";
            $stmt = $this->connection->prepare($query);
            $stmt->execute(array(':id' => $this->id));
            return $stmt->rowCount() > 0;
        }

       /**
        *   Is this user a Department Head for the given department?
        *   @param int $dept_id
        *   @return boolean
        */
        public function isDeptHead($dept_id)
        {
            if ($this->isAdmin()) {
                return true;
            }
            $query = "
              SELECT
                user_id
              FROM
                {$GLOBALS['CONFIG']['db_prefix']}dept_head
              WHERE
                dept_id = :dept_id AND user_id = :uid
            ";
            $stmt = $this->connection->prepare($query);
            $stmt->execute(array(
                ':dept_id' => $dept_id,
                ':uid' => $this->id
            ));
            return $stmt->rowCount() > 0;
        }

       /**
        *   Can this user set a document's classification to Sensitive or Highly sensitive?
        *   @param int $dept_id
        *   @return boolean
        */
        public function canSetHighClassification($dept_id)
        {
            return $this->isAdmin() || $this->isDeptHead($dept_id);
        }

       /**
        *   Is this user Department Head of at least one department (any department)?
        *   Used to gate visibility of cross-department screens like Access Request review.
        *   @param PDO $pdo
        *   @return boolean
        */
        public function isDeptHeadOfAny(PDO $pdo)
        {
            if ($this->isAdmin()) {
                return true;
            }
            $query = "SELECT dept_id FROM {$GLOBALS['CONFIG']['db_prefix']}dept_head WHERE user_id = :uid LIMIT 1";
            $stmt = $pdo->prepare($query);
            $stmt->execute(array(':uid' => $this->id));
            return $stmt->rowCount() > 0;
        }

       /**
        * Determine if the current user is a reviewer for a specific ID
        * @param int $file_id
        * @return boolean
        */
        public function isReviewerForFile($file_id)
        {
            $query = "SELECT
                            d.id
                      FROM
                            {$GLOBALS['CONFIG']['db_prefix']}data as d,
                            {$GLOBALS['CONFIG']['db_prefix']}dept_reviewer as dr
                      WHERE

                            dr.dept_id = d.department AND
                            dr.user_id = :user_id AND
                            d.department = dr.dept_id AND
                            d.id = :file_id
                            ";
            $stmt = $this->connection->prepare($query);
            $stmt->execute(array(
                ':user_id' => $this->id,
                ':file_id' => $file_id
            ));

            if ($stmt->rowCount() > 0) {
                return true;
            }

            return $this->isStageApproverForFile($file_id);
        }

        /**
         * Is this user a designated approver for the CURRENT stage of this
         * file's assigned Staged Approval workflow template? False if the
         * file has no template assigned (classic single-reviewer flow doesn't
         * use this path at all).
         * @param int $file_id
         * @return boolean
         */
        public function isStageApproverForFile($file_id)
        {
            $query = "
              SELECT wsa.user_id
              FROM {$GLOBALS['CONFIG']['db_prefix']}data d
              INNER JOIN {$GLOBALS['CONFIG']['db_prefix']}workflow_stage ws
                ON ws.template_id = d.workflow_template_id AND ws.stage_number = d.workflow_stage_number
              INNER JOIN {$GLOBALS['CONFIG']['db_prefix']}workflow_stage_approver wsa
                ON wsa.stage_id = ws.id
              WHERE d.id = :file_id AND wsa.user_id = :user_id
            ";
            $stmt = $this->connection->prepare($query);
            $stmt->execute(array(':file_id' => $file_id, ':user_id' => $this->id));
            return $stmt->rowCount() > 0;
        }

        /**
         * this functions assume that you are an admin thus allowing you to review all departments
         * @return array
         */
        public function getAllRevieweeIds()
        {
            if ($this->isAdmin()) {
                $query = "SELECT id FROM {$GLOBALS['CONFIG']['db_prefix']}$this->TABLE_DATA WHERE publishable = 0";
                $stmt = $this->connection->prepare($query);
                $stmt->execute(array());
                $result = $stmt->fetchAll();

                $file_data = array();
                $index = 0;
                foreach ($result as $row) {
                    $file_data[$index] = $row[0];
                    $index++;
                }

                return $file_data;
            }
        }
        
        /**
         * getRevieweeIds - Return an array of files that need reviewing under this person
         * @return array
         */
        public function getRevieweeIds()
        {
            if ($this->isReviewer()) {
                // Which departments can this user review?
                $query = "SELECT dept_id FROM {$GLOBALS['CONFIG']['db_prefix']}$this->TABLE_DEPT_REVIEWER WHERE user_id = :id";
                $stmt = $this->connection->prepare($query);
                $stmt->execute(array(
                    ':id' => $this->id
                ));
                $result = $stmt->fetchAll();

                $num_depts = $stmt->rowCount();
                $file_data = array();

                // Guard: a user can now be a reviewer purely via a Staged Approval
                // workflow-stage assignment, with zero department-reviewer rows —
                // skip the department-scoped query entirely in that case (it would
                // otherwise build an invalid "WHERE ( AND publishable = 0" query).
                if ($num_depts > 0) {
                    // Bug fix (2026-08-11): this used to reuse a single ':dept'
                    // placeholder for every department in the loop. PDO binds one
                    // value per named parameter, so with more than one department
                    // every occurrence silently got the LAST department's value —
                    // a reviewer for 2+ departments only ever saw pending files from
                    // whichever department happened to be last in the (unordered)
                    // SELECT above. Each department now gets its own placeholder.
                    $index = 0;
                    $query = "SELECT id FROM {$GLOBALS['CONFIG']['db_prefix']}data WHERE (";
                    $deptParams = array();
                    foreach ($result as $row) {
                        $paramName = ':dept' . $index;
                        $deptParams[$paramName] = $row['dept_id'];
                        if ($index != $num_depts -1) {
                            $query = $query . " department = $paramName OR ";
                        } else {
                            $query = $query . " department = $paramName )";
                        }
                        $index++;
                    }
                    $query = $query . " AND publishable = 0";

                    $stmt = $this->connection->prepare($query);
                    $stmt->execute($deptParams);
                    $result = $stmt->fetchAll();

                    $num_files = $stmt->rowCount();

                    for ($index = 0; $index< $num_files; $index++) {
                        $fid = $result[$index]['id'];
                        $file_data[$index] = $fid;
                    }
                }

                // Merge in documents this user can review purely via a Staged
                // Approval workflow-stage assignment (not necessarily tied to
                // their own department at all).
                $file_data = array_values(array_unique(array_merge($file_data, $this->getWorkflowRevieweeIds())));

                return $file_data;
            }
        }

        /**
         * Pending (publishable = 0) documents where this user is a designated
         * approver for the document's CURRENT Staged Approval workflow stage.
         * @return array
         */
        public function getWorkflowRevieweeIds()
        {
            $query = "
              SELECT d.id
              FROM {$GLOBALS['CONFIG']['db_prefix']}data d
              INNER JOIN {$GLOBALS['CONFIG']['db_prefix']}workflow_stage ws
                ON ws.template_id = d.workflow_template_id AND ws.stage_number = d.workflow_stage_number
              INNER JOIN {$GLOBALS['CONFIG']['db_prefix']}workflow_stage_approver wsa
                ON wsa.stage_id = ws.id
              WHERE d.publishable = 0 AND wsa.user_id = :user_id
            ";
            $stmt = $this->connection->prepare($query);
            $stmt->execute(array(':user_id' => $this->id));
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        /**
         * @return array
         */
        public function getAllRejectedFileIds()
        {
            $query = "SELECT id FROM {$GLOBALS['CONFIG']['db_prefix']}$this->TABLE_DATA WHERE publishable = '-1'";
            $stmt = $this->connection->prepare($query);
            $stmt->execute();
            $result = $stmt->fetchAll();

            $file_data = array();
            $num_files = $stmt->rowCount();

            for ($index = 0; $index< $num_files; $index++) {
                list($fid) = $result[$index];
                $file_data[$index] = $fid;
            }
            return $file_data;
        }

        /**
         * @return array
         */
        public function getRejectedFileIds()
        {
            $query = "SELECT id FROM {$GLOBALS['CONFIG']['db_prefix']}data WHERE publishable = '-1' and owner = :id";
            $stmt = $this->connection->prepare($query);
            $stmt->execute(array(
                ':id' => $this->id
            ));
            $result = $stmt->fetchAll();

            $file_data = array();
            $num_files = $stmt->rowCount();

            for ($index = 0; $index< $num_files; $index++) {
                list($fid) = $result[$index];
                $file_data[$index] = $fid;
            }
            return $file_data;
        }

        /**
         * @return array
         */
        public function getExpiredFileIds()
        {
            $query = "SELECT id FROM {$GLOBALS['CONFIG']['db_prefix']}data WHERE status = -1 AND owner = :id";
            $stmt = $this->connection->prepare($query);
            $stmt->execute(array(
                ':id' => $this->id
            ));
            $result = $stmt->fetchAll();

            $len = $stmt->rowCount();
            $file_data = array();

            for ($index = 0; $index< $len; $index++) {
                list($fid) = $result[$index];
                $file_data[$index] = $fid;
            }
            return $file_data;
        }

        /**
         * @return int
         */
        public function getNumExpiredFiles()
        {
            $query = "SELECT id FROM {$GLOBALS['CONFIG']['db_prefix']}data WHERE status =- 1 AND owner = :id";
            $stmt = $this->connection->prepare($query);
            $stmt->execute(array(
                ':id' => $this->id
            ));
            return $stmt->rowCount();
        }

        /**
         * @return mixed
         */
        public function getEmailAddress()
        {
            return $this->email;
        }

        /**
         * @return mixed
         */
        public function getPhoneNumber()
        {
            return $this->phone;
        }

        /**
         * /Return full name array where array[0]=firstname and array[1]=lastname
         * @return mixed
         */
        public function getFullName()
        {
            $full_name = array();
            $full_name[0] = $this->first_name;
            $full_name[1] = $this->last_name;

            return $full_name;
        }

        /**
         * Return username of current user
         * @return mixed
         */
        public function getUserName()
        {
            return $this->username;
        }

        /**
         * Return list of checked out files to root
         * @return array
         */
        public function getCheckedOutFiles()
        {
            if ($this->isRoot()) {
                $query = "SELECT id FROM {$GLOBALS['CONFIG']['db_prefix']}data WHERE status > 0";
                $stmt = $this->connection->prepare($query);
                $stmt->execute();
                $result = $stmt->fetchAll();

                $len = $stmt->rowCount();
                $file_data = array();
                for ($index = 0; $index < $len; $index++) {
                    list($fid) = $result[$index];
                    $file_data[$index] = $fid;
                }
                return $file_data;
            }
        }

        /**
         * exists - Check if a user with the given ID exists in the database
         * @param int $id
         * @param PDO $connection
         * @return bool
         */
        public static function exists($id, PDO $connection)
        {
            $query = "SELECT COUNT(*) FROM {$GLOBALS['CONFIG']['db_prefix']}user WHERE id = :id";
            $stmt = $connection->prepare($query);
            $stmt->execute(array(':id' => $id));
            return $stmt->fetchColumn() > 0;
        }

        /**
         * getAllUsers - Returns an array of all the active users
         * @param $pdo
         * @return array
         */
        public static function getAllUsers(PDO $pdo)
        {
            $userListArray = array();
            // query to get a list of available users
            $query = "SELECT id, username, first_name, last_name FROM {$GLOBALS['CONFIG']['db_prefix']}user ORDER BY username";
            $stmt = $pdo->prepare($query);
            $stmt->execute();
            $result = $stmt->fetchAll();
            foreach ($result as $row) {
                $userListArray[] = $row;
            }
            return $userListArray;
        }
    }
}
