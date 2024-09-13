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
function fetch_liputan6_articles($feed_url, $category, $count, $status) {
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
        save_liputan6_post($title, $content, $link, $thumbnail, $category, $status);
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
        $cleaned_dom = new DOMDocument();
        @$cleaned_dom->loadHTML($article_content);
        
        // Hapus semua elemen div
        $divs = $cleaned_dom->getElementsByTagName('div');
        while ($divs->length > 0) {
            $div = $divs->item(0);
            $div->parentNode->removeChild($div);
        }

        // Hapus semua elemen link (a)
        $links = $cleaned_dom->getElementsByTagName('a');
        while ($links->length > 0) {
            $link = $links->item(0);
            $link->parentNode->removeChild($link);
        }

        // Mengambil konten HTML yang telah dibersihkan
        $cleaned_content = $cleaned_dom->saveHTML();
        
        // Menghapus tag HTML yang tidak diinginkan, hanya biarkan yang tertentu
        $cleaned_content = strip_tags($cleaned_content, '<p><strong><em><ul><li><blockquote>'); // Biarkan tag tertentu

        return $cleaned_content;
    } else {
        echo "<div class='error'><p>Content not found.</p></div>";
        return null;
    }
}
// Fungsi untuk menyimpan artikel sebagai post di WordPress
function save_liputan6_post($title, $content, $link, $thumbnail, $category, $status) {
    $post_data = array(
        'post_title'    => wp_strip_all_tags($title),
        'post_content'  => $content,
        'post_status'   => $status,
        'post_type'     => 'post',
        'meta_input'    => array(
            'source_link' => esc_url($link),
            'thumbnail'   => esc_url($thumbnail),
        ),
    );

    // Insert post ke dalam WordPress
    $post_id = wp_insert_post($post_data);
    // set category
    if($category){
        wp_set_post_terms($post_id, $category, 'category');
    }
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
        $target = sanitize_text_field($_POST['target']);
        $category = sanitize_text_field($_POST['category']);
        $count = intval($_POST['count']);
        $status = sanitize_text_field($_POST['status']);
        fetch_liputan6_articles($target, $category, $count, $status);
        echo "<div class='updated'><p>Artikel berhasil diambil!</p></div>";
    }
    $target = [
        'https://feed.liputan6.com/rss/saham' => 'Saham',
        'https://feed.liputan6.com/rss/news/politik' => 'Politik',
        'https://feed.liputan6.com/rss/bisnis' => 'Bisnis',
        'https://feed.liputan6.com/rss/bola' => 'Bola',
        'https://feed.liputan6.com/rss/tekno' => 'Teknologi',
        'https://feed.liputan6.com/rss/islami' => 'Islami',
        'https://feed.liputan6.com/rss/opini' => 'Opini',
        'https://feed.liputan6.com/rss/otomotif' => 'Otomotif',
        'https://feed.liputan6.com/rss/global' => 'Global',
        'https://feed.liputan6.com/rss/hot' => 'Hot',
        'https://feed.liputan6.com/rss/crypto' => 'Crypto',
        'https://feed.liputan6.com/rss/regional' => 'Regional',
        'https://feed.liputan6.com/rss/lifestyle' => 'Lifestyle',
        'https://feed.liputan6.com/rss/health' => 'Health',
        'https://feed.liputan6.com/rss/sindikasi' => 'Sindikasi',
        'https://feed.liputan6.com/rss/fashion-beauty' => 'Fashion & Beauty',
        'https://feed.liputan6.com/rss/showbiz' => 'Showbiz'
    ];

    echo '<div class="wrap">';
    echo '<h1>Ambil Artikel dari Liputan6</h1>';
    echo '<form method="post">';
    echo '<table class="form-table">';
    // Dropdown kategori WordPress
    echo '<tr><td><label for="target">Pilih Target:</label></td>';
    echo '<td><select id="target" name="target">';
    foreach ($target as $key => $value) {
        echo "<option value='" . $key . "'>" . $value . "</option>";
    }
    echo '</select></td></tr>';
    echo '<tr><td><label for="category">Kategori Target:</label></td>';
    echo '<td>';
    wp_dropdown_categories(array(
        'show_option_all' => 'Pilih Kategori', 
        'name' => 'category',
        'id' => 'category',
        'class' => 'postform',
        'hide_empty' => 0,
    ));
    echo '</td></tr>';
    
    // Input untuk jumlah artikel
    echo '<tr><td>';
    echo '<label for="count">Jumlah Artikel:</label>';
    echo '</td>';
    echo '<td>';
    echo '<input type="number" id="count" name="count" value="5">';
    echo '</td></tr>';
    // pilih status
    echo '<tr><td>';
    echo '<label for="status">Status:</label>';
    echo '</td>';
    echo '<td>';
    echo '<select id="status" name="status">';
    echo '<option value="draft">Draft</option>';
    echo '<option value="publish">Publish</option>';
    echo '</select>';
    echo '</td></tr>';
    echo '<tr><td colspan="2">';
    echo '<input class="button button-primary" type="submit" value="Ambil Artikel">';
    echo '</td></tr>';
    echo '</table>';
    echo '</form>';
    echo '</div>';
}