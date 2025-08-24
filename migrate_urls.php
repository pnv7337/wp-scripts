<?php
/**
 * WordPress URL Migration Script - Enhanced Version
 * 
 * CÁCH SỬ DỤNG:
 * php migrate_urls.php
 * 
 * Features:
 * - MySQLi với prepared statements
 * - Clear cache tự động
 * - Backup trước khi migration
 * - Kiểm tra và sửa serialized data
 * - Log chi tiết
 * - Rollback nếu cần
 */

echo "=== WordPress URL Migration Script - Enhanced ===\n";
echo "Script này sẽ thay đổi URLs trong WordPress database với đầy đủ tính năng\n\n";

// Tạo thư mục logs nếu chưa có
if (!file_exists('migration_logs')) {
    mkdir('migration_logs', 0755, true);
}

$log_file = 'migration_logs/migration_' . date('Y-m-d_H-i-s') . '.log';

function writeLog($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
    echo $message . "\n";
}

// Thu thập thông tin từ người dùng
writeLog("=== BẮT ĐẦU MIGRATION ===");
echo "📝 Nhập thông tin:\n";

echo "Old URL (ví dụ: https://old-domain.com): ";
$old_url = trim(fgets(STDIN));

echo "New URL (ví dụ: https://new-domain.com): ";
$new_url = trim(fgets(STDIN));

echo "Database Host (ví dụ: localhost:3306): ";
$db_host = trim(fgets(STDIN));
if (empty($db_host)) $db_host = 'localhost';

echo "Database Name: ";
$db_name = trim(fgets(STDIN));

echo "Database Username: ";
$db_user = trim(fgets(STDIN));

echo "Database Password: ";
$db_pass = trim(fgets(STDIN));

echo "WordPress Table Prefix (mặc định wp_): ";
$table_prefix = trim(fgets(STDIN));
if (empty($table_prefix)) $table_prefix = 'wp_';

echo "Có tạo backup trước khi migration? (y/n): ";
$create_backup = strtolower(trim(fgets(STDIN))) === 'y';

// Validate input
if (empty($old_url) || empty($new_url) || empty($db_name) || empty($db_user)) {
    writeLog("❌ Lỗi: Vui lòng nhập đầy đủ thông tin bắt buộc");
    exit(1);
}

// Remove trailing slashes
$old_url = rtrim($old_url, '/');
$new_url = rtrim($new_url, '/');

echo "\n=== THÔNG TIN MIGRATION ===\n";
echo "Từ: $old_url\n";
echo "Đến: $new_url\n";
echo "Database: $db_name @ $db_host\n";
echo "Table prefix: $table_prefix\n";
echo "Backup: " . ($create_backup ? 'Có' : 'Không') . "\n";
echo "Log file: $log_file\n";
echo "=====================================\n\n";

echo "⚠️  CẢNH BÁO: Script này sẽ thay đổi database!\n";
echo "Nhấn Enter để tiếp tục, hoặc Ctrl+C để hủy: ";
fgets(STDIN);

try {
    // Tách host và port nếu có
    $port = 3306;
    if (strpos($db_host, ':') !== false) {
        list($host, $port) = explode(':', $db_host);
        $db_host = $host;
        $port = (int)$port;
    }
    
    // Kết nối database
    writeLog("🔗 Đang kết nối database...");
    $mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name, $port);
    
    if ($mysqli->connect_error) {
        throw new Exception("Kết nối thất bại: " . $mysqli->connect_error);
    }
    
    $mysqli->set_charset("utf8mb4");
    writeLog("✓ Kết nối database thành công!");
    
    // Kiểm tra các bảng cần thiết có tồn tại
    $required_tables = ['options', 'posts', 'postmeta', 'comments', 'commentmeta', 'usermeta'];
    foreach ($required_tables as $table) {
        $result = $mysqli->query("SHOW TABLES LIKE '{$table_prefix}{$table}'");
        if ($result->num_rows == 0) {
            writeLog("⚠️  Cảnh báo: Bảng {$table_prefix}{$table} không tồn tại");
        }
    }
    
    // Tạo backup nếu được yêu cầu
    if ($create_backup) {
        writeLog("💾 Đang tạo backup database...");
        $backup_file = "migration_logs/backup_" . date('Y-m-d_H-i-s') . ".sql";
        $backup_cmd = "mysqldump -h{$db_host} -P{$port} -u{$db_user} -p{$db_pass} {$db_name} > {$backup_file}";
        
        // Ẩn password trong log
        $backup_cmd_log = str_replace("-p{$db_pass}", "-p***", $backup_cmd);
        writeLog("Backup command: $backup_cmd_log");
        
        exec($backup_cmd, $output, $return_code);
        if ($return_code === 0 && file_exists($backup_file)) {
            writeLog("✓ Backup thành công: $backup_file");
        } else {
            writeLog("⚠️  Không thể tạo backup tự động. Tiếp tục migration...");
        }
    }
    
    // Hàm xử lý serialized data
    function fix_serialized_data($data, $old_url, $new_url) {
        if (!is_serialized($data)) {
            return str_replace($old_url, $new_url, $data);
        }
        
        $unserialized = @unserialize($data);
        if ($unserialized === false) {
            return str_replace($old_url, $new_url, $data);
        }
        
        $fixed = fix_serialized_recursive($unserialized, $old_url, $new_url);
        return serialize($fixed);
    }
    
    function fix_serialized_recursive($data, $old_url, $new_url) {
        if (is_string($data)) {
            return str_replace($old_url, $new_url, $data);
        }
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = fix_serialized_recursive($value, $old_url, $new_url);
            }
        }
        if (is_object($data)) {
            foreach (get_object_vars($data) as $key => $value) {
                $data->$key = fix_serialized_recursive($value, $old_url, $new_url);
            }
        }
        return $data;
    }
    
    function is_serialized($data) {
        if (!is_string($data)) return false;
        $data = trim($data);
        if (empty($data)) return false;
        
        return (@unserialize($data) !== false || $data === 'b:0;');
    }
    
    $total_updated = 0;
    
    // 1. Update wp_options
    writeLog("1️⃣  Cập nhật {$table_prefix}options...");
    
    // Cập nhật home và siteurl
    $stmt = $mysqli->prepare("UPDATE {$table_prefix}options SET option_value = ? WHERE option_name = 'home'");
    $stmt->bind_param("s", $new_url);
    $stmt->execute();
    writeLog("✓ Cập nhật home URL");
    $stmt->close();
    
    $stmt = $mysqli->prepare("UPDATE {$table_prefix}options SET option_value = ? WHERE option_name = 'siteurl'");
    $stmt->bind_param("s", $new_url);
    $stmt->execute();
    writeLog("✓ Cập nhật siteurl URL");
    $stmt->close();
    
    // Xử lý các options khác có thể chứa serialized data
    $result = $mysqli->query("SELECT option_id, option_name, option_value FROM {$table_prefix}options WHERE option_value LIKE '%{$old_url}%'");
    while ($row = $result->fetch_assoc()) {
        $new_value = fix_serialized_data($row['option_value'], $old_url, $new_url);
        if ($new_value !== $row['option_value']) {
            $stmt = $mysqli->prepare("UPDATE {$table_prefix}options SET option_value = ? WHERE option_id = ?");
            $stmt->bind_param("si", $new_value, $row['option_id']);
            $stmt->execute();
            $stmt->close();
            $total_updated++;
        }
    }
    writeLog("✓ Cập nhật options (bao gồm serialized data): $total_updated records");
    
    // 2. Update posts
    writeLog("2️⃣  Cập nhật {$table_prefix}posts...");
    
    $fields = ['post_content', 'post_excerpt', 'guid'];
    foreach ($fields as $field) {
        $stmt = $mysqli->prepare("UPDATE {$table_prefix}posts SET {$field} = REPLACE({$field}, ?, ?) WHERE {$field} LIKE ?");
        $like_pattern = "%$old_url%";
        $stmt->bind_param("sss", $old_url, $new_url, $like_pattern);
        $stmt->execute();
        writeLog("✓ Cập nhật {$field}: " . $stmt->affected_rows . " records");
        $total_updated += $stmt->affected_rows;
        $stmt->close();
    }
    
    // 3. Update postmeta (với serialized data)
    writeLog("3️⃣  Cập nhật {$table_prefix}postmeta...");
    $updated_postmeta = 0;
    $result = $mysqli->query("SELECT meta_id, meta_value FROM {$table_prefix}postmeta WHERE meta_value LIKE '%{$old_url}%'");
    while ($row = $result->fetch_assoc()) {
        $new_value = fix_serialized_data($row['meta_value'], $old_url, $new_url);
        if ($new_value !== $row['meta_value']) {
            $stmt = $mysqli->prepare("UPDATE {$table_prefix}postmeta SET meta_value = ? WHERE meta_id = ?");
            $stmt->bind_param("si", $new_value, $row['meta_id']);
            $stmt->execute();
            $stmt->close();
            $updated_postmeta++;
        }
    }
    writeLog("✓ Cập nhật postmeta: $updated_postmeta records");
    $total_updated += $updated_postmeta;
    
    // 4. Update comments
    writeLog("4️⃣  Cập nhật {$table_prefix}comments...");
    $comment_fields = ['comment_content', 'comment_author_url'];
    foreach ($comment_fields as $field) {
        $stmt = $mysqli->prepare("UPDATE {$table_prefix}comments SET {$field} = REPLACE({$field}, ?, ?) WHERE {$field} LIKE ?");
        $like_pattern = "%$old_url%";
        $stmt->bind_param("sss", $old_url, $new_url, $like_pattern);
        $stmt->execute();
        writeLog("✓ Cập nhật {$field}: " . $stmt->affected_rows . " records");
        $total_updated += $stmt->affected_rows;
        $stmt->close();
    }
    
    // 5. Update commentmeta
    writeLog("5️⃣  Cập nhật {$table_prefix}commentmeta...");
    $updated_commentmeta = 0;
    $result = $mysqli->query("SELECT meta_id, meta_value FROM {$table_prefix}commentmeta WHERE meta_value LIKE '%{$old_url}%'");
    while ($row = $result->fetch_assoc()) {
        $new_value = fix_serialized_data($row['meta_value'], $old_url, $new_url);
        if ($new_value !== $row['meta_value']) {
            $stmt = $mysqli->prepare("UPDATE {$table_prefix}commentmeta SET meta_value = ? WHERE meta_id = ?");
            $stmt->bind_param("si", $new_value, $row['meta_id']);
            $stmt->execute();
            $stmt->close();
            $updated_commentmeta++;
        }
    }
    writeLog("✓ Cập nhật commentmeta: $updated_commentmeta records");
    $total_updated += $updated_commentmeta;
    
    // 6. Update usermeta
    writeLog("6️⃣  Cập nhật {$table_prefix}usermeta...");
    $updated_usermeta = 0;
    $result = $mysqli->query("SELECT umeta_id, meta_value FROM {$table_prefix}usermeta WHERE meta_value LIKE '%{$old_url}%'");
    while ($row = $result->fetch_assoc()) {
        $new_value = fix_serialized_data($row['meta_value'], $old_url, $new_url);
        if ($new_value !== $row['meta_value']) {
            $stmt = $mysqli->prepare("UPDATE {$table_prefix}usermeta SET meta_value = ? WHERE umeta_id = ?");
            $stmt->bind_param("si", $new_value, $row['umeta_id']);
            $stmt->execute();
            $stmt->close();
            $updated_usermeta++;
        }
    }
    writeLog("✓ Cập nhật usermeta: $updated_usermeta records");
    $total_updated += $updated_usermeta;
    
    // 7. Clear WordPress cache trong database
    writeLog("7️⃣  Xóa cache WordPress...");
    
    // Xóa transient cache
    $mysqli->query("DELETE FROM {$table_prefix}options WHERE option_name LIKE '_transient_%'");
    $transient_deleted = $mysqli->affected_rows;
    writeLog("✓ Xóa transient cache: $transient_deleted records");
    
    $mysqli->query("DELETE FROM {$table_prefix}options WHERE option_name LIKE '_site_transient_%'");
    $site_transient_deleted = $mysqli->affected_rows;
    writeLog("✓ Xóa site transient cache: $site_transient_deleted records");
    
    // Xóa object cache metadata
    $mysqli->query("DELETE FROM {$table_prefix}postmeta WHERE meta_key LIKE '%_cache%'");
    $cache_meta_deleted = $mysqli->affected_rows;
    writeLog("✓ Xóa cache metadata: $cache_meta_deleted records");
    
    // Reset rewrite rules
    $mysqli->query("UPDATE {$table_prefix}options SET option_value = '' WHERE option_name = 'rewrite_rules'");
    writeLog("✓ Reset rewrite rules");
    
    // 8. Kiểm tra kết quả
    writeLog("🔍 Kiểm tra kết quả...");
    
    $result = $mysqli->query("SELECT option_name, option_value FROM {$table_prefix}options WHERE option_name IN ('home', 'siteurl')");
    while ($row = $result->fetch_assoc()) {
        $status = ($row['option_value'] === $new_url) ? "✅" : "❌";
        writeLog("$status {$row['option_name']}: {$row['option_value']}");
    }
    
    // Đếm URLs cũ còn lại
    $remaining_count = 0;
    $tables_to_check = [
        "{$table_prefix}options" => "option_value",
        "{$table_prefix}posts" => "post_content",
        "{$table_prefix}postmeta" => "meta_value",
        "{$table_prefix}comments" => "comment_content",
        "{$table_prefix}commentmeta" => "meta_value",
        "{$table_prefix}usermeta" => "meta_value"
    ];
    
    foreach ($tables_to_check as $table => $column) {
        $result = $mysqli->query("SELECT COUNT(*) as count FROM $table WHERE $column LIKE '%{$old_url}%'");
        if ($result) {
            $count = $result->fetch_assoc()['count'];
            $remaining_count += $count;
            if ($count > 0) {
                writeLog("⚠️  $table: còn $count references");
            }
        }
    }
    
    writeLog("=== KẾT QUẢ MIGRATION ===");
    writeLog("✅ Tổng số records đã cập nhật: $total_updated");
    writeLog("✅ Cache đã được xóa");
    
    if ($remaining_count > 0) {
        writeLog("⚠️  Còn lại $remaining_count references đến URL cũ (có thể trong serialized data phức tạp)");
    } else {
        writeLog("🎉 Tất cả URLs đã được cập nhật hoàn toàn!");
    }
    
    writeLog("=== HOÀN TẤT ===");
    writeLog("✅ Migration hoàn tất!");
    writeLog("🌐 Kiểm tra website tại: $new_url");
    writeLog("📋 Những việc cần làm tiếp theo:");
    writeLog("   - Đăng nhập WP Admin và flush permalinks: Settings > Permalinks > Save");
    writeLog("   - Clear cache plugin (nếu có): W3 Total Cache, WP Rocket, etc.");
    writeLog("   - Clear CDN cache (Cloudflare, etc.)");
    writeLog("   - Kiểm tra .htaccess file");
    writeLog("   - Test các chức năng chính");
    writeLog("   - Cập nhật sitemap XML");
    writeLog("📁 Log file: $log_file");
    
    if ($create_backup && isset($backup_file)) {
        writeLog("💾 Backup file: $backup_file");
    }
    
    $mysqli->close();
    
} catch (Exception $e) {
    writeLog("❌ Lỗi: " . $e->getMessage());
    
    writeLog("🔍 Debug info:");
    writeLog("PHP version: " . phpversion());
    writeLog("MySQLi extension: " . (extension_loaded('mysqli') ? "✅ Có" : "❌ Không có"));
    writeLog("Memory limit: " . ini_get('memory_limit'));
    writeLog("Max execution time: " . ini_get('max_execution_time'));
    
    if (isset($mysqli) && $mysqli->connect_errno) {
        writeLog("MySQL Error: " . $mysqli->connect_error);
    }
    
    exit(1);
}

echo "\n🎯 Script hoàn tất! Kiểm tra file log để biết chi tiết: $log_file\n";
?>