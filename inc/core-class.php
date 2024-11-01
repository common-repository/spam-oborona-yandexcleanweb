<?php

/**
 * Класс для плагина
 */
class Web20Spob {

    const API_URL = 'http://cleanweb-api.yandex.ru/1.0/';
    const API_SPAM_PATH = 'check-spam';
    const NAME_PLUGIN = 'SpamOborona'; //Название плагина
    const PATCH_PLUGIN = 'spam-oborona-yandexcleanweb'; //Название папки плагина
    const SERVER_URL = 'http://zixn.ru/spamsrv.html'; // Адрес сервера, куда шлют статистику
    const SERVER_TIME_CRON_NAME = 'spob_srv'; //Название временного промежутка для крона
    const SERVER_CRON_NAME = 'spob_cron'; //Название создаваемого КРон крукя
    const PATCH_STYLE = 'style'; //имя папки с стилями и скриптами
    const URL_OPTIONS_PAGE = 'spam-oborona-opt'; // страница настроек плагина

    private $api_key;

    /**
     * Констурктора класса
     */
    public function __construct() {
        require_once (WP_PLUGIN_DIR . '/' . self::PATCH_PLUGIN . '/spob-core.php');
        $this->addActios();
        $this->addFilter();
        $this->addOptions();

    }

    /**
     * Опции вызываемые активацией
     */
    public function activationPlugin() {
        wp_schedule_event(time(), self::SERVER_TIME_CRON_NAME, self::SERVER_CRON_NAME);
    }

    /**
     * Опции вызываемые деактивацией
     */
    public function deactivationPlugin() {
        delete_option('spob_idkey');
        delete_option('spam_comment_number');
        delete_option('old_spam_comment_number');
        wp_unregister_sidebar_widget('SpamOborona_1');
        wp_unregister_widget_control('SpamOborona_1');
        wp_clear_scheduled_hook(self::SERVER_CRON_NAME);
    }

//    /**
//     * Деактивация плагина
//     */
//    public function deactivationPlugin() {
//        register_deactivation_hook(__FILE__, array($this, 'deactivationPluginParam'));
//    }

    /**
     * Активация фишек
     */
    public function addActios() {
        add_action('admin_menu', array($this, 'adminOptions'));
        add_action(self::SERVER_CRON_NAME, array($this, 'postSRVcount')); // Задание крон на отправку статистики спама
        add_action(self::SERVER_CRON_NAME, array($this, 'deleteSpamCommentOld')); // Задание крон на очистку от спама корзины
        add_action('comment_form', array($this, 'formHoneypot')); //Форма горшком для мёда
        add_action('wp_insert_comment', array($this, 'addCommentHistory')); // записть комментария_мета в базу (только так поймал id)
    }

//Навес фильтров
    public function addFilter() {
        add_filter('preprocess_comment', array($this, 'getInfoComment')); //Навес фильтра на событие перед принятием что делать с комментом
        add_filter('cron_schedules', array($this, 'spobcron_schedules'));
    }

    /**
     * Добавление опций в базу данных
     */
    public function addOptions() {
        add_option('spam_comment_number', '0');
        add_option('old_spam_comment_number', '0');
    }

    /**
     * Параметры активируемого меню
     */
    public function adminOptions() {
        $page_option = add_options_page('Spam Oborona Settings', 'SpamOborona', 8, self::URL_OPTIONS_PAGE, array($this, 'showSettingPage'));
        add_action('admin_print_styles-' . $page_option, array($this, 'addCssScript')); //загружаем стили только для страницы плагина
        add_action('load-' . $page_option, array($this, 'helpOptionPage')); // добавленна возможность Help
    }

    /**
     * Страница меню
     */
    public function showSettingPage() {
        include_once WP_PLUGIN_DIR . '/' . self::PATCH_PLUGIN . '/' . 'spob-opt.php';
    }

    /**
     * Получение параметров комментария 
     * Отправка на серви Яндекса
     * Принятие решение что с ним делать по результату ответа
     */
    public function getInfoComment($commentdata) {
        //$comment_id=$comment_ID;


        if (!is_user_logged_in()) { //Если пользователе не залогинен
            $this->api_key = get_option('spob_idkey');
            $comment_txt = $commentdata['comment_content'];

            $check_spam_params = array(
                'key' => $this->api_key,
                'body-plain' => $comment_txt);
            $result = $this->httpReqToYandex("POST", self::API_URL . self::API_SPAM_PATH, $check_spam_params);
            $result = explode("\r\n", $result, 3);
            $result = new SimpleXMLElement($result[1]);
            if ($result->text['spam-flag'] == 'no') { //Это не спам
                if ($this->formCheckHoneypot() == 'yes') { //Проверка медовым горшком
                    $commentdata['comment_content'] = $comment_txt;
                    $commentStatus = 'spam';
                    add_filter('pre_comment_approved', create_function('$a', "return '" . $commentStatus . "';"));
                    //$comment_id = get_comment_ID(); //ID текущего комментария
                    //$this->addCommentHistory($comment_id, "Проверенно плагином " . self::NAME_PLUGIN );
                    $this->counterSpam(); //посчитали
                    return $commentdata;
                } else {
                    $commentdata['comment_content'] = $comment_txt;
                    return $commentdata;
                }
            } elseif ($result->text['spam-flag'] == 'yes') {//Это СПАМ!!!!
                $commentdata['comment_content'] = $comment_txt;
                $commentStatus = 'spam';
                add_filter('pre_comment_approved', create_function('$a', "return '" . $commentStatus . "';"));
                //$comment_id = get_comment_ID(); //ID текущего комментария
                //$this->addCommentHistory($comment_id, "Проверенно плагином " . self::NAME_PLUGIN );
                $this->counterSpam(); //посчитали
                return $commentdata;
            } else { //Если не получил ни кагого ответа от яндекса
                $commentdata['comment_content'] = $comment_txt;
                $commentStatus = 0;
                add_filter('pre_comment_approved', create_function('$a', "return '" . $commentStatus . "';"));
                return $commentdata;
            }
// echo "Ответ:\n  spam-flag -> ", $result->text['spam-flag'], "\n  request_id -> $result->id\n\n";
            return $commentdata;
        } else {
            return $commentdata;
        }
    }

    /**
     * Для проверок
     * @param array $commentdata
     * @return string
     */
    public function getInfoComment1($commentdata) {


        $commentdata['comment_author'] = "bot";
        return $commentdata;
    }

    /**
     * Отправка параметров яндексу
     * @param type $type POST или GET
     * @param type $url URL куда кидать запрос
     * @param type $params Массив Параметров, ключ, имя, мыло и т.д 
     * @return string
     */
    public function httpReqToYandex($type, $url, $params) {
        $url = parse_url($url);
        if ($url['scheme'] != 'http') {
            die('Error: Only HTTP request are supported !');
        }

        $host = $url['host'];
        $path = $url['path'];

        $fp = fsockopen($host, 80, $errno, $errstr, 30);
        $params = http_build_query($params);

        if ($fp) {
            if ($type == 'POST') {
                fputs($fp, "POST $path HTTP/1.1\r\n");
                fputs($fp, "Host: $host\r\n");
                fputs($fp, "Content-type: application/x-www-form-urlencoded\r\n");
                fputs($fp, "Content-length: " . strlen($params) . "\r\n");
                fputs($fp, "Connection: close\r\n\r\n");
                fputs($fp, $params);
            } else if ($type == 'GET') {
                fputs($fp, 'GET ' . $path . '?' . $params . " HTTP/1.1\r\n");
                fputs($fp, "Host: " . $host . "\r\n");
                fputs($fp, "Connection: close\r\n\r\n");
            }

            $result = '';
            while (!feof($fp)) {
                $result .= fgets($fp);
            }
        } else {
            return array(
                'status' => 'err',
                'error' => "$errstr ($errno)"
            );
        }

        fclose($fp);

        $result = preg_split('|(?:\r?\n){2}|m', $result, 2);
        if (isset($result[1])) {
            return $result[1];
        }

        return '';
    }

    /**
     * Добавление возможности перевода
     */
    public function spob_load_textdomain() {
        load_plugin_textdomain(self::NAME_PLUGIN, dirname(plugin_basename(__FILE__)) . '/lang/');
    }

    /**
     * Считает количество пойманных спам комментов
     */
    public function counterSpam() {
        $current = get_option('spam_comment_number');
        $new = $current + 1;
        update_option('spam_comment_number', $new);
    }

    /**
     * Расчтитывает изменения относительно базы отправленного счётчика
     * и базы текущего счётчика, и отправляет через CURL цифру
     */
    public function postSRVcount() {
        $curent_count = get_option('spam_comment_number');
        $old_count = get_option('old_spam_comment_number');
        $sentsrv_count = $curent_count - $old_count; //Отправляем на сервер
        update_option('old_spam_comment_number', $curent_count);
        $this->curlPostSRV($sentsrv_count);
    }

    /**
     * Curl работающий через Post
     * Шлёт запрос на сервер учёта спама
     */
    public function curlPostSRV($count) {
        $url = self::SERVER_URL;
        $post_data = array(
            "spob_server_spam_statistik_ping" => "hello_zix",
            "count_clietn" => $count
        );
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
// указываем, что у нас POST запрос
        curl_setopt($ch, CURLOPT_POST, 1);
// добавляем переменные
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        $output = curl_exec($ch);
        curl_close($ch);
    }

    /**
     * Cron периоды
     * Добавляет интервал крона в систему
     */
    public function spobcron_schedules($schedules) {
        if (!isset($schedules[self::SERVER_TIME_CRON_NAME]))
            $schedules[self::SERVER_TIME_CRON_NAME] = array('interval' => 72000, 'display' => __('20 часов'));

        return apply_filters('spobcron_schedules', $schedules);
    }

    /**
     * CSS стили для виджета
     */
    public function cssStyleWidgetGreen() {
        ?>

        <style type="text/css">
            .a-spob {
                width: auto;
            }
            .a-spob a {
                background: #7CA821;
                background-image:-moz-linear-gradient(0% 100% 90deg,#087F0C,#0BAD10);
                background-image:-webkit-gradient(linear,0% 0,0% 100%,from(#0BAD10),to(#087F0C));
                border: 1px solid #5F8E14;
                border-radius:3px;
                color: #CFEA93;
                cursor: pointer;
                display: block;
                font-weight: normal;
                height: 100%;
                -moz-border-radius:3px;
                padding: 7px 0 8px;
                text-align: center;
                text-decoration: none;
                -webkit-border-radius:3px;
                width: 100%;
            }
            .a-spob a:hover {
                text-decoration: none;
                background-image:-moz-linear-gradient(0% 100% 90deg,#6F9C1B,#659417);
                background-image:-webkit-gradient(linear,0% 0,0% 100%,from(#659417),to(#6F9C1B));
            }
            .a-spob .count {
                color: #FFF;
                display: block;
                font-size: 15px;
                line-height: 16px;
                padding: 0 13px;
                white-space: nowrap;
            }
        </style>

        <?php

    }

    /**
     * CSS стили для виджета
     */
    public function cssStyleWidgetBlue() {
        ?>

        <style type="text/css">
            .a-spob {
                width: auto;
            }
            .a-spob a {
                background: #7CA821;
                background-image:-moz-linear-gradient(0% 100% 90deg,#0325E4,#4961E6);
                background-image:-webkit-gradient(linear,0% 0,0% 100%,from(#4961E6),to(#0325E4));
                border: 1px solid #5F8E14;
                border-radius:3px;
                color: #CFEA93;
                cursor: pointer;
                display: block;
                font-weight: normal;
                height: 100%;
                -moz-border-radius:3px;
                padding: 7px 0 8px;
                text-align: center;
                text-decoration: none;
                -webkit-border-radius:3px;
                width: 100%;
            }
            .a-spob a:hover {
                text-decoration: none;
                background-image:-moz-linear-gradient(0% 100% 90deg,#0C7CA4,#4AC0EB);
                background-image:-webkit-gradient(linear,0% 0,0% 100%,from(#4AC0EB),to(#0C7CA4));
            }
            .a-spob .count {
                color: #FFF;
                display: block;
                font-size: 15px;
                line-height: 16px;
                padding: 0 13px;
                white-space: nowrap;
            }
        </style>

        <?php

    }

    /**
     * CSS стили для виджета
     */
    public function cssStyleWidgetOrange() {
        ?>

        <style type="text/css">
            .a-spob {
                width: auto;
            }
            .a-spob a {
                background: #7CA821;
                background-image:-moz-linear-gradient(0% 100% 90deg,#F59760,#ED5B06);
                background-image:-webkit-gradient(linear,0% 0,0% 100%,from(#ED5B06),to(#F59760));
                border: 1px solid #5F8E14;
                border-radius:3px;
                color: #CFEA93;
                cursor: pointer;
                display: block;
                font-weight: normal;
                height: 100%;
                -moz-border-radius:3px;
                padding: 7px 0 8px;
                text-align: center;
                text-decoration: none;
                -webkit-border-radius:3px;
                width: 100%;
            }
            .a-spob a:hover {
                text-decoration: none;
                background-image:-moz-linear-gradient(0% 100% 90deg,#DBC905,#F2E337);
                background-image:-webkit-gradient(linear,0% 0,0% 100%,from(#F2E337),to(#DBC905));
            }
            .a-spob .count {
                color: #FFF;
                display: block;
                font-size: 15px;
                line-height: 16px;
                padding: 0 13px;
                white-space: nowrap;
            }
        </style>

        <?php

    }

    public function cssStyleWidgetDark() {
        ?>

        <style type="text/css">
            .a-spob {
                width: auto;
            }
            .a-spob a {
                background: #7CA821;
                background-image:-moz-linear-gradient(0% 100% 90deg,#FFFFFF,#000000);
                background-image:-webkit-gradient(linear,0% 0,0% 100%,from(#000000),to(#FFFFFF));
                border: 1px solid #5F8E14;
                border-radius:3px;
                color: #CFEA93;
                cursor: pointer;
                display: block;
                font-weight: normal;
                height: 100%;
                -moz-border-radius:3px;
                padding: 7px 0 8px;
                text-align: center;
                text-decoration: none;
                -webkit-border-radius:3px;
                width: 100%;
            }
            .a-spob a:hover {
                text-decoration: none;
                background-image:-moz-linear-gradient(0% 100% 90deg,#031820,#37778E);
                background-image:-webkit-gradient(linear,0% 0,0% 100%,from(#37778E),to(#031820));
            }
            .a-spob .count {
                color: #FFF;
                display: block;
                font-size: 15px;
                line-height: 16px;
                padding: 0 13px;
                white-space: nowrap;
            }
        </style>

        <?php

    }

    /**
     * honeypot - форма
     * Поле встраивается в форму комментариев
     */
    public function formHoneypot() {
        echo '<p style="display:none!important"><textarea name="spamoborona-comment"></textarea></p>';
    }

    /**
     * Проверка honeypot
     * если вернёт no, то коммент не спам
     */
    public function formCheckHoneypot() {
        if (empty($_POST['spamoborona-comment'])) {
            return 'no'; //Не спам
        } else {
            return 'yes'; //Спам
        }
    }

    /**
     * Стили скрипты
     */
    public function addCssScript() {
        //стиль
        // $open_url = $_SERVER['REQUEST_URI'];
        //if (strpos($open_url, self::URL_OPTIONS_PAGE)) { //только если мы на страницы настроек плагина
        wp_register_style('spob_style', WP_PLUGIN_URL . "/" . self::PATCH_PLUGIN . "/" . self::PATCH_STYLE . '/spob_options.css');
        wp_enqueue_style('spob_style');
        // }
        //скрипт
        //wp_register_script('eparser_script', 'http://code.jquery.com/jquery-1.11.0.min.js');
        //wp_enqueue_script('eparser_script');
    }

    /**
     * Вкладка помощи на странице плагина
     */
    public function helpOptionPage() {
        $screen = get_current_screen();

        // Add my_help_tab if current screen is My Admin Page
        $screen->add_help_tab(array(
            'id' => 'spob_help_tab1',
            'title' => __('Как получить id от Yandex'),
            'content' => '<p>' . __('Для получения ключа достаточно перейти по <a href="http://api.yandex.ru/key/form.xml?service=cw">ссылке</a>.</br>
- Если вы зарегистрированны на сайте Yandex.ru — тогда нажмите кнопку «Авторизациии» </br>
- Введите ваши логин и проль от  Yandex.ru и создайте ключ для работы плагина «Spam Oborona YandexCleanWeb» </br>
- Если у вас нет учётноый записи на  Yandex.ru — перейдите на ссылку «Зарегистрируйтесь» </br>
После регистрации на сервисе  Yandex.ru вы сможете создать ключ для работы плагина «Spam Oborona YandexCleanWeb»') . '</p>',
        ));
        $screen->add_help_tab(array(
            'id' => 'spob_help_tab2',
            'title' => __('У меня есть предложение'),
            'content' => '<p>' . __('Плагин «Spam Oborona YandexCleanWeb» находится в активной разработке, по этому разработчик принимает любые предложения, даже самые бредовые, но оставляет за собой право о внесении их в работу плагина. По любым вопросам, предложениям, критике обращайтесь через страницу ресурса <a href="http://www.zixn.ru/plagin-spam-oborona-yandexcleanweb.html">Zixn.ru</a>') . '</p>',
        ));
        $screen->add_help_tab(array(
            'id' => 'spob_help_tab3',
            'title' => __('О плагине'),
            'content' => '<p>' . __('Плагин «Spam Oborona YandexCleanWeb» является решение по борьбе с спамом на платформах Wordpress. Технология используемая в борьбе с спамом полностью принадлежит компании Yandex.ru и называется Яндекс.ЧистыйВеб. Плагин «Spam Oborona YandexCleanWeb» лишь использует API инструменты данной технологии + небольшие собственные наработки в борьбе с спамом.') . '</p>',
        ));
        $screen->add_help_tab(array(
            'id' => 'spob_help_tab4',
            'title' => __('Видео'),
            'content' => '<p>Видео по настройке плагина WordPress Spam Oborona YandexCleanWeb </br>' . __('<iframe width="560" height="315" src="//www.youtube-nocookie.com/embed/vC3s5XwzuTk?rel=0" frameborder="0" allowfullscreen></iframe>') . '</p>',
        ));
    }

    /**
     * Добавляем мета параметров комментария (пока не использую, но они пишутся)
     */
    public function addCommentHistory($comment_id, $message = "spob-proveren", $event = null) {
        global $current_user;

        $user = '';
        if (is_object($current_user) && isset($current_user->user_login))
            $user = $current_user->user_login;

        $event = array(
            'time' => microtime(),
            'message' => $message,
            'event' => $event,
            'user' => $user,
        );

        // $unique = false so as to allow multiple values per comment
        $r = add_comment_meta($comment_id, 'spob_history', $event, false);
    }

    /**
     * Удалялка спам комментариев
     */
    public function deleteSpamCommentOld() {
        global $wpdb;

        while ($comment_ids = $wpdb->get_col($wpdb->prepare("SELECT comment_id FROM {$wpdb->comments} WHERE DATE_SUB(NOW(), INTERVAL 1 DAY) > comment_date_gmt AND comment_approved = 'spam' LIMIT %d", 10000))) {
            var_dump($comment_ids);
            if (empty($comment_ids))
                return;

            $wpdb->queries = array();

            do_action('delete_comment', $comment_ids);

            $comma_comment_ids = implode(', ', array_map('intval', $comment_ids));
            $wpdb->query("DELETE FROM {$wpdb->comments} WHERE comment_id IN ( $comma_comment_ids )");
            $wpdb->query("DELETE FROM {$wpdb->commentmeta} WHERE comment_id IN ( $comma_comment_ids )");

            clean_comment_cache($comment_ids);
        }


    }

}
