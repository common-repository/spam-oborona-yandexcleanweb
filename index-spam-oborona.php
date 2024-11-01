<?php
/*
  Plugin Name: Spam Oborona YandexCleanWeb
  Plugin URI: http://www.zixn.ru/category/wp_create_plugin
  Description: *RU* Борьба с спамом в комментариях средствами бесплатного сервиса Яндекс.Чистый Веб *EN* The fight against spam in comments by free service Yandex .NET Web
  Version: 1.3.3
  Author: Djon
  Author URI: http://zixn.ru
 */

/*  Copyright 2014  Djon  (email: Ermak_not@mail.ru)

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 * 
 */

/**
 * Класс для плагина wordpress
 */
/**
 * 
 */
require_once (WP_PLUGIN_DIR . '/' . dirname(plugin_basename(__FILE__)) . '/inc/core-class.php');

$spob=new Web20Spob;

add_action('plugins_loaded', array($spob,'spob_load_textdomain'));
register_activation_hook(__FILE__, array($spob, 'activationPlugin'));
register_deactivation_hook(__FILE__, array($spob, 'deactivationPlugin'));