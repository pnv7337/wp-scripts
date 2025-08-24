<?php
/**
 * WordPress URL Migration Script - MySQLi Version
 * 
 * CÁCH SỬ DỤNG:
 * php migrate_urls.php
 * 
 * Script này sử dụng MySQLi thay vì PDO để tương thích tốt hơn
 */

echo "=== WordPress URL Migration Script ===\n";
echo "Script này sẽ thay đổi URLs trong WordPress database\n\n";

// Thu thập thông tin từ người dùng
echo "📝 Nhập thông tin:\n";

echo "Old URL (ví dụ: https://old-domain.com): ";
$old_url = trim(fgets(STDIN));

echo "New URL (ví dụ: https://new-domain.com): ";
$new_url = trim(fgets(STDIN));

echo "Database Host (ví dụ: localhost:3306 hoặc Internal Host): ";
$db_host = trim(fgets(STDIN));
if (empty($db_host)) $db_host = 'localhost';

echo "Database Name: ";
$db_name = trim(fgets(STDIN));

echo "Database Username: ";
$db_user = trim(fgets(STDIN));

echo "Database Password: ";
$db_pass = trim(fgets(STDIN));

// Validate input
if (empty($old_url) || empty($new_url) || empty($db_name) || empty($db_user)) {
    echo "❌ Lỗi: Vui lòng nhập đầy đủ thông tin bắt buộc\n";
    exit(1);
}

// Remove trailing slashes
$old_url = rtrim($old_url, '/');
$new_url = rtrim($new_url, '/');

echo "\n=== THÔNG TIN MIGRATION ===\n";
echo "Từ: $old_url\n";
echo "Đến: $new_url\n";
echo "Database: $db_name @ $db_host\n";
echo "=====================================\n\n";

echo "⚠️  CẢNH BÁO: Script này sẽ thay đổi database!\n";
echo "Nhấn Enter để tiếp tục, hoặc Ctrl+C để hủy: ";
fgets(STDIN);

try {
    // Tách host và port nếu có
    $port = 3306; // default MySQL port
    if (strpos($db_host, ':') !== false) {
        list($host, $port) = explode(':', $db_host);
        $db_host = $host;
        $port = (int)$port;
    }
    
    // Kết nối database bằng MySQLi
    echo "🔗 Đang kết nối database...\n";
    $mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name, $port);
    
    // Kiểm tra kết nối
    if ($mysqli->connect_error) {
        throw new Exception("Kết nối thất bại: " . $mysqli->connect_error);
    }
    
    // Set charset
    $mysqli->set_charset("utf8mb4");
    echo "✓ Kết nối database thành công!\n\n";
    
    // 1. Update wp_options (home, siteurl)
    echo "1️⃣  Cập nhật wp_options...\n";
    
    $stmt = $mysqli->prepare("UPDATE wp_options SET option_value = ? WHERE option_name = 'home'");
    $stmt->bind_param("s", $new_url);
    $stmt->execute();
    echo "✓ Cập nhật home URL\n";
    $stmt->close();
    
    $stmt = $mysqli->prepare("UPDATE wp_options SET option_value = ? WHERE option_name = 'siteurl'");
    $stmt->bind_param("s", $new_url);
    $stmt->execute();
    echo "✓ Cập nhật siteurl URL\n";
    $stmt->close();
    
    // 2. Update other options with old URL
    $stmt = $mysqli->prepare("UPDATE wp_options SET option_value = REPLACE(option_value, ?, ?) WHERE option_value LIKE ?");
    $like_pattern = "%$old_url%";
    $stmt->bind_param("sss", $old_url, $new_url, $like_pattern);
    $stmt->execute();
    echo "✓ Cập nhật các options khác: " . $stmt->affected_rows . " records\n";
    $stmt->close();
    
    // 3. Update wp_posts content
    echo "\n2️⃣  Cập nhật nội dung bài viết...\n";
    
    $stmt = $mysqli->prepare("UPDATE wp_posts SET post_content = REPLACE(post_content, ?, ?) WHERE post_content LIKE ?");
    $stmt->bind_param("sss", $old_url, $new_url, $like_pattern);
    $stmt->execute();
    echo "✓ Cập nhật post_content: " . $stmt->affected_rows . " records\n";
    $stmt->close();
    
    $stmt = $mysqli->prepare("UPDATE wp_posts SET post_excerpt = REPLACE(post_excerpt, ?, ?) WHERE post_excerpt LIKE ?");
    $stmt->bind_param("sss", $old_url, $new_url, $like_pattern);
    $stmt->execute();
    echo "✓ Cập nhật post_excerpt: " . $stmt->affected_rows . " records\n";
    $stmt->close();
    
    $stmt = $mysqli->prepare("UPDATE wp_posts SET guid = REPLACE(guid, ?, ?) WHERE guid LIKE ?");
    $stmt->bind_param("sss", $old_url, $new_url, $like_pattern);
    $stmt->execute();
    echo "✓ Cập nhật guid: " . $stmt->affected_rows . " records\n";
    $stmt->close();
    
    // 4. Update wp_postmeta
    echo "\n3️⃣  Cập nhật post meta...\n";
    $stmt = $mysqli->prepare("UPDATE wp_postmeta SET meta_value = REPLACE(meta_value, ?, ?) WHERE meta_value LIKE ?");
    $stmt->bind_param("sss", $old_url, $new_url, $like_pattern);
    $stmt->execute();
    echo "✓ Cập nhật postmeta: " . $stmt->affected_rows . " records\n";
    $stmt->close();
    
    // 5. Update wp_comments
    echo "\n4️⃣  Cập nhật comments...\n";
    $stmt = $mysqli->prepare("UPDATE wp_comments SET comment_content = REPLACE(comment_content, ?, ?) WHERE comment_content LIKE ?");
    $stmt->bind_param("sss", $old_url, $new_url, $like_pattern);
    $stmt->execute();
    echo "✓ Cập nhật comment_content: " . $stmt->affected_rows . " records\n";
    $stmt->close();
    
    $stmt = $mysqli->prepare("UPDATE wp_comments SET comment_author_url = REPLACE(comment_author_url, ?, ?) WHERE comment_author_url LIKE ?");
    $stmt->bind_param("sss", $old_url, $new_url, $like_pattern);
    $stmt->execute();
    echo "✓ Cập nhật comment_author_url: " . $stmt->affected_rows . " records\n";
    $stmt->close();
    
    // 6. Update wp_commentmeta
    echo "\n5️⃣  Cập nhật comment meta...\n";
    $stmt = $mysqli->prepare("UPDATE wp_commentmeta SET meta_value = REPLACE(meta_value, ?, ?) WHERE meta_value LIKE ?");
    $stmt->bind_param("sss", $old_url, $new_url, $like_pattern);
    $stmt->execute();
    echo "✓ Cập nhật commentmeta: " . $stmt->affected_rows . " records\n";
    $stmt->close();
    
    // 7. Update wp_usermeta
    echo "\n6️⃣  Cập nhật user meta...\n";
    $stmt = $mysqli->prepare("UPDATE wp_usermeta SET meta_value = REPLACE(meta_value, ?, ?) WHERE meta_value LIKE ?");
    $stmt->bind_param("sss", $old_url, $new_url, $like_pattern);
    $stmt->execute();
    echo "✓ Cập nhật usermeta: " . $stmt->affected_rows . " records\n";
    $stmt->close();
    
    // 8. Kiểm tra kết quả
    echo "\n🔍 Kiểm tra kết quả...\n";
    
    $result = $mysqli->query("SELECT option_name, option_value FROM wp_options WHERE option_name IN ('home', 'siteurl')");
    while ($row = $result->fetch_assoc()) {
        $status = ($row['option_value'] === $new_url) ? "✅" : "❌";
        echo "$status {$row['option_name']}: {$row['option_value']}\n";
    }
    $result->free();
    
    // Đếm URLs cũ còn lại
    $stmt = $mysqli->prepare("SELECT COUNT(*) as count FROM wp_options WHERE option_value LIKE ?");
    $stmt->bind_param("s", $like_pattern);
    $stmt->execute();
    $result = $stmt->get_result();
    $remaining_options = $result->fetch_assoc()['count'];
    $stmt->close();
    
    $stmt = $mysqli->prepare("SELECT COUNT(*) as count FROM wp_posts WHERE post_content LIKE ? OR post_excerpt LIKE ? OR guid LIKE ?");
    $stmt->bind_param("sss", $like_pattern, $like_pattern, $like_pattern);
    $stmt->execute();
    $result = $stmt->get_result();
    $remaining_posts = $result->fetch_assoc()['count'];
    $stmt->close();
    
    $total_remaining = $remaining_options + $remaining_posts;
    
    if ($total_remaining > 0) {
        echo "\n⚠️  Còn lại $total_remaining references đến URL cũ\n";
    } else {
        echo "\n✅ Tất cả URLs đã được cập nhật!\n";
    }
    
    echo "\n=== HOÀN TẤT ===\n";
    echo "✅ Migration hoàn tất!\n";
    echo "🌐 Kiểm tra website tại: $new_url\n";
    echo "🔧 Nhớ:\n";
    echo "   - Clear cache (nếu có)\n";
    echo "   - Kiểm tra permalinks trong WP Admin\n";
    echo "   - Test các chức năng chính\n";
    
    // Đóng kết nối
    $mysqli->close();
    
} catch (Exception $e) {
    echo "❌ Lỗi: " . $e->getMessage() . "\n";
    
    // Debug thông tin
    echo "\n🔍 Debug info:\n";
    echo "PHP version: " . phpversion() . "\n";
    echo "MySQLi extension: " . (extension_loaded('mysqli') ? "✅ Có" : "❌ Không có") . "\n";
    echo "PDO extension: " . (extension_loaded('pdo') ? "✅ Có" : "❌ Không có") . "\n";
    echo "PDO MySQL: " . (extension_loaded('pdo_mysql') ? "✅ Có" : "❌ Không có") . "\n";
    
    exit(1);
}

?>