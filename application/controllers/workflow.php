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

// Administer Staged Approval workflow templates (Hybrid model): named,
// reusable, ordered stage lists with per-stage approvers. Modeled on
// group.php's single-controller dispatch pattern.

use Aura\Html\Escaper as e;

session_start();

$pdo = $GLOBALS['pdo'];

if (!isset($_SESSION['uid'])) {
    redirect_visitor();
}

$last_message = (isset($_REQUEST['last_message']) ? $_REQUEST['last_message'] : '');

$user_obj = new User($_SESSION['uid'], $pdo);

if (!$user_obj->isAdmin()) {
    header('Location:error?ec=4');
    exit;
}

if (isset($_GET['submit']) && $_GET['submit'] == 'add') {
    draw_header('Add New Workflow Template', $last_message);
    ?>
        <form id="addWorkflowForm" action="workflow" method="POST" enctype="multipart/form-data">
    <?php echo isset($GLOBALS['csrf']) ? $GLOBALS['csrf']->getTokenField('/workflow') : ''; ?>
    <table border="0" cellspacing="5" cellpadding="5">
            <tr>
                <td><b>Template Name</b></td>
                <td colspan="3"><input name="name" type="text" class="required" minlength="2"></td>
            </tr>
            <tr>
                <td><b>Description</b></td>
                <td colspan="3"><input name="description" type="text" size="50"></td>
            </tr>
            <tr>
                <td align="center">
                    <div class="buttons">
                        <button class="positive" type="submit" name="submit" value="Add Template">Add Template</button>
                    </div>
                </td>
                <td align="center">
                    <div class="buttons">
                        <button class="negative cancel" type="button" onclick="window.location.href='admin'">Cancel</button>
                    </div>
                </td>
            </tr>
    </table>
           </form>
   <script>
  $(document).ready(function(){
    $('#addWorkflowForm').validate();
  });
  </script>
    <?php
    draw_footer();
} elseif (isset($_POST['submit']) && 'Add Template' == $_POST['submit']) {
    if (isset($GLOBALS['csrf']) && !$GLOBALS['csrf']->validateToken($_POST, '/workflow')) {
        header('Location: error?ec=1&last_message=' . urlencode('CSRF token validation failed'));
        exit;
    }

    $name = trim(isset($_POST['name']) ? $_POST['name'] : '');
    $description = isset($_POST['description']) ? $_POST['description'] : '';

    if ($name == '') {
        header('Location: admin?last_message=' . urlencode('Template name is required'));
        exit;
    }

    $query = "SELECT id FROM {$GLOBALS['CONFIG']['db_prefix']}workflow_template WHERE name = :name";
    $stmt = $pdo->prepare($query);
    $stmt->execute(array(':name' => $name));
    if ($stmt->rowCount() != 0) {
        header('Location: error?ec=3&last_message=' . urlencode($name . ' already exists as a workflow template'));
        exit;
    }

    $query = "INSERT INTO {$GLOBALS['CONFIG']['db_prefix']}workflow_template (name, description) VALUES (:name, :description)";
    $stmt = $pdo->prepare($query);
    $stmt->execute(array(':name' => $name, ':description' => $description));
    $newId = $pdo->lastInsertId();

    header('Location: workflow?submit=manage_stages&item=' . $newId . '&stage_count=1&last_message=' . urlencode('Template added — now add at least one stage'));
} elseif (isset($_REQUEST['submit']) && $_REQUEST['submit'] == 'showpick') {
    draw_header('Choose a Workflow Template', $last_message);
    $templates = WorkflowTemplate::getAllTemplates($pdo);
    ?>
    <table border="0" cellspacing="5" cellpadding="5">
        <form action="workflow" method="POST" enctype="multipart/form-data">
            <tr>
                <td><b>Template</b></td>
                <td colspan="3">
                    <select name="item">
                        <?php foreach ($templates as $t) {
                            echo '<option value="' . e::h($t['id']) . '">' . e::h($t['name']) . '</option>';
                        } ?>
                    </select>
                </td>
                <td align="center">
                    <div class="buttons">
                        <button class="positive" type="submit" name="submit" value="Show Template">View</button>
                    </div>
                </td>
                <td align="center">
                    <div class="buttons">
                        <button class="negative" type="submit" name="submit" value="Cancel">Cancel</button>
                    </div>
                </td>
            </tr>
        </form>
    </table>
    <?php
    draw_footer();
} elseif (isset($_POST['submit']) && $_POST['submit'] == 'Show Template') {
    $template = new WorkflowTemplate((int) $_POST['item'], $pdo);
    draw_header('Workflow Template: ' . e::h($template->getName()), $last_message);

    echo '<table cellspacing="15" border="0">';
    echo '<tr><th>ID</th><th>Name</th><th>Description</th></tr>';
    echo '<tr><td>' . e::h($template->getId()) . '</td><td>' . e::h($template->getName()) . '</td><td>' . e::h($template->getDescription()) . '</td></tr>';
    echo '</table>';

    echo '<p><b>Stages (in order)</b></p>';
    $stages = $template->getStages();
    if (empty($stages)) {
        echo '<p>No stages yet — this template cannot be assigned to a document until it has at least one stage.</p>';
    } else {
        echo '<table cellspacing="5" border="1">';
        echo '<tr><th>#</th><th>Stage</th><th>Approvers</th></tr>';
        foreach ($stages as $s) {
            $approvers = $template->getApproversForStage($s['stage_number']);
            $names = array();
            foreach ($approvers as $a) {
                $names[] = $a['last_name'] . ', ' . $a['first_name'];
            }
            echo '<tr><td>' . e::h($s['stage_number']) . '</td><td>' . e::h($s['name']) . '</td><td>' . (empty($names) ? '<i>none assigned</i>' : e::h(implode('; ', $names))) . '</td></tr>';
        }
        echo '</table>';
    }
    ?>
    <table border="0" cellspacing="5" cellpadding="5">
        <tr>
            <td>
                <div class="buttons">
                    <a class="positive" href="<?php echo 'workflow?submit=manage_stages&item=' . e::h($template->getId()) . '&stage_count=' . max(1, count($stages)); ?>">Manage Stages</a>
                </div>
            </td>
            <td>
                <div class="buttons">
                    <button class="regular" type="button" onclick="window.location.href='admin'">Back</button>
                </div>
            </td>
        </tr>
    </table>
    <?php
    draw_footer();
} elseif (isset($_REQUEST['submit']) && $_REQUEST['submit'] == 'manage_stages') {
    $template = new WorkflowTemplate((int) $_REQUEST['item'], $pdo);
    $stageCount = isset($_REQUEST['stage_count']) ? max(1, (int) $_REQUEST['stage_count']) : max(1, $template->getStageCount());
    $existingStages = $template->getStages();
    $allUsers = User::getAllUsers($pdo);

    draw_header('Manage Stages: ' . e::h($template->getName()), $last_message);
    ?>
    <form action="workflow" method="GET" enctype="multipart/form-data" style="margin-bottom:15px;">
        <input type="hidden" name="submit" value="manage_stages">
        <input type="hidden" name="item" value="<?php echo e::h($template->getId()); ?>">
        Number of stages: <input type="number" name="stage_count" min="1" max="10" value="<?php echo e::h($stageCount); ?>">
        <button class="regular" type="submit">Set</button>
    </form>
    <form action="workflow" method="POST" enctype="multipart/form-data">
        <?php echo isset($GLOBALS['csrf']) ? $GLOBALS['csrf']->getTokenField('/workflow') : ''; ?>
        <input type="hidden" name="template_id" value="<?php echo e::h($template->getId()); ?>">
        <input type="hidden" name="stage_count" value="<?php echo e::h($stageCount); ?>">
        <table border="1" cellspacing="5" cellpadding="5">
            <?php for ($i = 0; $i < $stageCount; $i++) {
                $stageNumber = $i + 1;
                $existingName = isset($existingStages[$i]) ? $existingStages[$i]['name'] : '';
                $existingApproverIds = isset($existingStages[$i]) ? array_column($template->getApproversForStage($existingStages[$i]['stage_number']), 'id') : array();
                ?>
                <tr>
                    <td valign="top"><b>Stage <?php echo e::h($stageNumber); ?></b></td>
                    <td>
                        Stage name: <input type="text" name="stage_name[]" value="<?php echo e::h($existingName); ?>" class="required" placeholder="e.g. Legal Review"><br><br>
                        Approvers:
                        <?php foreach ($allUsers as $u) { ?>
                            <label style="display:inline-block; margin-right:10px;">
                                <input type="checkbox" name="stage_approvers[<?php echo $i; ?>][]" value="<?php echo e::h($u['id']); ?>" <?php echo in_array($u['id'], $existingApproverIds) ? 'checked="checked"' : ''; ?>>
                                <?php echo e::h($u['last_name']) . ', ' . e::h($u['first_name']); ?>
                            </label>
                        <?php } ?>
                    </td>
                </tr>
            <?php } ?>
            <tr>
                <td colspan="2" align="center">
                    <div class="buttons">
                        <button class="positive" type="submit" name="submit" value="Update Stages">Save</button>
                    </div>
                </td>
            </tr>
        </table>
    </form>
    <?php
    draw_footer();
} elseif (isset($_POST['submit']) && $_POST['submit'] == 'Update Stages') {
    if (isset($GLOBALS['csrf']) && !$GLOBALS['csrf']->validateToken($_POST, '/workflow')) {
        header('Location: error?ec=1&last_message=' . urlencode('CSRF token validation failed'));
        exit;
    }

    $template = new WorkflowTemplate((int) $_POST['template_id'], $pdo);
    $stageCount = (int) $_POST['stage_count'];

    $stages = array();
    for ($i = 0; $i < $stageCount; $i++) {
        $name = trim(isset($_POST['stage_name'][$i]) ? $_POST['stage_name'][$i] : '');
        if ($name == '') {
            header('Location: workflow?submit=manage_stages&item=' . $template->getId() . '&stage_count=' . $stageCount . '&last_message=' . urlencode('Every stage needs a name'));
            exit;
        }
        $approverIds = isset($_POST['stage_approvers'][$i]) ? array_map('intval', $_POST['stage_approvers'][$i]) : array();
        $stages[] = array('name' => $name, 'approver_ids' => $approverIds);
    }

    $template->setStages($stages);

    header('Location: admin?last_message=' . urlencode('Stages updated for ' . $template->getName()));
} elseif (isset($_REQUEST['submit']) && $_REQUEST['submit'] == 'updatepick') {
    draw_header('Choose a Template to Modify', $last_message);
    $templates = WorkflowTemplate::getAllTemplates($pdo);
    ?>
    <table border="0" cellspacing="5" cellpadding="5">
        <form action="workflow" method="GET" enctype="multipart/form-data">
            <tr>
                <td><b>Template to modify</b></td>
                <td colspan="3">
                    <select name="item">
                        <?php foreach ($templates as $t) {
                            echo '<option value="' . e::h($t['id']) . '">' . e::h($t['name']) . '</option>';
                        } ?>
                    </select>
                </td>
                <td>
                    <div class="buttons">
                        <button class="positive" type="submit" name="submit" value="modify">Modify</button>
                    </div>
                </td>
                <td>
                    <div class="buttons">
                        <button class="negative" type="submit" name="submit" value="Cancel">Cancel</button>
                    </div>
                </td>
            </tr>
        </form>
    </table>
    <?php
    draw_footer();
} elseif (isset($_REQUEST['submit']) && $_REQUEST['submit'] == 'modify') {
    $template = new WorkflowTemplate((int) $_REQUEST['item'], $pdo);
    draw_header('Update Template: ' . e::h($template->getName()), $last_message);
    ?>
    <form action="workflow" id="modifyWorkflowForm" method="POST" enctype="multipart/form-data">
        <?php echo isset($GLOBALS['csrf']) ? $GLOBALS['csrf']->getTokenField('/workflow') : ''; ?>
        <table border="0" cellspacing="5" cellpadding="5">
            <tr>
                <td><b>Template Name</b></td>
                <td colspan="3">
                    <input type="text" name="name" value="<?php echo e::h($template->getName()); ?>" class="required" maxlength="100">
                    <input type="hidden" name="id" value="<?php echo e::h($template->getId()); ?>">
                </td>
            </tr>
            <tr>
                <td><b>Description</b></td>
                <td colspan="3"><input type="text" name="description" size="50" value="<?php echo e::h($template->getDescription()); ?>"></td>
            </tr>
            <tr>
                <td align="center">
                    <div class="buttons">
                        <button class="positive" type="Submit" name="submit" value="Update Template">Save</button>
                    </div>
                </td>
                <td align="center">
                    <div class="buttons">
                        <button class="negative cancel" type="button" onclick="window.location.href='admin'">Cancel</button>
                    </div>
                </td>
            </tr>
        </table>
    </form>
   <script>
  $(document).ready(function(){
    $('#modifyWorkflowForm').validate();
  });
  </script>
    <?php
    draw_footer();
} elseif (isset($_POST['submit']) && 'Update Template' == $_POST['submit']) {
    if (isset($GLOBALS['csrf']) && !$GLOBALS['csrf']->validateToken($_POST, '/workflow')) {
        header('Location: error?ec=1&last_message=' . urlencode('CSRF token validation failed'));
        exit;
    }

    $name = trim(isset($_POST['name']) ? $_POST['name'] : '');
    if ($name == '') {
        header('Location: admin?last_message=' . urlencode('Template name is required'));
        exit;
    }

    $query = "SELECT id FROM {$GLOBALS['CONFIG']['db_prefix']}workflow_template WHERE name = :name AND id != :id";
    $stmt = $pdo->prepare($query);
    $stmt->execute(array(':name' => $name, ':id' => $_POST['id']));
    if ($stmt->rowCount() != 0) {
        header('Location: error?ec=3&last_message=' . urlencode($name . ' already exists as a workflow template'));
        exit;
    }

    $query = "UPDATE {$GLOBALS['CONFIG']['db_prefix']}workflow_template SET name = :name, description = :description WHERE id = :id";
    $stmt = $pdo->prepare($query);
    $stmt->execute(array(
        ':name' => $name,
        ':description' => isset($_POST['description']) ? $_POST['description'] : '',
        ':id' => $_POST['id']
    ));

    header('Location: admin?last_message=' . urlencode('Template successfully updated'));
} elseif (isset($_REQUEST['submit']) && $_REQUEST['submit'] == 'deletepick') {
    draw_header('Delete a Workflow Template', $last_message);
    $templates = WorkflowTemplate::getAllTemplates($pdo);
    ?>
    <table border="0" cellspacing="5" cellpadding="5">
        <form action="workflow" method="POST" enctype="multipart/form-data">
            <tr>
                <td><b>Template</b></td>
                <td colspan="3">
                    <select name="item">
                        <?php foreach ($templates as $t) {
                            echo '<option value="' . e::h($t['id']) . '">' . e::h($t['name']) . '</option>';
                        } ?>
                    </select>
                </td>
                <td align="center">
                    <div class="buttons">
                        <button class="positive" type="submit" name="submit" value="delete">Delete</button>
                    </div>
                </td>
                <td>
                    <div class="buttons">
                        <button class="negative cancel" type="button" onclick="window.location.href='admin'">Cancel</button>
                    </div>
                </td>
            </tr>
        </form>
    </table>
    <?php
    draw_footer();
} elseif (isset($_REQUEST['submit']) && $_REQUEST['submit'] == 'delete') {
    $template = new WorkflowTemplate((int) $_REQUEST['item'], $pdo);
    $refCount = $template->countReferencingDocuments($pdo);
    draw_header('Delete Template: ' . e::h($template->getName()), $last_message);

    if ($refCount > 0) {
        echo '<p>"' . e::h($template->getName()) . '" is currently assigned to ' . (int) $refCount . ' document(s) and cannot be deleted. Reassign or clear those documents\' workflow template first.</p>';
        echo '<div class="buttons"><button class="regular" type="button" onclick="window.location.href=\'admin\'">Back</button></div>';
        draw_footer();
        exit;
    }
    ?>
    <form action="workflow" method="POST" enctype="multipart/form-data">
        <?php echo isset($GLOBALS['csrf']) ? $GLOBALS['csrf']->getTokenField('/workflow') : ''; ?>
        <input type="hidden" name="id" value="<?php echo e::h($template->getId()); ?>">
        <table border="0">
            <tr><td>Delete "<?php echo e::h($template->getName()); ?>"? This also removes its stages and approver assignments.</td></tr>
            <tr>
                <td>
                    <div class="buttons">
                        <button class="positive" type="submit" name="deletetemplate" value="Yes">Yes</button>
                    </div>
                    <div class="buttons">
                        <button class="negative" type="submit" name="submit" value="Cancel">Cancel</button>
                    </div>
                </td>
            </tr>
        </table>
    </form>
    <?php
    draw_footer();
} elseif (isset($_REQUEST['deletetemplate'])) {
    if (isset($GLOBALS['csrf']) && !$GLOBALS['csrf']->validateToken($_POST, '/workflow')) {
        header('Location: error?ec=1&last_message=' . urlencode('CSRF token validation failed'));
        exit;
    }

    $template = new WorkflowTemplate((int) $_REQUEST['id'], $pdo);
    if ($template->countReferencingDocuments($pdo) > 0) {
        header('Location: admin?last_message=' . urlencode('Cannot delete — still assigned to documents'));
        exit;
    }
    $templateName = $template->getName();
    $template->deleteTemplate();

    header('Location: admin?last_message=' . urlencode('Template "' . $templateName . '" deleted'));
} elseif (isset($_REQUEST['submit']) and $_REQUEST['submit'] == 'Cancel') {
    header('Location: admin?last_message=' . urlencode('Action cancelled'));
} else {
    header('Location: admin?last_message=' . urlencode('Nothing to do'));
}
