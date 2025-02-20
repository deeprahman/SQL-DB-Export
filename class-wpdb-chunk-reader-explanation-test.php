<?php
use PHPUnit\Framework\TestCase;

require_once 'WpPostContentBackup.php';

class WpPostContentBackupTest extends TestCase {

    private $backupFile;

    protected function setUp(): void {
        // Define a temporary file path for our backup.
        $this->backupFile = sys_get_temp_dir() . '/post_content_backup_test.bin';
    }

    protected function tearDown(): void {
        if (file_exists($this->backupFile)) {
            unlink($this->backupFile);
        }
    }

    public function testBackupPostContentFailureForInvalidPost() {
        $backup = new WpPostContentBackup();
        $result = $backup->backupPostContent(-1, $this->backupFile);
        $this->assertFalse($result, "Backup should fail for an invalid post ID.");
    }

    public function testBackupPostContentSuccess() {
        $backup = new WpPostContentBackup();
        // Replace '1' with a real post ID from your WordPress test environment.
        $postId = 1;
        $result = $backup->backupPostContent($postId, $this->backupFile);
        $this->assertTrue($result, "Backup should succeed for a valid post ID.");
        $this->assertFileExists($this->backupFile);

        // Compare the expected binary content size with the destination file size.
        global $wpdb;
        $expectedSize = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT LENGTH(CAST(post_content AS BINARY)) FROM {$wpdb->posts} WHERE ID = %d", 
            $postId
        ));
        $actualSize = filesize($this->backupFile);
        $this->assertEquals($expectedSize, $actualSize, "The backup file size should match the binary size of post_content.");
    }
}
?>