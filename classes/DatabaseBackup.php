<?php
/**
 * DatabaseBackup — full backup and restore of the system's database.
 *
 * Backups are plain .sql files written by PHP (no mysqldump needed), saved
 * in /backups, which .htaccess blocks from direct web access; downloads go
 * through super_admin_backup.php with a role check.
 *
 * File layout:
 *   -- header lines (format, date, creator, label, body checksum)
 *   SET FOREIGN_KEY_CHECKS=0;
 *   -- @table users           ← one section per table:
 *   DROP TABLE ...; CREATE TABLE ...; INSERT ... (one statement per line)
 *   -- @trigger users_table   ← one section per trigger, named by its table:
 *   DROP TRIGGER ...; CREATE TRIGGER ...;
 *   SET FOREIGN_KEY_CHECKS=1;
 *
 * Every statement ends with ";" + newline, and text values are escaped by
 * PDO::quote (newlines become \n), so splitting on ";\n" is safe.
 *
 * RESTORE RULES
 *   - Only files made by this system are accepted (format line + SHA-256
 *     checksum of the body must match), so a damaged or edited file is
 *     rejected before anything is changed.
 *   - A "pre-restore" safety backup is taken first, so a restore can be undone.
 *   - The audit log is NEVER restored: going back in time must not erase
 *     the record of what happened. Its table and triggers are skipped.
 */
class DatabaseBackup {
    const FORMAT = 'oswd-backup-v1';
    const DIR = __DIR__ . '/../backups/';
    const PROTECTED_TABLE = 'super_admin_audit_log';
    const MAX_UPLOAD_BYTES = 52428800; // 50 MB

    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        if (!is_dir(self::DIR)) {
            mkdir(self::DIR, 0750, true);
        }
        // Block direct browser access to the backup files (Apache).
        if (!is_file(self::DIR . '.htaccess')) {
            file_put_contents(self::DIR . '.htaccess', "Require all denied\n");
        }
    }

    /* ==============================================================
     * LIST / FIND
     * ============================================================== */

    /** Backup files, newest first, with their header details. */
    public function all() {
        $files = [];
        foreach (glob(self::DIR . 'backup_*.sql') ?: [] as $path) {
            $files[] = ['name' => basename($path), 'size' => filesize($path), 'mtime' => filemtime($path)]
                + $this->readHeader($path);
        }
        usort($files, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
        return $files;
    }

    /**
     * Full path for a backup name, or null. Only names in our own pattern
     * are accepted, which also prevents "../" path tricks.
     */
    public function path($name) {
        $name = (string)$name;
        if (!preg_match('/^backup_\d{8}_\d{6}_[a-z-]+_[a-f0-9]{6}\.sql$/', $name)) {
            return null;
        }
        $path = self::DIR . $name;
        return is_file($path) ? $path : null;
    }

    /** The "-- Key: value" lines at the top of a backup file. */
    private function readHeader($path) {
        $header = ['created' => '', 'created_by' => '', 'label' => '', 'tables' => ''];
        $handle = fopen($path, 'r');
        for ($i = 0; $i < 12 && ($line = fgets($handle)) !== false; $i++) {
            if (preg_match('/^-- (Created|Created-By|Label|Tables): (.*)$/', rtrim($line), $m)) {
                $header[strtolower(str_replace('-', '_', $m[1]))] = $m[2];
            }
        }
        fclose($handle);
        return $header;
    }

    /* ==============================================================
     * CREATE
     * ============================================================== */

    /** Write a new backup. $label: manual | pre-restore | uploaded. Returns the file name. */
    public function create($label = 'manual', $actor = null) {
        @set_time_limit(300);
        $tables = $this->db->query('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'')->fetchAll(PDO::FETCH_COLUMN);

        $body = "SET FOREIGN_KEY_CHECKS=0;\n";
        foreach ($tables as $table) {
            $body .= $this->dumpTable($table);
        }
        $body .= $this->dumpTriggers();
        $body .= "SET FOREIGN_KEY_CHECKS=1;\n";

        $actor = $actor ?? ($_SESSION['username'] ?? 'system');
        $header = "-- OSWD Complaint System database backup\n"
            . '-- Format: ' . self::FORMAT . "\n"
            . '-- Created: ' . date('Y-m-d H:i:s') . "\n"
            . '-- Created-By: ' . $actor . "\n"
            . '-- Label: ' . $label . "\n"
            . '-- Tables: ' . count($tables) . "\n"
            . '-- Body-SHA256: ' . hash('sha256', $body) . "\n\n";

        $name = 'backup_' . date('Ymd_His') . '_' . $label . '_' . bin2hex(random_bytes(3)) . '.sql';
        file_put_contents(self::DIR . $name, $header . $body, LOCK_EX);
        return $name;
    }

    private function dumpTable($table) {
        $q = '`' . str_replace('`', '``', $table) . '`';
        $create = $this->db->query("SHOW CREATE TABLE {$q}")->fetch(PDO::FETCH_NUM)[1];

        $sql = "-- @table {$table}\n"
            . "DROP TABLE IF EXISTS {$q};\n"
            . $create . ";\n";

        // Rows in batches of 100 per INSERT, each INSERT on one line.
        $rows = $this->db->query("SELECT * FROM {$q}");
        $batch = [];
        $columns = null;
        while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
            if ($columns === null) {
                $columns = '(`' . implode('`, `', array_keys($row)) . '`)';
            }
            $values = array_map(fn($v) => $v === null ? 'NULL' : $this->db->quote((string)$v), array_values($row));
            $batch[] = '(' . implode(', ', $values) . ')';
            if (count($batch) === 100) {
                $sql .= "INSERT INTO {$q} {$columns} VALUES " . implode(', ', $batch) . ";\n";
                $batch = [];
            }
        }
        if ($batch) {
            $sql .= "INSERT INTO {$q} {$columns} VALUES " . implode(', ', $batch) . ";\n";
        }
        return $sql;
    }

    /**
     * One "-- @trigger <table>" section per trigger, so its DROP and CREATE
     * are always kept or skipped TOGETHER on restore (the DROP statement
     * alone does not name the table).
     */
    private function dumpTriggers() {
        $sql = '';
        foreach ($this->db->query('SHOW TRIGGERS')->fetchAll() as $trigger) {
            $name = $trigger['Trigger'];
            $create = $this->db->query('SHOW CREATE TRIGGER `' . str_replace('`', '``', $name) . '`')->fetch()['SQL Original Statement'];
            // Drop "DEFINER=`root`@`localhost`" so the file restores on any server.
            $create = preg_replace('/\s+DEFINER=`[^`]*`@`[^`]*`/', '', $create);
            $sql .= "-- @trigger {$trigger['Table']}\n"
                . "DROP TRIGGER IF EXISTS `{$name}`;\n" . preg_replace('/\s*\R\s*/', ' ', $create) . ";\n";
        }
        return $sql;
    }

    /* ==============================================================
     * VERIFY / UPLOAD / DELETE
     * ============================================================== */

    /**
     * Check a file is an untouched backup made by this system.
     * Returns ['ok' => true, 'body' => ...] or ['ok' => false, 'message' => ...].
     */
    public function verify($path) {
        $content = file_get_contents($path);
        $split = $content === false ? false : strpos($content, "\n\n");
        if ($split === false
            || !preg_match('/^-- Format: ' . preg_quote(self::FORMAT, '/') . '$/m', substr($content, 0, $split))
            || !preg_match('/^-- Body-SHA256: ([a-f0-9]{64})$/m', substr($content, 0, $split), $m)) {
            return ['ok' => false, 'message' => 'This is not a backup created by this system.'];
        }
        $body = substr($content, $split + 2);
        if (!hash_equals($m[1], hash('sha256', $body))) {
            return ['ok' => false, 'message' => 'The backup file is damaged or was edited (checksum does not match). It was not restored.'];
        }
        return ['ok' => true, 'body' => $body];
    }

    /** Save an uploaded backup into /backups after verifying it. */
    public function storeUpload(array $file) {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            return ['success' => false, 'message' => 'Choose a backup file to upload.'];
        }
        if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'sql' || $file['size'] > self::MAX_UPLOAD_BYTES) {
            return ['success' => false, 'message' => 'Upload a .sql backup file of up to 50 MB.'];
        }
        $check = $this->verify($file['tmp_name']);
        if (!$check['ok']) {
            return ['success' => false, 'message' => $check['message']];
        }
        $name = 'backup_' . date('Ymd_His') . '_uploaded_' . bin2hex(random_bytes(3)) . '.sql';
        move_uploaded_file($file['tmp_name'], self::DIR . $name);
        (new AuditLog())->record('backup_upload', 'backup', null, $name, null, ['original_name' => $file['name']]);
        return ['success' => true, 'message' => 'Backup uploaded and verified. You can restore it from the list.', 'name' => $name];
    }

    public function delete($name) {
        $path = $this->path($name);
        if (!$path) {
            return ['success' => false, 'message' => 'Backup not found.'];
        }
        unlink($path);
        (new AuditLog())->record('backup_delete', 'backup', null, $name);
        return ['success' => true, 'message' => 'Backup ' . $name . ' was deleted.'];
    }

    /* ==============================================================
     * RESTORE
     * ============================================================== */

    public function restore($name) {
        @set_time_limit(300);
        $path = $this->path($name);
        if (!$path) {
            return ['success' => false, 'message' => 'Backup not found.'];
        }
        $check = $this->verify($path);
        if (!$check['ok']) {
            return ['success' => false, 'message' => $check['message']];
        }

        // 1. Safety copy of the CURRENT database, so this can be undone.
        $safety = $this->create('pre-restore');

        // 2. Split the body into sections and statements.
        $restored = [];
        $statements = [];
        $section = null;
        foreach (preg_split('/;\n/', $check['body']) as $chunk) {
            // A chunk may start with "-- @table name" / "-- @trigger name" marker
            // lines; $section is the table that the following statements belong to.
            while (preg_match('/^\s*-- @(table|trigger) (\S+)\s*\n/', $chunk, $m)) {
                $section = $m[2];
                if ($m[1] === 'table') {
                    $restored[$section] = true;
                }
                $chunk = substr($chunk, strlen($m[0]));
            }
            $sql = trim($chunk);
            if ($sql === '') {
                continue;
            }
            // 3. Never touch the audit log: skip its table AND its triggers
            //    (both DROP and CREATE), so they stay exactly as they are.
            if ($section === self::PROTECTED_TABLE) {
                continue;
            }
            $statements[] = $sql;
        }
        unset($restored[self::PROTECTED_TABLE]);

        // 4. Run them. DDL cannot be rolled back in MySQL, which is why the
        //    safety backup above exists.
        try {
            foreach ($statements as $sql) {
                $this->db->exec($sql);
            }
        } catch (PDOException $e) {
            $this->db->exec('SET FOREIGN_KEY_CHECKS=1');
            (new AuditLog())->record('backup_restore', 'backup', null, $name, null,
                ['result' => 'failed', 'error' => $e->getMessage(), 'safety_backup' => $safety]);
            return ['success' => false, 'message' => 'The restore stopped with an error. Restore the safety backup "'
                . $safety . '" to return to the state before this attempt.', 'safety' => $safety];
        }

        // 5. Safety net: make sure the audit log is still read-only.
        $this->ensureAuditProtection();

        (new AuditLog())->record('backup_restore', 'backup', null, $name, null,
            ['result' => 'ok', 'tables' => count($restored), 'safety_backup' => $safety]);
        return ['success' => true, 'tables' => count($restored), 'safety' => $safety];
    }

    /* ==============================================================
     * AUTOMATIC BACKUPS
     *
     * No Windows Task Scheduler or cron is required: config.php calls
     * autoBackupIfDue() after every page request. The check only reads a
     * marker file's timestamp, so it is free when no backup is due.
     * backup_cron.php can also call it on a schedule, so backups still
     * happen on days when nobody uses the system.
     * ============================================================== */

    const AUTO_MARKER = self::DIR . '.last_auto_backup';
    const AUTO_LOCK = self::DIR . '.auto_backup.lock';

    /** When the last automatic backup ran (Unix time), or 0 if never. */
    public static function lastAutoBackupTime() {
        return is_file(self::AUTO_MARKER) ? (int)filemtime(self::AUTO_MARKER) : 0;
    }

    /** Create an automatic backup if one is due. Returns its name, or null. */
    public static function autoBackupIfDue($force = false) {
        if (!Settings::get('auto_backup_enabled') && !$force) {
            return null;
        }
        $interval = Settings::get('auto_backup_interval_hours') * 3600;
        if (!$force && time() - self::lastAutoBackupTime() < $interval) {
            return null;   // not due yet (the usual, cheap case)
        }

        $backup = new self();   // also creates /backups if needed
        // Only one request may run the backup: others skip instead of waiting.
        $lock = fopen(self::AUTO_LOCK, 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            return null;
        }
        try {
            // Re-check inside the lock: another request may have just finished one.
            if (!$force && time() - self::lastAutoBackupTime() < $interval) {
                return null;
            }
            $name = $backup->create('auto', 'system (automatic)');
            touch(self::AUTO_MARKER);
            $removed = $backup->pruneAutoBackups(Settings::get('auto_backup_keep'));

            (new AuditLog())->record('backup_create', 'backup', null, $name, null,
                ['type' => 'automatic', 'old_auto_backups_removed' => $removed],
                ['id' => null, 'username' => 'system']);
            return $name;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** Delete the oldest AUTOMATIC backups beyond $keep. Returns how many were removed. */
    private function pruneAutoBackups($keep) {
        $auto = array_values(array_filter($this->all(), fn($b) => $b['label'] === 'auto'));   // newest first
        $removed = 0;
        foreach (array_slice($auto, max(1, (int)$keep)) as $old) {
            if (@unlink(self::DIR . $old['name'])) {
                $removed++;
            }
        }
        return $removed;
    }

    /** Re-create the audit log's UPDATE/DELETE-blocking triggers if either is missing. */
    private function ensureAuditProtection() {
        $exists = $this->db->prepare('SELECT 1 FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = :n');
        foreach (['trg_audit_log_no_update' => 'BEFORE UPDATE', 'trg_audit_log_no_delete' => 'BEFORE DELETE'] as $name => $timing) {
            $exists->execute([':n' => $name]);
            if (!$exists->fetch()) {
                $this->db->exec("CREATE TRIGGER {$name} {$timing} ON " . self::PROTECTED_TABLE
                    . " FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The audit log is read-only.'");
            }
        }
    }
}
