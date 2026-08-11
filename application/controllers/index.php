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

// Main login form

session_start();

$pdo = $GLOBALS['pdo'];

/*
 * Test to see if we have the config.php file. If not, must not be installed yet.
*/
if (!file_exists(__DIR__ . '/../configs/config.php') && !file_exists(__DIR__ . '/../configs/docker-configs/config.php')) {
    if (
        !extension_loaded('pdo')
        || !extension_loaded('pdo_mysql')
    ) {
        echo "<p>PHP pdo Extensions not loaded. <a href='./'>try again</a>.</p>";
        exit;
    }
    // A config file doesn't exist

    ?>
    <html>
    <head>
        <link rel="stylesheet" href="css/install.css" type="text/css"/>
    </head>
        <body>
            <h2>Looks like this is a new installation because we did not find a configs/config.php file or we cannot locate the
            database. We need to create a configs/config.php file now:</h2>
            <p><a href="install/setup-config" class="button">Create a Configuration File</a></p>
        </body>
    </html>
    <?php
    exit;
}


if (!isset($_REQUEST['last_message'])) {
    $_REQUEST['last_message'] = '';
}

// Check if system is properly installed before proceeding
if (!isset($GLOBALS['CONFIG']['authen'])) {
    // System not installed yet, redirect to installation
    header('Location: install/setup-config');
    exit;
}

// Call the plugin API (only if function exists)
if (function_exists('callPluginMethod')) {
    callPluginMethod('onBeforeLogin');
}



if (isset($_SESSION['uid'])) {
    // redirect to main page
    if (isset($_REQUEST['redirection'])) {
        redirect_visitor($_REQUEST['redirection']);
    } else {
        redirect_visitor('out');
    }
}

if (isset($_POST['login'])) {
    // Validate CSRF token for login form
    if (isset($GLOBALS['csrf']) && !$GLOBALS['csrf']->validateToken($_POST)) {
        echo "<font color=red>Security token validation failed. Please try again.</font>";
        draw_footer();
        exit;
    } elseif (!is_dir($GLOBALS['CONFIG']['dataDir']) || !is_writable($GLOBALS['CONFIG']['dataDir'])) {
        echo "<font color=red>" . msg('message_datadir_problem') . "</font>";
    }

    $frmuser = $_POST['frmuser'];
    $frmpass = $_POST['frmpass'];

    // Look up by username only, then verify the password in PHP via
    // User::validatePassword() — which checks password_hash()-based storage
    // first, falling back to (and transparently upgrading) any account still
    // on the legacy unsalted-MD5/MySQL-PASSWORD() format. Keeping the actual
    // verification logic in one place (User.class.php) instead of
    // duplicating hash-specific SQL here avoids the two copies drifting out
    // of sync, which is how this login flow and User::validatePassword() had
    // each grown their own slightly different legacy-fallback handling.
    $query = "SELECT id FROM {$GLOBALS['CONFIG']['db_prefix']}user WHERE username = :frmuser";
    $stmt = $pdo->prepare($query);
    $stmt->execute(array(':frmuser' => $frmuser));
    $row = $stmt->fetch();

    $loginOk = false;
    if ($row) {
        $candidate_user = new User((int) $row['id'], $pdo);
        $loginOk = $candidate_user->validatePassword($frmpass);
    }

    // if credentials are correct
    if ($loginOk) {
        // register the user's ID
        $id = (int) $row['id'];

        // Regenerate the session ID on privilege change (anonymous ->
        // authenticated) so a session ID an attacker may have fixed onto
        // this browser before login isn't still valid afterward.
        session_regenerate_id(true);

        // initiate a session
        $_SESSION['uid'] = $id;

        // Run the plugin API (only if function exists)
        if (function_exists('callPluginMethod')) {
            callPluginMethod('onAfterLogin');
        }

        // redirect to main page
        if (isset($_REQUEST['redirection'])) {
            redirect_visitor($_REQUEST['redirection']);
        } else {
            redirect_visitor('out');
        }
    } else {
        // Login Failed
        // redirect to error page

        // Call the plugin API (only if function exists)
        if (function_exists('callPluginMethod')) {
            callPluginMethod('onFailedLogin');
        }

        header('Location: error?ec=0');
    }
} elseif (!isset($_POST['login']) && $GLOBALS['CONFIG']['authen'] == 'mysql') {
    $redirection = (isset($_REQUEST['redirection']) ? $_REQUEST['redirection'] : '');
    $GLOBALS['smarty']->assign('redirection', htmlentities($redirection, ENT_QUOTES));
    display_smarty_template('login.tpl');
} else {
    echo 'Check your config';
}
draw_footer();
