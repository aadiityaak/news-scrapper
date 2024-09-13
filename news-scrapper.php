<?php

/**
 *
 * @since             1.0.6
 * @package           news-scrapper
 *
 * @wordpress-plugin
 * Plugin Name:       News Scrapper
 * Plugin URI:        https://websweetstudio.com
 * Description:       News Scrapper Liputan6
 * Version:           1.1.4
 * Author:            Velocity
 * Author URI:        https://websweetstudio.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       news-scrapper
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

include_once(ABSPATH . WPINC . '/feed.php');

// Fungsi untuk mengambil artikel dari RSS Feed
function fetch_liputan6_articles($category, $count) {
    $feed_url = 'https://feed.liputan6.com/rss/news';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $feed_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    curl_close($ch);

    if ($response === false) {
        echo "<div class='error'><p>Error fetching feed.</p></div>";
        return;
    }

    $xml = simplexml_load_string($response);

    if ($xml === false) {
        echo "<div class='error'><p>Error parsing feed.</p></div>";
        return;
    }

    $items = $xml->channel->item;
    $maxitems = min($count, count($items));

    for ($i = 0; $i < $maxitems; $i++) {
        $item = $items[$i];
        $title = (string) $item->title;
        $link = (string) $item->link;
        $content = (string) fetch_full_article($item->link);
        $thumbnail = (string) $item->enclosure['url'] ?? '';

        echo '<p>'.$title.'</p>';
        save_liputan6_post($title, $content, $link, $thumbnail);
    }
}
function fetch_full_article($article_url) {
    // Ambil konten halaman artikel
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $article_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    curl_close($ch);

    if ($response === false) {
        echo "<div class='error'><p>Error fetching article.</p></div>";
        return null;
    }

    // Parsing HTML
    $dom = new DOMDocument();
    @$dom->loadHTML($response); // Gunakan @ untuk mengabaikan peringatan

    // Mengambil konten artikel
    $xpath = new DOMXPath($dom);
    $content_nodes = $xpath->query('//div[contains(@class, "article-content-body__item-content")]');

    if ($content_nodes->length > 0) {
        $article_content = '';
        foreach ($content_nodes as $node) {
            $article_content .= $dom->saveHTML($node);
        }

        // Membersihkan HTML
        $cleaned_content = strip_tags($article_content, '<p><a><strong><em><ul><li><blockquote>'); // Biarkan tag tertentu

        return $cleaned_content;
    } else {
        echo "<div class='error'><p>Content not found.</p></div>";
        return null;
    }
}
// Fungsi untuk menyimpan artikel sebagai post di WordPress
function save_liputan6_post($title, $content, $link, $thumbnail) {
    $post_data = array(
        'post_title'    => wp_strip_all_tags($title),
        'post_content'  => $content,
        'post_status'   => 'draft',
        'post_type'     => 'post',
        'meta_input'    => array(
            'source_link' => esc_url($link),
            'thumbnail'   => esc_url($thumbnail),
        ),
    );

    // Insert post ke dalam WordPress
    $post_id = wp_insert_post($post_data);

    // Jika ada thumbnail, set sebagai featured image
    if (!empty($thumbnail)) {
        set_featured_image_from_url($post_id, $thumbnail);
    }
}

// Fungsi untuk menetapkan featured image dari URL
function set_featured_image_from_url($post_id, $image_url) {
    $upload_dir = wp_upload_dir();
    $image_data = file_get_contents($image_url);
    $filename = basename($image_url);
    $file = $upload_dir['path'] . '/' . $filename;
    file_put_contents($file, $image_data);

    $wp_filetype = wp_check_filetype($filename, null);
    $attachment = array(
        'post_mime_type' => $wp_filetype['type'],
        'post_title'     => sanitize_file_name($filename),
        'post_content'   => '',
        'post_status'    => 'inherit',
    );

    $attach_id = wp_insert_attachment($attachment, $file, $post_id);
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $attach_data = wp_generate_attachment_metadata($attach_id, $file);
    wp_update_attachment_metadata($attach_id, $attach_data);
    set_post_thumbnail($post_id, $attach_id);
}

// Menambahkan opsi di menu admin untuk generate artikel
function liputan6_admin_menu() {
    add_menu_page('Liputan6 Fetcher', 'Liputan6 Fetcher', 'manage_options', 'liputan6-fetcher', 'liputan6_admin_page');
}

add_action('admin_menu', 'liputan6_admin_menu');

// Halaman admin untuk fetch artikel
function liputan6_admin_page() {
    if (isset($_POST['category']) && isset($_POST['count'])) {
        $category = sanitize_text_field($_POST['category']);
        $count = intval($_POST['count']);
        fetch_liputan6_articles($category, $count);
        echo "<div class='updated'><p>Artikel berhasil diambil!</p></div>";
    }

    echo '<div class="wrap">';
    echo '<h1>Ambil Artikel dari Liputan6</h1>';
    echo '<form method="post">';
    
    // Dropdown kategori WordPress
    echo '<label for="category">Kategori:</label>';
    wp_dropdown_categories(array(
        'show_option_all' => 'Pilih Kategori', 
        'name' => 'category',
        'id' => 'category',
        'class' => 'postform',
        'hide_empty' => 0,
    ));
    
    // Input untuk jumlah artikel
    echo '<label for="count">Jumlah Artikel:</label>';
    echo '<input type="number" id="count" name="count" value="5">';

    echo '<input type="submit" value="Ambil Artikel">';
    echo '</form>';
    echo '</div>';
}
?>
