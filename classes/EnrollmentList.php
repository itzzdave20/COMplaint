<?php
/**
 * EnrollmentList — the list of enrolled students uploaded by OSWD.
 *
 * Purpose: only real, currently enrolled students may register. OSWD
 * uploads the official list (CSV, Excel .xlsx or a plain text file), and
 * registration (register.php → User::register) checks the Student ID
 * against it before an account can be created.
 *
 * Student IDs are compared in a normalised form (trimmed, inner spaces
 * removed, upper-case), so " 2023-01721 " and "2023-01721" match.
 */
class EnrollmentList {
    const MAX_ROWS = 50000;          // safety limit per upload
    const MAX_FILE_BYTES = 10485760; // 10 MB

    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /* ==============================================================
     * CHECKS USED BY REGISTRATION
     * ============================================================== */

    /** Normalise an ID for storage and comparison. */
    public static function normalizeId($id) {
        return strtoupper(preg_replace('/\s+/', '', trim((string)$id)));
    }

    /** IDs are letters, digits and dashes, 3–50 characters (e.g. 2023-01721). */
    public static function isValidIdFormat($id) {
        return (bool)preg_match('/^[A-Z0-9][A-Z0-9-]{2,49}$/', $id);
    }

    /** The enrolment record for this ID, or null if the ID is not on the list. */
    public function find($studentId) {
        $stmt = $this->db->prepare('SELECT * FROM enrolled_students WHERE student_id = :id');
        $stmt->execute([':id' => self::normalizeId($studentId)]);
        return $stmt->fetch() ?: null;
    }

    /** True if an account already uses this Student ID. */
    public function isRegistered($studentId) {
        $stmt = $this->db->prepare('SELECT 1 FROM users WHERE student_id = :id LIMIT 1');
        $stmt->execute([':id' => self::normalizeId($studentId)]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Full registration check. Returns ['ok' => true, 'record' => [...]]
     * or ['ok' => false, 'message' => '...'].
     */
    public function verifyForRegistration($studentId) {
        $id = self::normalizeId($studentId);
        if ($id === '') {
            return ['ok' => false, 'message' => 'Please enter your Student ID.'];
        }
        if (!self::isValidIdFormat($id)) {
            return ['ok' => false, 'message' => 'That does not look like a Student ID. Use letters, numbers and dashes only (e.g. 2023-01721).'];
        }
        $record = $this->find($id);
        if (!$record) {
            return ['ok' => false, 'message' => 'Student ID ' . $id . ' was not found in the list of enrolled students. '
                . 'Check that it is typed correctly, or contact OSWD if you are enrolled.'];
        }
        if ($this->isRegistered($id)) {
            return ['ok' => false, 'message' => 'An account already exists for Student ID ' . $id . '. '
                . 'Sign in instead, or contact Student Services if you cannot access it.'];
        }
        return ['ok' => true, 'record' => $record];
    }

    /* ==============================================================
     * OSWD MANAGEMENT
     * ============================================================== */

    public function stats() {
        $row = $this->db->query(
            "SELECT COUNT(*) AS total,
                    SUM(EXISTS(SELECT 1 FROM users u WHERE u.student_id = e.student_id)) AS registered
             FROM enrolled_students e"
        )->fetch();
        $total = (int)$row['total'];
        $registered = (int)$row['registered'];
        return ['total' => $total, 'registered' => $registered, 'not_registered' => $total - $registered];
    }

    /** Search/paginate the list; each row says whether that student has an account. */
    public function search(array $f, $page = 1, $perPage = 25) {
        $where = [];
        $params = [];
        if (!empty($f['q'])) {
            $where[] = '(e.student_id LIKE :q1 OR e.full_name LIKE :q2)';
            $params[':q1'] = '%' . $f['q'] . '%';
            $params[':q2'] = '%' . $f['q'] . '%';
        }
        if (in_array($f['department'] ?? '', departments(), true)) {
            $where[] = 'e.department = :department';
            $params[':department'] = $f['department'];
        }
        if (($f['registered'] ?? '') === 'yes') {
            $where[] = 'u.user_id IS NOT NULL';
        } elseif (($f['registered'] ?? '') === 'no') {
            $where[] = 'u.user_id IS NULL';
        }
        $join = 'FROM enrolled_students e LEFT JOIN users u ON u.student_id = e.student_id';
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $count = $this->db->prepare("SELECT COUNT(*) {$join} {$whereSql}");
        $count->execute($params);
        $total = (int)$count->fetchColumn();

        $offset = (max(1, (int)$page) - 1) * $perPage;
        $stmt = $this->db->prepare(
            "SELECT e.*, u.username AS registered_username {$join} {$whereSql}
             ORDER BY e.student_id LIMIT " . (int)$perPage . ' OFFSET ' . (int)$offset
        );
        $stmt->execute($params);
        return ['rows' => $stmt->fetchAll(), 'total' => $total];
    }

    /** Add or update one student by hand. */
    public function addOne($studentId, $fullName, $department, $uploadedBy) {
        $id = self::normalizeId($studentId);
        if (!self::isValidIdFormat($id)) {
            return ['success' => false, 'message' => 'Enter a valid Student ID (letters, numbers and dashes).'];
        }
        $this->upsert($id, trim((string)$fullName), $this->matchDepartment($department), '', $uploadedBy);
        (new AuditLog())->record('enrollment_add', 'enrollment', null, $id);
        return ['success' => true, 'message' => 'Student ID ' . $id . ' is on the enrolled list.'];
    }

    public function remove($enrolledId) {
        $stmt = $this->db->prepare('SELECT student_id FROM enrolled_students WHERE enrolled_id = :id');
        $stmt->execute([':id' => (int)$enrolledId]);
        $id = $stmt->fetchColumn();
        if ($id === false) {
            return ['success' => false, 'message' => 'Entry not found.'];
        }
        // Existing accounts are not affected: removal only stops NEW registrations.
        $this->db->prepare('DELETE FROM enrolled_students WHERE enrolled_id = :id')->execute([':id' => (int)$enrolledId]);
        (new AuditLog())->record('enrollment_remove', 'enrollment', null, $id);
        return ['success' => true, 'message' => 'Student ID ' . $id . ' was removed from the enrolled list.'];
    }

    /**
     * Import an uploaded file.
     * $mode = 'add' keeps the current list and adds/updates entries;
     *         'replace' deletes the current list first (e.g. new semester).
     * Runs in one transaction: if anything fails, the old list is kept.
     */
    public function import($tmpPath, $originalName, $mode, $uploadedBy) {
        $ext = strtolower(pathinfo((string)$originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'txt', 'xlsx'], true)) {
            return ['success' => false, 'message' => 'Upload a .csv, .xlsx or .txt file.'];
        }
        if (filesize($tmpPath) > self::MAX_FILE_BYTES) {
            return ['success' => false, 'message' => 'The file is larger than 10 MB.'];
        }

        try {
            $rows = $ext === 'xlsx' ? self::readXlsx($tmpPath) : self::readDelimited($tmpPath);
        } catch (RuntimeException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
        if (count($rows) > self::MAX_ROWS) {
            return ['success' => false, 'message' => 'The file has more than ' . self::MAX_ROWS . ' rows.'];
        }

        [$records, $invalid, $duplicates] = $this->extractRecords($rows);
        if (!$records) {
            return ['success' => false, 'message' => 'No valid Student IDs were found in the file. '
                . 'Put the IDs in the first column, or use a header named "Student ID".'];
        }

        $added = 0;
        $updated = 0;
        $this->db->beginTransaction();
        try {
            if ($mode === 'replace') {
                $this->db->exec('DELETE FROM enrolled_students');
            }
            foreach ($records as $r) {
                $affected = $this->upsert($r['student_id'], $r['full_name'], $r['department'], $r['program'], $uploadedBy);
                if ($affected === 1) {
                    $added++;
                } elseif ($affected === 2) {
                    $updated++;
                }
            }
            $this->db->commit();
        } catch (PDOException $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => 'The list could not be saved. Nothing was changed.'];
        }

        (new AuditLog())->record('enrollment_upload', 'enrollment', null, (string)$originalName, null, [
            'mode' => $mode, 'ids_in_file' => count($records), 'added' => $added,
            'updated' => $updated, 'invalid' => count($invalid),
        ]);

        return [
            'success' => true,
            'mode' => $mode,
            'valid' => count($records),
            'added' => $added,
            'updated' => $updated,
            'unchanged' => count($records) - $added - $updated,
            'duplicates' => $duplicates,
            'invalid' => $invalid,
        ];
    }

    /**
     * Insert, or update the optional details of an existing ID. Blank
     * details in the file never erase details already stored.
     * Returns MySQL's affected rows: 1 = inserted, 2 = updated, 0 = unchanged.
     */
    private function upsert($id, $fullName, $department, $program, $uploadedBy) {
        $stmt = $this->db->prepare(
            'INSERT INTO enrolled_students (student_id, full_name, department, program, uploaded_by)
             VALUES (:id, :name, :dept, :program, :by)
             ON DUPLICATE KEY UPDATE
                full_name = COALESCE(VALUES(full_name), full_name),
                department = COALESCE(VALUES(department), department),
                program = COALESCE(VALUES(program), program)'
        );
        $stmt->execute([
            ':id' => $id,
            ':name' => $fullName !== '' ? $fullName : null,
            ':dept' => $department,
            ':program' => $program !== '' ? $program : null,
            ':by' => $uploadedBy,
        ]);
        return $stmt->rowCount();
    }

    /** "dcs", "Dcs " → "DCS"; anything not in departments() → null. */
    private function matchDepartment($value) {
        $value = strtoupper(trim((string)$value));
        return in_array($value, departments(), true) ? $value : null;
    }

    /* ==============================================================
     * READING THE UPLOADED FILE
     * ============================================================== */

    /**
     * Turn raw rows into records. If the first row is a header, columns are
     * found by name ("Student ID", "Name", "Department", "Program"/"Course");
     * otherwise the first column is the ID and the second (if any) the name.
     */
    private function extractRecords(array $rows) {
        $cols = ['id' => 0, 'name' => 1, 'department' => null, 'program' => null];
        if ($rows) {
            $header = array_map(fn($h) => strtolower(trim((string)$h)), $rows[0]);
            $idCol = null;
            foreach ($header as $i => $h) {
                if (preg_match('/(student\s*_?\s*(id|no|number))|(^id\s*(no|number)?\.?$)|(^id_?number$)/', $h)) {
                    $idCol = $i;
                    break;
                }
            }
            if ($idCol !== null) {
                array_shift($rows);   // header row is not data
                $cols = ['id' => $idCol, 'name' => null, 'department' => null, 'program' => null];
                foreach ($header as $i => $h) {
                    if ($cols['name'] === null && preg_match('/name/', $h)) {
                        $cols['name'] = $i;
                    } elseif ($cols['department'] === null && preg_match('/dep|dept|college/', $h)) {
                        $cols['department'] = $i;
                    } elseif ($cols['program'] === null && preg_match('/program|course/', $h)) {
                        $cols['program'] = $i;
                    }
                }
            }
        }

        $records = [];
        $invalid = [];
        $duplicates = 0;
        foreach ($rows as $n => $row) {
            $raw = trim((string)($row[$cols['id']] ?? ''));
            if ($raw === '' && count(array_filter($row, fn($v) => trim((string)$v) !== '')) === 0) {
                continue;   // blank line
            }
            $id = self::normalizeId($raw);
            if (!self::isValidIdFormat($id)) {
                $invalid[] = $raw === '' ? '(empty ID)' : $raw;
                continue;
            }
            if (isset($records[$id])) {
                $duplicates++;
                continue;
            }
            $records[$id] = [
                'student_id' => $id,
                'full_name' => $cols['name'] !== null ? trim((string)($row[$cols['name']] ?? '')) : '',
                'department' => $cols['department'] !== null ? $this->matchDepartment($row[$cols['department']] ?? '') : null,
                'program' => $cols['program'] !== null ? trim((string)($row[$cols['program']] ?? '')) : '',
            ];
        }
        return [array_values($records), $invalid, $duplicates];
    }

    /** CSV or TXT. The separator (comma, semicolon or tab) is detected. */
    private static function readDelimited($path) {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException('The file could not be read.');
        }
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);   // Excel's UTF-8 marker
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }
        $firstLine = strtok($content, "\r\n") ?: '';
        $delimiter = ',';
        foreach ([';', "\t"] as $candidate) {
            if (substr_count($firstLine, $candidate) > substr_count($firstLine, $delimiter)) {
                $delimiter = $candidate;
            }
        }

        $rows = [];
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);
        return $rows;
    }

    /**
     * Excel .xlsx without any library. An .xlsx is a ZIP of XML files; we
     * read the ZIP's central directory, unpack the first worksheet and the
     * shared-strings table with gzinflate(), and rebuild the rows.
     */
    private static function readXlsx($path) {
        $zip = file_get_contents($path);
        $eocd = $zip === false ? false : strrpos($zip, "PK\x05\x06");
        if ($eocd === false) {
            throw new RuntimeException('That .xlsx file could not be opened. Save it again in Excel, or upload a CSV.');
        }

        // Central directory: name → [method, compressed size, local header offset].
        // End record: total entries (2 bytes) at +10, directory size at +12, offset at +16.
        $end = unpack('ventries/Vsize/Voffset', substr($zip, $eocd + 10, 10));
        $entries = [];
        $pos = $end['offset'];
        for ($i = 0; $i < $end['entries'] && substr($zip, $pos, 4) === "PK\x01\x02"; $i++) {
            $h = unpack('vmethod/vtime/vdate/Vcrc/Vcsize/Vsize/vnlen/vxlen/vclen/vdisk/vint/Vext/Voffset', substr($zip, $pos + 10, 36));
            $name = substr($zip, $pos + 46, $h['nlen']);
            $entries[$name] = $h;
            $pos += 46 + $h['nlen'] + $h['xlen'] + $h['clen'];
        }
        $read = function ($name) use ($zip, $entries) {
            if (!isset($entries[$name])) {
                return null;
            }
            $h = $entries[$name];
            $local = unpack('vnlen/vxlen', substr($zip, $h['offset'] + 26, 4));
            $data = substr($zip, $h['offset'] + 30 + $local['nlen'] + $local['xlen'], $h['csize']);
            return $h['method'] === 8 ? @gzinflate($data) : $data;   // 8 = deflate, 0 = stored
        };

        // First worksheet (sheet1.xml, or whichever worksheet comes first).
        $sheetName = isset($entries['xl/worksheets/sheet1.xml']) ? 'xl/worksheets/sheet1.xml' : null;
        foreach (array_keys($entries) as $name) {
            if ($sheetName === null && preg_match('#^xl/worksheets/[^/]+\.xml$#', $name)) {
                $sheetName = $name;
            }
        }
        $sheetXml = $sheetName ? $read($sheetName) : null;
        if (!$sheetXml) {
            throw new RuntimeException('No worksheet was found in that .xlsx file.');
        }

        // Text cells point into the shared-strings table by number.
        $shared = [];
        $sharedXml = $read('xl/sharedStrings.xml');
        if ($sharedXml) {
            $sst = simplexml_load_string($sharedXml);
            foreach ($sst->si as $si) {
                if (isset($si->t)) {
                    $shared[] = (string)$si->t;
                } else {
                    $text = '';
                    foreach ($si->r as $run) {
                        $text .= (string)$run->t;   // formatted ("rich") text is split into runs
                    }
                    $shared[] = $text;
                }
            }
        }

        $sheet = simplexml_load_string($sheetXml);
        if ($sheet === false) {
            throw new RuntimeException('That .xlsx file could not be read.');
        }
        $rows = [];
        foreach ($sheet->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                // "C5" → column index 2
                $letters = preg_replace('/\d+/', '', (string)$c['r']);
                $col = 0;
                foreach (str_split($letters) as $ch) {
                    $col = $col * 26 + (ord($ch) - 64);
                }
                $type = (string)$c['t'];
                if ($type === 's') {
                    $value = $shared[(int)$c->v] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = (string)$c->is->t;
                } else {
                    $value = (string)$c->v;
                    // Numbers stored by Excel: 20230123 or 20230123.0 → "20230123"
                    if (is_numeric($value) && floor((float)$value) == (float)$value) {
                        $value = sprintf('%.0f', (float)$value);
                    }
                }
                $cells[$col - 1] = $value;
            }
            if ($cells) {
                $filled = array_fill(0, max(array_keys($cells)) + 1, '');
                $rows[] = array_replace($filled, $cells);
            }
        }
        return $rows;
    }
}
