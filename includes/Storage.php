<?php
/**
 * Minimal JSON-file "table" with file locking and atomic (write-temp + rename)
 * writes, so concurrent admin/editor requests can never corrupt a data file
 * or silently overwrite each other's changes.
 *
 * No database is used per the product spec; this is the whole persistence
 * layer. Every entity has a unique string id and entities reference each
 * other by id, so this could migrate to a real database later without an
 * application rewrite.
 */

declare(strict_types=1);

final class JsonTable
{
    /**
     * Data files are named "<table>.json.php" and start with this line, so
     * requesting the file directly over HTTP always executes it as PHP and
     * gets a bare 403 — never the raw JSON. That holds true on any PHP-
     * capable server, regardless of whether .htaccess/nginx rules are
     * correctly deployed (belt AND suspenders: data/.htaccess and
     * data/index.php provide a second layer, but this one doesn't depend on
     * web-server configuration at all).
     */
    private const GUARD_HEADER = "<?php http_response_code(403); exit; ?>\n";

    private string $file;
    private string $lockFile;

    public function __construct(string $file)
    {
        $this->file = $file;
        $this->lockFile = $file . '.lock';

        if (!file_exists($this->file)) {
            file_put_contents($this->file, self::GUARD_HEADER . "[]\n", LOCK_EX);
        }
        if (!file_exists($this->lockFile)) {
            touch($this->lockFile);
        }
    }

    private function stripGuard(string $raw): string
    {
        return str_starts_with($raw, self::GUARD_HEADER)
            ? substr($raw, strlen(self::GUARD_HEADER))
            : $raw;
    }

    /** Read all rows (shared lock — safe for concurrent reads). */
    public function all(): array
    {
        $fp = fopen($this->lockFile, 'c');
        if ($fp === false) {
            throw new RuntimeException('Unable to open lock file: ' . $this->lockFile);
        }
        flock($fp, LOCK_SH);
        try {
            $raw = file_get_contents($this->file);
            $raw = $raw !== false ? $this->stripGuard($raw) : '';
            $data = $raw !== '' ? json_decode($raw, true) : [];
            return is_array($data) ? $data : [];
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    public function find(string $id): ?array
    {
        foreach ($this->all() as $row) {
            if (($row['id'] ?? null) === $id) {
                return $row;
            }
        }
        return null;
    }

    /**
     * Read-modify-write under an exclusive lock. $mutator receives the
     * current array of rows and must return ['data' => array, 'return' => mixed].
     * The write is atomic: content is written to a temp file then rename()'d
     * over the original, so a crash mid-write cannot leave a corrupt/partial
     * JSON file on disk.
     */
    public function transact(callable $mutator)
    {
        $fp = fopen($this->lockFile, 'c');
        if ($fp === false) {
            throw new RuntimeException('Unable to open lock file: ' . $this->lockFile);
        }
        flock($fp, LOCK_EX);
        try {
            $raw = file_get_contents($this->file);
            $raw = $raw !== false ? $this->stripGuard($raw) : '';
            $data = $raw !== '' ? json_decode($raw, true) : [];
            if (!is_array($data)) {
                $data = [];
            }

            $result = $mutator($data);
            if (!is_array($result) || !array_key_exists('data', $result)) {
                throw new RuntimeException('JsonTable mutator must return ["data" => ..., "return" => ...]');
            }

            $newData = $result['data'];
            $json = json_encode(
                $newData,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
            if ($json === false) {
                throw new RuntimeException('Failed to encode JSON for ' . $this->file);
            }
            $json = self::GUARD_HEADER . $json;

            $tmp = $this->file . '.tmp.' . bin2hex(random_bytes(6));
            if (file_put_contents($tmp, $json) === false) {
                throw new RuntimeException('Failed to write temp file for ' . $this->file);
            }
            if (!rename($tmp, $this->file)) {
                @unlink($tmp);
                throw new RuntimeException('Failed to atomically replace ' . $this->file);
            }

            return $result['return'] ?? null;
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    public function insert(array $row): array
    {
        return $this->transact(function (array $data) use ($row) {
            $data[] = $row;
            return ['data' => $data, 'return' => $row];
        });
    }

    /** Merge $fields into the row matching $id. Returns the updated row or null. */
    public function update(string $id, array $fields): ?array
    {
        return $this->transact(function (array $data) use ($id, $fields) {
            $updated = null;
            foreach ($data as &$row) {
                if (($row['id'] ?? null) === $id) {
                    $row = array_merge($row, $fields);
                    $updated = $row;
                    break;
                }
            }
            unset($row);
            return ['data' => $data, 'return' => $updated];
        });
    }

    /** Generate an id guaranteed not to collide with an existing row in this table. */
    public function generateId(string $prefix): string
    {
        $existing = array_column($this->all(), 'id');
        do {
            $id = $prefix . '_' . bin2hex(random_bytes(8));
        } while (in_array($id, $existing, true));
        return $id;
    }
}

/**
 * Table registry — one shared instance per data file per request.
 */
final class Storage
{
    /** @var array<string, JsonTable> */
    private static array $tables = [];

    private static function table(string $name): JsonTable
    {
        if (!isset(self::$tables[$name])) {
            self::$tables[$name] = new JsonTable(DATA_DIR . '/' . $name . '.json.php');
        }
        return self::$tables[$name];
    }

    public static function users(): JsonTable { return self::table('users'); }
    public static function projects(): JsonTable { return self::table('projects'); }
    public static function videos(): JsonTable { return self::table('videos'); }
    public static function versions(): JsonTable { return self::table('versions'); }
}
