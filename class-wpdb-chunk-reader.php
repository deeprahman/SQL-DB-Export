/**
 * WPDBChunkReader reads a column from a MySQL table in fixed-size binary chunks,
 * processes each chunk using a callback, and supports resuming from the last processed offset.
 * 
 * Algorithm Steps:
 * 1. Initialize the reader with table name, column name, chunk size, and callback function.
 * 2. Load the last saved offset from the manifest file (if available); otherwise, start from 1.
 * 3. Enter a loop to process the column data:
 *    a. Fetch a 128-byte chunk (or configured chunk size) from the database using SUBSTRING(BINARY ...).
 *    b. If no data is returned, stop processing.
 *    c. Pass the chunk to the callback function for processing (e.g., saving to a file).
 *    d. Update the offset based on the length of the chunk processed.
 *    e. Save the new offset to the manifest file for resumption in case of failure.
 * 4. Repeat until all data is processed.
 * 5. The resume() method ensures the process continues from the last saved offset.
 */
class WPDBChunkReader {
    private $wpdb;
    private $table;
    private $column;
    private $callback;
    private $manifest;
    private $chunkSize;

    public function __construct($wpdb, $table, $column, callable $callback, $manifestFile = 'manifest.json', $chunkSize = 128) {
        $this->wpdb = $wpdb;
        $this->table = $table;
        $this->column = $column;
        $this->callback = $callback;
        $this->manifest = new Manifest($manifestFile);
        $this->chunkSize = $chunkSize;
    }

    public function process() {
        $offset = $this->manifest->getOffset($this->table, $this->column);
        
        while (true) {
            $chunk = $this->fetchChunk($offset);
            if ($chunk === null) {
                break;
            }
            
            call_user_func($this->callback, $chunk);
            
            $offset += strlen($chunk);
            $this->manifest->saveOffset($this->table, $this->column, $offset);
        }
    }

    public function resume() {
        $this->process();
    }

    private function fetchChunk($offset) {
        $query = $this->wpdb->prepare(
            "SELECT SUBSTRING(BINARY {$this->column} FROM %d FOR %d) AS chunk FROM {$this->table} LIMIT 1", 
            $offset, 
            $this->chunkSize
        );
        
        $result = $this->wpdb->get_row($query);
        return $result && !empty($result->chunk) ? $result->chunk : null;
    }
}

class Manifest {
    private $file;

    public function __construct($file) {
        $this->file = $file;
    }

    public function getOffset($table, $column) {
        if (!file_exists($this->file)) {
            return 1;
        }
        $data = json_decode(file_get_contents($this->file), true);
        return $data[$table][$column] ?? 1;
    }

    public function saveOffset($table, $column, $offset) {
        $data = file_exists($this->file) ? json_decode(file_get_contents($this->file), true) : [];
        $data[$table][$column] = $offset;
        file_put_contents($this->file, json_encode($data, JSON_PRETTY_PRINT));
    }
}

// Example Usage:
$reader = new WPDBChunkReader(
    $wpdb, 
    'your_table', 
    'your_column', 
    function ($chunk) {
        file_put_contents('output.bin', $chunk, FILE_APPEND);
    }
);
$reader->resume();
