<?php
/**
 * Class WpPostContentBackup
 *
 * This class reads the raw binary data of the wp_posts.post_content column in 128-byte chunks.
 * It uses MySQL’s CAST to BINARY to ensure that operations are performed at the byte level, which
 * is ideal for handling UTF-8 multi-byte data without corrupting any multi-byte characters.
 *
 * The backup is written to a file in binary mode.
 */
class Wpdb_Chunk_Reader_Explaination {

    const CHUNK_SIZE = 128; // number of bytes per chunk

    /**
     * Back up the post_content of a specific post to a file.
     *
     * @param int    $postId         The ID of the wp_posts entry.
     * @param string $destinationFile The path to the file where the content is written.
     * @return bool  True on success, false on failure.
     */
    public function backupPostContent($postId, $destinationFile) {
        global $wpdb;

        // Step 1: Validate the post ID.
        $postId = intval($postId);
        if ($postId <= 0) {
            return false;
        }

        // Step 2: Measure the total length in bytes of the post_content column.
        // We use CAST(post_content AS BINARY) to ensure the calculation is based on raw bytes.
        $totalLength = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT LENGTH(CAST(post_content AS BINARY)) FROM {$wpdb->posts} WHERE ID = %d", 
            $postId
        ));

        if ($totalLength === 0) {
            // Post does not exist or the content is empty.
            return false;
        }

        // Step 3: Open the destination file in binary mode (wb).
        $handle = fopen($destinationFile, 'wb');
        if (!$handle) {
            return false;
        }

        // Step 4: Calculate the number of 128-byte chunks required.
        $chunkCount = ceil($totalLength / self::CHUNK_SIZE);

        // Step 5: Loop through each chunk.
        for ($i = 0; $i < $chunkCount; $i++) {
            // Calculate the offset.
            // In MySQL SUBSTRING, the index starts at 1.
            $offset = ($i * self::CHUNK_SIZE) + 1;
            
            // Step 5a: Read a 128-byte chunk from the post_content column.
            // We use SUBSTRING() on the BINARY-cast of post_content so that it is measured in bytes.
            $chunk = $wpdb->get_var($wpdb->prepare(
                "SELECT SUBSTRING(CAST(post_content AS BINARY), %d, %d) FROM {$wpdb->posts} WHERE ID = %d", 
                $offset, self::CHUNK_SIZE, $postId
            ));

            if ($chunk === null) {
                fclose($handle);
                return false;
            }

            // Step 5b: Write the 128-byte chunk to the destination file.
            if (fwrite($handle, $chunk) === false) {
                fclose($handle);
                return false;
            }
        }

        // Step 6: Close the file handle.
        fclose($handle);
        return true;
    }
}
?>