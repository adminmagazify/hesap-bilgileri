<?php
/*
Plugin Name: Hesap Bilgileri
Description: Hesap Bilgileri Eklentisi
Version: 2.3
* GitHub Plugin URI: https://github.com/adminmagazify/hesap-bilgileri
Author: Magazac
*/
require plugin_dir_path(__FILE__) . 'plugin-update-checker-master/plugin-update-checker.php';

$updateChecker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
    'https://github.com/adminmagazify/hesap-bilgileri',
    __FILE__,
    'hesap-bilgileri'
);

$updateChecker->setBranch('main');

if (!defined("ABSPATH")) exit;

/* ---------------------------------------------------------
   CSS & JS
--------------------------------------------------------- */
add_action("wp_enqueue_scripts", function() {
    wp_enqueue_style("hb-style", plugin_dir_url(__FILE__) . "style.css");
    wp_enqueue_script("hb-script", plugin_dir_url(__FILE__) . "script.js", ["jquery"], null, true);
});

/* ---------------------------------------------------------
   Admin Menü
--------------------------------------------------------- */
add_action("admin_menu", function() {
    add_menu_page(
        "Hesap Bilgileri",
        "Hesap Bilgileri",
        "manage_woocommerce",
        "hesap-bilgileri",
        "hb_admin_page",
        "dashicons-id",
        27
    );
});

/* ---------------------------------------------------------
   Kullanıcı meta alanları listesi
--------------------------------------------------------- */
function hb_fields() {
    return [

        // Temel bilgiler
        "ad" => "Ad",
        "soyad" => "Soyad",

        // Firma bilgileri
        "firma_adi" => "Firma Adı (1)",
        "vergi_dairesi" => "Firma Vergi Dairesi (1)",
        "vergi_no" => "Firma Vergi No (1)",

        // Adres bilgileri
        "ulke" => "Ülke",
        "sehir" => "Şehir",
        "ilce" => "İlçe",
        "mahalle" => "Mahalle",
        "adres" => "Sokak/Apartman/Daire",
        "posta_kodu" => "Posta Kodu",

        // İletişim
        "telefon" => "Telefon",
        "email" => "E-posta Adresi",

        // Banka bilgileri
        "banka_hesap_sahibi" => "Banka Hesap Sahibi Ad Soyad (1)",
        "banka" => "Banka",
        "iban" => "IBAN",

        // Kimlik yüklemeleri
        "kimlik_on" => "Mağaza Sahibi Kimlik Ön Yüz",
        "kimlik_arka" => "Mağaza Sahibi Kimlik Arka Yüz",
        "banka_kimlik_on" => "Banka Hesap Sahibi Kimlik Ön Yüz",
        "banka_kimlik_arka" => "Banka Hesap Sahibi Kimlik Arka Yüz",
    ];
}

/* ---------------------------------------------------------
   Shortcode: [hesap-bilgileri]
--------------------------------------------------------- */
add_shortcode("hesap-bilgileri", function() {

    // Sadece mağaza yöneticisi veya admin görebilir
    if (!current_user_can("manage_woocommerce")) {
        return "<p>Bu alanı görüntüleme yetkiniz yok.</p>";
    }

    $user_id = get_current_user_id();
    $fields = hb_fields();

    ob_start();
    ?>

    <div class="hb-wrapper">

        <h2>Hesap Bilgileri</h2>

        <p style="margin-top:-8px; margin-bottom:25px; font-size:14px; color:#333;">
            <strong>(1)</strong> Banka hesap sahibinin banka hesabında tanımlı tam Ad Soyad'ı girilmelidir.
            18 yaşından küçük mağaza sahipleri anne, baba veya yasal vasi banka hesap numarasını girebilirler.
            18 yaşından küçüklere ödeme yapılmamaktadır.
        </p>

        <form method="post" enctype="multipart/form-data" class="hb-form">

            <?php foreach ($fields as $key => $label): ?>

                <?php $value = get_user_meta($user_id, $key, true); ?>

                <!-- Dosya alanları -->
                <?php if (in_array($key, ["kimlik_on", "kimlik_arka", "banka_kimlik_on", "banka_kimlik_arka"])): ?>

                    <?php 
                        $existing = $value ? "<a href='".wp_get_attachment_url($value)."' target='_blank'>Görüntüle</a>" : "Henüz yüklenmedi";
                    ?>

                    <div class="hb-field">
                        <label><?php echo $label; ?></label>
                        <input type="file" name="<?php echo $key; ?>" accept="image/*">
                        <div class="hb-existing"><?php echo $existing; ?></div>
                    </div>

                <?php else: ?>

                    <!-- Metin alanları -->
                    <div class="hb-field">
                        <label><?php echo $label; ?></label>
                        <input type="text" name="<?php echo $key; ?>" value="<?php echo esc_attr($value); ?>">
                    </div>

                <?php endif; ?>

            <?php endforeach; ?>

            <button type="submit" name="hb_save" class="hb-save">Bilgileri Kaydet</button>

        </form>

    </div>

    <?php
    return ob_get_clean();
});

/* ---------------------------------------------------------
   Form POST işlemi
--------------------------------------------------------- */
add_action("init", function() {

    if (!isset($_POST["hb_save"])) return;
    if (!current_user_can("manage_woocommerce")) return;

    $user_id = get_current_user_id();
    $fields = hb_fields();

    foreach ($fields as $key => $label) {

        // Dosya yükleme işlemleri
        if (in_array($key, ["kimlik_on", "kimlik_arka", "banka_kimlik_on", "banka_kimlik_arka"])) {

            if (!empty($_FILES[$key]["name"])) {

                require_once(ABSPATH . "wp-admin/includes/file.php");

                $upload = wp_handle_upload($_FILES[$key], ["test_form" => false]);

                if (!isset($upload["error"])) {

                    $attachment_id = wp_insert_attachment([
                        "post_mime_type" => $upload["type"],
                        "post_title" => sanitize_file_name($_FILES[$key]["name"]),
                        "post_content" => "",
                        "post_status" => "inherit"
                    ], $upload["file"]);

                    require_once(ABSPATH . "wp-admin/includes/image.php");
                    wp_generate_attachment_metadata($attachment_id, $upload["file"]);

                    update_user_meta($user_id, $key, $attachment_id);
                }
            }

            continue;
        }

        // Normal alanlar
        if (isset($_POST[$key])) {
            update_user_meta($user_id, $key, sanitize_text_field($_POST[$key]));
        }
    }

    wp_redirect($_SERVER["REQUEST_URI"]);
    exit;
});

/* ---------------------------------------------------------
   Admin Panel: Hesap Bilgileri Görüntüleme (Kullanıcı Seçmeli)
--------------------------------------------------------- */
function hb_admin_page() {

    if (!current_user_can("manage_woocommerce")) {
        echo "<p>Bu alanı görüntüleme yetkiniz yok.</p>";
        return;
    }

    echo "<div class='wrap'>";
    echo "<h1>Hesap Bilgileri</h1>";

    /* ---------------------------------------------------------
       1) Kullanıcı seçme dropdown
    --------------------------------------------------------- */
    $selected_user = isset($_GET["user_id"]) ? intval($_GET["user_id"]) : 0;

    $users = get_users([ 'exclude_roles' => ['subscriber'] ]);

    echo "<form method='GET' style='margin-bottom:20px;'>";
    echo "<input type='hidden' name='page' value='hesap-bilgileri'>";
    echo "<select name='user_id' onchange='this.form.submit()'>";
    echo "<option value=''>— Kullanıcı Seçin —</option>";

    foreach ($users as $user) {
        $sel = ($selected_user == $user->ID) ? "selected" : "";
        echo "<option value='{$user->ID}' $sel>{$user->display_name} ({$user->user_email})</option>";
    }

    echo "</select>";
    echo "</form>";

    if (!$selected_user) {
        echo "<p>Lütfen bir kullanıcı seçin.</p></div>";
        return;
    }

    /* ---------------------------------------------------------
       2) Form gönderildiyse bilgileri kaydet
    --------------------------------------------------------- */
    if (isset($_POST["hb_admin_save"])) {

        $fields = hb_fields();

        foreach ($fields as $key => $label) {

            // Dosya alanı mı?
            if (in_array($key, ["kimlik_on", "kimlik_arka", "banka_kimlik_on", "banka_kimlik_arka"])) {

                if (!empty($_FILES[$key]["name"])) {

                    require_once(ABSPATH . "wp-admin/includes/file.php");

                    $upload = wp_handle_upload($_FILES[$key], ["test_form" => false]);

                    if (isset($upload["file"])) {

                        $attachment_id = wp_insert_attachment([
                            "post_mime_type" => $upload["type"],
                            "post_title" => sanitize_file_name($_FILES[$key]["name"]),
                            "post_content" => "",
                            "post_status" => "inherit"
                        ], $upload["file"]);

                        require_once(ABSPATH . "wp-admin/includes/image.php");
                        wp_generate_attachment_metadata($attachment_id, $upload["file"]);

                        update_user_meta($selected_user, $key, $attachment_id);
                    }
                }

            } else {
                update_user_meta($selected_user, $key, sanitize_text_field($_POST[$key]));
            }
        }

        echo "<div class='updated'><p>Bilgiler güncellendi.</p></div>";
    }

    /* ---------------------------------------------------------
       3) Düzenleme formunu göster
    --------------------------------------------------------- */
    $fields = hb_fields();

    echo "<form method='POST' enctype='multipart/form-data'>";
    echo "<table class='form-table'>";

    foreach ($fields as $key => $label):

        $value = get_user_meta($selected_user, $key, true);

        echo "<tr><th><label>$label</label></th><td>";

        // Dosya alanı
        if (in_array($key, ["kimlik_on", "kimlik_arka", "banka_kimlik_on", "banka_kimlik_arka"])) {

            $existing = $value ? "<a href='".wp_get_attachment_url($value)."' target='_blank'>Görüntüle</a>" : "Yüklenmemiş";

            echo "
                <input type='file' name='$key' accept='image/*'><br>
                <small>$existing</small>
            ";

        } else {
            echo "<input type='text' name='$key' value='".esc_attr($value)."' class='regular-text' />";
        }

        echo "</td></tr>";

    endforeach;

    echo "</table>";

    echo "<p><button type='submit' name='hb_admin_save' class='button button-primary'>Kaydet</button></p>";

    echo "</form></div>";
}