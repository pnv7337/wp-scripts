<?php
/**
 * WordPress URL Migration Script - Simplified Auto Environment Version
 * 
 * CÁCH SỬ DỤNG:
 * php migrate_urls.php
 * 
 * Features:
 * - Tự động đọc database config từ WordPress environment
 * - Chỉ cần nhập old URL và new URL
 * - Clear cache tự động
 * - Xử lý serialized data
 * - Log chi tiết
 */

echo "=== WordPress URL Migration Script - Auto Environment ===\n";
echo "Script tự động đọc config database từ WordPress environment\n";
echo "Chỉ cần nhập 2 URLs: cũ và mới\n\n";

// Tạo thư mục logs
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

// Đọc database config từ WordPress environment
function getWpDbConfig() {
    writeLog("🔍 Đọc database config từ WordPress environment...");
    
    $config = [
        'host' => getenv('WORDPRESS_DB_HOST') ?: getenv('DB_HOST') ?: 'mysql',
        'name' => getenv('WORDPRESS_DB_NAME') ?: getenv('DB_NAME') ?: 'wordpress', 
        'user' => getenv('WORDPRESS_DB_USER') ?: getenv('DB_USER') ?: 'wordpress',
        'pass' => getenv('WORDPRESS_DB_PASSWORD') ?: getenv('DB_PASSWORD') ?: '',
        'prefix' => 'wp_'
    ];
    
    writeLog("✓ Database Host: " . $config['host']);
    writeLog("✓ Database Name: " . $config['name']);
    writeLog("✓ Database User: " . $config['user']);
    writeLog("✓ Table Prefix: " . $config['prefix']);
    
    return $config;
}

// Test kết nối database
function testDbConnection($config) {
    writeLog("🔗 Test kết nối database...");
    
    $host = $config['host'];
    $port = 3306;
    
    if (strpos($host, ':') !== false) {
        list($host, $port) = explode(':', $host);
        $port = (int)$port;
    }
    
    try {
        $mysqli = new mysqli($host, $config['user'], $config['pass'], $config['name'], $port);
        
        if ($mysqli->connect_error) {
            // Thử các host khác
            $alt_hosts = ['localhost', '127.0.0.1', 'mysql', 'db', 'mariadb'];
            
            foreach ($alt_hosts as $alt_host) {
                if ($alt_host === $host) continue;
                
                writeLog("🔄 Thử host: $alt_host");
                $alt_mysqli = @new mysqli($alt_host, $config['user'], $config['pass'], $config['name'], 3306);
                
                if (!$alt_mysqli->connect_error) {
                    writeLog("✅ Kết nối thành công với host: $alt_host");
                    $config['host'] = $alt_host;
                    $alt_mysqli->close();
                    return $config;
                }
            }
            
            throw new Exception("Không thể kết nối database với bất kỳ host nào");
        }
        
        $mysqli->set_charset("utf8mb4");
        writeLog("✅ Kết nối database thành công!");
        writeLog("📊 MySQL version: " . $mysqli->server_info);
        
        // Kiểm tra WordPress tables
        $result = $mysqli->query("SHOW TABLES LIKE '{$config['prefix']}options'");
        if ($result->num_rows > 0) {
            writeLog("✅ Tìm thấy WordPress tables");
        } else {
            writeLog("⚠️  Không tìm thấy WordPress tables với prefix: " . $config['prefix']);
        }
        
        $mysqli->close();
        return $config;
        
    } catch (Exception $e) {
        throw new Exception("Database connection failed: " . $e->getMessage());
    }
}

// Hàm xử lý serialized data
function fixSerializedData($data, $old_url, $new_url) {
    if (!is_string($data) || empty($data)) {
        return $data;
    }
    
    // Kiểm tra nếu là serialized data
    if (@unserialize($data) !== false || $data === 'b:0;') {
        $unserialized = @unserialize($data);
        if ($unserialized !== false) {
            $fixed = replaceInData($unserialized, $old_url, $new_url);
            return serialize($fixed);
        }
    }
    
    // Nếu không phải serialized, thay thế bình thường
    return str_replace($old_url, $new_url, $data);
}

function replaceInData($data, $old_url, $new_url) {
    if (is_string($data)) {
        return str_replace($old_url, $new_url, $data);
    }
    
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = replaceInData($value, $old_url, $new_url);
        }
        return $data;
    }
    
    if (is_object($data)) {
        foreach (get_object_vars($data) as $key => $value) {
            $data->$key = replaceInData($value, $old_url, $new_url);
        }
        return $data;
    }
    
    return $data;
}

// Bắt đầu migration
try {
    writeLog("=== BẮT ĐẦU MIGRATION ===");
    
    // 1. Lấy database config tự động
    $db_config = getWpDbConfig();
    
    // 2. Test kết nối
    $db_config = testDbConnection($db_config);
    
    // 3. Thu thập URLs từ user (chỉ cần 2 thông tin)
    echo "\n📝 Nhập thông tin URLs:\n";
    echo "Old URL (ví dụ: https://old-domain.com): ";
    $old_url = trim(fgets(STDIN));
    
    echo "New URL (ví dụ: https://new-domain.com): ";
    $new_url = trim(fgets(STDIN));
    
    // Validate URLs
    if (empty($old_url) || empty($new_url)) {
        throw new Exception("Vui lòng nhập đầy đủ old URL và new URL");
    }
    
    // Remove trailing slashes
    $old_url = rtrim($old_url, '/');
    $new_url = rtrim($new_url, '/');
    
    echo "\n=== THÔNG TIN MIGRATION ===\n";
    echo "Từ: $old_url\n";
    echo "Đến: $new_url\n";
    echo "Database: {$db_config['name']} @ {$db_config['host']}\n";
    echo "Log file: $log_file\n";
    echo "=====================================\n\n";
    
    echo "⚠️  CẢNH BÁO: Script này sẽ thay đổi database!\n";
    echo "Nhấn Enter để tiếp tục, hoặc Ctrl+C để hủy: ";
    fgets(STDIN);
    
    // 4. Kết nối database để thực hiện migration
    writeLog("🚀 Bắt đầu migration...");
    
    $host = $db_config['host'];
    $port = 3306;
    if (strpos($host, ':') !== false) {
        list($host, $port) = explode(':', $host);
        $port = (int)$port;
    }
    
    $mysqli = new mysqli($host, $db_config['user'], $db_config['pass'], $db_config['name'], $port);
    
    if ($mysqli->connect_error) {
        throw new Exception("Kết nối database thất bại: " . $mysqli->connect_error);
    }
    
    $mysqli->set_charset("utf8mb4");
    $prefix = $db_config['prefix'];
    $total_updated = 0;
    
    // 5. Update wp_options (home, siteurl)
    writeLog("1️⃣ Cập nhật options chính...");
    
    $stmt = $mysqli->prepare("UPDATE {$prefix}options SET option_value = ? WHERE option_name = 'home'");
    $stmt->bind_param("s", $new_url);
    $stmt->execute();
    writeLog("✓ Cập nhật home URL");
    $stmt->close();
    
    $stmt = $mysqli->prepare("UPDATE {$prefix}options SET option_value = ? WHERE option_name = 'siteurl'");
    $stmt->bind_param("s", $new_url);
    $stmt->execute();
    writeLog("✓ Cập nhật siteurl URL");
    $stmt->close();
    
    // 6. Update options khác có chứa old URL (bao gồm serialized data)
    writeLog("2️⃣ Cập nhật options khác...");
    $result = $mysqli->query("SELECT option_id, option_name, option_value FROM {$prefix}options WHERE option_value LIKE '%{$old_url}%'");
    $options_updated = 0;
    
    while ($row = $result->fetch_assoc()) {
        $new_value = fixSerializedData($row['option_value'], $old_url, $new_url);
        if ($new_value !== $row['option_value']) {
            $stmt = $mysqli->prepare("UPDATE {$prefix}options SET option_value = ? WHERE option_id = ?");
            $stmt->bind_param("si", $new_value, $row['option_id']);
            $stmt->execute();
            $stmt->close();
            $options_updated++;
        }
    }
    writeLog("✓ Cập nhật options: $options_updated records");
    $total_updated += $options_updated;
    
    // 7. Update posts content
    writeLog("3️⃣ Cập nhật posts...");
    $post_fields = ['post_content', 'post_excerpt', 'guid'];
    foreach ($post_fields as $field) {
        $stmt = $mysqli->prepare("UPDATE {$prefix}posts SET {$field} = REPLACE({$field}, ?, ?) WHERE {$field} LIKE ?");
        $like_pattern = "%$old_url%";
        $stmt->bind_param("sss", $old_url, $new_url, $like_pattern);
        $stmt->execute();
        writeLog("✓ Cập nhật {$field}: " . $stmt->affected_rows . " records");
        $total_updated += $stmt->affected_rows;
        $stmt->close();
    }
    
    // 8. Update postmeta
    writeLog("4️⃣ Cập nhật postmeta...");
    $result = $mysqli->query("SELECT meta_id, meta_value FROM {$prefix}postmeta WHERE meta_value LIKE '%{$old_url}%'");
    $postmeta_updated = 0;
    
    while ($row = $result->fetch_assoc()) {
        $new_value = fixSerializedData($row['meta_value'], $old_url, $new_url);
        if ($new_value !== $row['meta_value']) {
            $stmt = $mysqli->prepare("UPDATE {$prefix}postmeta SET meta_value = ? WHERE meta_id = ?");
            $stmt->bind_param("si", $new_value, $row['meta_id']);
            $stmt->execute();
            $stmt->close();
            $postmeta_updated++;
        }
    }
    writeLog("✓ Cập nhật postmeta: $postmeta_updated records");
    $total_updated += $postmeta_updated;
    
    // 9. Update comments
    writeLog("5️⃣ Cập nhật comments...");
    $comment_fields = ['comment_content', 'comment_author_url'];
    foreach ($comment_fields as $field) {
        $stmt = $mysqli->prepare("UPDATE {$prefix}comments SET {$field} = REPLACE({$field}, ?, ?) WHERE {$field} LIKE ?");
        $like_pattern = "%$old_url%";
        $stmt->bind_param("sss", $old_url, $new_url, $like_pattern);
        $stmt->execute();
        writeLog("✓ Cập nhật {$field}: " . $stmt->affected_rows . " records");
        $total_updated += $stmt->affected_rows;
        $stmt->close();
    }
    
    // 10. Clear cache
    writeLog("6️⃣ Xóa cache WordPress...");
    
    // Xóa transient cache
    $mysqli->query("DELETE FROM {$prefix}options WHERE option_name LIKE '_transient_%'");
    $transient_deleted = $mysqli->affected_rows;
    writeLog("✓ Xóa transient cache: $transient_deleted records");
    
    $mysqli->query("DELETE FROM {$prefix}options WHERE option_name LIKE '_site_transient_%'");
    $site_transient_deleted = $mysqli->affected_rows;
    writeLog("✓ Xóa site transient cache: $site_transient_deleted records");
    
    // Reset rewrite rules
    $mysqli->query("UPDATE {$prefix}options SET option_value = '' WHERE option_name = 'rewrite_rules'");
    writeLog("✓ Reset rewrite rules");
    
    // 11. Kiểm tra kết quả
    writeLog("7️⃣ Kiểm tra kết quả...");
    
    $result = $mysqli->query("SELECT option_name, option_value FROM {$prefix}options WHERE option_name IN ('home', 'siteurl')");
    while ($row = $result->fetch_assoc()) {
        $status = ($row['option_value'] === $new_url) ? "✅" : "❌";
        writeLog("$status {$row['option_name']}: {$row['option_value']}");
    }
    
    // Đếm URLs cũ còn lại
    $remaining_count = 0;
    $tables = [
        "{$prefix}options" => "option_value",
        "{$prefix}posts" => "post_content", 
        "{$prefix}postmeta" => "meta_value"
    ];
    
    foreach ($tables as $table => $column) {
        $result = $mysqli->query("SELECT COUNT(*) as count FROM $table WHERE $column LIKE '%{$old_url}%'");
        if ($result) {
            $count = $result->fetch_assoc()['count'];
            $remaining_count += $count;
            if ($count > 0) {
                writeLog("⚠️  $table: còn $count references");
            }
        }
    }
    
    // 12. Kết quả cuối cùng
    writeLog("=== KẾT QUẢ MIGRATION ===");
    writeLog("✅ Tổng records đã cập nhật: $total_updated");
    writeLog("✅ Cache đã được xóa: " . ($transient_deleted + $site_transient_deleted) . " cache entries");
    
    if ($remaining_count > 0) {
        writeLog("⚠️  Còn lại $remaining_count references (có thể trong data phức tạp)");
    } else {
        writeLog("🎉 Migration hoàn tất 100%!");
    }
    
    writeLog("=== VIỆC CẦN LÀM TIẾP THEO ===");
    writeLog("🌐 Kiểm tra website: $new_url");
    writeLog("🔧 Đăng nhập WP Admin → Settings → Permalinks → Save");
    writeLog("🧹 Clear cache plugin nếu có");
    writeLog("☁️  Clear CDN cache nếu có");
    writeLog("📁 Log chi tiết: $log_file");
    
    $mysqli->close();
    
    echo "\n🎯 Migration hoàn tất! Kiểm tra log để biết chi tiết.\n";
    
} catch (Exception $e) {
    writeLog("❌ LỖI: " . $e->getMessage());
    
    writeLog("🔍 Debug info:");
    writeLog("PHP version: " . phpversion());
    writeLog("Environment variables:");
    
    $env_vars = ['WORDPRESS_DB_HOST', 'WORDPRESS_DB_NAME', 'WORDPRESS_DB_USER', 'WORDPRESS_DB_PASSWORD'];
    foreach ($env_vars as $var) {
        $value = getenv($var);
        writeLog("$var: " . ($value ? "SET" : "NOT SET"));
    }
    
    if (isset($mysqli) && $mysqli->connect_errno) {
        writeLog("MySQL Error: " . $mysqli->connect_error);
    }
    
    echo "\n❌ Migration thất bại! Xem log: $log_file\n";
    exit(1);
}
?>