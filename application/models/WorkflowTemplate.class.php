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

// Named, reusable multi-stage approval workflow templates (Staged Approval,
// Hybrid model). A template has an ordered list of stages; each stage has
// one or more approver users. Classification (ICMH) imposes a *minimum*
// stage-count floor — see getRequiredStageCount() — but a template can
// always have more stages than the floor, and can be reused across any
// classification level that meets its stage count.

if (!defined('WorkflowTemplate_class')) {
    define('WorkflowTemplate_class', 'true', false);

    class WorkflowTemplate extends databaseData
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
            $this->tablename = 'workflow_template';
            $this->connection = $connection;
            $this->setId($id);
        }

        /**
         * @return string
         */
        public function getDescription()
        {
            $query = "SELECT description FROM {$GLOBALS['CONFIG']['db_prefix']}workflow_template WHERE id = :id";
            $stmt = $this->connection->prepare($query);
            $stmt->execute(array(':id' => $this->id));
            $result = $stmt->fetch();
            return $result ? $result['description'] : '';
        }

        /**
         * @param PDO $pdo
         * @return array
         */
        public static function getAllTemplates(PDO $pdo)
        {
            $templates = array();
            $query = "SELECT id, name, description FROM {$GLOBALS['CONFIG']['db_prefix']}workflow_template ORDER BY name";
            $stmt = $pdo->prepare($query);
            $stmt->execute();
            foreach ($stmt->fetchAll() as $row) {
                $templates[] = $row;
            }
            return $templates;
        }

        /**
         * Ordered list of this template's stages (id, stage_number, name)
         * @return array
         */
        public function getStages()
        {
            $query = "
              SELECT id, stage_number, name
              FROM {$GLOBALS['CONFIG']['db_prefix']}workflow_stage
              WHERE template_id = :template_id
              ORDER BY stage_number
            ";
            $stmt = $this->connection->prepare($query);
            $stmt->execute(array(':template_id' => $this->id));
            return $stmt->fetchAll();
        }

        /**
         * @return int
         */
        public function getStageCount()
        {
            $query = "SELECT COUNT(*) FROM {$GLOBALS['CONFIG']['db_prefix']}workflow_stage WHERE template_id = :template_id";
            $stmt = $this->connection->prepare($query);
            $stmt->execute(array(':template_id' => $this->id));
            return (int) $stmt->fetchColumn();
        }

        /**
         * Look up a stage's numeric id from its (template, stage_number) pair.
         * @param int $stageNumber
         * @return int|null
         */
        public function getStageId($stageNumber)
        {
            $query = "
              SELECT id FROM {$GLOBALS['CONFIG']['db_prefix']}workflow_stage
              WHERE template_id = :template_id AND stage_number = :stage_number
            ";
            $stmt = $this->connection->prepare($query);
            $stmt->execute(array(':template_id' => $this->id, ':stage_number' => $stageNumber));
            $result = $stmt->fetchColumn();
            return $result !== false ? (int) $result : null;
        }

        /**
         * Get the approvers (id, first_name, last_name) for a given stage number of this template
         * @param int $stageNumber
         * @return array
         */
        public function getApproversForStage($stageNumber)
        {
            $query = "
              SELECT u.id, u.first_name, u.last_name
              FROM {$GLOBALS['CONFIG']['db_prefix']}workflow_stage ws
              INNER JOIN {$GLOBALS['CONFIG']['db_prefix']}workflow_stage_approver wsa ON wsa.stage_id = ws.id
              INNER JOIN {$GLOBALS['CONFIG']['db_prefix']}user u ON u.id = wsa.user_id
              WHERE ws.template_id = :template_id AND ws.stage_number = :stage_number
              ORDER BY u.last_name, u.first_name
            ";
            $stmt = $this->connection->prepare($query);
            $stmt->execute(array(':template_id' => $this->id, ':stage_number' => $stageNumber));
            return $stmt->fetchAll();
        }

        /**
         * Replace this template's entire stage list (and their approvers) wholesale.
         * $stages is an ordered array of ['name' => ..., 'approver_ids' => [...]]
         * @param array $stages
         */
        public function setStages(array $stages)
        {
            // Deleting stages cascades their approver rows too (no FK, do it explicitly)
            $existingStageIds = $this->connection->prepare("SELECT id FROM {$GLOBALS['CONFIG']['db_prefix']}workflow_stage WHERE template_id = :template_id");
            $existingStageIds->execute(array(':template_id' => $this->id));
            $ids = $existingStageIds->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $del = $this->connection->prepare("DELETE FROM {$GLOBALS['CONFIG']['db_prefix']}workflow_stage_approver WHERE stage_id IN ($placeholders)");
                $del->execute($ids);
            }
            $delStages = $this->connection->prepare("DELETE FROM {$GLOBALS['CONFIG']['db_prefix']}workflow_stage WHERE template_id = :template_id");
            $delStages->execute(array(':template_id' => $this->id));

            $insertStage = $this->connection->prepare("INSERT INTO {$GLOBALS['CONFIG']['db_prefix']}workflow_stage (template_id, stage_number, name) VALUES (:template_id, :stage_number, :name)");
            $insertApprover = $this->connection->prepare("INSERT INTO {$GLOBALS['CONFIG']['db_prefix']}workflow_stage_approver (stage_id, user_id) VALUES (:stage_id, :user_id)");

            $stageNumber = 1;
            foreach ($stages as $stage) {
                $insertStage->execute(array(
                    ':template_id' => $this->id,
                    ':stage_number' => $stageNumber,
                    ':name' => $stage['name']
                ));
                $stageId = $this->connection->lastInsertId();
                foreach ($stage['approver_ids'] as $userId) {
                    $insertApprover->execute(array(':stage_id' => $stageId, ':user_id' => $userId));
                }
                $stageNumber++;
            }
        }

        /**
         * Delete this template and everything that references it (stages, approvers).
         * Does NOT touch documents already assigned to it — see the caller in
         * workflow.php, which blocks deletion while any document still references it.
         */
        public function deleteTemplate()
        {
            $stageIds = $this->connection->prepare("SELECT id FROM {$GLOBALS['CONFIG']['db_prefix']}workflow_stage WHERE template_id = :id");
            $stageIds->execute(array(':id' => $this->id));
            $ids = $stageIds->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $del = $this->connection->prepare("DELETE FROM {$GLOBALS['CONFIG']['db_prefix']}workflow_stage_approver WHERE stage_id IN ($placeholders)");
                $del->execute($ids);
            }
            $this->connection->prepare("DELETE FROM {$GLOBALS['CONFIG']['db_prefix']}workflow_stage WHERE template_id = :id")->execute(array(':id' => $this->id));
            $this->connection->prepare("DELETE FROM {$GLOBALS['CONFIG']['db_prefix']}workflow_template WHERE id = :id")->execute(array(':id' => $this->id));
        }

        /**
         * How many documents currently reference this template (any status).
         * @param PDO $pdo
         * @return int
         */
        public function countReferencingDocuments(PDO $pdo)
        {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$GLOBALS['CONFIG']['db_prefix']}data WHERE workflow_template_id = :id");
            $stmt->execute(array(':id' => $this->id));
            return (int) $stmt->fetchColumn();
        }

        /**
         * The ICMH classification-derived minimum stage count a template must
         * have before it can be assigned to a document of that classification.
         * Public/Internal/Limited have no floor (0 = staged workflow optional,
         * the classic single dept-reviewer flow remains valid). Sensitive and
         * Highly sensitive documents MUST have an adequately-staged template —
         * this is what gives the classification scheme real teeth per the
         * Hybrid workflow-model decision (2026-08-11).
         * @param string $classification
         * @return int
         */
        public static function getRequiredStageCount($classification)
        {
            switch ($classification) {
                case 'Highly sensitive':
                    return 3;
                case 'Sensitive':
                    return 2;
                default:
                    return 0;
            }
        }
    }
}
