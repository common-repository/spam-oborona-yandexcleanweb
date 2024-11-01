<?php

/**
 * Adds SpamOborona_Widget widget.
 */
class SpamOborona_Widget extends WP_Widget {

    /**
     * Register widget with WordPress.
     */
    function __construct() {
        parent::__construct(
                'SpamOborona_1', // Base ID
                __('SpamOborona', 'text_domain'), // Name
                array('description' => __('Отображает количество заблокированного спама на странице вашего сайта', 'text_domain'),) // Args
        );

        $theme_w = get_option('widget_spamoborona_1');
        foreach ($theme_w as $option) {
            $theme = $option['theme'];
            break;
        }
        if ($theme == "Синий") {
            if (is_active_widget(false, false, $this->id_base)) {
                add_action('wp_head', array(Web20Spob, 'cssStyleWidgetBlue'));
            }
        }
        if ($theme == "Зеленый") {
            if (is_active_widget(false, false, $this->id_base)) {
                add_action('wp_head', array(Web20Spob, 'cssStyleWidgetGreen'));
            }
        }
        if ($theme == "Оранжевый") {
            if (is_active_widget(false, false, $this->id_base)) {
                add_action('wp_head', array(Web20Spob, 'cssStyleWidgetOrange'));
            }
        }
        if ($theme == "Черный") {
            if (is_active_widget(false, false, $this->id_base)) {
                add_action('wp_head', array(Web20Spob, 'cssStyleWidgetDark'));
            }
        }
    }

    /**
     * Front-end display of widget.
     *
     * @see WP_Widget::widget()
     *
     * @param array $args     Widget arguments.
     * @param array $instance Saved values from database.
     */
    public function widget($args, $instance) {
        $title = apply_filters('widget_title', $instance['title']);

        echo $args['before_widget'];
        if (!empty($title)) {
            echo $args['before_title'] . $title . $args['after_title'];
        }
        $current = get_option('spam_comment_number');
        //echo "SpamOborona отразил " . $current . " spam!";
        ?>
        <div class="a-spob">
            <a href="http://zixn.ru" target="_blank" title=""><?php printf(_n('<strong class="count">%1$s spam</strong> Защита от <strong>SpamOborona</strong>', '<strong class="count">%1$s spam</strong> Защита от <strong>SpamOborona</strong>', $current, 'spob'), number_format_i18n($current)); ?></a>
        </div>
        <?php
        echo $args['after_widget'];
    }

    //        echo $before_widget;
//        $current = get_option('spam_comment_number');
//        echo $before_title . 'SpamOborona' . $after_title;
//        // Код виджета, при выводе в шаблон
//        echo "SpamOborona отразил " . $current . " spam!";
//        echo $after_widget;

    /**
     * Back-end widget form.
     *
     * @see WP_Widget::form()
     *
     * @param array $instance Previously saved values from database.
     */
    public function form($instance) {
        if (isset($instance['title'])) {
            $title = $instance['title'];
        } else {
            $title = __('New title', 'text_domain');
        }
        $theme = $instance['theme'];
        ?>
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label> 
            <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('text'); ?>">Оформление:</label>
            <select class="widefat" id="<?php echo $this->get_field_id('theme'); ?>" name="<?php echo $this->get_field_name('theme'); ?>" type="text" >
                <option value="Зеленый" <?php echo ($theme == 'Зеленый') ? 'selected' : ''; ?>>Зеленый</option>
                <option value="Синий" <?php echo ($theme == 'Синий') ? 'selected' : ''; ?>>Синий</option>
                <option value="Оранжевый" <?php echo ($theme == 'Оранжевый') ? 'selected' : ''; ?>>Оранжевый</option>
                <option value="Черный" <?php echo ($theme == 'Черный') ? 'selected' : ''; ?>>Черный</option>
            </select>
        </p>
        <?php
    }

    /**
     * Sanitize widget form values as they are saved.
     *
     * @see WP_Widget::update()
     *
     * @param array $new_instance Values just sent to be saved.
     * @param array $old_instance Previously saved values from database.
     *
     * @return array Updated safe values to be saved.
     */
    public function update($new_instance, $old_instance) {
        //$instance = array();
        $instance = $old_instance;
        $instance['title'] = (!empty($new_instance['title']) ) ? strip_tags($new_instance['title']) : '';
        $instance['theme'] = $new_instance['theme'];

        return $instance;
    }

}

// class SpamOborona_Widget
// register Foo_Widget widget
function register_SpamOborona_widget() {
    register_widget('SpamOborona_Widget');
}

add_action('widgets_init', 'register_SpamOborona_widget');
