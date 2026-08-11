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

//  (C) 2002-2007 Stephen Lawrence Jr., Khoa Nguyen, Jon Miner
//  Edit file properties

session_start();

if (!isset($_SESSION['uid'])) {
    redirect_visitor();
}

$pdo = $GLOBALS['pdo'];

$user_perms_obj = new User_Perms($_SESSION['uid'], $pdo);

$last_message = (isset($_REQUEST['last_message']) ? $_REQUEST['last_message'] : '');

if (!isset($_REQUEST['id']) || $_REQUEST['id'] == '') {
    header('Location:error?ec=2');
    exit;
}

if (strchr($_REQUEST['id'], '_')) {
    header('Location:error?ec=20');
}

$filedata = new FileData($_REQUEST['id'], $pdo);

if ($filedata->isArchived()) {
    header('Location:error?ec=21');
}

// form not yet submitted, display initial form
if (!isset($_REQUEST['submit'])) {
    draw_header(msg('area_update_file'), $last_message);
    checkUserPermission($_REQUEST['id'], $filedata->ADMIN_RIGHT, $filedata);

    $current_user_dept = $user_perms_obj->user_obj->getDeptId();

    $data_id = $_REQUEST['id'];
    $department_query = "SELECT department FROM {$GLOBALS['CONFIG']['db_prefix']}user WHERE id=:user_id";
    $department_stmt = $pdo->prepare($department_query);
    $department_stmt->bindParam(':user_id', $_SESSION['uid']);
    $department_stmt->execute();
    $result = $department_stmt->fetchAll();

    if ($department_stmt->rowCount() != 1) {
        header('Location:error?ec=14');
        exit; //non-unique error
    }

    $filedata = new FileData($data_id, $pdo);

    // error check
    if (!$filedata->exists()) {
        header('Location:error?ec=2');
        exit;
    } else {
        $category = $filedata->getCategory();
        $realname = $filedata->getName();
        $description = $filedata->getDescription();
        $comment = $filedata->getComment();
        $owner_id = $filedata->getOwner();
        $department = $filedata->getDepartment();

        //CHM
        $table_name_query = "SELECT table_name FROM {$GLOBALS['CONFIG']['db_prefix']}udf WHERE field_type = '4'";
        $table_name_stmt = $pdo->prepare($table_name_query);
        $table_name_stmt->execute();
        $result = $table_name_stmt->fetchAll();

        $num_rows = $table_name_stmt->rowCount();
        
        $t_name = array();
        $i = 0;
        foreach ($result as $data) {
            $explode_v = explode('_', $data['table_name']);
            $t_name = $explode_v[2];
            $i++;
        }

        // For the User dropdown
        $avail_users = $user_perms_obj->user_obj->getAllUsers($pdo);
        
        // We need to set a form value for the current department so that
        // it can be pre-selected on the form
        $avail_departments = Department::getAllDepartments($pdo);


        $avail_categories = Category::getAllCategories($pdo);

        $cats_array = array();
        foreach ($avail_categories as $avail_category) {
            array_push($cats_array, $avail_category);
        }


        //////Populate department perm list/////////////////
        $dept_perms_array = array();
        foreach ($avail_departments as $dept) {
            $avail_dept_perms['name'] = $dept['name'];
            $avail_dept_perms['id'] = $dept['id'];
            $avail_dept_perms['rights'] = $filedata->getDeptRights($dept['id']);
            array_push($dept_perms_array, $avail_dept_perms);
        }
        
        //////Populate users perm list/////////////////
        $user_perms_array = array();
        foreach ($avail_users as $user) {
            $avail_user_perms['fid'] = $data_id;
            $avail_user_perms['first_name'] = $user['first_name'];
            $avail_user_perms['last_name'] = $user['last_name'];
            $avail_user_perms['id'] = $user['id'];
            $avail_user_perms['rights'] = $user_perms_obj->getPermissionForUser($user['id'], $data_id);
            array_push($user_perms_array, $avail_user_perms);
        }

        //////Populate group perm list/////////////////
        $group_perms_obj = new Group_Perms($_SESSION['uid'], $pdo);
        $group_perms_array = array();
        foreach (Group::getAllGroups($pdo) as $grp) {
            $avail_group_perms = array();
            $avail_group_perms['id'] = $grp['id'];
            $avail_group_perms['name'] = $grp['name'];
            $avail_group_perms['rights'] = $group_perms_obj->getPermissionForGroup($grp['id'], $data_id);
            array_push($group_perms_array, $avail_group_perms);
        }

        $GLOBALS['smarty']->assign('file_id', $filedata->getId());
        $GLOBALS['smarty']->assign('realname', $filedata->name);
        $GLOBALS['smarty']->assign('allDepartments', $avail_departments);
        $GLOBALS['smarty']->assign('current_user_dept', $current_user_dept);
        $GLOBALS['smarty']->assign('t_name', $t_name);
        $GLOBALS['smarty']->assign('is_admin', $user_perms_obj->user_obj->isAdmin());
        $GLOBALS['smarty']->assign('can_set_high_classification', $user_perms_obj->user_obj->canSetHighClassification($department));
        $GLOBALS['smarty']->assign('current_classification', $filedata->getClassification());
        $GLOBALS['smarty']->assign('current_valid_until', $filedata->getValidUntil());
        $GLOBALS['smarty']->assign('avail_users', $user_perms_array);
        $GLOBALS['smarty']->assign('avail_depts', $dept_perms_array);
        $GLOBALS['smarty']->assign('avail_groups', $group_perms_array);

        $workflow_templates_array = array();
        foreach (WorkflowTemplate::getAllTemplates($pdo) as $wft) {
            $wft_obj = new WorkflowTemplate($wft['id'], $pdo);
            $wft['stage_count'] = $wft_obj->getStageCount();
            $workflow_templates_array[] = $wft;
        }
        $GLOBALS['smarty']->assign('avail_workflow_templates', $workflow_templates_array);
        $GLOBALS['smarty']->assign('current_workflow_template_id', $filedata->getWorkflowTemplateId());
        $GLOBALS['smarty']->assign('current_workflow_stage_number', $filedata->getWorkflowStageNumber());

        $GLOBALS['smarty']->assign('cats_array', $cats_array);
        $GLOBALS['smarty']->assign('user_id', $_SESSION['uid']);
        $GLOBALS['smarty']->assign('pre_selected_owner', $owner_id);
        $GLOBALS['smarty']->assign('pre_selected_category', $category);
        $GLOBALS['smarty']->assign('pre_selected_department', $department);
        $GLOBALS['smarty']->assign('description', $description);
        $GLOBALS['smarty']->assign('comment', $comment);
        $GLOBALS['smarty']->assign('db_prefix', $GLOBALS['CONFIG']['db_prefix']);
       
        display_smarty_template('edit.tpl');
        udf_edit_file_form();

        // Call Plugin API
        callPluginMethod('onBeforeEditFile', $data_id);

        display_smarty_template('_edit_footer.tpl');
    }//end else
} else {
    // Validate CSRF token for edit form
    if (isset($GLOBALS['csrf']) && !$GLOBALS['csrf']->validateToken($_POST)) {
        header('Location:error?ec=29&last_message=' . urlencode('Security token validation failed'));
        exit;
    }
    
    // form submitted, process data
    $fileId = $_REQUEST['id'];
    $filedata = new FileData($fileId, $pdo);

    // Call the plugin API
    callPluginMethod('onBeforeEditFileSaved');

    $filedata->setId($fileId);
    $perms_error = false;
    // check submitted data
    // at least one user must have "view" and "modify" rights
    foreach ($_REQUEST['user_permission'] as $permission) {
        if ($permission > 2) {
            $perms_error = true;
        }
    }
     
    if (!$perms_error) {
        header("Location:error?ec=12");
        exit;
    }

    // Check to make sure the file is available
    $status = $filedata->getStatus($fileId);
    if ($status != 0) {
        header('Location:error?ec=2');
        exit;
    }

    // update category
    $filedata->setCategory($_REQUEST['category']);
    $filedata->setDescription($_REQUEST['description']);
    $filedata->setComment($_REQUEST['comment']);

    // Note: odm-init.php's sanitizeme() converts empty-string request values to
    // boolean false, not ''. A strict '!== ''' check does not catch a blank field,
    // so use !empty() here to correctly treat both '' and false as "not submitted".
    if (!empty($_REQUEST['valid_until'])) {
        $user_obj = new User($_SESSION['uid'], $pdo);
        if (!$user_obj->canSetHighClassification($filedata->getDepartment())) {
            header('Location:error?ec=33&last_message=' . urlencode('Only Admins or Department Heads may set or renew a document\'s validity date'));
            exit;
        }
        $reason = !empty($_REQUEST['valid_until_reason']) ? $_REQUEST['valid_until_reason'] : 'No reason provided';
        $filedata->setValidUntil($_REQUEST['valid_until'], $reason, $pdo);
    }

    if (!empty($_REQUEST['new_classification']) && $_REQUEST['new_classification'] !== $filedata->getClassification()) {
        $user_obj = new User($_SESSION['uid'], $pdo);
        // Reclassifying an existing document is stricter than the initial upload-time
        // rule — Admin/Super-Admin only, not Department Head, since this changes the
        // classification of a document that may already be approved and in active use.
        if (!$user_obj->isAdmin()) {
            header('Location:error?ec=35&last_message=' . urlencode('Only Admins or Super-Admins may reclassify an existing document'));
            exit;
        }
        $reason = !empty($_REQUEST['new_classification_reason']) ? $_REQUEST['new_classification_reason'] : 'No reason provided';
        $filedata->setClassification($_REQUEST['new_classification'], $reason, $pdo);
    }

    // Staged Approval floor check (Hybrid model, 2026-08-11 decision). Evaluated
    // after any classification change above, so getClassification() already
    // reflects the EFFECTIVE final state — Sensitive/Highly sensitive documents
    // must end this request with an adequately-staged workflow template.
    $requested_workflow_template_id = !empty($_REQUEST['workflow_template_id']) ? (int) $_REQUEST['workflow_template_id'] : null;
    $required_stages = WorkflowTemplate::getRequiredStageCount($filedata->getClassification());
    if ($required_stages > 0) {
        if (!$requested_workflow_template_id) {
            header('Location:error?ec=36&last_message=' . urlencode($filedata->getClassification() . ' documents require a workflow template with at least ' . $required_stages . ' stage(s) to be assigned.'));
            exit;
        }
        $requested_template = new WorkflowTemplate($requested_workflow_template_id, $pdo);
        if ($requested_template->getStageCount() < $required_stages) {
            header('Location:error?ec=36&last_message=' . urlencode('The selected workflow template only has ' . $requested_template->getStageCount() . ' stage(s), but ' . $filedata->getClassification() . ' requires at least ' . $required_stages . '.'));
            exit;
        }
    }

    $current_workflow_template_id = $filedata->getWorkflowTemplateId() ? (int) $filedata->getWorkflowTemplateId() : null;
    if ($requested_workflow_template_id !== $current_workflow_template_id) {
        // Admin-only, same rationale as reclassification — changing an existing
        // document's approval workflow after the fact is a governance action.
        $workflow_editing_user = new User($_SESSION['uid'], $pdo);
        if (!$workflow_editing_user->isAdmin()) {
            header('Location:error?ec=37&last_message=' . urlencode('Only Admins or Super-Admins may change an existing document\'s workflow template'));
            exit;
        }
        $filedata->setWorkflowTemplate($requested_workflow_template_id, $pdo);
    }

    if (isset($_REQUEST['file_owner'])) {
        $filedata->setOwner($_REQUEST['file_owner']);
    }
    if (isset($_REQUEST['file_department'])) {
        $filedata->setDepartment($_REQUEST['file_department']);
    }

    // Update the file with the new values
    $filedata->updateData();

    udf_edit_file_update();

    // clean out old permissions
    $del_user_perms_query = "DELETE FROM {$GLOBALS['CONFIG']['db_prefix']}user_perms WHERE fid = :file_id";
    $del_user_perms_stmt = $pdo->prepare($del_user_perms_query);
    $del_user_perms_stmt->bindParam(':file_id', $fileId);
    $del_user_perms_stmt->execute();

    // clean out old permissions
    $del_dept_perms_query = "DELETE FROM {$GLOBALS['CONFIG']['db_prefix']}dept_perms WHERE fid = :file_id";
    $del_dept_perms_stmt = $pdo->prepare($del_dept_perms_query);
    $del_dept_perms_stmt->bindParam(':file_id', $fileId);
    $del_dept_perms_stmt->execute();

    // clean out old group permissions
    $del_group_perms_query = "DELETE FROM {$GLOBALS['CONFIG']['db_prefix']}group_perms WHERE fid = :file_id";
    $del_group_perms_stmt = $pdo->prepare($del_group_perms_query);
    $del_group_perms_stmt->bindParam(':file_id', $fileId);
    $del_group_perms_stmt->execute();

    $result_array = array(); // init;

    foreach ($_REQUEST['user_permission'] as $user_id=>$permission) {
        $insert_user_perms_query = "
            INSERT INTO {$GLOBALS['CONFIG']['db_prefix']}user_perms 
            (
                fid, 
                uid, 
                rights
            ) VALUES(
                :file_id, 
                :user_id, 
                :permission
            )";
        //echo $query."<br>";
        $insert_user_perms_stmt = $pdo->prepare($insert_user_perms_query);
        $insert_user_perms_stmt->bindParam(':file_id', $fileId);
        $insert_user_perms_stmt->bindParam(':user_id', $user_id);
        $insert_user_perms_stmt->bindParam(':permission', $permission);
        $insert_user_perms_stmt->execute();
    }

    //UPDATE Department Rights into dept_perms
    foreach ($_POST['department_permission'] as $dept_id => $dept_perm) {
        $update_dept_perms_query = "
            INSERT INTO
                {$GLOBALS['CONFIG']['db_prefix']}dept_perms
            (
                fid,
                dept_id,
                rights
            )
            VALUES
             (
                :file_id,
                :dept_id,
                :dept_perm
             )
             ";
        $update_dept_perms_stmt = $pdo->prepare($update_dept_perms_query);
        $update_dept_perms_stmt->bindParam(':dept_perm', $dept_perm);
        $update_dept_perms_stmt->bindParam(':dept_id', $dept_id);
        $update_dept_perms_stmt->bindParam(':file_id', $filedata->getId());
        $update_dept_perms_stmt->execute();
    }

    // UPDATE Group Rights into group_perms (absent if no groups exist yet)
    if (isset($_POST['group_permission'])) {
        foreach ($_POST['group_permission'] as $group_id => $group_perm) {
            $update_group_perms_query = "
                INSERT INTO
                    {$GLOBALS['CONFIG']['db_prefix']}group_perms
                (
                    fid,
                    group_id,
                    rights
                )
                VALUES
                 (
                    :file_id,
                    :group_id,
                    :group_perm
                 )
                 ";
            $update_group_perms_stmt = $pdo->prepare($update_group_perms_query);
            $update_group_perms_stmt->bindParam(':group_perm', $group_perm);
            $update_group_perms_stmt->bindParam(':group_id', $group_id);
            $update_group_perms_stmt->bindParam(':file_id', $filedata->getId());
            $update_group_perms_stmt->execute();
        }
    }

    $message = 'Document successfully updated';

    AccessLog::addLogEntry($fileId, 'M', $pdo);

    // Call the plugin API
    callPluginMethod('onAfterEditFile', $fileId);

    header('Location: details?id=' . $fileId . '&last_message=' . urlencode($message));
}
draw_footer();
