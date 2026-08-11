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

// Access Request workflow: any logged-in user can request a specific rights
// level on a document they can't sufficiently access; Admin or the file's
// Department Head can grant (writes a real individual permission) or deny.

use Aura\Html\Escaper as e;

session_start();

$pdo = $GLOBALS['pdo'];

if (!isset($_SESSION['uid'])) {
    redirect_visitor();
}

$last_message = (isset($_REQUEST['last_message']) ? $_REQUEST['last_message'] : '');
$user_obj = new User($_SESSION['uid'], $pdo);

if (isset($_GET['submit']) && $_GET['submit'] == 'request') {
    if (!isset($_GET['id']) || $_GET['id'] == '') {
        header('Location:error?ec=2');
        exit;
    }
    $file_obj = new FileData((int) $_GET['id'], $pdo);
    if ($file_obj->getError() != null) {
        header('Location:error?ec=2');
        exit;
    }

    // Same minimum visibility gate as details.php: a user with truly no
    // access (below VIEW_RIGHT, i.e. "No Access"/"Blocked") cannot discover
    // this document exists via Details, and must not be able to route
    // around that by requesting access to a file ID they only guessed —
    // Access Request is for "I can see this exists but need more," not a
    // way to enumerate hidden documents.
    checkUserPermission($file_obj->getId(), $file_obj->VIEW_RIGHT, $file_obj);

    if (AccessRequest::hasPendingRequest($file_obj->getId(), $_SESSION['uid'], $pdo)) {
        header('Location: details?id=' . $file_obj->getId() . '&last_message=' . urlencode('You already have a pending access request on this document.'));
        exit;
    }

    draw_header('Request Access', $last_message);
    ?>
    <form action="access_request" method="POST" enctype="multipart/form-data">
        <?php echo isset($GLOBALS['csrf']) ? $GLOBALS['csrf']->getTokenField('/access_request') : ''; ?>
        <input type="hidden" name="file_id" value="<?php echo e::h($file_obj->getId()); ?>">
        <table border="0" cellspacing="5" cellpadding="5">
            <tr>
                <td><b>Document</b></td>
                <td colspan="3"><?php echo e::h($file_obj->getName()); ?></td>
            </tr>
            <tr>
                <td><b>Requested Access Level</b></td>
                <td colspan="3">
                    <select name="requested_level">
                        <option value="2">View &amp; Download</option>
                        <option value="3">Edit</option>
                        <option value="4">Full Control</option>
                    </select>
                </td>
            </tr>
            <tr>
                <td><b>Reason</b></td>
                <td colspan="3"><input type="text" name="reason" size="50" placeholder="Why do you need this access?" class="required"></td>
            </tr>
            <tr>
                <td align="center">
                    <div class="buttons">
                        <button class="positive" type="submit" name="submit" value="Submit Request">Submit Request</button>
                    </div>
                </td>
                <td align="center">
                    <div class="buttons">
                        <button class="negative cancel" type="button" onclick="window.location.href='details?id=<?php echo e::h($file_obj->getId()); ?>'">Cancel</button>
                    </div>
                </td>
            </tr>
        </table>
    </form>
    <?php
    draw_footer();
} elseif (isset($_POST['submit']) && $_POST['submit'] == 'Submit Request') {
    if (isset($GLOBALS['csrf']) && !$GLOBALS['csrf']->validateToken($_POST, '/access_request')) {
        header('Location: error?ec=1&last_message=' . urlencode('CSRF token validation failed'));
        exit;
    }

    $fileId = (int) $_POST['file_id'];
    $level = (int) $_POST['requested_level'];
    $reason = trim(isset($_POST['reason']) ? $_POST['reason'] : '');

    // Same visibility gate as the GET form above — defense in depth against
    // POSTing directly to this action for a file id never actually seen.
    $submit_file_obj = new FileData($fileId, $pdo);
    checkUserPermission($fileId, $submit_file_obj->VIEW_RIGHT, $submit_file_obj);

    if ($reason == '') {
        header('Location: access_request?submit=request&id=' . $fileId . '&last_message=' . urlencode('A reason is required'));
        exit;
    }
    if (!in_array($level, array(2, 3, 4))) {
        header('Location:error?ec=2');
        exit;
    }

    AccessRequest::createRequest($fileId, $_SESSION['uid'], $level, $reason, $submit_file_obj->getClassification(), $pdo);

    header('Location: details?id=' . $fileId . '&last_message=' . urlencode('Access request submitted.'));
} elseif (isset($_GET['submit']) && $_GET['submit'] == 'review') {
    draw_header('Access Requests Awaiting Review', $last_message);

    $requests = AccessRequest::getResolvableRequests($user_obj, $pdo);
    if (empty($requests)) {
        echo '<p>No pending access requests.</p>';
    } else {
        echo '<table border="1" cellspacing="5" cellpadding="5">';
        echo '<tr><th>Document</th><th>Requested By</th><th>Level</th><th>Classification</th><th>Reason</th><th>Requested On</th><th>Action</th></tr>';
        $levelLabels = array(2 => 'View & Download', 3 => 'Edit', 4 => 'Full Control');
        foreach ($requests as $r) {
            // Surface the document's classification so a resolver can see it
            // before deciding — and flag it if it's changed since the request
            // was submitted (classification_at_request is null for requests
            // made before this tracking existed; nothing to compare against).
            $classificationCell = e::h($r['current_classification']);
            if ($r['classification_at_request'] !== null && $r['classification_at_request'] !== $r['current_classification']) {
                $classificationCell = '<span style="color:#b00; font-weight:bold;">&#9888; ' . e::h($r['current_classification'])
                    . ' (was ' . e::h($r['classification_at_request']) . ' at request time)</span>';
            }

            echo '<tr>';
            echo '<td><a href="details?id=' . e::h($r['file_id']) . '">' . e::h($r['realname']) . '</a></td>';
            echo '<td>' . e::h($r['last_name']) . ', ' . e::h($r['first_name']) . '</td>';
            echo '<td>' . e::h($levelLabels[$r['requested_level']] ?? $r['requested_level']) . '</td>';
            echo '<td>' . $classificationCell . '</td>';
            echo '<td>' . e::h($r['reason']) . '</td>';
            echo '<td>' . e::h($r['requested_on']) . '</td>';
            echo '<td>';
            echo '<form action="access_request" method="POST" enctype="multipart/form-data" style="display:inline">';
            echo isset($GLOBALS['csrf']) ? $GLOBALS['csrf']->getTokenField('/access_request') : '';
            echo '<input type="hidden" name="request_id" value="' . e::h($r['id']) . '">';
            echo '<input type="text" name="resolution_note" placeholder="Note (optional)" size="20">';
            echo '<button class="positive" type="submit" name="submit" value="Grant">Grant</button>';
            echo '<button class="negative" type="submit" name="submit" value="Deny">Deny</button>';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
    draw_footer();
} elseif (isset($_POST['submit']) && ($_POST['submit'] == 'Grant' || $_POST['submit'] == 'Deny')) {
    if (isset($GLOBALS['csrf']) && !$GLOBALS['csrf']->validateToken($_POST, '/access_request')) {
        header('Location: error?ec=1&last_message=' . urlencode('CSRF token validation failed'));
        exit;
    }

    $requestId = (int) $_POST['request_id'];
    $note = trim(isset($_POST['resolution_note']) ? $_POST['resolution_note'] : '');
    $access_request = new AccessRequest($requestId, $pdo);

    if ($access_request->status !== 'pending') {
        header('Location: access_request?submit=review&last_message=' . urlencode('That request has already been resolved.'));
        exit;
    }

    // Authorization: Admin, or Department Head of the file's department
    $file_obj = new FileData($access_request->file_id, $pdo);
    if (!$user_obj->canSetHighClassification($file_obj->getDepartment())) {
        header('Location:error?ec=4');
        exit;
    }

    if ($_POST['submit'] == 'Grant') {
        $resolved = $access_request->grant($_SESSION['uid'], $note, $pdo);
        $message = $resolved ? 'Access request granted.' : 'That request was already resolved by someone else just now.';
    } else {
        $resolved = $access_request->deny($_SESSION['uid'], $note, $pdo);
        $message = $resolved ? 'Access request denied.' : 'That request was already resolved by someone else just now.';
    }

    header('Location: access_request?submit=review&last_message=' . urlencode($message));
} else {
    header('Location: out?last_message=' . urlencode('Nothing to do'));
}
