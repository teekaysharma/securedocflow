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
// Draws screen which allows users to view files inline

session_cache_limiter('private');
session_start();

if (!isset($_SESSION['uid'])) {
    redirect_visitor();
}

$pdo = $GLOBALS['pdo'];

$last_message = (isset($_REQUEST['last_message']) ? $_REQUEST['last_message'] : '');

$request_id = $_REQUEST['id']; //save an original copy of id
// Every filesystem path built later in this file must come from a
// validated integer, never the raw request string — a crafted id like
// "5/../6" would otherwise let a read authorized for document 5 silently
// serve document 6's actual bytes (checkUserPermission below would only
// ever see the safe (int) prefix, while an unvalidated path built from
// the raw string can still walk outside dataDir). $revision_id likewise
// must be validated as a plain non-negative integer before it's allowed
// anywhere near a path — a malformed/unsafe suffix is treated as "no
// revision requested" rather than trusted.
$revision_id = null;
if (strchr($_REQUEST['id'], '_')) {
    list($idPart, $revisionPart) = explode('_', $_REQUEST['id'], 2);
    if (ctype_digit((string) $revisionPart)) {
        $revision_id = (int) $revisionPart;
    }
} else {
    $idPart = $_REQUEST['id'];
}
$_REQUEST['id'] = (int) $idPart;
$revision_dir = $GLOBALS['CONFIG']['revisionDir'] . '/'. $_REQUEST['id'] . '/';

if (!isset($_GET['submit'])) {
    draw_header(msg('view') . ' ' . msg('file'), $last_message);
    $file_obj = new FileData($_REQUEST['id'], $pdo);
    $file_name = $file_obj->getName();
    $file_id = $file_obj->getId();
    $realname = $file_obj->getName();

    // Get the suffix of the file so we can look it up
    $suffix = '';
    if (strchr($realname, '.')) {
        // Fix by blackwes
        $prefix = (substr($realname, 0, (strrpos($realname, "."))));
        $suffix = strtolower((substr($realname, ((strrpos($realname, ".")+1)))));
    }

    // If we have a revision ID lets use the original
    // request id that included the file id and revision number (ex. 1_0)
    if (isset($revision_id)) {
        $file_id = $request_id;
    }

    $mimetype = File::mime_by_ext($suffix);

    $GLOBALS['smarty']->assign('mimetype', $mimetype);
    $GLOBALS['smarty']->assign('file_id', $file_id);

    // drw form
    display_smarty_template('view_file.tpl');
    draw_footer();
} elseif ($_GET['submit'] == 'view') {
    $file_obj = new FileData($_REQUEST['id'], $pdo);
    // Added this check to keep unauthorized users from downloading - Thanks to Chad Bloomquist
    checkUserPermission($_REQUEST['id'], $file_obj->READ_RIGHT, $file_obj);
    $realname = $file_obj->getName();

    $user_obj = new User($_SESSION['uid'], $pdo);

    // Block view/download of unauthorized (pending) documents — only Admin or the assigned Reviewer may preview for review purposes
    // isPublishable() returns the raw publishable column (-1 rejected, 0 pending, 1 approved).
    // PHP treats -1 as truthy, so a plain !isPublishable() only catches pending (0), not
    // rejected (-1) — check both explicitly so rejected documents are gated the same way.
    if ($file_obj->isPublishable() != 1 && !$user_obj->isAdmin() && !$user_obj->isReviewerForFile($_REQUEST['id'])) {
        header('Location: error?ec=31&last_message=' . urlencode('This document has not yet been reviewed and approved and cannot be viewed or downloaded.'));
        exit;
    }

    // Archived documents — content access restricted to Admin/Super-Admin only
    if ($file_obj->isArchived() && !$user_obj->isAdmin()) {
        header('Location: error?ec=32&last_message=' . urlencode('This document has been archived. Only an Admin or Super-Admin may view archived document content.'));
        exit;
    }

    if ($file_obj->isExpired() && !$user_obj->isAdmin()) {
        header('Location: error?ec=34&last_message=' . urlencode('This document\'s validity period has ended. Only an Admin or Super-Admin may view it and renew its validity date.'));
        exit;
    }

    // Checked-out Sensitive/Highly sensitive documents — only the person who checked it out may view/download while locked
    $high_class = array('Sensitive', 'Highly sensitive');
    if ($file_obj->getStatus() > 0 && in_array($file_obj->getClassification(), $high_class) && $file_obj->getStatus() != $_SESSION['uid']) {
        header('Location: details?id=' . $_REQUEST['id']);
        exit;
    }

    if (isset($revision_id)) {
        $filename = $revision_dir . $_REQUEST['id'] . '_' . $revision_id . ".dat";
    } elseif ($file_obj->isArchived()) {
        $filename = $GLOBALS['CONFIG']['archiveDir'] . $_REQUEST['id'] . ".dat";
    } else {
        $filename = $GLOBALS['CONFIG']['dataDir'] . $_REQUEST['id'] . ".dat";
    }

    $mimetype = $_GET['mimetype'];
    // Prefix the traceable serial number onto whatever gets served, so it
    // travels with the file the moment it leaves the system — regardless of
    // how the uploader originally named it. Documents uploaded before the
    // Serial Number feature existed have none; fall back to the plain name.
    $serialPrefix = $file_obj->getSerialNumber() ? $file_obj->getSerialNumber() . '_' : '';
    $display_name = $serialPrefix . $realname;

    // In-browser preview for non-PDF office formats (Word/Excel/PowerPoint/
    // OpenDocument/RTF): convert to PDF via LibreOffice headless (cached —
    // see PreviewGenerator) and serve that instead, so the browser can
    // render it inline instead of forcing a download of a format it can't
    // display. Not attempted for a past-revision view — those .dat files
    // don't carry a stable doc_version/doc_revision cache key the same way.
    $realExtension = pathinfo($realname, PATHINFO_EXTENSION);
    if (!isset($revision_id) && PreviewGenerator::isConvertible($realExtension) && file_exists($filename)) {
        $previewPath = PreviewGenerator::getPreviewPath($filename, $file_obj->getId(), $file_obj->getDocVersion(), $file_obj->getDocRevision());
        if ($previewPath !== null) {
            $filename = $previewPath;
            $mimetype = 'application/pdf';
            $display_name = $serialPrefix . pathinfo($realname, PATHINFO_FILENAME) . '.pdf';
        } else {
            // Conversion failed (LibreOffice unavailable, timed out, corrupt
            // or pathological source content — see PreviewGenerator). Don't
            // try to inline-serve a format the browser can't render, which
            // produces a confusing broken-preview experience. Send the user
            // back to the view/download choice page (view_file.tpl already
            // offers a direct Download button there) with a clear reason.
            header('Location: view_file?id=' . urlencode($request_id) . '&mimetype=' . urlencode($mimetype) . '&last_message=' . urlencode('A preview could not be generated for this document. Use Download below to get the original file.'));
            exit;
        }
    }

    if (file_exists($filename)) {
        // send headers to browser to initiate file download
        header('Content-Length: '.filesize($filename));
        // Pass the mimetype so the browser can open it
        header('Cache-control: private');
        header('Content-Type: ' . $mimetype);
        header('Content-Disposition: inline; filename="' . rawurlencode($display_name) . '"');
        // Apache is sending Last Modified header, so we'll do it, too
        $modified=filemtime($filename);
        header('Last-Modified: '. date('D, j M Y G:i:s T', $modified));   // something like Thu, 03 Oct 2002 18:01:08 GMT
        readfile($filename);
        AccessLog::addLogEntry($_REQUEST['id'], 'V', $pdo);
    } else {
        echo msg('message_file_does_not_exist');
    }
} elseif ($_GET['submit'] == 'Download') {
    $file_obj = new FileData($_REQUEST['id'], $pdo);

    // Added this check to keep unauthorized users from downloading - Thanks to Chad Bloomquist
    checkUserPermission($_REQUEST['id'], $file_obj->READ_RIGHT, $file_obj);

    $realname = $file_obj->getName();

    $user_obj = new User($_SESSION['uid'], $pdo);

    // isPublishable() returns the raw publishable column (-1 rejected, 0 pending, 1 approved).
    // PHP treats -1 as truthy, so a plain !isPublishable() only catches pending (0), not
    // rejected (-1) — check both explicitly so rejected documents are gated the same way.
    if ($file_obj->isPublishable() != 1 && !$user_obj->isAdmin() && !$user_obj->isReviewerForFile($_REQUEST['id'])) {
        header('Location: error?ec=31&last_message=' . urlencode('This document has not yet been reviewed and approved and cannot be viewed or downloaded.'));
        exit;
    }

    if ($file_obj->isArchived() && !$user_obj->isAdmin()) {
        header('Location: error?ec=32&last_message=' . urlencode('This document has been archived. Only an Admin or Super-Admin may view archived document content.'));
        exit;
    }

    if ($file_obj->isExpired() && !$user_obj->isAdmin()) {
        header('Location: error?ec=34&last_message=' . urlencode('This document\'s validity period has ended. Only an Admin or Super-Admin may view it and renew its validity date.'));
        exit;
    }

    $high_class = array('Sensitive', 'Highly sensitive');
    if ($file_obj->getStatus() > 0 && in_array($file_obj->getClassification(), $high_class) && $file_obj->getStatus() != $_SESSION['uid']) {
        header('Location: details?id=' . $_REQUEST['id']);
        exit;
    }

    if (isset($revision_id)) {
        $filename = $revision_dir . $_REQUEST['id'] . '_' . $revision_id . ".dat";
    } elseif ($file_obj->isArchived()) {
        $filename = $GLOBALS['CONFIG']['archiveDir'] . $_REQUEST['id'] . ".dat";
    } else {
        $filename = $GLOBALS['CONFIG']['dataDir'] . $_REQUEST['id'] . ".dat";
    }

    if (file_exists($filename)) {
        // Prefix the traceable serial number, same as the View action above.
        $serialPrefix = $file_obj->getSerialNumber() ? $file_obj->getSerialNumber() . '_' : '';
        // send headers to browser to initiate file download
        header('Cache-control: private');
        header('Content-Type: '.$_GET['mimetype']);
        header('Content-Disposition: attachment; filename="' . $serialPrefix . $realname . '"');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        readfile($filename);
        AccessLog::addLogEntry($_REQUEST['id'], 'D', $pdo);
    } else {
        echo msg('message_file_does_not_exist');
    }
} else {
    echo msg('message_nothing_to_do');
}
