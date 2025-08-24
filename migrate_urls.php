<?php
/**
 * WordPress URL Migration Script - Simple Interactive Version
 * 
 * CÁCH SỬ DỤNG:
 * php migrate_urls.php
 * 
 * Script sẽ hỏi bạn nhập:
 * - Old URL (URL cũ)
 * - New URL (URL mới)  
 * - Database host
 * - Database name
 * - Database username
 * - Database password
 */

echo "=== WordPress URL Migration Script ===\n";
echo "Script này sẽ thay đổi URLs trong WordPress database\n\n";

// Thu thập thông tin từ người dùng
echo "📝 Nhập thông tin:\n";

echo "Old URL (ví dụ: https://old-domain.com): ";
$old_url = trim(fgets(STDIN));

echo "New URL (ví dụ: https://new-domain.com): ";
$new_url = trim(fgets(STDIN));

echo "Database Host (thường là localhost): ";
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
    // Kết nối database
    echo "🔗 Đang kết nối database...\n";
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ Kết nối database thành công!\n\n";
    
    // 1. Update wp_options (home, siteurl)
    echo "1️⃣  Cập nhật wp_options...\n";
    
    $stmt = $pdo->prepare("UPDATE wp_options SET option_value = ? WHERE option_name = 'home'");
    $stmt->execute([$new_url]);
    echo "✓ Cập nhật home URL\n";
    
    $stmt = $pdo->prepare("UPDATE wp_options SET option_value = ? WHERE option_name = 'siteurl'");
    $stmt->execute([$new_url]);
    echo "✓ Cập nhật siteurl URL\n";
    
    // 2. Update other options with old URL
    $stmt = $pdo->prepare("UPDATE wp_options SET option_value = REPLACE(option_value, ?, ?) WHERE option_value LIKE ?");
    $stmt->execute([$old_url, $new_url, "%$old_url%"]);
    echo "✓ Cập nhật các options khác: " . $stmt->rowCount() . " records\n";
    
    // 3. Update wp_posts content
    echo "\n2️⃣  Cập nhật nội dung bài viết...\n";
    
    $stmt = $pdo->prepare("UPDATE wp_posts SET post_content = REPLACE(post_content, ?, ?) WHERE post_content LIKE ?");
    $stmt->execute([$old_url, $new_url, "%$old_url%"]);
    echo "✓ Cập nhật post_content: " . $stmt->rowCount() . " records\n";
    
    $stmt = $pdo->prepare("UPDATE wp_posts SET post_excerpt = REPLACE(post_excerpt, ?, ?) WHERE post_excerpt LIKE ?");
    $stmt->execute([$old_url, $new_url, "%$old_url%"]);
    echo "✓ Cập nhật post_excerpt: " . $stmt->rowCount() . " records\n";
    
    $stmt = $pdo->prepare("UPDATE wp_posts SET guid = REPLACE(guid, ?, ?) WHERE guid LIKE ?");
    $stmt->execute([$old_url, $new_url, "%$old_url%"]);
    echo "✓ Cập nhật guid: " . $stmt->rowCount() . " records\n";
    
    // 4. Update wp_postmeta
    echo "\n3️⃣  Cập nhật post meta...\n";
    $stmt = $pdo->prepare("UPDATE wp_postmeta SET meta_value = REPLACE(meta_value, ?, ?) WHERE meta_value LIKE ?");
    $stmt->execute([$old_url, $new_url, "%$old_url%"]);
    echo "✓ Cập nhật postmeta: " . $stmt->rowCount() . " records\n";
    
    // 5. Update wp_comments
    echo "\n4️⃣  Cập nhật comments...\n";
    $stmt = $pdo->prepare("UPDATE wp_comments SET comment_content = REPLACE(comment_content, ?, ?) WHERE comment_content LIKE ?");
    $stmt->execute([$old_url, $new_url, "%$old_url%"]);
    echo "✓ Cập nhật comment_content: " . $stmt->rowCount() . " records\n";
    
    $stmt = $pdo->prepare("UPDATE wp_comments SET comment_author_url = REPLACE(comment_author_url, ?, ?) WHERE comment_author_url LIKE ?");
    $stmt->execute([$old_url, $new_url, "%$old_url%"]);
    echo "✓ Cập nhật comment_author_url: " . $stmt->rowCount() . " records\n";
    
    // 6. Update wp_commentmeta
    echo "\n5️⃣  Cập nhật comment meta...\n";
    $stmt = $pdo->prepare("UPDATE wp_commentmeta SET meta_value = REPLACE(meta_value, ?, ?) WHERE meta_value LIKE ?");
    $stmt->execute([$old_url, $new_url, "%$old_url%"]);
    echo "✓ Cập nhật commentmeta: " . $stmt->rowCount() . " records\n";
    
    // 7. Update wp_usermeta
    echo "\n6️⃣  Cập nhật user meta...\n";
    $stmt = $pdo->prepare("UPDATE wp_usermeta SET meta_value = REPLACE(meta_value, ?, ?) WHERE meta_value LIKE ?");
    $stmt->execute([$old_url, $new_url, "%$old_url%"]);
    echo "✓ Cập nhật usermeta: " . $stmt->rowCount() . " records\n";
    
    // 8. Kiểm tra kết quả
    echo "\n🔍 Kiểm tra kết quả...\n";
    
    $stmt = $pdo->query("SELECT option_name, option_value FROM wp_options WHERE option_name IN ('home', 'siteurl')");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $status = ($row['option_value'] === $new_url) ? "✅" : "❌";
        echo "$status {$row['option_name']}: {$row['option_value']}\n";
    }
    
    // Đếm URLs cũ còn lại
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM wp_options WHERE option_value LIKE ?");
    $stmt->execute(["%$old_url%"]);
    $remaining_options = $stmt->fetch()['count'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM wp_posts WHERE post_content LIKE ? OR post_excerpt LIKE ? OR guid LIKE ?");
    $stmt->execute(["%$old_url%", "%$old_url%", "%$old_url%"]);
    $remaining_posts = $stmt->fetch()['count'];
    
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
    
} catch (PDOException $e) {
    echo "❌ Lỗi database: " . $e->getMessage() . "\n";
    echo "💡 Kiểm tra lại thông tin kết nối database\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ Lỗi: " . $e->getMessage() . "\n";
    exit(1);
}

?>