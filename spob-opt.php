<?php
$spob_idkey = get_option('spob_idkey'); //id yandex
$spob_obj=new Web20Spob;
?>

<h2><?php _e('Настройка Плагина Спам оборона на работу в вашей системе','SpamOborona') ?></h2>
<div class="image_bloc">
<img class="spob_help_img" src="<?php echo WP_PLUGIN_URL . "/" . Web20Spob::PATCH_PLUGIN . "/img/help.png"; ?>">
</div>

<form method="post" action="options.php">
    <?php wp_nonce_field('update-options'); ?>

    <table class="form-table">
        <h3>Общие настройки</h3>
        <tr valign="top">
            <th scope="row">API-ключ Yandex</th>
            <td>
                <input type="text" size="80" name="spob_idkey" value="<?php echo $spob_idkey; ?>" />
                <span class="description">Для того что бы работал сервис борьбы с спамом, нужно
                    указать API-ключ Yandex, получить его можно <a href="http://api.yandex.ru/key/form.xml?service=cw">на странице</a></span>
            </td>
        </tr>
    </table>

    <input type="hidden" name="action" value="update" />
    <input type="hidden" name="page_options" value="spob_idkey" />

    <p class="submit">
        <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
    </p>

</form>
<?php

    $key_id = get_option('spob_idkey');
    $comment_txt = "Привет, как дела?";
    $check_spam_params = array(
        'key' => $key_id,
        'body-plain' => $comment_txt);

    $result = $spob_obj->httpReqToYandex("POST", Web20Spob::API_URL . Web20Spob::API_SPAM_PATH, $check_spam_params);
    $result = explode("\r\n", $result, 3);
    $result = new SimpleXMLElement($result[1]);
   if(isset($key_id) and $result->text['spam-flag']) {
       echo '<span class="valid_key"> Всё хорошо -  ваш ключ валиден - защита работает</span>';
   }else {
       echo '<span class="novalid_key"> Защита НЕ работает</span>';
   }
    //echo "Ответ:\n  spam-flag -> ", $result->text['spam-flag'], "\n  request_id -> $result->id\n\n";




?>
<form method="post">
    <?php wp_nonce_field('update-options'); ?>

    <table class="form-table">
        <h3>Тест работы ключа и Спам обороны вашего блога</h3>
        <tr valign="top">
            <th scope="row">Фраза, для проверки на спам</th>
            <td>
                <input type="text" size="40" name="spob_test_spam" value="Введите ваш текст, или оставте как есть" />
                <span class="description">В случае успешного ответа вы получите строку видта: 
                "Ответ: spam-flag -> no request_id -> 140144137500000F"</span>
            </td>
        </tr>
    </table>

    <p class="submit">
        <input type="submit" name="test_spam_oborona" class="button-primary" value="Проверить" />
    </p>
</form>
<?php
if (isset($_POST['test_spam_oborona'])) {
    //$spob_obj=new Web20Spob;
    $key_id = get_option('spob_idkey');
    $comment_txt = $_POST['spob_test_spam'];
    $check_spam_params = array(
        'key' => $key_id,
        'body-plain' => $comment_txt);

    $result = $spob_obj->httpReqToYandex("POST", Web20Spob::API_URL . Web20Spob::API_SPAM_PATH, $check_spam_params);
    $result = explode("\r\n", $result, 3);
    $result = new SimpleXMLElement($result[1]);
    echo "Ответ:\n  spam-flag -> ", $result->text['spam-flag'], "\n  request_id -> $result->id\n\n";
    //$spob_obj->postSRVcount();
    //Удаление Спам
    //$spob_obj->deleteSpamCommentOld();
}
$spam_all=get_option('spam_comment_number');
echo '<br>За время работы плагина '.Web20Spob::NAME_PLUGIN.' пойманно спам сообщений - '.$spam_all;

