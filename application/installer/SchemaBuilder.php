<?php

class SchemaBuilder
{
    public function getVersion(): string
    {
        return '1.4.1';
    }

    public function getCreateTableStatements(string $prefix): array
    {
        return [
            // action enum and the doc_version/doc_revision/reason columns cover
            // every code AccessLog::addLogEntry() is actually called with
            // across the codebase (grepped exhaustively 2026-09-23) --
            // upstream's original 11-code set (A,B,C,V,D,M,X,I,O,Y,R) plus
            // this fork's own additions for Access Request (Q/G/N),
            // reclassification/validity (L/E), and Staged Approval (S/U/P).
            "CREATE TABLE `{$prefix}access_log` (
                `file_id` int(11) NOT NULL,
                `user_id` int(11) NOT NULL,
                `timestamp` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
                `action` enum('A','B','C','D','E','G','I','L','M','N','O','P','Q','R','S','U','V','X','Y') NOT NULL,
                `doc_version` tinyint(4) unsigned default NULL,
                `doc_revision` tinyint(4) unsigned default NULL,
                `reason` varchar(255) default NULL
            ) ENGINE = MYISAM",

            "CREATE TABLE `{$prefix}admin` (
                id int(11) unsigned default NULL,
                admin tinyint(4) default NULL
            ) ENGINE = MYISAM",

            "CREATE TABLE `{$prefix}category` (
                id int(11) unsigned NOT NULL auto_increment,
                name varchar(255) NOT NULL default '',
                PRIMARY KEY  (id)
            ) ENGINE = MYISAM",

            // doc_version through serial_number: this fork's own columns
            // (classification, Staged Approval assignment, document
            // versioning, serial numbers) -- reverse-engineered from
            // FileData.class.php's loadData()/getters/setters 2026-09-23,
            // since none of it existed in this schema despite being fully
            // wired up in application code. Types/defaults/nullability match
            // exactly what FileData.class.php reads and writes.
            "CREATE TABLE `{$prefix}data` (
                id int(11) unsigned NOT NULL auto_increment,
                category int(11) unsigned NOT NULL default '0',
                owner int(11) unsigned default NULL,
                realname varchar(255) NOT NULL default '',
                created datetime NOT NULL,
                description varchar(255) default NULL,
                comment varchar(255) default '',
                status smallint(6) default NULL,
                department smallint(6) unsigned default NULL,
                default_rights tinyint(4) default NULL,
                publishable tinyint(4) default NULL,
                reviewer int(11) unsigned default NULL,
                reviewer_comments varchar(255) default NULL,
                doc_version smallint(5) unsigned NOT NULL default '1',
                doc_revision tinyint(3) unsigned NOT NULL default '0',
                doc_classification varchar(20) NOT NULL default 'Internal',
                valid_until date default NULL,
                workflow_template_id int(11) unsigned default NULL,
                workflow_stage_number tinyint(4) unsigned default NULL,
                serial_number varchar(50) default NULL,
                PRIMARY KEY  (id),
                KEY data_idx (id,owner),
                KEY id (id),
                KEY id_2 (id),
                KEY publishable (publishable),
                KEY description (description(200)),
                KEY workflow_template_id (workflow_template_id),
                KEY doc_classification (doc_classification),
                KEY serial_number (serial_number)
            ) ENGINE = MYISAM",

            "CREATE TABLE `{$prefix}department` (
                id int(11) unsigned NOT NULL auto_increment,
                name varchar(255) NOT NULL default '',
                PRIMARY KEY  (id)
            ) ENGINE = MYISAM",

            "CREATE TABLE `{$prefix}dept_perms` (
                fid int(11) unsigned default NULL,
                dept_id int(11) unsigned default NULL,
                rights tinyint(4) NOT NULL default '0',
                KEY rights (rights),
                KEY dept_id (dept_id),
                KEY fid (fid)
            ) ENGINE = MYISAM",

            "CREATE TABLE `{$prefix}dept_reviewer` (
                dept_id int(11) unsigned default NULL,
                user_id int(11) unsigned default NULL
            ) ENGINE = MYISAM",

            // Department Head assignment (elevated per-department authority:
            // classification-setting, validity-date-setting, Access Request
            // resolution) -- same shape as dept_reviewer above. Reverse-
            // engineered from application/controllers/user.php and
            // User.class.php's isDeptHead()/isDeptHeadOfAny(); this table was
            // also missing from the schema entirely.
            "CREATE TABLE `{$prefix}dept_head` (
                dept_id int(11) unsigned default NULL,
                user_id int(11) unsigned default NULL,
                KEY dept_id (dept_id),
                KEY user_id (user_id)
            ) ENGINE = MYISAM",

            "CREATE TABLE `{$prefix}log` (
                id int(11) unsigned NOT NULL default '0',
                modified_on datetime NOT NULL,
                modified_by varchar(25) default NULL,
                note text,
                revision varchar(255) default NULL,
                KEY id (id),
                KEY modified_on (modified_on)
            ) ENGINE = MYISAM",

            "CREATE TABLE `{$prefix}rights` (
                RightId tinyint(4) default NULL,
                Description varchar(255) default NULL
            ) ENGINE = MYISAM",

            "CREATE TABLE `{$prefix}user` (
                id int(11) unsigned NOT NULL auto_increment,
                username varchar(25) NOT NULL default '',
                password varchar(255) NOT NULL default '',
                department int(11) unsigned default NULL,
                phone varchar(20) default NULL,
                Email varchar(50) default NULL,
                last_name varchar(255) default NULL,
                first_name varchar(255) default NULL,
                pw_reset_code char(32) default NULL,
                can_add tinyint(1) NULL DEFAULT 1,
                can_checkin tinyint(1) NULL DEFAULT 1,
                PRIMARY KEY  (id)
            ) ENGINE = MYISAM",

            "CREATE TABLE `{$prefix}user_perms` (
                fid int(11) unsigned default NULL,
                uid int(11) unsigned NOT NULL default '0',
                rights tinyint(4) NOT NULL default '0',
                KEY user_perms_idx (fid,uid,rights),
                KEY fid (fid),
                KEY uid (uid),
                KEY rights (rights)
            ) ENGINE = MYISAM",

            "CREATE TABLE `{$prefix}udf` (
                id  int auto_increment unique,
                table_name varchar(50),
                display_name varchar(16),
                field_type int
            ) ENGINE = MYISAM",

            "CREATE TABLE `{$prefix}odmsys` (
                id  int(11) auto_increment unique,
                sys_name  varchar(16),
                sys_value    varchar(255)
            ) ENGINE = MYISAM",

            "CREATE TABLE `{$prefix}settings` (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL,
                value VARCHAR(255) NOT NULL,
                description VARCHAR(255) NOT NULL,
                validation VARCHAR(255) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE (name(200))
            ) ENGINE = MYISAM",

            "CREATE TABLE `{$prefix}filetypes` (
                id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                type VARCHAR(255) NOT NULL,
                active TINYINT(4) NOT NULL,
                PRIMARY KEY (id)
            ) ENGINE = MYISAM",

            // Groups: reusable named permission groups layered on top of the
            // existing individual/department permission model. Table names
            // come from databaseData::$TABLE_GROUP/$TABLE_GROUP_MEMBER/
            // $TABLE_GROUP_PERMS; group_perms mirrors dept_perms's shape with
            // group_id substituted for dept_id (Group_Perms.class.php).
            // Reverse-engineered 2026-09-23 -- also missing entirely.
            "CREATE TABLE `{$prefix}group` (
                id int(11) unsigned NOT NULL auto_increment,
                name varchar(255) NOT NULL default '',
                description varchar(255) default NULL,
                PRIMARY KEY (id)
            ) ENGINE = MYISAM",

            "CREATE TABLE `{$prefix}group_member` (
                group_id int(11) unsigned default NULL,
                user_id int(11) unsigned default NULL,
                KEY group_id (group_id),
                KEY user_id (user_id)
            ) ENGINE = MYISAM",

            "CREATE TABLE `{$prefix}group_perms` (
                fid int(11) unsigned default NULL,
                group_id int(11) unsigned default NULL,
                rights tinyint(4) NOT NULL default '0',
                KEY rights (rights),
                KEY group_id (group_id),
                KEY fid (fid)
            ) ENGINE = MYISAM",

            // Staged Approval workflow: named, reusable multi-stage templates
            // with per-stage designated approvers. Reverse-engineered from
            // application/controllers/workflow.php and
            // WorkflowTemplate.class.php 2026-09-23 -- also missing entirely.
            "CREATE TABLE `{$prefix}workflow_template` (
                id int(11) unsigned NOT NULL auto_increment,
                name varchar(255) NOT NULL default '',
                description varchar(255) default NULL,
                PRIMARY KEY (id)
            ) ENGINE = MYISAM",

            "CREATE TABLE `{$prefix}workflow_stage` (
                id int(11) unsigned NOT NULL auto_increment,
                template_id int(11) unsigned default NULL,
                stage_number tinyint(4) unsigned NOT NULL default '0',
                name varchar(255) NOT NULL default '',
                PRIMARY KEY (id),
                KEY template_id (template_id),
                KEY template_stage_idx (template_id, stage_number)
            ) ENGINE = MYISAM",

            "CREATE TABLE `{$prefix}workflow_stage_approver` (
                stage_id int(11) unsigned default NULL,
                user_id int(11) unsigned default NULL,
                KEY stage_id (stage_id),
                KEY user_id (user_id)
            ) ENGINE = MYISAM",

            // Access Request workflow: a user lacking sufficient access can
            // request a specific rights level; a Department Head or Admin
            // resolves it. Reverse-engineered from AccessRequest.class.php
            // 2026-09-23 -- also missing entirely. status is a string enum
            // (literal 'pending'/'granted'/'denied' values throughout the
            // class, never an int), matching this codebase's existing use of
            // enum for access_log.action.
            "CREATE TABLE `{$prefix}access_request` (
                id int(11) unsigned NOT NULL auto_increment,
                file_id int(11) unsigned NOT NULL,
                requesting_user_id int(11) unsigned NOT NULL,
                requested_level tinyint(4) NOT NULL default '0',
                classification_at_request varchar(20) default NULL,
                reason varchar(255) NOT NULL default '',
                status enum('pending','granted','denied') NOT NULL default 'pending',
                requested_on datetime NOT NULL,
                resolved_by int(11) unsigned default NULL,
                resolved_on datetime default NULL,
                resolution_note varchar(255) default NULL,
                PRIMARY KEY (id),
                KEY file_id (file_id),
                KEY requesting_user_id (requesting_user_id),
                KEY status (status),
                KEY requested_on (requested_on)
            ) ENGINE = MYISAM",
        ];
    }

    public function getDefaultDataStatements(string $prefix, array $options = []): array
    {
        // Seed the admin account with a real password_hash() (bcrypt) value
        // from the start, matching how validatePassword() actually checks
        // credentials post-2026-08-11 -- seeding MD5 here meant every fresh
        // install's first login silently fell through to validatePassword()'s
        // legacy-MD5-upgrade path, which then crashed writing a 60-char
        // bcrypt hash into what was a varchar(50) column. Both the seed
        // format and the column width (see getSchemaStatements() above) are
        // fixed together. bcrypt's own alphabet never includes a single
        // quote, so interpolating the *hash* (not the raw password) into
        // this raw SQL string is safe.
        $adminPasswordHash = password_hash($options['admin_password'] ?? 'admin', PASSWORD_DEFAULT);
        $dataDir = $options['datadir'] ?? '/var/www/document_repository/';

        return [
            "INSERT INTO `{$prefix}admin` VALUES (1,1)",
            "INSERT INTO `{$prefix}category` VALUES (NULL,'SOP')",
            "INSERT INTO `{$prefix}category` VALUES (NULL,'Training Manual')",
            "INSERT INTO `{$prefix}category` VALUES (NULL,'Letter')",
            "INSERT INTO `{$prefix}category` VALUES (NULL,'Presentation')",
            "INSERT INTO `{$prefix}department` VALUES (NULL,'Information Systems')",
            "INSERT INTO `{$prefix}dept_reviewer` VALUES (1,1)",
            "INSERT INTO `{$prefix}rights` VALUES (0,'none')",
            "INSERT INTO `{$prefix}rights` VALUES (1,'view')",
            "INSERT INTO `{$prefix}rights` VALUES (-1,'forbidden')",
            "INSERT INTO `{$prefix}rights` VALUES (2,'read')",
            "INSERT INTO `{$prefix}rights` VALUES (3,'write')",
            "INSERT INTO `{$prefix}rights` VALUES (4,'admin')",
            "INSERT INTO `{$prefix}user` VALUES (NULL,'admin','{$adminPasswordHash}','1','5555551212','admin@example.com','User','Admin','',1,1)",
            "INSERT INTO `{$prefix}odmsys` VALUES (NULL,'version','{$this->getVersion()}')",
            "INSERT INTO `{$prefix}settings` VALUES(NULL, 'debug', 'False', '(True/False) - Default=False - Debug the installation (not working)', 'bool')",
            "INSERT INTO `{$prefix}settings` VALUES(NULL, 'demo', 'False', '(True/False) This setting is for a demo installation, where random people will be all loggging in as the same username/password like \"demo/demo\". This will keep users from removing files, users, etc.', 'bool')",
            "INSERT INTO `{$prefix}settings` VALUES(NULL, 'authen', 'mysql', '(Default = mysql) Currently only MySQL authentication is supported', '')",
            "INSERT INTO `{$prefix}settings` VALUES(NULL, 'title', 'Document Repository', 'This is the browser window title', 'maxsize=255')",
            "INSERT INTO `{$prefix}settings` VALUES(NULL, 'site_mail', 'root@localhost', 'The email address of the administrator of this site', 'email|maxsize=255|req')",
            "INSERT INTO `{$prefix}settings` VALUES(NULL, 'root_id', '1', 'This variable sets the root user id.  The root user will be able to access all files and have authority for everything.', 'num|req')",
            "INSERT INTO `{$prefix}settings` VALUES(NULL, 'dataDir', '{$dataDir}', 'location of file repository. This should ideally be outside the Web server root. Make sure the server has permissions to read/write files to this folder!. (Examples: Linux - /var/www/document_repository/ : Windows - c:/document_repository/', 'maxsize=255')",
            "INSERT INTO `{$prefix}settings` VALUES(NULL, 'max_filesize', '5000000', 'Set the maximum file upload size', 'num|maxsize=255')",
            "INSERT INTO `{$prefix}settings` VALUES(NULL, 'revision_expiration', '90', 'This var sets the amount of days until each file needs to be revised,  assuming that there are 30 days in a month for all months.', 'num|maxsize=255')",
            "INSERT INTO `{$prefix}settings` VALUES(NULL, 'file_expired_action', '1', 'Choose an action option when a file is found to be expired The first two options also result in sending email to reviewer  (1) Remove from file list until renewed (2) Show in file list but non-checkoutable (3) Send email to reviewer only (4) Do Nothing', 'num')",
            "INSERT INTO `{$prefix}settings` VALUES(NULL, 'authorization', 'True', 'True or False. If set True, every document must be reviewed by an admin before it can go public. To disable set to False. If False, all newly added/checked-in documents will immediately be listed', 'bool')",
            "INSERT INTO `{$prefix}settings` VALUES(NULL, 'allow_signup', 'False', 'Should we display the sign-up link?', 'bool')",
            "INSERT INTO `{$prefix}settings` VALUES(NULL, 'allow_password_reset', 'False', 'Should we allow users to reset their forgotten password?', 'bool')",
            "INSERT INTO `{$prefix}settings` VALUES(NULL, 'try_nis', 'False', 'Attempt NIS password lookups from YP server?', 'bool')",
            "INSERT INTO `{$prefix}settings` VALUES(NULL, 'theme', 'tweeter', 'Which theme to use?', '')",
            "INSERT INTO `{$prefix}settings` VALUES(NULL, 'language', 'english', 'Set the default language (english, spanish, turkish, etc.). Local users may override this setting. Check include/language folder for languages available', 'alpha|req')",
            "INSERT INTO `{$prefix}settings` VALUES(NULL, 'max_query', '500', 'Set this to the maximum number of rows you want to be returned in a file listing. If your file list is slow decrease this value.', 'num')",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'image/gif', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'image/jpeg', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'image/pjpeg', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'image/png', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'image/webp', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'image/bmp', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'image/tiff', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'image/tif', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'image/svg+xml', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'image/x-dwg', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'image/x-dfx', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'drawing/x-dwf', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'application/pdf', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'application/x-pdf', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'application/msword', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'application/vnd.ms-excel', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'application/vnd.ms-powerpoint', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'application/vnd.ms-access', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'application/rtf', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'text/plain', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'text/html', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'text/csv', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'text/xml', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'application/json', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'application/vnd.oasis.opendocument.text', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'application/vnd.oasis.opendocument.text-template', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'application/vnd.oasis.opendocument.text-master', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'application/vnd.oasis.opendocument.text-web', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'application/vnd.oasis.opendocument.spreadsheet', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'application/vnd.oasis.opendocument.spreadsheet-template', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'application/vnd.oasis.opendocument.presentation', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'application/vnd.oasis.opendocument.presentation-template', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'application/vnd.oasis.opendocument.graphics', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'application/vnd.oasis.opendocument.graphics-template', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'application/vnd.oasis.opendocument.chart', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'application/vnd.oasis.opendocument.chart-template', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'application/vnd.oasis.opendocument.formula', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'application/vnd.oasis.opendocument.formula-template', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'application/vnd.oasis.opendocument.image', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'application/vnd.oasis.opendocument.image-template', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'application/zip', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'application/x-zip-compressed', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'application/x-rar-compressed', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'application/x-7z-compressed', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'application/x-tar', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'application/gzip', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'application/x-bzip2', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'application/octet-stream', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'audio/mpeg', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'audio/ogg', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'audio/x-wav', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'audio/x-flac', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'video/mp4', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'video/mpeg', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'video/x-msvideo', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'video/x-ms-wmv', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'video/quicktime', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'video/webm', 1)",
            "INSERT INTO `{$prefix}filetypes` VALUES(NULL, 'video/3gpp', 1)",
        ];
    }

    public function buildFullDump(string $prefix, array $options = []): string
    {
        $lines = [];
        $lines[] = '# MySQL dump of OpenDocMan';
        $lines[] = '# Generated by SchemaBuilder';
        $lines[] = '#';

        foreach ($this->getCreateTableStatements($prefix) as $stmt) {
            $lines[] = '';
            $lines[] = $stmt . ';';
        }

        foreach ($this->getDefaultDataStatements($prefix, $options) as $stmt) {
            $lines[] = $stmt . ';';
        }

        return implode("\n", $lines) . "\n";
    }
}