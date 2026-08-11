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

// Administer reusable named user Groups. Modeled on department.php's
// single-controller-dispatch pattern, with member management added since
// (unlike departments) a user can belong to more than one group.

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

/*
   Add A New Group
*/
if (isset($_GET['submit']) && $_GET['submit'] == 'add') {
    draw_header('Add New Group', $last_message);
    ?>
        <form id="addGroupForm" action="group" method="POST" enctype="multipart/form-data">
    <?php echo isset($GLOBALS['csrf']) ? $GLOBALS['csrf']->getTokenField('/group') : ''; ?>
    <table border="0" cellspacing="5" cellpadding="5">
            <tr>
                <td><b>Group Name</b></td>
                <td colspan="3"><input name="name" type="text" class="required" minlength="2"></td>
            </tr>
            <tr>
                <td><b>Description</b></td>
                <td colspan="3"><input name="description" type="text" size="50"></td>
            </tr>
            <tr>
                <td align="center">
                    <div class="buttons">
                        <button class="positive" type="submit" name="submit" value="Add Group">Add Group</button>
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
    $('#addGroupForm').validate();
  });
  </script>
    <?php
    draw_footer();
} elseif (isset($_POST['submit']) && 'Add Group' == $_POST['submit']) {
    if (isset($GLOBALS['csrf']) && !$GLOBALS['csrf']->validateToken($_POST, '/group')) {
        header('Location: error?ec=1&last_message=' . urlencode('CSRF token validation failed'));
        exit;
    }

    $name = trim(isset($_POST['name']) ? $_POST['name'] : '');
    $description = isset($_POST['description']) ? $_POST['description'] : '';

    if ($name == '') {
        header('Location: admin?last_message=' . urlencode('Group name is required'));
        exit;
    }

    $query = "SELECT id FROM {$GLOBALS['CONFIG']['db_prefix']}group WHERE name = :name";
    $stmt = $pdo->prepare($query);
    $stmt->execute(array(':name' => $name));
    if ($stmt->rowCount() != 0) {
        header('Location: error?ec=3&last_message=' . urlencode($name . ' already exists as a group'));
        exit;
    }

    $query = "INSERT INTO {$GLOBALS['CONFIG']['db_prefix']}group (name, description) VALUES (:name, :description)";
    $stmt = $pdo->prepare($query);
    $stmt->execute(array(':name' => $name, ':description' => $description));

    header('Location: admin?last_message=' . urlencode('Group successfully added'));
} elseif (isset($_REQUEST['submit']) && $_REQUEST['submit'] == 'showpick') {
    draw_header('Choose a Group', $last_message);
    $groups = Group::getAllGroups($pdo);
    ?>
    <table border="0" cellspacing="5" cellpadding="5">
        <form action="group" method="POST" enctype="multipart/form-data">
            <tr>
                <td><b>Group</b></td>
                <td colspan="3">
                    <select name="item">
                        <?php foreach ($groups as $g) {
                            echo '<option value="' . e::h($g['id']) . '">' . e::h($g['name']) . '</option>';
                        } ?>
                    </select>
                </td>
                <td align="center">
                    <div class="buttons">
                        <button class="positive" type="submit" name="submit" value="Show Group">View</button>
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
} elseif (isset($_POST['submit']) && $_POST['submit'] == 'Show Group') {
    $group = new Group((int) $_POST['item'], $pdo);
    draw_header('Group Information: ' . e::h($group->getName()), $last_message);

    echo '<table cellspacing="15" border="0">';
    echo '<tr><th>ID</th><th>Name</th><th>Description</th></tr>';
    echo '<tr><td>' . e::h($group->getId()) . '</td><td>' . e::h($group->getName()) . '</td><td>' . e::h($group->getDescription()) . '</td></tr>';
    echo '</table>';

    echo '<p><b>Members</b></p>';
    $members = $group->getMembers();
    if (empty($members)) {
        echo '<p>No members yet.</p>';
    } else {
        echo '<table cellspacing="5" border="0">';
        foreach ($members as $m) {
            echo '<tr><td>' . e::h($m['last_name']) . ', ' . e::h($m['first_name']) . '</td></tr>';
        }
        echo '</table>';
    }
    ?>
    <table border="0" cellspacing="5" cellpadding="5">
        <tr>
            <td>
                <div class="buttons">
                    <a class="positive" href="<?php echo 'group?submit=manage_members&item=' . e::h($group->getId()); ?>">Manage Members</a>
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
} elseif (isset($_REQUEST['submit']) && $_REQUEST['submit'] == 'manage_members') {
    $group = new Group((int) $_REQUEST['item'], $pdo);
    draw_header('Manage Members: ' . e::h($group->getName()), $last_message);

    $allUsers = User::getAllUsers($pdo);
    $currentMemberIds = $group->getMemberIds();
    ?>
    <form action="group" method="POST" enctype="multipart/form-data">
        <?php echo isset($GLOBALS['csrf']) ? $GLOBALS['csrf']->getTokenField('/group') : ''; ?>
        <input type="hidden" name="group_id" value="<?php echo e::h($group->getId()); ?>">
        <table border="0" cellspacing="5" cellpadding="5">
            <?php foreach ($allUsers as $u) { ?>
                <tr>
                    <td>
                        <label>
                            <input type="checkbox" name="member[]" value="<?php echo e::h($u['id']); ?>" <?php echo in_array($u['id'], $currentMemberIds) ? 'checked="checked"' : ''; ?>>
                            <?php echo e::h($u['last_name']) . ', ' . e::h($u['first_name']); ?>
                        </label>
                    </td>
                </tr>
            <?php } ?>
            <tr>
                <td align="center">
                    <div class="buttons">
                        <button class="positive" type="submit" name="submit" value="Update Members">Save</button>
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
    <?php
    draw_footer();
} elseif (isset($_POST['submit']) && $_POST['submit'] == 'Update Members') {
    if (isset($GLOBALS['csrf']) && !$GLOBALS['csrf']->validateToken($_POST, '/group')) {
        header('Location: error?ec=1&last_message=' . urlencode('CSRF token validation failed'));
        exit;
    }

    $group = new Group((int) $_POST['group_id'], $pdo);
    $memberIds = isset($_POST['member']) ? array_map('intval', $_POST['member']) : array();
    $group->setMembers($memberIds);

    header('Location: admin?last_message=' . urlencode('Group membership updated for ' . $group->getName()));
} elseif (isset($_REQUEST['submit']) && $_REQUEST['submit'] == 'updatepick') {
    draw_header('Choose a Group to Modify', $last_message);
    $groups = Group::getAllGroups($pdo);
    ?>
    <table border="0" cellspacing="5" cellpadding="5">
        <form action="group" method="GET" enctype="multipart/form-data">
            <tr>
                <td><b>Group to modify</b></td>
                <td colspan="3">
                    <select name="item">
                        <?php foreach ($groups as $g) {
                            echo '<option value="' . e::h($g['id']) . '">' . e::h($g['name']) . '</option>';
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
    $group = new Group((int) $_REQUEST['item'], $pdo);
    draw_header('Update Group: ' . e::h($group->getName()), $last_message);
    ?>
    <form action="group" id="modifyGroupForm" method="POST" enctype="multipart/form-data">
        <?php echo isset($GLOBALS['csrf']) ? $GLOBALS['csrf']->getTokenField('/group') : ''; ?>
        <table border="0" cellspacing="5" cellpadding="5">
            <tr>
                <td><b>Group Name</b></td>
                <td colspan="3">
                    <input type="text" name="name" value="<?php echo e::h($group->getName()); ?>" class="required" maxlength="100">
                    <input type="hidden" name="id" value="<?php echo e::h($group->getId()); ?>">
                </td>
            </tr>
            <tr>
                <td><b>Description</b></td>
                <td colspan="3"><input type="text" name="description" size="50" value="<?php echo e::h($group->getDescription()); ?>"></td>
            </tr>
            <tr>
                <td align="center">
                    <div class="buttons">
                        <button class="positive" type="Submit" name="submit" value="Update Group">Save</button>
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
    $('#modifyGroupForm').validate();
  });
  </script>
    <?php
    draw_footer();
} elseif (isset($_POST['submit']) && 'Update Group' == $_POST['submit']) {
    if (isset($GLOBALS['csrf']) && !$GLOBALS['csrf']->validateToken($_POST, '/group')) {
        header('Location: error?ec=1&last_message=' . urlencode('CSRF token validation failed'));
        exit;
    }

    $name = trim(isset($_POST['name']) ? $_POST['name'] : '');
    if ($name == '') {
        header('Location: admin?last_message=' . urlencode('Group name is required'));
        exit;
    }

    $query = "SELECT id FROM {$GLOBALS['CONFIG']['db_prefix']}group WHERE name = :name AND id != :id";
    $stmt = $pdo->prepare($query);
    $stmt->execute(array(':name' => $name, ':id' => $_POST['id']));
    if ($stmt->rowCount() != 0) {
        header('Location: error?ec=3&last_message=' . urlencode($name . ' already exists as a group'));
        exit;
    }

    $query = "UPDATE {$GLOBALS['CONFIG']['db_prefix']}group SET name = :name, description = :description WHERE id = :id";
    $stmt = $pdo->prepare($query);
    $stmt->execute(array(
        ':name' => $name,
        ':description' => isset($_POST['description']) ? $_POST['description'] : '',
        ':id' => $_POST['id']
    ));

    header('Location: admin?last_message=' . urlencode('Group successfully updated'));
} elseif (isset($_REQUEST['submit']) && $_REQUEST['submit'] == 'deletepick') {
    draw_header('Delete a Group', $last_message);
    $groups = Group::getAllGroups($pdo);
    ?>
    <table border="0" cellspacing="5" cellpadding="5">
        <form action="group" method="POST" enctype="multipart/form-data">
            <tr>
                <td><b>Group</b></td>
                <td colspan="3">
                    <select name="item">
                        <?php foreach ($groups as $g) {
                            echo '<option value="' . e::h($g['id']) . '">' . e::h($g['name']) . '</option>';
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
    $group = new Group((int) $_REQUEST['item'], $pdo);
    draw_header('Delete Group: ' . e::h($group->getName()), $last_message);
    ?>
    <form action="group" method="POST" enctype="multipart/form-data">
        <?php echo isset($GLOBALS['csrf']) ? $GLOBALS['csrf']->getTokenField('/group') : ''; ?>
        <input type="hidden" name="id" value="<?php echo e::h($group->getId()); ?>">
        <table border="0">
            <tr><td>Delete "<?php echo e::h($group->getName()); ?>"? This also removes its membership list and any file permissions granted to it.</td></tr>
            <tr>
                <td>
                    <div class="buttons">
                        <button class="positive" type="submit" name="deletegroup" value="Yes">Yes</button>
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
} elseif (isset($_REQUEST['deletegroup'])) {
    if (isset($GLOBALS['csrf']) && !$GLOBALS['csrf']->validateToken($_POST, '/group')) {
        header('Location: error?ec=1&last_message=' . urlencode('CSRF token validation failed'));
        exit;
    }

    $group = new Group((int) $_REQUEST['id'], $pdo);
    $groupName = $group->getName();
    $group->deleteGroup();

    header('Location: admin?last_message=' . urlencode('Group "' . $groupName . '" deleted'));
} elseif (isset($_REQUEST['submit']) and $_REQUEST['submit'] == 'Cancel') {
    header('Location: admin?last_message=' . urlencode('Action cancelled'));
} else {
    header('Location: admin?last_message=' . urlencode('Nothing to do'));
}
