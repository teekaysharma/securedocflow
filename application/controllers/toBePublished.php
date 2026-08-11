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

// (C) 2002, 2003, 2004  Stephen Lawrence, Khoa Nguyen
// Display list of publishable files to reviewer

use Aura\Html\Escaper as e;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['uid'])) {
    redirect_visitor();
}

$pdo = $GLOBALS['pdo'];

$last_message = (isset($_REQUEST['last_message']) ? $_REQUEST['last_message'] : '');

$user_obj = new User($_SESSION['uid'], $pdo);
if (!$user_obj->isReviewer()) {
    header('Location:out?last_message=Access+denied');
}

$comments = isset($_REQUEST['comments']) ? stripslashes($_REQUEST['comments']) : '';

if (!isset($_POST['submit'])) {
    draw_header(msg('message_documents_waiting'), $last_message);
    $userpermission = new UserPermission($_SESSION['uid'], $pdo);

    if ($user_obj->isAdmin()) {
        $id_array = $user_obj->getAllRevieweeIds();
    } else {
        $id_array = $user_obj->getRevieweeIds();
    }

    // Ensure fresh CSRF token for the file list form
    if (isset($GLOBALS['csrf'])) {
        $csrf_data = $GLOBALS['csrf']->getTokenForTemplate('/toBePublished');
        $GLOBALS['smarty']->assign('csrf_token_field', $csrf_data['field']);
        $GLOBALS['smarty']->assign('csrf_token_value', $csrf_data['token']);
        $GLOBALS['smarty']->assign('csrf_field_name', $csrf_data['field_name']);
        $GLOBALS['smarty']->assign('csrf_index_name', $csrf_data['index_name']);
    }
    $list_status = list_files($id_array, $userpermission, $GLOBALS['CONFIG']['dataDir'], true);
    if ($list_status != -1) {
        $GLOBALS['smarty']->assign('lmode', '');
        display_smarty_template('toBePublished.tpl');
    }
} elseif (isset($_POST['submit']) && ($_POST['submit'] =='commentAuthorize' || $_POST['submit'] == 'commentReject')) {
    // Validate CSRF token for Approve/Deny initial POST
    if (isset($GLOBALS['csrf']) && !$GLOBALS['csrf']->validateToken($_POST, '/toBePublished')) {

        header('Location: error?ec=1&last_message=' . urlencode('CSRF token validation failed'));
        exit;
    }
    if (!isset($_POST['checkbox'])) {
        header('Location: toBePublished?last_message=' . urlencode(msg('message_you_did_not_enter_value')));
    }

    draw_header(msg('label_comment'), $last_message);

    $checkbox = isset($_POST['checkbox']) ? $_POST['checkbox'] : '';
/*    if($mode == 'reviewer')
    {
        $access_mode = 'enabled';
    }
    else
    {
        $access_mode = 'disabled';
    }

*/
    if ($_POST['submit'] == 'commentReject') {
        $submit_value='Reject';
    } elseif ($_POST['submit'] == 'commentAuthorize') {
        $submit_value='Authorize';
    } else {
        $submit_value='None';
    }

    $query = "
      SELECT
        id,
        first_name,
        last_name
      FROM
        {$GLOBALS['CONFIG']['db_prefix']}user
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute(array());
    $result = $stmt->fetchAll();

    $GLOBALS['smarty']->assign('user_info', $result);
    $GLOBALS['smarty']->assign('submit_value', $submit_value);
    $GLOBALS['smarty']->assign('checkbox', $checkbox);
    $GLOBALS['smarty']->assign('access_mode', '');
    $GLOBALS['smarty']->assign('mode', '');

    // Pre-fill the real document owner's name when reviewing a single document
    $checkbox_ids = is_array($checkbox) ? $checkbox : explode(' ', trim($checkbox));
    if (count($checkbox_ids) == 1 && $checkbox_ids[0] !== '') {
        $single_file_obj = new FileData((int) $checkbox_ids[0], $pdo);
        $owner_obj = new User($single_file_obj->getOwner(), $pdo);
        $owner_name_parts = $owner_obj->getFullName();
        $GLOBALS['smarty']->assign('default_to_name', $owner_name_parts[0] . ' ' . $owner_name_parts[1]);
    }
    // Refresh CSRF token for the comment form to avoid using a consumed token
    if (isset($GLOBALS['csrf'])) {
        $csrf_data = $GLOBALS['csrf']->getTokenForTemplate('/toBePublished');
        $GLOBALS['smarty']->assign('csrf_token_field', $csrf_data['field']);
        $GLOBALS['smarty']->assign('csrf_token_value', $csrf_data['token']);
        $GLOBALS['smarty']->assign('csrf_field_name', $csrf_data['field_name']);
        $GLOBALS['smarty']->assign('csrf_index_name', $csrf_data['index_name']);
    }
    display_smarty_template('commentform.tpl');
} elseif (isset($_POST['submit']) && $_POST['submit'] == 'Reject') {
    // Validate CSRF token for Reject operation
    if (isset($GLOBALS['csrf']) && !$GLOBALS['csrf']->validateToken($_POST, '/toBePublished')) {

        header('Location: error?ec=1&last_message=' . urlencode('CSRF token validation failed'));
        exit;
    }

    $to = isset($_POST['to']) ? e::h($_POST['to']) : '';
    $subject = isset($_POST['subject']) ? e::h($_POST['subject']) : '';
    $checkbox = isset($_POST['checkbox']) ? e::h($_POST['checkbox']) : '';

    $mail_break = '--------------------------------------------------'.PHP_EOL;
    $reviewer_comments = "To=$to;Subject=$subject;Comments=$comments;";
    $user_obj = new user($_SESSION['uid'], $pdo);
    $date = date('Y-m-d H:i:s T'); //locale insensitive
    $get_full_name = $user_obj->getFullName();
    $full_name = e::h($get_full_name[0]) .' '. e::h($get_full_name[1]);
    $mail_from= $full_name.' <'.$user_obj->getEmailAddress().'>';
    $mail_headers = "From: " . e::h($mail_from) . PHP_EOL;
    $mail_headers .="Content-Type: text/plain; charset=UTF-8".PHP_EOL;
    $mail_subject= (!empty($_REQUEST['subject']) ? stripslashes(e::h($_REQUEST['subject'])) : msg('email_subject_review_status'));
    $mail_greeting=msg('email_greeting'). ":" . PHP_EOL . "\t" . msg('email_i_would_like_to_inform');
    $mail_body = $comments . PHP_EOL . PHP_EOL;
    $mail_body .= msg('email_was_declined_for_publishing_at') . ' ' .$date. ' ' . msg('email_for_the_following_reasons') . ':'. PHP_EOL . PHP_EOL . $mail_break . e::h($_REQUEST['comments']) . PHP_EOL . $mail_break;
    $mail_salute=PHP_EOL . PHP_EOL . msg('email_salute') . ",". PHP_EOL . $full_name;

    if ($user_obj->isAdmin()) {
        $id_array = $user_obj->getAllRevieweeIds();
    } else {
        $id_array = $user_obj->getRevieweeIds();
    }

    // See the matching comment in the Authorize branch below — captured before
    // the loop reassigns $user_obj to each file's owner.
    $current_reviewer_obj = $user_obj;

    $id_field = explode(' ', trim($checkbox));
    foreach ($id_field as $key=>$value) {
        // Check to make sure the current file_id is in their list of rejectable ID's
        if (in_array($value, $id_array)) {
            $fileid = $value;
            $file_obj = new FileData($fileid, $pdo);

            // Same Staged Approval authorization narrowing as Authorize — only
            // the current stage's designated approver (or Admin) may reject a
            // document that's under a workflow template, even if the user also
            // has classic department-reviewer access to it.
            if ($file_obj->getWorkflowTemplateId()
                && !$current_reviewer_obj->isAdmin()
                && !$current_reviewer_obj->isStageApproverForFile($fileid)) {
                continue;
            }

            $user_obj = new User($file_obj->getOwner(), $pdo);
            $mail_to = $user_obj->getEmailAddress();
            $dept_id = $file_obj->getDepartment();
            // Build email for author notification
            if (isset($_POST['send_to_users'][0]) && in_array('owner', $_POST['send_to_users'])) {
                // Lets unset this now so the new array will just be user_id's
                $_POST['send_to_users'] = array_slice($_POST['send_to_users'], 1);
                $mail_body1 = e::h($comments) . PHP_EOL . PHP_EOL;
                $mail_body1.=msg('email_was_rejected_from_repository') . PHP_EOL . PHP_EOL;
                $mail_body1.=msg('label_filename') . ':  ' . $file_obj->getName() . PHP_EOL . PHP_EOL;
                $mail_body1.=msg('label_status') . ': ' . msg('message_authorized') . PHP_EOL . PHP_EOL;
                $mail_body1.=msg('date') . ': ' . $date . PHP_EOL . PHP_EOL;
                $mail_body1.=msg('label_reviewer') . ': ' . e::h($full_name) . PHP_EOL . PHP_EOL;
                $mail_body1.=msg('email_thank_you') . ',' . PHP_EOL . PHP_EOL;
                $mail_body1.=msg('email_automated_document_messenger') . PHP_EOL . PHP_EOL;
                $mail_body1.=$GLOBALS['CONFIG']['base_url'] . PHP_EOL . PHP_EOL;

                if ($GLOBALS['CONFIG']['demo'] == 'False') {
                    mail($mail_to, $mail_subject . ' ' . $file_obj->getName(), $mail_greeting . $file_obj->getName() . ' ' . $mail_body1 . $mail_salute, $mail_headers);
                }
            }

            $file_obj->Publishable(-1);
            $file_obj->setReviewerComments($reviewer_comments);
            AccessLog::addLogEntry($fileid, 'R', $pdo, $reviewer_comments);
            // Set up rejected email message to sent out
            $mail_subject = (!empty($_REQUEST['subject']) ? stripslashes(e::h($_REQUEST['subject'])) : msg('email_a_new_file_has_been_rejected'));
            $mail_body = e::h($comments) . PHP_EOL . PHP_EOL;
            $mail_body.=msg('email_a_new_file_has_been_rejected').PHP_EOL . PHP_EOL;
            $mail_body.=msg('label_filename'). ':  ' .$file_obj->getName() . PHP_EOL . PHP_EOL;
            $mail_body.=msg('label_status').': ' .msg('message_rejected'). PHP_EOL . PHP_EOL;
            $mail_body.=msg('date'). ': ' .$date. PHP_EOL . PHP_EOL;
            $mail_body.=msg('label_reviewer'). ': ' . e::h($full_name) . PHP_EOL . PHP_EOL;
            $mail_body.=msg('email_thank_you'). ','. PHP_EOL . PHP_EOL;
            $mail_body.=msg('email_automated_document_messenger'). PHP_EOL . PHP_EOL;
            $mail_body.=$GLOBALS['CONFIG']['base_url'] . PHP_EOL . PHP_EOL;

            if (isset($_POST['send_to_all'])) {
                email_all($mail_subject, $mail_body, $mail_headers);
            }

            if (isset($_POST['send_to_dept'])) {
                email_dept($dept_id, $mail_subject, $mail_body, $mail_headers);
            }

            if (isset($_POST['send_to_users']) && is_array($_POST['send_to_users']) && isset($_POST['send_to_users'][0])) {
                email_users_id($_POST['send_to_users'], $mail_subject, $mail_body, $mail_headers);
            }
        } else {
            // If their user cannot reject this file_id, display error
            header("Location:toBePublished?last_message=" .urlencode(msg('message_error_performing_action')));
        }
    }
    header("Location: out?last_message=" .urlencode(msg('message_file_rejected')));
} elseif (isset($_POST['submit']) && $_POST['submit'] == 'Authorize') {
    // Validate CSRF token for Authorize operation
    if (isset($GLOBALS['csrf']) && !$GLOBALS['csrf']->validateToken($_POST, '/toBePublished')) {

        header('Location: error?ec=1&last_message=' . urlencode('CSRF token validation failed'));
        exit;
    }

    $checkbox = isset($_POST['checkbox']) ? e::h($_POST['checkbox']) : '';
    $reviewer_comments = "To= " . e::h($_POST['to']) . ";Subject=" . e::h($_POST['subject']) . ";Comments=" . e::h($_POST['comments']) . ";";
    $user_obj = new User($_SESSION['uid'], $pdo);
    $date = date('Y-m-d H:i:s T'); //locale insensitive
    $get_full_name = $user_obj->getFullName();
    $full_name = $get_full_name[0].' '.$get_full_name[1];
    $mail_subject = (!empty($_POST['subject']) ? stripslashes(e::h($_POST['subject'])) : msg('email_subject_review_status'));
    $mail_from= e::h($full_name) . ' <'.$user_obj->getEmailAddress().'>';
    $mail_headers = "From: ". e::h($mail_from) .PHP_EOL;
    $mail_headers .="Content-Type: text/plain; charset=UTF-8".PHP_EOL;
    $mail_greeting=msg('email_greeting'). ":" . PHP_EOL . "\t" . msg('email_i_would_like_to_inform');
    $mail_salute=PHP_EOL . PHP_EOL . msg('email_salute') . ",". PHP_EOL . $full_name;

    if ($user_obj->isAdmin()) {
        $id_array = $user_obj->getAllRevieweeIds();
    } else {
        $id_array = $user_obj->getRevieweeIds();
    }

    // Captured before the loop below reassigns $user_obj to each file's owner —
    // this is who is actually performing the approval.
    $current_reviewer_obj = $user_obj;

    $id_field=explode(' ', trim($checkbox));
    foreach ($id_field as $key=>$value) {
        // Check to make sure the current file_id is in their list of reviewable ID's
        if (in_array($value, $id_array)) {
            $fileid = $value;
            $file_obj = new FileData($fileid, $pdo);

            // Staged Approval authorization check: being in $id_array is not
            // enough on its own — it's satisfied by *either* department
            // reviewership *or* workflow-stage approvership (see
            // User::getRevieweeIds()), but once a document has a workflow
            // template assigned, only the CURRENT stage's designated
            // approver (or Admin) may act on it. Without this, a department
            // reviewer who happens to have classic access to a document that
            // is also under Staged Approval could approve/advance a stage
            // they were never assigned to — defeating the point of naming
            // specific per-stage approvers.
            if ($file_obj->getWorkflowTemplateId()
                && !$current_reviewer_obj->isAdmin()
                && !$current_reviewer_obj->isStageApproverForFile($fileid)) {
                continue;
            }

            $user_obj = new User($file_obj->getOwner(), $pdo);
            $mail_to = $user_obj->getEmailAddress();
            $dept_id = $file_obj->getDepartment();

            // Staged Approval (Hybrid model): if this document has a workflow
            // template assigned and isn't on its last stage yet, "Approve" here
            // means advance to the next stage, not final publication — the
            // document stays pending (publishable stays 0) and none of the
            // "your file has been authorized" / "added to repository" emails
            // below apply yet, since it genuinely hasn't been authorized yet.
            $workflow_template_id = $file_obj->getWorkflowTemplateId();
            $is_intermediate_stage = false;
            if ($workflow_template_id) {
                $workflow_template = new WorkflowTemplate($workflow_template_id, $pdo);
                $current_stage_number = (int) $file_obj->getWorkflowStageNumber();
                $stage_count = $workflow_template->getStageCount();
                if ($current_stage_number < $stage_count) {
                    $is_intermediate_stage = true;
                }
            }

            if ($is_intermediate_stage) {
                $file_obj->advanceWorkflowStage($pdo);
                $file_obj->setReviewerComments($reviewer_comments);
                $stage_note = 'Stage ' . $current_stage_number . ' of ' . $stage_count . ' approved ("' . $workflow_template->getName() . '"), advanced to stage ' . $file_obj->getWorkflowStageNumber() . '. ' . $reviewer_comments;
                AccessLog::addLogEntry($fileid, 'S', $pdo, $stage_note);
            } else {
                // Build email for author notification
                if (isset($_POST['send_to_users'][0]) && in_array('owner', $_POST['send_to_users'])) {
                    // Lets unset this now so the new array will just be user_id's
                    $_POST['send_to_users'] = array_slice($_POST['send_to_users'], 1);

                    $mail_body1 = e::h($comments) . PHP_EOL . PHP_EOL;
                    $mail_body1.=msg('email_your_file_has_been_authorized') . PHP_EOL . PHP_EOL;
                    $mail_body1.=msg('label_filename') . ':  ' . $file_obj->getName() . PHP_EOL . PHP_EOL;
                    $mail_body1.=msg('label_status') . ': ' . msg('message_authorized') . PHP_EOL . PHP_EOL;
                    $mail_body1.=msg('date') . ': ' . $date . PHP_EOL . PHP_EOL;
                    $mail_body1.=msg('label_reviewer') . ': ' . e::h($full_name) . PHP_EOL . PHP_EOL;
                    $mail_body1.=msg('email_thank_you') . ',' . PHP_EOL . PHP_EOL;
                    $mail_body1.=msg('email_automated_document_messenger') . PHP_EOL . PHP_EOL;
                    $mail_body1.=$GLOBALS['CONFIG']['base_url'] . PHP_EOL . PHP_EOL;
                    if ($GLOBALS['CONFIG']['demo'] == 'False')
                    {
                        mail($mail_to, $mail_subject . ' ' . $file_obj->getName(), $mail_greeting . $file_obj->getName() . ' ' . $mail_body1 . $mail_salute, $mail_headers);
                    }
                }

                $file_obj->Publishable(1);
                $file_obj->setReviewerComments($reviewer_comments);
                AccessLog::addLogEntry($fileid, 'Y', $pdo, $reviewer_comments);

                // Build email for general notices
                $mail_subject = (!empty($_POST['subject']) ? stripslashes(e::h($_POST['subject'])) : $file_obj->getName().' ' .msg('email_added_to_repository'));
                $mail_body2=$comments . PHP_EOL . PHP_EOL;
                $mail_body2.=msg('email_a_new_file_has_been_added'). PHP_EOL . PHP_EOL;
                $mail_body2.=msg('label_filename'). ':  ' . $file_obj->getName() . PHP_EOL . PHP_EOL;
                $mail_body2.=msg('label_status'). ': New'. PHP_EOL . PHP_EOL;
                $mail_body2.=msg('date'). ': ' . $date . PHP_EOL . PHP_EOL;
                $mail_body2.=msg('label_reviewer'). ': ' . e::h($full_name) . PHP_EOL . PHP_EOL;
                $mail_body2.=msg('email_thank_you'). ','. PHP_EOL . PHP_EOL;
                $mail_body2.=msg('email_automated_document_messenger'). PHP_EOL . PHP_EOL;
                $mail_body2.=$GLOBALS['CONFIG']['base_url'] . PHP_EOL . PHP_EOL;

                if (isset($_POST['send_to_all'])) {
                    email_all($mail_subject, $mail_body2, $mail_headers);
                }

                if (isset($_POST['send_to_dept'])) {
                    email_dept($dept_id, $mail_subject, $mail_body2, $mail_headers);
                }
                if (!empty($_POST['send_to_users'][0]) && is_array($_POST['send_to_users']) && $_POST['send_to_users'][0] > 0) {
                    email_users_id($_POST['send_to_users'], $mail_subject, $mail_body2, $mail_headers);
                }
            }
        } else {
            // If their user cannot authorize this file_id, display error
            header("Location:toBePublished?last_message=" .urlencode(msg('message_error_performing_action')));
        }
    }
    header('Location: out?last_message=' .urlencode(msg('message_file_authorized')));
} elseif (isset($_POST['submit']) && $_POST['submit'] == 'comments' && isset($_POST['id'])) {
    /*
     * Used to display the reviewer comments in a popup
     */
    $file_id = (int) $_POST['id'];
    $file_obj = new FileData($file_id, $pdo);
    echo $file_obj->getReviewerComments();
} elseif (isset($_POST['submit']) && $_POST['submit'] == 'Cancel') {
    $last_message=urlencode(msg('message_action_cancelled'));
    header('Location: toBePublished?last_message=' . urlencode($last_message));
}
    draw_footer();
